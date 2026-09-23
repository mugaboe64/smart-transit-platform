<?php

require_once __DIR__ . "/../middleware/auth.php";

$user = requireRole(["DRIVER"]);

echo json_encode([
    "success" => true,
    "message" => "Welcome to the driver area",
    "user" => [
        "id" => $user["id"],
        "name" => $user["first_name"] . " " . $user["last_name"],
        "role" => $user["role"]
    ]
]);