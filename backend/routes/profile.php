<?php

require_once __DIR__ . "/../middleware/auth.php";

$user = authenticate();

echo json_encode([
    "success" => true,
    "message" => "Authentication successful",
    "user" => $user
]);