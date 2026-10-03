 
<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../middleware/auth.php";

header("Content-Type: application/json");

$user = requireRole(["ADMIN"]);

$input = $_POST;

if (empty($input)) {
    $json = json_decode(file_get_contents("php://input"), true);
    if (is_array($json)) {
        $input = $json;
    }
}

$required = ["user_id", "title", "message"];

foreach ($required as $field) {
    if (!isset($input[$field]) || trim((string)$input[$field]) === "") {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "$field is required"
        ]);

        exit;
    }
}

$user_id = filter_var($input["user_id"], FILTER_VALIDATE_INT);
$bus_id = isset($input["bus_id"]) && $input["bus_id"] !== ""
    ? filter_var($input["bus_id"], FILTER_VALIDATE_INT)
    : null;
$route_id = isset($input["route_id"]) && $input["route_id"] !== ""
    ? filter_var($input["route_id"], FILTER_VALIDATE_INT)
    : null;

$title = trim($input["title"]);
$message = trim($input["message"]);
$type = trim($input["type"] ?? "GENERAL");

if (!$user_id || $user_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A valid user_id is required"
    ]);
    exit;
}

if (
    ($bus_id !== null && (!$bus_id || $bus_id <= 0)) ||
    ($route_id !== null && (!$route_id || $route_id <= 0))
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "bus_id and route_id must be valid positive integers"
    ]);
    exit;
}

if (strlen($title) > 150 || strlen($type) > 50) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Title or type exceeds the allowed length"
    ]);
    exit;
}

$database = new Database();
$db = $database->connect();

try {

    $stmt = $db->prepare(
        "SELECT id FROM users WHERE id = :user_id LIMIT 1"
    );
    $stmt->execute([":user_id" => $user_id]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "User not found"
        ]);
        exit;
    }

    if ($bus_id !== null) {
        $stmt = $db->prepare(
            "SELECT id FROM buses WHERE id = :bus_id LIMIT 1"
        );
        $stmt->execute([":bus_id" => $bus_id]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Bus not found"
            ]);
            exit;
        }
    }

    if ($route_id !== null) {
        $stmt = $db->prepare(
            "SELECT id FROM routes WHERE id = :route_id LIMIT 1"
        );
        $stmt->execute([":route_id" => $route_id]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Route not found"
            ]);
            exit;
        }
    }

    $stmt = $db->prepare(
        "INSERT INTO notifications
        (user_id, bus_id, route_id, title, message, type)
        VALUES
        (:user_id, :bus_id, :route_id, :title, :message, :type)"
    );

    $stmt->execute([
        ":user_id" => $user_id,
        ":bus_id" => $bus_id,
        ":route_id" => $route_id,
        ":title" => $title,
        ":message" => $message,
        ":type" => $type !== "" ? $type : "GENERAL"
    ]);

    http_response_code(201);

    echo json_encode([
        "success" => true,
        "message" => "Notification created successfully",
        "notification_id" => $db->lastInsertId()
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to create notification"
    ]);
}