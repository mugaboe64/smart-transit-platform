<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../middleware/auth.php";

header("Content-Type: application/json");

// Authenticated users can view route progress
$user = requireRole(["ADMIN", "DRIVER", "PASSENGER"]);

$bus_id = $_GET["bus_id"] ?? null;

if (!$bus_id) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "bus_id is required"
    ]);

    exit;
}

$database = new Database();
$db = $database->connect();

try {

    /*
    |--------------------------------------------------------------------------
    | 1. Get active trip + current GPS location
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare(
        "SELECT
            t.id AS trip_id,
            t.bus_id,
            t.route_id,
            t.direction_id,

            b.bus_number,
            b.plate_number,

            r.route_code,
            r.route_name,
            r.origin,
            r.destination,

            d.name AS direction_name,
            d.direction_code,

            c.latitude,
            c.longitude,
            c.speed,
            c.heading,
            c.accuracy,
            c.status AS gps_status,
            c.last_updated

         FROM trips t

         INNER JOIN buses b
            ON t.bus_id = b.id

         INNER JOIN routes r
            ON t.route_id = r.id

         INNER JOIN directions d
            ON t.direction_id = d.id

         INNER JOIN bus_current_locations c
            ON c.bus_id = t.bus_id

         WHERE t.bus_id = :bus_id
         AND t.status = 'ACTIVE'

         LIMIT 1"
    );

    $stmt->execute([
        ":bus_id" => $bus_id
    ]);

    $trip = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Active trip or current bus location not found"
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Get all stops for this route and direction
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare(
        "SELECT
            rs.id AS route_stop_id,
            rs.sequence_number,
            rs.distance_from_previous,
            rs.scheduled_arrival,
            rs.scheduled_departure,

            s.id AS stop_id,
            s.stop_code,
            s.name AS stop_name,
            s.latitude,
            s.longitude,
            s.description,
            s.accessibility,
            s.status AS stop_status

         FROM route_stops rs

         INNER JOIN stops s
            ON rs.stop_id = s.id

         WHERE rs.route_id = :route_id
         AND rs.direction_id = :direction_id
         AND s.status = 'ACTIVE'

         ORDER BY rs.sequence_number ASC"
    );

    $stmt->execute([
        ":route_id" => $trip["route_id"],
        ":direction_id" => $trip["direction_id"]
    ]);

    $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$stops) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "No stops found for this route and direction"
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Calculate distance from bus to every stop
    |--------------------------------------------------------------------------
    |
    | Haversine formula
    | Distance returned in kilometers
    |
    */

    $busLatitude = (float) $trip["latitude"];
    $busLongitude = (float) $trip["longitude"];

    $earthRadius = 6371;

    $closestStop = null;
    $closestDistance = null;

    foreach ($stops as &$stop) {

        $stopLatitude = (float) $stop["latitude"];
        $stopLongitude = (float) $stop["longitude"];

        $latDifference = deg2rad($stopLatitude - $busLatitude);
        $lonDifference = deg2rad($stopLongitude - $busLongitude);

        $a =
            sin($latDifference / 2) * sin($latDifference / 2)
            +
            cos(deg2rad($busLatitude))
            *
            cos(deg2rad($stopLatitude))
            *
            sin($lonDifference / 2)
            *
            sin($lonDifference / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c;

        $stop["distance_from_bus_km"] = round($distance, 3);

        if ($closestDistance === null || $distance < $closestDistance) {

            $closestDistance = $distance;
            $closestStop = $stop;
        }
    }

    unset($stop);

    /*
    |--------------------------------------------------------------------------
    | 4. Find next stop
    |--------------------------------------------------------------------------
    */

    $nextStop = null;

    if ($closestStop) {

        $currentSequence = (int) $closestStop["sequence_number"];

        foreach ($stops as $stop) {

            if ((int) $stop["sequence_number"] > $currentSequence) {

                $nextStop = $stop;

                break;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Count remaining stops
    |--------------------------------------------------------------------------
    */

    $remainingStops = [];

    if ($nextStop) {

        foreach ($stops as $stop) {

            if (
                (int) $stop["sequence_number"]
                >=
                (int) $nextStop["sequence_number"]
            ) {

                $remainingStops[] = $stop;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Response
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "success" => true,

        "message" => "Route progress retrieved successfully",

        "data" => [

            "bus" => [
                "id" => $trip["bus_id"],
                "bus_number" => $trip["bus_number"],
                "plate_number" => $trip["plate_number"]
            ],

            "trip" => [
                "id" => $trip["trip_id"],
                "status" => "ACTIVE"
            ],

            "route" => [
                "id" => $trip["route_id"],
                "code" => $trip["route_code"],
                "name" => $trip["route_name"],
                "origin" => $trip["origin"],
                "destination" => $trip["destination"]
            ],

            "direction" => [
                "id" => $trip["direction_id"],
                "name" => $trip["direction_name"],
                "code" => $trip["direction_code"]
            ],

            "current_location" => [
                "latitude" => $trip["latitude"],
                "longitude" => $trip["longitude"],
                "speed" => $trip["speed"],
                "heading" => $trip["heading"],
                "accuracy" => $trip["accuracy"],
                "status" => $trip["gps_status"],
                "last_updated" => $trip["last_updated"]
            ],

            "current_stop" => $closestStop,

            "next_stop" => $nextStop,

            "remaining_stops_count" => count($remainingStops),

            "remaining_stops" => $remainingStops
        ]
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve route progress"
    ]);
}