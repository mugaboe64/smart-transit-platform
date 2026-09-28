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
    | 1. Get active trip and current GPS location
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
            rs.sequence_number,

            s.id AS stop_id,
            s.stop_code,
            s.name AS stop_name,
            s.latitude,
            s.longitude

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
    | 3. Calculate distance from bus to each stop
    |--------------------------------------------------------------------------
    */

    $busLatitude = (float) $trip["latitude"];
    $busLongitude = (float) $trip["longitude"];

    $earthRadius = 6371;

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

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        $distanceKm = $earthRadius * $c;

        $stop["distance_from_bus_km"] = round($distanceKm, 3);
    }

    unset($stop);

    /*
    |--------------------------------------------------------------------------
    | 4. Find nearest stop
    |--------------------------------------------------------------------------
    */

    $currentStop = null;
    $currentDistance = null;

    foreach ($stops as $stop) {

        if (
            $currentDistance === null
            ||
            $stop["distance_from_bus_km"] < $currentDistance
        ) {

            $currentDistance = $stop["distance_from_bus_km"];
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
                (int) $stop["sequence_number"]
                >
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

    if (!$nextStop) {

        echo json_encode([
            "success" => true,
            "message" => "Bus has reached the final stop",
            "data" => [
                "bus" => [
                    "id" => $trip["bus_id"],
                    "bus_number" => $trip["bus_number"],
                    "plate_number" => $trip["plate_number"]
                ],

                "route" => [
                    "id" => $trip["route_id"],
                    "name" => $trip["route_name"],
                    "origin" => $trip["origin"],
                    "destination" => $trip["destination"]
                ],

                "current_stop" => $currentStop,

                "next_stop" => null,

                "eta" => null
            ]
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Distance from bus to next stop
    |--------------------------------------------------------------------------
    */

    $nextStopLatitude = (float) $nextStop["latitude"];
    $nextStopLongitude = (float) $nextStop["longitude"];

    $latDifference =
        deg2rad($nextStopLatitude - $busLatitude);

    $lonDifference =
        deg2rad($nextStopLongitude - $busLongitude);

    $a =
        sin($latDifference / 2) * sin($latDifference / 2)
        +
        cos(deg2rad($busLatitude))
        *
        cos(deg2rad($nextStopLatitude))
        *
        sin($lonDifference / 2)
        *
        sin($lonDifference / 2);

    $c = 2 * atan2(
        sqrt($a),
        sqrt(1 - $a)
    );

    $distanceToNextStop =
        $earthRadius * $c;

    /*
    |--------------------------------------------------------------------------
    | 7. Calculate ETA using current speed
    |--------------------------------------------------------------------------
    */

    $speed = (float) $trip["speed"];

    if ($speed <= 0) {

        echo json_encode([
            "success" => true,
            "message" => "Bus location found but ETA cannot be calculated because current speed is zero",

            "data" => [
                "bus_id" => $trip["bus_id"],

                "current_location" => [
                    "latitude" => $trip["latitude"],
                    "longitude" => $trip["longitude"],
                    "speed" => $trip["speed"],
                    "status" => $trip["gps_status"]
                ],

                "next_stop" => [
                    "id" => $nextStop["stop_id"],
                    "code" => $nextStop["stop_code"],
                    "name" => $nextStop["stop_name"]
                ],

                "distance_to_next_stop_km" =>
                    round($distanceToNextStop, 3),

                "eta_minutes" => null
            ]
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Time = Distance / Speed
    |--------------------------------------------------------------------------
    |
    | distance = kilometers
    | speed = kilometers per hour
    |
    */

    $timeHours =
        $distanceToNextStop / $speed;

    $etaMinutes =
        $timeHours * 60;

    $etaMinutesRounded =
        max(1, (int) ceil($etaMinutes));

    /*
    |--------------------------------------------------------------------------
    | 8. Response
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "success" => true,

        "message" => "ETA calculated successfully",

        "data" => [

            "bus" => [
                "id" => $trip["bus_id"],
                "bus_number" => $trip["bus_number"],
                "plate_number" => $trip["plate_number"]
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
                "speed_kmh" => $trip["speed"],
                "status" => $trip["gps_status"],
                "last_updated" => $trip["last_updated"]
            ],

            "current_stop" => [
                "id" => $currentStop["stop_id"],
                "code" => $currentStop["stop_code"],
                "name" => $currentStop["stop_name"],
                "distance_from_bus_km" =>
                    $currentStop["distance_from_bus_km"]
            ],

            "next_stop" => [
                "id" => $nextStop["stop_id"],
                "code" => $nextStop["stop_code"],
                "name" => $nextStop["stop_name"]
            ],

            "distance_to_next_stop_km" =>
                round($distanceToNextStop, 3),

            "eta_minutes" =>
                $etaMinutesRounded
        ]
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to calculate ETA"
    ]);
}