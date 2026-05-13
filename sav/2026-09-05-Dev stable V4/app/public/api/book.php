<?php
require '../../config/config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $input['action'] ?? null;

    switch ($action) {
        case 'get':
            $book = BookManager::load();
            echo json_encode(['success' => true, 'properties' => $book['properties']]);
            break;

        case 'saveProperties':
            $properties = BookManager::updateProperties($input['properties'] ?? []);
            echo json_encode(['success' => true, 'properties' => $properties]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
