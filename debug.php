<?php
echo json_encode([
    "REQUEST_URI"    => $_SERVER["REQUEST_URI"],
    "parsed_uri"     => parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH),
    "stripped_uri"   => str_replace("/LMS-Project/backend", "", parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)),
    "REQUEST_METHOD" => $_SERVER["REQUEST_METHOD"],
]);