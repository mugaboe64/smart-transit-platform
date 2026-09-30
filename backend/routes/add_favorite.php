<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../middleware/auth.php";

header("Content-Type: application/json");

$user = requireRole(["PASSENGER", "ADMIN"]);

$bus_id = $_POST["bus_id"] ?? null;
$route_id = $_POST["route_id"] ?? null;

if (!$bus_id && !$route_id) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "bus_id or route_id is required"
    ]);

    exit;
}

$database = new Database();
$db = $database->connect();

try {

    // Check bus
    if ($bus_id) {

        $stmt = $db->prepare(
            "SELECT id FROM buses WHERE id = :bus_id LIMIT 1"
        );

        $stmt->execute([
            ":bus_id" => $bus_id
        ]);

        if (!$stmt->fetch()) {

            http_response_code(404);

            echo json_encode([
                "success" => false,
                "message" => "Bus not found"
            ]);

            exit;
        }
    }

    // Check route
    if ($route_id) {

        $stmt = $db->prepare(
            "SELECT id FROM routes WHERE id = :route_id LIMIT 1"
        );

        $stmt->execute([
            ":route_id" => $route_id
        ]);

        if (!$stmt->fetch()) {

            http_response_code(404);

            echo json_encode([
                "success" => false,
                "message" => "Route not found"
            ]);

            exit;
        }
    }

    // Add favorite
    $stmt = $db->prepare(
        "INSERT INTO favorites
        (user_id, bus_id, route_id)
        VALUES
        (:user_id, :bus_id, :route_id)"
    );

    $stmt->execute([
        ":user_id" => $user["id"],
        ":bus_id" => $bus_id ?: null,
        ":route_id" => $route_id ?: null
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Favorite added successfully",
        "favorite_id" => $db->lastInsertId()
    ]);

} catch (PDOException $e) {

    if ($e->getCode() == 23000) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "This favorite already exists"
        ]);

        exit;
    }

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to add favorite"
    ]);
}