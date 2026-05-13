<?php
require '../../config/config.php';

header('Content-Type: application/json');

try {
    // Get action from POST or JSON body
    $input = isset($_POST['action']) ? $_POST : json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;
    $pageNumber = intval($input['page'] ?? 0);

    // Validate page number
    $book = BookManager::load();
    $totalPages = $book['totalPages'] ?? TOTAL_PAGES;
    if ($pageNumber < 1 || $pageNumber > $totalPages) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid page number']);
        exit;
    }

    switch ($action) {
        case 'upload':
            handleUpload($pageNumber);
            break;

        case 'update':
            handleUpdate($pageNumber, $input);
            break;

        case 'delete':
            handleDelete($pageNumber, $input);
            break;

        case 'move_to_page':
            handleMoveToPage($pageNumber, $input);
            break;

        case 'addFromMedia':
            handleAddFromMedia($pageNumber, $input);
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

/**
 * Handle photo upload
 */
function handleUpload($pageNumber) {
    if (!isset($_FILES['photo'])) {
        throw new Exception('No file uploaded');
    }

    $page = BookManager::getPage($pageNumber);
    if (!$page || $page['type'] !== 'photo') {
        throw new Exception('Invalid page or not a photo page');
    }

    // Upload file
    $metadata = PhotoManager::upload($_FILES['photo'], $pageNumber);

    // Créer l'entrée médiathèque
    $mediaId = 'm_' . uniqid('', true);
    BookManager::addMedia([
        'id'             => $mediaId,
        'filename'       => $metadata['filename'],
        'width'          => $metadata['width'],
        'height'         => $metadata['height'],
        'uploadedAt'     => date('c'),
        'defaultCaption' => '',
    ]);
    $metadata['mediaId'] = $mediaId;

    // Add to page
    $page['photos'][] = $metadata;
    BookManager::updatePage($pageNumber, $page);

    echo json_encode([
        'success' => true,
        'photo' => $metadata,
        'message' => 'Photo uploaded successfully'
    ]);
}

/**
 * Handle photo update (position, caption, filter, etc.)
 */
function handleUpdate($pageNumber, $input) {
    $photoId = $input['photoId'] ?? null;
    if (!$photoId) {
        throw new Exception('Photo ID required');
    }

    $page = BookManager::getPage($pageNumber);
    if (!$page || $page['type'] !== 'photo') {
        throw new Exception('Invalid page or not a photo page');
    }

    // Find and update photo
    $found = false;
    foreach ($page['photos'] as &$photo) {
        if ($photo['id'] === $photoId) {
            // Calculate dimensions if missing
            if (!isset($photo['width']) || !isset($photo['height'])) {
                $uploadPath = PhotoManager::getUploadPath($photo['filename']);
                if (file_exists($uploadPath)) {
                    $imageSize = getimagesize($uploadPath);
                    if ($imageSize) {
                        $photo['width'] = $imageSize[0];
                        $photo['height'] = $imageSize[1];
                    }
                }
            }

            // Update allowed fields
            if (isset($input['position'])) {
                $photo['position'] = $input['position'];
            }
            if (isset($input['caption'])) {
                $photo['caption'] = $input['caption'];
            }
            if (isset($input['rotation'])) {
                $photo['rotation'] = intval($input['rotation']);
            }
            if (isset($input['filter'])) {
                $photo['filter'] = $input['filter'];
            }
            if (isset($input['frame'])) {
                $frame = is_array($input['frame']) ? $input['frame'] : [];
                $currentFrame = (isset($photo['frame']) && is_array($photo['frame'])) ? $photo['frame'] : [];
                $photo['frame'] = [
                    'x' => isset($frame['x']) ? (float)$frame['x'] : (float)($currentFrame['x'] ?? $photo['position']['x'] ?? 5),
                    'y' => isset($frame['y']) ? (float)$frame['y'] : (float)($currentFrame['y'] ?? $photo['position']['y'] ?? 5),
                    'w' => isset($frame['w']) ? (float)$frame['w'] : (float)($currentFrame['w'] ?? $photo['position']['w'] ?? 40),
                    'h' => isset($frame['h']) ? (float)$frame['h'] : (float)($currentFrame['h'] ?? $photo['position']['h'] ?? 40),
                    'z' => isset($frame['z']) ? intval($frame['z']) : intval($currentFrame['z'] ?? $photo['position']['z'] ?? 1),
                    'shape' => $frame['shape'] ?? ($currentFrame['shape'] ?? 'rect'),
                    'ratio' => $frame['ratio'] ?? ($currentFrame['ratio'] ?? null),
                    'borderWidth' => max(0, min(20, intval($frame['borderWidth'] ?? $photo['borderWidth'] ?? $currentFrame['borderWidth'] ?? 0))),
                    'borderColor' => $frame['borderColor'] ?? ($photo['borderColor'] ?? $currentFrame['borderColor'] ?? 'white'),
                    'backgroundColor' => $frame['backgroundColor'] ?? ($currentFrame['backgroundColor'] ?? 'white'),
                ];
                unset($photo['position'], $photo['borderWidth'], $photo['borderColor']);
            }
            if (isset($input['crop'])) {
                $crop = is_array($input['crop']) ? $input['crop'] : [];
                $currentCrop = (isset($photo['crop']) && is_array($photo['crop'])) ? $photo['crop'] : [];
                $fitMode = $crop['fitMode'] ?? $crop['fit'] ?? $photo['fit'] ?? $currentCrop['fitMode'] ?? 'cover';
                $photo['crop'] = [
                    'fitMode' => in_array($fitMode, ['cover', 'contain']) ? $fitMode : 'cover',
                    'zoom' => round((float)($crop['zoom'] ?? $photo['zoom'] ?? $currentCrop['zoom'] ?? 1), 2),
                    'panX' => (float)($crop['panX'] ?? $photo['panX'] ?? $currentCrop['panX'] ?? 0),
                    'panY' => (float)($crop['panY'] ?? $photo['panY'] ?? $currentCrop['panY'] ?? 0),
                ];
                unset($photo['fit'], $photo['zoom'], $photo['panX'], $photo['panY']);
            }
            if (isset($input['zoom'])) {
                $photo['zoom'] = round((float)$input['zoom'], 2);
            }
            if (isset($input['panX'])) {
                $photo['panX'] = (float)$input['panX'];
            }
            if (isset($input['panY'])) {
                $photo['panY'] = (float)$input['panY'];
            }
            if (isset($input['brightness'])) {
                $photo['brightness'] = intval($input['brightness']);
            }
            if (isset($input['contrast'])) {
                $photo['contrast'] = intval($input['contrast']);
            }
            if (isset($input['fit'])) {
                $photo['fit'] = in_array($input['fit'], ['cover', 'contain']) ? $input['fit'] : 'cover';
            }
            if (isset($input['captionColor'])) {
                $photo['captionColor'] = in_array($input['captionColor'], ['white', 'black']) ? $input['captionColor'] : 'white';
            }
            if (isset($input['captionSize'])) {
                $photo['captionSize'] = max(8, min(28, intval($input['captionSize'])));
            }
            if (isset($input['captionPos'])) {
                $allowed = ['below', 'above', 'inside-bottom', 'inside-top'];
                $photo['captionPos'] = in_array($input['captionPos'], $allowed) ? $input['captionPos'] : 'below';
            }
            if (isset($input['captionAlign'])) {
                $allowed = ['left', 'center', 'right'];
                $photo['captionAlign'] = in_array($input['captionAlign'], $allowed) ? $input['captionAlign'] : 'left';
            }
            if (isset($input['borderWidth'])) {
                $photo['borderWidth'] = max(0, min(20, intval($input['borderWidth'])));
            }
            if (isset($input['borderColor'])) {
                $allowed = ['white', '#ccc', '#666', 'black'];
                $photo['borderColor'] = in_array($input['borderColor'], $allowed) ? $input['borderColor'] : 'white';
            }
            if (isset($input['width'])) {
                $photo['width'] = intval($input['width']);
            }
            if (isset($input['height'])) {
                $photo['height'] = intval($input['height']);
            }

            $found = true;
            break;
        }
    }
    unset($photo);

    if (!$found) {
        throw new Exception('Photo not found');
    }

    BookManager::updatePage($pageNumber, $page);

    echo json_encode([
        'success' => true,
        'message' => 'Photo updated'
    ]);
}

/**
 * Handle photo deletion
 */
function handleMoveToPage($pageNumber, $input) {
    $photoId = $input['photoId'] ?? null;
    $targetPage = intval($input['targetPage'] ?? 0);

    if (!$photoId) throw new Exception('Photo ID required');
    $book = BookManager::load();
    $totalPages = $book['totalPages'] ?? TOTAL_PAGES;
    if ($targetPage < 1 || $targetPage > $totalPages) throw new Exception('Invalid target page');
    if ($targetPage === $pageNumber) throw new Exception('Same page');

    $target = BookManager::getPage($targetPage);
    if (!$target || $target['type'] !== 'photo') throw new Exception('Target page is not a photo page');

    // Find photo on source page
    $sourcePage = BookManager::getPage($pageNumber);
    $photoData = null;
    foreach ($sourcePage['photos'] as $i => $photo) {
        if ($photo['id'] === $photoId) {
            $photoData = $photo;
            array_splice($sourcePage['photos'], $i, 1);
            break;
        }
    }
    if (!$photoData) throw new Exception('Photo not found');

    // Remove from any slot assignment on source page
    if (isset($sourcePage['slotAssignments'])) {
        foreach ($sourcePage['slotAssignments'] as $slot => $pid) {
            if ($pid === $photoId) $sourcePage['slotAssignments'][$slot] = null;
        }
    }

    $target['photos'][] = $photoData;

    BookManager::updatePage($pageNumber, $sourcePage);
    BookManager::updatePage($targetPage, $target);

    echo json_encode(['success' => true]);
}

function handleDelete($pageNumber, $input) {
    $photoId = $input['photoId'] ?? null;
    if (!$photoId) {
        throw new Exception('Photo ID required');
    }

    $deleted = PhotoManager::delete($photoId, $pageNumber, false);

    if (!$deleted) {
        throw new Exception('Photo not found');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Photo deleted'
    ]);
}


function handleAddFromMedia($pageNumber, $input) {
    $photoData = $input['photo'] ?? null;
    if (!$photoData || empty($photoData['mediaId']) || empty($photoData['filename'])) {
        throw new Exception('Invalid photo data');
    }

    $page = BookManager::getPage($pageNumber);
    if (!$page || $page['type'] !== 'photo') {
        throw new Exception('Invalid page');
    }

    // Vérifier que le media existe
    $media = BookManager::getMedia();
    $found = false;
    foreach ($media as $m) {
        if ($m['id'] === $photoData['mediaId']) { $found = true; break; }
    }
    if (!$found) throw new Exception('Media not found');

    // S'assurer que la photo n'est pas déjà sur la page
    foreach ($page['photos'] as $p) {
        if (($p['mediaId'] ?? '') === $photoData['mediaId']) {
            throw new Exception('Photo déjà sur cette page');
        }
    }

    $page['photos'][] = $photoData;
    BookManager::updatePage($pageNumber, $page);

    echo json_encode(['success' => true, 'photo' => $photoData]);
}
