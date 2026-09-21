<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json");

$database = new Database();
$db = $database->connect();

try {

    $query = "SELECT
                id,
                bus_number,
                plate_number,
                capacity,
                gps_device_id,
                status
              FROM buses
              ORDER BY id ASC";

    $statement = $db->prepare($query);
    $statement->execute();

    $buses = $statement->fetchAll();

    echo json_encode([
        "success" => true,
        "count" => count($buses),
        "data" => $buses
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve buses"
    ]);
}