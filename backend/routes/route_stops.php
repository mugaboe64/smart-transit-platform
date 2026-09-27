<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../middleware/auth.php";

header("Content-Type: application/json");

// Authenticated users can view route stops
$user = requireRole(["ADMIN", "DRIVER", "PASSENGER"]);

$route_id = $_GET["route_id"] ?? null;
$direction_id = $_GET["direction_id"] ?? null;

if (!$route_id || !$direction_id) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "route_id and direction_id are required"
    ]);

    exit;
}

$database = new Database();
$db = $database->connect();

try {

    /*
    |--------------------------------------------------------------------------
    | Get route information
    |--------------------------------------------------------------------------
    */

    $routeStmt = $db->prepare(
        "SELECT
            id,
            route_code,
            route_name,
            origin,
            destination,
            total_distance,
            status
         FROM routes
         WHERE id = :route_id
         LIMIT 1"
    );

    $routeStmt->execute([
        ":route_id" => $route_id
    ]);

    $route = $routeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$route) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Route not found"
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Get direction information
    |--------------------------------------------------------------------------
    */

    $directionStmt = $db->prepare(
        "SELECT
            id,
            route_id,
            name,
            direction_code
         FROM directions
         WHERE id = :direction_id
         AND route_id = :route_id
         LIMIT 1"
    );

    $directionStmt->execute([
        ":direction_id" => $direction_id,
        ":route_id" => $route_id
    ]);

    $direction = $directionStmt->fetch(PDO::FETCH_ASSOC);

    if (!$direction) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Direction not found for this route"
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Get stops in sequence
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare(
        "SELECT
            rs.id AS route_stop_id,
            rs.sequence_number,
            rs.distance_from_previous,
            rs.scheduled_arrival,
            rs.scheduled_departure,

            s.id AS stop_id,
            s.stop_code,
            s.name AS stop_name,
            s.latitude,
            s.longitude,
            s.description,
            s.accessibility,
            s.status AS stop_status

         FROM route_stops rs

         INNER JOIN stops s
            ON rs.stop_id = s.id

         WHERE rs.route_id = :route_id
         AND rs.direction_id = :direction_id

         ORDER BY rs.sequence_number ASC"
    );

    $stmt->execute([
        ":route_id" => $route_id,
        ":direction_id" => $direction_id
    ]);

    $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "message" => "Route stops retrieved successfully",

        "route" => [
            "id" => $route["id"],
            "code" => $route["route_code"],
            "name" => $route["route_name"],
            "origin" => $route["origin"],
            "destination" => $route["destination"],
            "total_distance" => $route["total_distance"],
            "status" => $route["status"]
        ],

        "direction" => [
            "id" => $direction["id"],
            "name" => $direction["name"],
            "code" => $direction["direction_code"]
        ],

        "stops_count" => count($stops),

        "stops" => $stops
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve route stops"
    ]);
}