<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json");

$database = new Database();
$db = $database->connect();

try {

    $query = "SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                r.name AS role,
                u.status,
                u.created_at
              FROM users u
              INNER JOIN roles r
                ON u.role_id = r.id
              ORDER BY u.id ASC";

    $statement = $db->prepare($query);
    $statement->execute();

    $users = $statement->fetchAll();

    echo json_encode([
        "success" => true,
        "count" => count($users),
        "data" => $users
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}