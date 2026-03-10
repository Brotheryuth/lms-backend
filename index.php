<?php

require_once __DIR__ . "/config/cors.php";
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/helpers/Response.php";
require_once __DIR__ . "/helpers/JWT.php";
require_once __DIR__ . "/helpers/Validator.php";
require_once __DIR__ . "/middleware/AuthMiddleware.php";

// Set CORS headers first
setCorsHeaders();

// Parse URI
$requestUri    = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$requestUri    = rtrim($requestUri, "/");
$requestMethod = $_SERVER["REQUEST_METHOD"];

// Strip base path including /index.php if called directly
$basePath = "/LMS-Project/backend";
$uri      = str_replace($basePath, "", $requestUri);
$uri      = str_replace("/index.php", "", $uri);
if ($uri === "" || $uri === "/") $uri = "/";

// Load routes
require_once __DIR__ . "/routes/api.php";