<?php
require '../../config/config.php';

header('Content-Type: application/json');

try {
    $input = isset($_POST['action']) ? $_POST : json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? null;

    switch ($action) {
        case 'list':
            handleList();
            break;
        case 'upload':
            handleUpload();
            break;
        case 'updateCaption':
            handleUpdateCaption($input);
            break;
        case 'delete':
            handleDelete($input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function handleList() {
    $media = BookManager::getMedia();

    // Enrichir avec l'utilisation par page
    foreach ($media as &$m) {
        $m['usedOnPages'] = BookManager::getMediaUsage($m['id']);
    }
    unset($m);

    echo json_encode(['success' => true, 'media' => $media]);
}

function handleUpload() {
    if (!isset($_FILES['photo'])) {
        throw new Exception('No file uploaded');
    }

    // Upload le fichier via PhotoManager
    $metadata = PhotoManager::upload($_FILES['photo'], 0);

    // Créer l'entrée media
    $mediaId = 'm_' . uniqid('', true);
    $mediaEntry = [
        'id'             => $mediaId,
        'filename'       => $metadata['filename'],
        'width'          => $metadata['width'],
        'height'         => $metadata['height'],
        'uploadedAt'     => date('c'),
        'defaultCaption' => '',
    ];
    BookManager::addMedia($mediaEntry);

    // Si une page est précisée, ajouter aussi à la page
    $pageNumber = intval($_POST['page'] ?? 0);
    if ($pageNumber > 0) {
        $page = BookManager::getPage($pageNumber);
        if ($page && $page['type'] === 'photo') {
            $metadata['mediaId'] = $mediaId;
            $page['photos'][] = $metadata;
            BookManager::updatePage($pageNumber, $page);
        }
    }

    $mediaEntry['usedOnPages'] = $pageNumber > 0 ? [$pageNumber] : [];
    echo json_encode(['success' => true, 'media' => $mediaEntry, 'photo' => $metadata]);
}

function handleUpdateCaption($input) {
    $mediaId = $input['mediaId'] ?? null;
    $caption = $input['caption'] ?? '';
    if (!$mediaId) throw new Exception('mediaId required');

    $updated = BookManager::updateMediaCaption($mediaId, $caption);
    if (!$updated) throw new Exception('Media not found');

    echo json_encode(['success' => true, 'media' => $updated]);
}

function handleDelete($input) {
    $mediaId = $input['mediaId'] ?? null;
    if (!$mediaId) throw new Exception('mediaId required');

    $usage = BookManager::getMediaUsage($mediaId);
    if (!empty($usage)) {
        throw new Exception('Photo utilisée sur ' . count($usage) . ' page(s)');
    }

    // Récupérer le filename avant suppression
    $media = BookManager::getMedia();
    $filename = null;
    foreach ($media as $m) {
        if ($m['id'] === $mediaId) { $filename = $m['filename']; break; }
    }

    BookManager::deleteMedia($mediaId);

    // Supprimer le fichier physique
    if ($filename) {
        $path = UPLOAD_DIR . '/' . $filename;
        if (file_exists($path)) unlink($path);
    }

    echo json_encode(['success' => true]);
}
