<?php
require_once 'config/database.php';

try {
    $db   = (new Database())->connect();
    $stmt = $db->query("SELECT COUNT(*) as total FROM public.tblstudent");
    $row  = $stmt->fetch();

    echo json_encode([
        "status"   => "connected",
        "host"     => getenv('PGHOST')     ?: 'localhost (local fallback)',
        "database" => getenv('PGDATABASE') ?: 'test_lms (local fallback)',
        "students" => $row['total'],
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status"  => "failed",
        "message" => $e->getMessage(),
    ]);
}