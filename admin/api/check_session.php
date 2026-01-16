<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (isset($_SESSION['user']) && isset($_SESSION['user_data'])) {
    echo json_encode([
        'user' => [
            'name' => $_SESSION['user_data']['name'] ?? $_SESSION['user'],
            'role' => $_SESSION['role'] ?? 'user',
            'username' => $_SESSION['user']
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
}