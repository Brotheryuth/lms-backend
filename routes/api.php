<?php

require_once __DIR__ . "/../controllers/AuthController.php";
require_once __DIR__ . "/../controllers/StudentController.php";
require_once __DIR__ . "/../controllers/CourseController.php";
require_once __DIR__ . "/../controllers/InstructorController.php";
require_once __DIR__ . "/../controllers/EnrollmentController.php";
require_once __DIR__ . "/../controllers/AnalyticsController.php";

// ── Helper: match URI with params like /api/students/S00001 ──
function matchRoute(string $pattern, string $uri, array &$params = []): bool {
    $pattern = preg_replace("/\/:([^\/]+)/", "/(?P<$1>[^/]+)", $pattern);
    if (preg_match("#^$pattern$#", $uri, $matches)) {
        foreach ($matches as $k => $v) {
            if (!is_int($k)) $params[$k] = $v;
        }
        return true;
    }
    return false;
}

$params = [];

// ── SEED ADMIN (delete after use) ────────────────────────────
if ($requestMethod === "GET" && $uri === "/api/seed") {
    $db = (new Database())->connect();
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(50) DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $hash = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (email, password_hash, role) VALUES (:email, :hash, 'admin') ON CONFLICT (email) DO UPDATE SET password_hash = :hash");
    $stmt->execute([':email' => 'admin@lms.com', ':hash' => $hash]);
    echo json_encode(['success' => true, 'message' => 'Admin created! Email: admin@lms.com | Password: admin123']);
    exit;
}

// ── AUTH ──────────────────────────────────────────────────────
if ($requestMethod === "POST" && $uri === "/api/auth/login") {
    (new AuthController())->login();
}
elseif ($requestMethod === "GET" && $uri === "/api/auth/me") {
    (new AuthController())->me();
}

// ── STUDENTS ─────────────────────────────────────────────────
elseif ($requestMethod === "GET" && $uri === "/api/students") {
    (new StudentController())->index();
}
elseif ($requestMethod === "POST" && $uri === "/api/students") {
    (new StudentController())->store();
}
elseif ($requestMethod === "GET" && matchRoute("/api/students/:id", $uri, $params)) {
    (new StudentController())->show($params["id"]);
}
elseif ($requestMethod === "PUT" && matchRoute("/api/students/:id", $uri, $params)) {
    (new StudentController())->update($params["id"]);
}
elseif ($requestMethod === "DELETE" && matchRoute("/api/students/:id", $uri, $params)) {
    (new StudentController())->destroy($params["id"]);
}

// ── COURSES ───────────────────────────────────────────────────
elseif ($requestMethod === "GET" && $uri === "/api/courses") {
    (new CourseController())->index();
}
elseif ($requestMethod === "POST" && $uri === "/api/courses") {
    (new CourseController())->store();
}
elseif ($requestMethod === "GET" && matchRoute("/api/courses/:id", $uri, $params)) {
    (new CourseController())->show($params["id"]);
}
elseif ($requestMethod === "PUT" && matchRoute("/api/courses/:id", $uri, $params)) {
    (new CourseController())->update($params["id"]);
}
elseif ($requestMethod === "DELETE" && matchRoute("/api/courses/:id", $uri, $params)) {
    (new CourseController())->destroy($params["id"]);
}

// ── INSTRUCTORS ───────────────────────────────────────────────
elseif ($requestMethod === "GET" && $uri === "/api/instructors") {
    (new InstructorController())->index();
}
elseif ($requestMethod === "POST" && $uri === "/api/instructors") {
    (new InstructorController())->store();
}
elseif ($requestMethod === "GET" && matchRoute("/api/instructors/:id", $uri, $params)) {
    (new InstructorController())->show($params["id"]);
}
elseif ($requestMethod === "PUT" && matchRoute("/api/instructors/:id", $uri, $params)) {
    (new InstructorController())->update($params["id"]);
}
elseif ($requestMethod === "DELETE" && matchRoute("/api/instructors/:id", $uri, $params)) {
    (new InstructorController())->destroy($params["id"]);
}

// ── ENROLLMENTS ───────────────────────────────────────────────
elseif ($requestMethod === "GET" && $uri === "/api/enrollments") {
    (new EnrollmentController())->index();
}
elseif ($requestMethod === "POST" && $uri === "/api/enrollments") {
    (new EnrollmentController())->store();
}
elseif ($requestMethod === "GET" && matchRoute("/api/enrollments/:id", $uri, $params)) {
    (new EnrollmentController())->show($params["id"]);
}
elseif ($requestMethod === "PUT" && matchRoute("/api/enrollments/:id", $uri, $params)) {
    (new EnrollmentController())->update($params["id"]);
}
elseif ($requestMethod === "DELETE" && matchRoute("/api/enrollments/:id", $uri, $params)) {
    (new EnrollmentController())->destroy($params["id"]);
}

// ── ANALYTICS ─────────────────────────────────────────────────
elseif ($requestMethod === "GET" && $uri === "/api/analytics/overview") {
    (new AnalyticsController())->overview();
}
elseif ($requestMethod === "GET" && $uri === "/api/analytics/completion-rate") {
    (new AnalyticsController())->completionRate();
}
elseif ($requestMethod === "GET" && $uri === "/api/analytics/drop-rate") {
    (new AnalyticsController())->dropRate();
}
elseif ($requestMethod === "GET" && $uri === "/api/analytics/scores") {
    (new AnalyticsController())->scores();
}
elseif ($requestMethod === "GET" && $uri === "/api/analytics/grades") {
    (new AnalyticsController())->grades();
}
elseif ($requestMethod === "GET" && $uri === "/api/analytics/engagement") {
    (new AnalyticsController())->engagement();
}
elseif ($requestMethod === "GET" && $uri === "/api/analytics/at-risk") {
    (new AnalyticsController())->atRisk();
}
elseif ($requestMethod === "GET" && $uri === "/api/analytics/devices") {
    (new AnalyticsController())->devices();
}

// ── 404 ───────────────────────────────────────────────────────
else {
    Response::error("Route not found", 404);
}