
<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../middleware/auth.php";

header("Content-Type: application/json");

$user = requireRole(["PASSENGER", "ADMIN"]);

$favorite_id = $_POST["favorite_id"] ?? null;

if (!$favorite_id || !is_numeric($favorite_id) || (int)$favorite_id <= 0) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "A valid favorite_id is required"
    ]);

    exit;
}

$database = new Database();
$db = $database->connect();

try {

    // Check whether this favorite belongs to the logged-in user
    $stmt = $db->prepare(
        "SELECT id
         FROM favorites
         WHERE id = :favorite_id
         AND user_id = :user_id
         LIMIT 1"
    );

    $stmt->execute([
        ":favorite_id" => (int)$favorite_id,
        ":user_id" => $user["id"]
    ]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Favorite not found"
        ]);

        exit;
    }

    // Delete the favorite
    $stmt = $db->prepare(
        "DELETE FROM favorites
         WHERE id = :favorite_id
         AND user_id = :user_id"
    );

    $stmt->execute([
        ":favorite_id" => (int)$favorite_id,
        ":user_id" => $user["id"]
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Favorite removed successfully"
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to remove favorite"
    ]);
}