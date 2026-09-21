<?php

require_once __DIR__ . "/config/database.php";

$database = new Database();
$db = $database->connect();

echo "Smart Transit Platform Backend is connected to the database successfully!";