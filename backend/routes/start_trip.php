<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../middleware/auth.php";

header("Content-Type: application/json");

$user = requireRole(["ADMIN"]);

$input = json_decode(file_get_contents("php://input"), true);

$trip_id = $input["trip_id"] ?? null;

if (!$trip_id) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "trip_id is required"
    ]);

    exit;
}

$database = new Database();
$db = $database->connect();

try {

    // Find active trip
    $stmt = $db->prepare(
        "SELECT id
         FROM trips
         WHERE id = :trip_id
         AND status = 'ACTIVE'
         LIMIT 1"
    );

    $stmt->execute([
        ":trip_id" => $trip_id
    ]);

    if (!$stmt->fetch()) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Active trip not found"
        ]);

        exit;
    }

    // Complete trip
    $stmt = $db->prepare(
        "UPDATE trips
         SET status = 'COMPLETED',
             completed_at = NOW()
         WHERE id = :trip_id
         AND status = 'ACTIVE'"
    );

    $stmt->execute([
        ":trip_id" => $trip_id
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Trip completed successfully",
        "trip_id" => $trip_id,
        "status" => "COMPLETED"
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to complete trip"
    ]);
}