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

    // Check bus
    $stmt = $db->prepare(
        "SELECT id FROM buses
         WHERE id = :id
         AND status = 'ACTIVE'
         LIMIT 1"
    );

    $stmt->execute([
        ":id" => $bus_id
    ]);

    if (!$stmt->fetch()) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Active bus not found"
        ]);

        exit;
    }

    // Check driver
    $stmt = $db->prepare(
        "SELECT id FROM drivers
         WHERE id = :id
         AND employment_status = 'ACTIVE'
         LIMIT 1"
    );

    $stmt->execute([
        ":id" => $driver_id
    ]);

    if (!$stmt->fetch()) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Active driver not found"
        ]);

        exit;
    }

    // Check route
    $stmt = $db->prepare(
        "SELECT id FROM routes
         WHERE id = :id
         AND status = 'ACTIVE'
         LIMIT 1"
    );

    $stmt->execute([
        ":id" => $route_id
    ]);

    if (!$stmt->fetch()) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Active route not found"
        ]);

        exit;
    }

    // Check direction
    $stmt = $db->prepare(
        "SELECT id FROM directions
         WHERE id = :id
         LIMIT 1"
    );

    $stmt->execute([
        ":id" => $direction_id
    ]);

    if (!$stmt->fetch()) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Direction not found"
        ]);

        exit;
    }

    // Check existing active assignment for this bus
    $stmt = $db->prepare(
        "SELECT id
         FROM bus_assignments
         WHERE bus_id = :bus_id
         AND status = 'ACTIVE'
         AND unassigned_at IS NULL
         LIMIT 1"
    );

    $stmt->execute([
        ":bus_id" => $bus_id
    ]);

    if ($stmt->fetch()) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "This bus already has an active assignment"
        ]);

        exit;
    }

    // Create assignment
    $stmt = $db->prepare(
        "INSERT INTO bus_assignments
        (
            bus_id,
            driver_id,
            route_id,
            direction_id,
            status
        )
        VALUES
        (
            :bus_id,
            :driver_id,
            :route_id,
            :direction_id,
            'ACTIVE'
        )"
    );

    $stmt->execute([
        ":bus_id" => $bus_id,
        ":driver_id" => $driver_id,
        ":route_id" => $route_id,
        ":direction_id" => $direction_id
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Bus assigned successfully",
        "assignment_id" => $db->lastInsertId()
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to create bus assignment"
    ]);
}