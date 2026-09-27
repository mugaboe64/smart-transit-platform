<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../middleware/auth.php";

header("Content-Type: application/json");

// Allow authenticated users
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
    | Get complete live bus tracking information
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare(
        "SELECT
            b.id AS bus_id,
            b.bus_number,
            b.plate_number,
            b.capacity,
            b.gps_device_id,
            b.status AS bus_status,

            t.id AS trip_id,
            t.status AS trip_status,

            r.id AS route_id,
            r.route_code,
            r.route_name,
            r.origin,
            r.destination,

            d.id AS direction_id,
            d.name AS direction_name,
            d.direction_code,

            c.latitude,
            c.longitude,
            c.speed,
            c.heading,
            c.accuracy,
            c.status AS gps_status,
            c.last_updated

        FROM buses b

        LEFT JOIN trips t
            ON t.bus_id = b.id
            AND t.status = 'ACTIVE'

        LEFT JOIN routes r
            ON t.route_id = r.id

        LEFT JOIN directions d
            ON t.direction_id = d.id

        LEFT JOIN bus_current_locations c
            ON c.bus_id = b.id

        WHERE b.id = :bus_id

        LIMIT 1"
    );

    $stmt->execute([
        ":bus_id" => $bus_id
    ]);

    $bus = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bus) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Bus not found"
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Check whether GPS location exists
    |--------------------------------------------------------------------------
    */

    if ($bus["latitude"] === null) {

        echo json_encode([
            "success" => true,
            "message" => "Bus found but current GPS location is not available",
            "data" => [
                "bus" => [
                    "id" => $bus["bus_id"],
                    "bus_number" => $bus["bus_number"],
                    "plate_number" => $bus["plate_number"],
                    "capacity" => $bus["capacity"],
                    "gps_device_id" => $bus["gps_device_id"],
                    "status" => $bus["bus_status"]
                ],
                "trip" => null,
                "route" => null,
                "direction" => null,
                "location" => null
            ]
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Return complete tracking information
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "success" => true,
        "message" => "Bus tracking information retrieved successfully",

        "data" => [

            "bus" => [
                "id" => $bus["bus_id"],
                "bus_number" => $bus["bus_number"],
                "plate_number" => $bus["plate_number"],
                "capacity" => $bus["capacity"],
                "gps_device_id" => $bus["gps_device_id"],
                "status" => $bus["bus_status"]
            ],

            "trip" => [
                "id" => $bus["trip_id"],
                "status" => $bus["trip_status"]
            ],

            "route" => [
                "id" => $bus["route_id"],
                "code" => $bus["route_code"],
                "name" => $bus["route_name"],
                "origin" => $bus["origin"],
                "destination" => $bus["destination"]
            ],

            "direction" => [
                "id" => $bus["direction_id"],
                "name" => $bus["direction_name"],
                "code" => $bus["direction_code"]
            ],

            "location" => [
                "latitude" => $bus["latitude"],
                "longitude" => $bus["longitude"],
                "speed" => $bus["speed"],
                "heading" => $bus["heading"],
                "accuracy" => $bus["accuracy"],
                "status" => $bus["gps_status"],
                "last_updated" => $bus["last_updated"]
            ]
        ]
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve bus tracking information"
    ]);
}