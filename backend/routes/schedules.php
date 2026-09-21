<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json");

$database = new Database();
$db = $database->connect();

try {

    $query = "SELECT
                s.id,
                s.route_id,
                r.route_code,
                r.route_name,
                s.direction_id,
                d.name AS direction_name,
                d.direction_code,
                s.departure_time,
                s.arrival_time,
                s.days_of_operation,
                s.status
              FROM schedules s

              INNER JOIN routes r
                ON s.route_id = r.id

              INNER JOIN directions d
                ON s.direction_id = d.id

              ORDER BY s.departure_time ASC";

    $statement = $db->prepare($query);
    $statement->execute();

    $schedules = $statement->fetchAll();

    echo json_encode([
        "success" => true,
        "count" => count($schedules),
        "data" => $schedules
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve schedules"
    ]);
}