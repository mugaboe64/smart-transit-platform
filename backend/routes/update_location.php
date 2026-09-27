<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../middleware/auth.php";

header("Content-Type: application/json");

// Only ADMIN can update bus location for now
$user = requireRole(["ADMIN"]);

$input = json_decode(file_get_contents("php://input"), true);

$bus_id = $input["bus_id"] ?? null;
$trip_id = $input["trip_id"] ?? null;
$latitude = $input["latitude"] ?? null;
$longitude = $input["longitude"] ?? null;
$speed = $input["speed"] ?? null;
$heading = $input["heading"] ?? null;
$accuracy = $input["accuracy"] ?? null;

// Validate required fields
if (!$bus_id || !$trip_id || $latitude === null || $longitude === null) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "bus_id, trip_id, latitude and longitude are required"
    ]);

    exit;
}

// Validate latitude
if ($latitude < -90 || $latitude > 90) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid latitude"
    ]);

    exit;
}

// Validate longitude
if ($longitude < -180 || $longitude > 180) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid longitude"
    ]);

    exit;
}

$database = new Database();
$db = $database->connect();

try {

    /*
    |--------------------------------------------------------------------------
    | 1. Check that the trip exists and is ACTIVE
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare(
        "SELECT id, bus_id
         FROM trips
         WHERE id = :trip_id
         AND bus_id = :bus_id
         AND status = 'ACTIVE'
         LIMIT 1"
    );

    $stmt->execute([
        ":trip_id" => $trip_id,
        ":bus_id" => $bus_id
    ]);

    $trip = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Active trip for this bus not found"
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Save GPS location history
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare(
        "INSERT INTO bus_locations
        (
            bus_id,
            trip_id,
            latitude,
            longitude,
            speed,
            heading,
            accuracy
        )
        VALUES
        (
            :bus_id,
            :trip_id,
            :latitude,
            :longitude,
            :speed,
            :heading,
            :accuracy
        )"
    );

    $stmt->execute([
        ":bus_id" => $bus_id,
        ":trip_id" => $trip_id,
        ":latitude" => $latitude,
        ":longitude" => $longitude,
        ":speed" => $speed,
        ":heading" => $heading,
        ":accuracy" => $accuracy
    ]);

    $location_id = $db->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | 3. Update current bus location
    |--------------------------------------------------------------------------
    |
    | bus_id is UNIQUE in bus_current_locations.
    | Therefore:
    |
    | - If bus does not exist → INSERT
    | - If bus already exists → UPDATE
    |
    */

    $stmt = $db->prepare(
        "INSERT INTO bus_current_locations
        (
            bus_id,
            latitude,
            longitude,
            speed,
            heading,
            accuracy,
            status
        )
        VALUES
        (
            :bus_id,
            :latitude,
            :longitude,
            :speed,
            :heading,
            :accuracy,
            'LIVE'
        )
        ON DUPLICATE KEY UPDATE
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            speed = VALUES(speed),
            heading = VALUES(heading),
            accuracy = VALUES(accuracy),
            status = 'LIVE',
            last_updated = CURRENT_TIMESTAMP"
    );

    $stmt->execute([
        ":bus_id" => $bus_id,
        ":latitude" => $latitude,
        ":longitude" => $longitude,
        ":speed" => $speed,
        ":heading" => $heading,
        ":accuracy" => $accuracy
    ]);

    /*
    |--------------------------------------------------------------------------
    | 4. Success response
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "success" => true,
        "message" => "Bus location recorded successfully",
        "location_id" => $location_id,
        "bus_id" => $bus_id,
        "trip_id" => $trip_id,
        "latitude" => $latitude,
        "longitude" => $longitude,
        "speed" => $speed,
        "heading" => $heading,
        "accuracy" => $accuracy
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to record bus location"
    ]);
}