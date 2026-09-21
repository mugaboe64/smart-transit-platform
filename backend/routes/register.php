<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed"
    ]);

    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$firstName = trim($data["first_name"] ?? "");
$lastName  = trim($data["last_name"] ?? "");
$email     = trim($data["email"] ?? "");
$phone     = trim($data["phone"] ?? "");
$password  = $data["password"] ?? "";
$roleId    = $data["role_id"] ?? 3;

if (
    $firstName === "" ||
    $lastName === "" ||
    $email === "" ||
    $password === ""
) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "First name, last name, email and password are required"
    ]);

    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid email address"
    ]);

    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Password must contain at least 6 characters"
    ]);

    exit;
}

$database = new Database();
$db = $database->connect();

try {

    // Check whether email already exists
    $checkQuery = "SELECT id FROM users WHERE email = :email LIMIT 1";

    $checkStatement = $db->prepare($checkQuery);
    $checkStatement->execute([
        ":email" => $email
    ]);

    if ($checkStatement->fetch()) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Email already exists"
        ]);

        exit;
    }

    // Check whether selected role exists
    $roleQuery = "SELECT id FROM roles WHERE id = :role_id LIMIT 1";

    $roleStatement = $db->prepare($roleQuery);
    $roleStatement->execute([
        ":role_id" => $roleId
    ]);

    if (!$roleStatement->fetch()) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Selected role does not exist"
        ]);

        exit;
    }

    // Secure password hashing
    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    // Create user
    $query = "INSERT INTO users
                (
                    role_id,
                    first_name,
                    last_name,
                    email,
                    phone,
                    password_hash,
                    status
                )
              VALUES
                (
                    :role_id,
                    :first_name,
                    :last_name,
                    :email,
                    :phone,
                    :password_hash,
                    'ACTIVE'
                )";

    $statement = $db->prepare($query);

    $statement->execute([
        ":role_id"       => $roleId,
        ":first_name"    => $firstName,
        ":last_name"     => $lastName,
        ":email"         => $email,
        ":phone"         => $phone !== "" ? $phone : null,
        ":password_hash" => $passwordHash
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Account created successfully",
        "user_id" => $db->lastInsertId()
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Registration failed"
    ]);
}