<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json");

$database = new Database();
$db = $database->connect();

try {

    $query = "SELECT
                id,
                stop_code,
                name,
                latitude,
                longitude,
                description,
                accessibility,
                status
              FROM stops
              ORDER BY id ASC";

    $statement = $db->prepare($query);
    $statement->execute();

    $stops = $statement->fetchAll();

    echo json_encode([
        "success" => true,
        "count" => count($stops),
        "data" => $stops
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve stops"
    ]);
}