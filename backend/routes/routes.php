<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json");

$database = new Database();
$db = $database->connect();

try {

    $query = "SELECT
                id,
                route_code,
                route_name,
                origin,
                destination,
                total_distance,
                status
              FROM routes
              ORDER BY id ASC";

    $statement = $db->prepare($query);
    $statement->execute();

    $routes = $statement->fetchAll();

    echo json_encode([
        "success" => true,
        "count" => count($routes),
        "data" => $routes
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve routes"
    ]);
}