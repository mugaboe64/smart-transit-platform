<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../middleware/auth.php";

header("Content-Type: application/json");

$user = requireRole(["ADMIN"]);

$input = json_decode(file_get_contents("php://input"), true);

$bus_id = $input["bus_id"] ?? null;
$driver_id = $input["driver_id"] ?? null;
$route_id = $input["route_id"] ?? null;
$direction_id = $input["direction_id"] ?? null;
$schedule_id = $input["schedule_id"] ?? null;

if (!$bus_id || !$driver_id || !$route_id || !$direction_id) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "bus_id, driver_id, route_id and direction_id are required"
    ]);

    exit;
}

$database = new Database();
$db = $database->connect();

try {

    // Check active bus assignment
    $stmt = $db->prepare(
        "SELECT id
         FROM bus_assignments
         WHERE bus_id = :bus_id
         AND driver_id = :driver_id
         AND route_id = :route_id
         AND direction_id = :direction_id
         AND status = 'ACTIVE'
         AND unassigned_at IS NULL
         LIMIT 1"
    );

    $stmt->execute([
        ":bus_id" => $bus_id,
        ":driver_id" => $driver_id,
        ":route_id" => $route_id,
        ":direction_id" => $direction_id
    ]);

    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Active bus assignment not found"
        ]);

        exit;
    }

    // Check if bus already has an active trip
    $stmt = $db->prepare(
        "SELECT id
         FROM trips
         WHERE bus_id = :bus_id
         AND status = 'ACTIVE'
         LIMIT 1"
    );

    $stmt->execute([
        ":bus_id" => $bus_id
    ]);

    if ($stmt->fetch()) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "This bus already has an active trip"
        ]);

        exit;
    }

    // Create trip
    $stmt = $db->prepare(
        "INSERT INTO trips
        (
            bus_id,
            driver_id,
            route_id,
            direction_id,
            schedule_id,
            status
        )
        VALUES
        (
            :bus_id,
            :driver_id,
            :route_id,
            :direction_id,
            :schedule_id,
            'SCHEDULED'
        )"
    );

    $stmt->execute([
        ":bus_id" => $bus_id,
        ":driver_id" => $driver_id,
        ":route_id" => $route_id,
        ":direction_id" => $direction_id,
        ":schedule_id" => $schedule_id
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Trip created successfully",
        "trip_id" => $db->lastInsertId(),
        "status" => "SCHEDULED"
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to create trip"
    ]);
}