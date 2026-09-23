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

$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";

if ($email === "" || $password === "") {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Email and password are required"
    ]);

    exit;
}

$database = new Database();
$db = $database->connect();

try {

    $query = "SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                u.password_hash,
                u.status,
                r.id AS role_id,
                r.name AS role
              FROM users u
              INNER JOIN roles r
                ON u.role_id = r.id
              WHERE u.email = :email
              LIMIT 1";

    $statement = $db->prepare($query);

    $statement->execute([
        ":email" => $email
    ]);

    $user = $statement->fetch();

    if (!$user) {
        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password"
        ]);

        exit;
    }

    if (!password_verify($password, $user["password_hash"])) {
        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password"
        ]);

        exit;
    }

    if ($user["status"] !== "ACTIVE") {
        http_response_code(403);

        echo json_encode([
            "success" => false,
            "message" => "Account is not active"
        ]);

        exit;
    }

    /*
     * Generate secure random token
     */
    $plainToken = bin2hex(random_bytes(32));

    /*
     * Store only the hash of the token
     */
    $tokenHash = hash("sha256", $plainToken);

    /*
     * Token expires after 24 hours
     */
    $expiresAt = date(
        "Y-m-d H:i:s",
        time() + (24 * 60 * 60)
    );

    /*
     * Remove old tokens for this user
     */
    $deleteQuery = "DELETE FROM auth_tokens
                    WHERE user_id = :user_id";

    $deleteStatement = $db->prepare($deleteQuery);

    $deleteStatement->execute([
        ":user_id" => $user["id"]
    ]);

    /*
     * Save new token
     */
    $tokenQuery = "INSERT INTO auth_tokens
                    (
                        user_id,
                        token_hash,
                        expires_at
                    )
                   VALUES
                    (
                        :user_id,
                        :token_hash,
                        :expires_at
                    )";

    $tokenStatement = $db->prepare($tokenQuery);

    $tokenStatement->execute([
        ":user_id" => $user["id"],
        ":token_hash" => $tokenHash,
        ":expires_at" => $expiresAt
    ]);

    /*
     * Never return password hash
     */
    unset($user["password_hash"]);

    echo json_encode([
        "success" => true,
        "message" => "Login successful",
        "token" => $plainToken,
        "expires_at" => $expiresAt,
        "user" => $user
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Login failed"
    ]);
}