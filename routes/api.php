<?php

require_once __DIR__ . "/../controllers/AuthController.php";
require_once __DIR__ . "/../controllers/StudentController.php";
require_once __DIR__ . "/../controllers/CourseController.php";
require_once __DIR__ . "/../controllers/InstructorController.php";
require_once __DIR__ . "/../controllers/EnrollmentController.php";
require_once __DIR__ . "/../controllers/AnalyticsController.php";

$db = (new Database())->connect();

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

// ── AUTH ──────────────────────────────────────────────────────
if ($requestMethod === "POST" && $uri === "/api/auth/login") {
    (new AuthController($db))->login();
}
elseif ($requestMethod === "GET" && $uri === "/api/auth/me") {
    (new AuthController($db))->me();
}

// ── STUDENTS ─────────────────────────────────────────────────
elseif ($requestMethod === "GET" && $uri === "/api/students") {
    (new StudentController($db))->index();
}
elseif ($requestMethod === "POST" && $uri === "/api/students") {
    (new StudentController($db))->store();
}
elseif ($requestMethod === "GET" && matchRoute("/api/students/:id", $uri, $params)) {
    (new StudentController($db))->show($params["id"]);
}
elseif ($requestMethod === "PUT" && matchRoute("/api/students/:id", $uri, $params)) {
    (new StudentController($db))->update($params["id"]);
}
elseif ($requestMethod === "DELETE" && matchRoute("/api/students/:id", $uri, $params)) {
    (new StudentController($db))->destroy($params["id"]);
}

// ── COURSES ───────────────────────────────────────────────────
elseif ($requestMethod === "GET" && $uri === "/api/courses") {
    (new CourseController($db))->index();
}
elseif ($requestMethod === "POST" && $uri === "/api/courses") {
    (new CourseController($db))->store();
}
elseif ($requestMethod === "GET" && matchRoute("/api/courses/:id", $uri, $params)) {
    (new CourseController($db))->show($params["id"]);
}
elseif ($requestMethod === "PUT" && matchRoute("/api/courses/:id", $uri, $params)) {
    (new CourseController($db))->update($params["id"]);
}
elseif ($requestMethod === "DELETE" && matchRoute("/api/courses/:id", $uri, $params)) {
    (new CourseController($db))->destroy($params["id"]);
}

// ── INSTRUCTORS ───────────────────────────────────────────────
elseif ($requestMethod === "GET" && $uri === "/api/instructors") {
    (new InstructorController($db))->index();
}
elseif ($requestMethod === "POST" && $uri === "/api/instructors") {
    (new InstructorController($db))->store();
}
elseif ($requestMethod === "GET" && matchRoute("/api/instructors/:id", $uri, $params)) {
    (new InstructorController($db))->show($params["id"]);
}
elseif ($requestMethod === "PUT" && matchRoute("/api/instructors/:id", $uri, $params)) {
    (new InstructorController($db))->update($params["id"]);
}
elseif ($requestMethod === "DELETE" && matchRoute("/api/instructors/:id", $uri, $params)) {
    (new InstructorController($db))->destroy($params["id"]);
}

// ── ENROLLMENTS ───────────────────────────────────────────────
elseif ($requestMethod === "GET" && $uri === "/api/enrollments") {
    (new EnrollmentController($db))->index();
}
elseif ($requestMethod === "POST" && $uri === "/api/enrollments") {
    (new EnrollmentController($db))->store();
}
elseif ($requestMethod === "GET" && matchRoute("/api/enrollments/:id", $uri, $params)) {
    (new EnrollmentController($db))->show($params["id"]);
}
elseif ($requestMethod === "PUT" && matchRoute("/api/enrollments/:id", $uri, $params)) {
    (new EnrollmentController($db))->update($params["id"]);
}
elseif ($requestMethod === "DELETE" && matchRoute("/api/enrollments/:id", $uri, $params)) {
    (new EnrollmentController($db))->destroy($params["id"]);
}

// ── ANALYTICS ─────────────────────────────────────────────────
elseif ($requestMethod === "GET" && $uri === "/api/analytics/overview") {
    (new AnalyticsController($db))->overview();
}
elseif ($requestMethod === "GET" && $uri === "/api/analytics/completion-rate") {
    (new AnalyticsController($db))->completionRate();
}
elseif ($requestMethod === "GET" && $uri === "/api/analytics/drop-rate") {
    (new AnalyticsController($db))->dropRate();
}
elseif ($requestMethod === "GET" && $uri === "/api/analytics/scores") {
    (new AnalyticsController($db))->scores();
}
elseif ($requestMethod === "GET" && $uri === "/api/analytics/grades") {
    (new AnalyticsController($db))->grades();
}
elseif ($requestMethod === "GET" && $uri === "/api/analytics/engagement") {
    (new AnalyticsController($db))->engagement();
}
elseif ($requestMethod === "GET" && $uri === "/api/analytics/at-risk") {
    (new AnalyticsController($db))->atRisk();
}
elseif ($requestMethod === "GET" && $uri === "/api/analytics/devices") {
    (new AnalyticsController($db))->devices();
}

// ── 404 ───────────────────────────────────────────────────────
else {
    Response::error("Route not found", 404);
}