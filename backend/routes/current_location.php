<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../middleware/auth.php";

header("Content-Type: application/json");

// For now, require an authenticated user
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

    $stmt = $db->prepare(
        "SELECT
            id,
            bus_id,
            latitude,
            longitude,
            speed,
            heading,
            accuracy,
            status,
            last_updated
         FROM bus_current_locations
         WHERE bus_id = :bus_id
         LIMIT 1"
    );

    $stmt->execute([
        ":bus_id" => $bus_id
    ]);

    $location = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$location) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Current location not found for this bus"
        ]);

        exit;
    }

    echo json_encode([
        "success" => true,
        "message" => "Current bus location retrieved successfully",
        "data" => $location
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve current bus location"
    ]);
}