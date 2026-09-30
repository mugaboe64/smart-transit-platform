<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../middleware/auth.php";

header("Content-Type: application/json");

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
    | 1. Get active trip + bus + route + direction + GPS
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
            b.capacity,
            b.status AS bus_status,

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
            "message" => "Active trip or current location not found"
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Get route stops
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
            s.accessibility

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
    | 3. Calculate distance from bus to stops
    |--------------------------------------------------------------------------
    */

    $busLatitude = (float) $trip["latitude"];
    $busLongitude = (float) $trip["longitude"];

    $earthRadius = 6371;

    foreach ($stops as &$stop) {

        $stopLatitude = (float) $stop["latitude"];
        $stopLongitude = (float) $stop["longitude"];

        $latDifference =
            deg2rad($stopLatitude - $busLatitude);

        $lonDifference =
            deg2rad($stopLongitude - $busLongitude);

        $a =
            sin($latDifference / 2) *
            sin($latDifference / 2)
            +
            cos(deg2rad($busLatitude)) *
            cos(deg2rad($stopLatitude)) *
            sin($lonDifference / 2) *
            sin($lonDifference / 2);

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        $distanceKm = $earthRadius * $c;

        $stop["distance_from_bus_km"] =
            round($distanceKm, 3);
    }

    unset($stop);

    /*
    |--------------------------------------------------------------------------
    | 4. Find current / nearest stop
    |--------------------------------------------------------------------------
    */

    $currentStop = null;
    $closestDistance = null;

    foreach ($stops as $stop) {

        if (
            $closestDistance === null ||
            $stop["distance_from_bus_km"] < $closestDistance
        ) {

            $closestDistance =
                $stop["distance_from_bus_km"];

            $currentStop = $stop;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Find next stop
    |--------------------------------------------------------------------------
    */

    $nextStop = null;

    if ($currentStop) {

        $currentSequence =
            (int) $currentStop["sequence_number"];

        foreach ($stops as $stop) {

            if (
                (int) $stop["sequence_number"] >
                $currentSequence
            ) {

                $nextStop = $stop;

                break;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Calculate ETA
    |--------------------------------------------------------------------------
    */

    $etaMinutes = null;
    $distanceToNextStop = null;

    if ($nextStop) {

        $nextLatitude =
            (float) $nextStop["latitude"];

        $nextLongitude =
            (float) $nextStop["longitude"];

        $latDifference =
            deg2rad($nextLatitude - $busLatitude);

        $lonDifference =
            deg2rad($nextLongitude - $busLongitude);

        $a =
            sin($latDifference / 2) *
            sin($latDifference / 2)
            +
            cos(deg2rad($busLatitude)) *
            cos(deg2rad($nextLatitude)) *
            sin($lonDifference / 2) *
            sin($lonDifference / 2);

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        $distanceToNextStop =
            $earthRadius * $c;

        $speed = (float) $trip["speed"];

        if ($speed > 0) {

            $timeHours =
                $distanceToNextStop / $speed;

            $etaMinutes =
                max(
                    1,
                    (int) ceil($timeHours * 60)
                );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 7. Remaining stops
    |--------------------------------------------------------------------------
    */

    $remainingStops = [];

    if ($nextStop) {

        foreach ($stops as $stop) {

            if (
                (int) $stop["sequence_number"] >=
                (int) $nextStop["sequence_number"]
            ) {

                $remainingStops[] = [
                    "id" => $stop["stop_id"],
                    "code" => $stop["stop_code"],
                    "name" => $stop["stop_name"],
                    "sequence_number" =>
                        $stop["sequence_number"]
                ];
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 8. Passenger tracking response
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "success" => true,

        "message" =>
            "Passenger tracking information retrieved successfully",

        "data" => [

            "bus" => [
                "id" => $trip["bus_id"],
                "bus_number" => $trip["bus_number"],
                "plate_number" => $trip["plate_number"],
                "capacity" => $trip["capacity"],
                "status" => $trip["bus_status"]
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

            "location" => [
                "latitude" => $trip["latitude"],
                "longitude" => $trip["longitude"],
                "speed_kmh" => $trip["speed"],
                "heading" => $trip["heading"],
                "accuracy" => $trip["accuracy"],
                "status" => $trip["gps_status"],
                "last_updated" => $trip["last_updated"]
            ],

            "current_stop" => $currentStop,

            "next_stop" => $nextStop,

            "distance_to_next_stop_km" =>
                $distanceToNextStop !== null
                    ? round($distanceToNextStop, 3)
                    : null,

            "eta_minutes" => $etaMinutes,

            "remaining_stops_count" =>
                count($remainingStops),

            "remaining_stops" =>
                $remainingStops
        ]
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" =>
            "Failed to retrieve passenger tracking information"
    ]);
}