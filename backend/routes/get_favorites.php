
<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../middleware/auth.php";

header("Content-Type: application/json");

$user = requireRole(["PASSENGER", "ADMIN"]);

$database = new Database();
$db = $database->connect();

try {

    $stmt = $db->prepare(
        "SELECT
            f.id AS favorite_id,
            f.bus_id,
            b.bus_number,
            b.plate_number,
            f.route_id,
            r.route_code,
            r.route_name,
            r.origin,
            r.destination,
            f.created_at
        FROM favorites f
        LEFT JOIN buses b ON f.bus_id = b.id
        LEFT JOIN routes r ON f.route_id = r.id
        WHERE f.user_id = :user_id
        ORDER BY f.created_at DESC"
    );

    $stmt->execute([
        ":user_id" => $user["id"]
    ]);

    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "message" => "Favorites retrieved successfully",
        "count" => count($favorites),
        "favorites" => $favorites
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve favorites"
    ]);
}