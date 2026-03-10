<?php

require_once __DIR__ . "/../helpers/JWT.php";
require_once __DIR__ . "/../helpers/Response.php";

class AuthMiddleware {
    public static function handle(): array {
        $headers = getallheaders();
        $auth    = $headers["Authorization"] ?? $headers["authorization"] ?? "";

        if (!str_starts_with($auth, "Bearer ")) {
            Response::error("Unauthorized — no token provided", 401);
        }

        $token   = substr($auth, 7);
        $payload = JWT::decode($token);

        if (!$payload) {
            Response::error("Unauthorized — invalid or expired token", 401);
        }

        return $payload;
    }

    public static function requireRole(array $payload, string $role): void {
        if (($payload["role"] ?? "") !== $role) {
            Response::error("Forbidden — insufficient permissions", 403);
        }
    }
}