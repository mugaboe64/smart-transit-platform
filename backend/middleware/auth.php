<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json");

function authenticate()
{
    $headers = getallheaders();

    $authorization = $headers["Authorization"]
        ?? $headers["authorization"]
        ?? null;

    if (!$authorization) {
        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Authorization token is required"
        ]);

        exit;
    }

    if (!preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Invalid authorization format"
        ]);

        exit;
    }

    $plainToken = trim($matches[1]);

    if ($plainToken === "") {
        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Invalid token"
        ]);

        exit;
    }

    $tokenHash = hash("sha256", $plainToken);

    $database = new Database();
    $db = $database->connect();

    try {

        $query = "SELECT
                    u.id,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.phone,
                    u.status,
                    r.id AS role_id,
                    r.name AS role,
                    t.expires_at
                  FROM auth_tokens t

                  INNER JOIN users u
                    ON t.user_id = u.id

                  INNER JOIN roles r
                    ON u.role_id = r.id

                  WHERE t.token_hash = :token_hash
                  LIMIT 1";

        $statement = $db->prepare($query);

        $statement->execute([
            ":token_hash" => $tokenHash
        ]);

        $user = $statement->fetch();

        if (!$user) {
            http_response_code(401);

            echo json_encode([
                "success" => false,
                "message" => "Invalid token"
            ]);

            exit;
        }

        if (strtotime($user["expires_at"]) < time()) {

            http_response_code(401);

            echo json_encode([
                "success" => false,
                "message" => "Token has expired"
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

        return $user;

    } catch (PDOException $e) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Authentication failed"
        ]);

        exit;
    }
}
 
function requireRole($allowedRoles)
{
    $user = authenticate();

    $userRole = strtoupper($user["role"]);

    $allowedRoles = array_map(
        "strtoupper",
        $allowedRoles
    );

    if (!in_array($userRole, $allowedRoles)) {

        http_response_code(403);

        echo json_encode([
            "success" => false,
            "message" => "Access denied",
            "required_roles" => $allowedRoles,
            "your_role" => $userRole
        ]);

        exit;
    }

    return $user;
}