<?php

require_once __DIR__ . "/../helpers/JWT.php";
require_once __DIR__ . "/../helpers/Response.php";
require_once __DIR__ . "/../helpers/Validator.php";
require_once __DIR__ . "/../middleware/AuthMiddleware.php";

class AuthController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // POST /api/auth/login
    public function login(): void {
        $body = json_decode(file_get_contents("php://input"), true);

        $email    = trim($body["email"]    ?? "");
        $password = trim($body["password"] ?? "");

        $v = new Validator();
        $v->required("email", $email)
          ->email("email", $email)
          ->required("password", $password);

        if (!$v->passes()) {
            Response::error("Validation failed", 422, $v->errors());
        }

        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([":email" => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user["password"])) {
            Response::error("Invalid email or password", 401);
        }

        $payload = [
            "id"    => $user["id"],
            "name"  => $user["name"],
            "email" => $user["email"],
            "role"  => $user["role"],
            "exp"   => time() + (60 * 60 * 24), // 24 hours
        ];

        $token = JWT::encode($payload);

        Response::success([
            "token" => $token,
            "user"  => [
                "id"    => $user["id"],
                "name"  => $user["name"],
                "email" => $user["email"],
                "role"  => $user["role"],
            ]
        ], "Login successful");
    }

    // GET /api/auth/me
    public function me(): void {
        $payload = AuthMiddleware::handle();

        $stmt = $this->db->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = :id");
        $stmt->execute([":id" => $payload["id"]]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error("User not found", 404);
        }

        Response::success($user);
    }
}