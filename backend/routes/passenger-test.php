<?php

require_once __DIR__ . "/../middleware/auth.php";

$user = requireRole(["PASSENGER"]);

echo json_encode([
    "success" => true,
    "message" => "Welcome to the passenger area",
    "user" => [
        "id" => $user["id"],
        "name" => $user["first_name"] . " " . $user["last_name"],
        "role" => $user["role"]
    ]
]);