<?php
require '../../config/config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;
    $pageNumber = intval($input['page'] ?? 0);

    // insertSpread / deleteSpread ne nécessitent pas de pageNumber valide
    $noPageActions = ['insertSpread', 'deleteSpread'];
    if (!in_array($action, $noPageActions)) {
        $book = BookManager::load();
        $totalPages = $book['totalPages'] ?? TOTAL_PAGES;
        if ($pageNumber < 1 || $pageNumber > $totalPages) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid page number']);
            exit;
        }
    }

    switch ($action) {
        case 'get':
            $page = BookManager::getPage($pageNumber);
            echo json_encode(['success' => true, 'page' => $page]);
            break;

        case 'save':
            handleSave($pageNumber, $input);
            break;

        case 'insertSpread':
            $spreadNumber = intval($input['spread'] ?? 0);
            if ($spreadNumber < 1) throw new Exception('Invalid spread number');
            BookManager::insertSpread($spreadNumber);
            echo json_encode(['success' => true]);
            break;

        case 'deleteSpread':
            $spreadNumber = intval($input['spread'] ?? 0);
            $force = !empty($input['force']);
            if ($spreadNumber < 1) throw new Exception('Invalid spread number');
            $result = BookManager::deleteSpread($spreadNumber, $force);
            if ($result === false) {
                echo json_encode(['success' => false, 'hasPhotos' => true]);
            } else {
                echo json_encode(['success' => true]);
            }
            break;

        case 'setPageType':
            $type = $input['pageType'] ?? '';
            if (!in_array($type, ['text', 'photo'])) throw new Exception('Invalid page type');
            BookManager::setPageType($pageNumber, $type);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function handleSave($pageNumber, $input) {
    $page = BookManager::getPage($pageNumber);
    if (!$page) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Page not found']);
        return;
    }

    if (isset($input['layout'])) {
        $page['layout'] = $input['layout'];
    }

    // slotAssignments : tableau [photoId|null, ...] indexé par slot
    if (array_key_exists('slotAssignments', $input)) {
        $page['slotAssignments'] = array_values($input['slotAssignments']);
    }

    // Mode libre : mise à jour des positions individuelles
    if (isset($input['freeFrames'])) {
        $existing = [];
        foreach ($page['photos'] as $p) {
            $existing[$p['id']] = $p;
        }
        foreach ($input['freeFrames'] as $fp) {
            $id = $fp['id'] ?? null;
            if ($id && isset($existing[$id])) {
                $frame = is_array($fp['frame'] ?? null) ? $fp['frame'] : [];
                $currentFrame = (isset($existing[$id]['frame']) && is_array($existing[$id]['frame'])) ? $existing[$id]['frame'] : [];
                $existing[$id]['frame'] = [
                    'x' => isset($frame['x']) ? (float)$frame['x'] : (float)($currentFrame['x'] ?? $existing[$id]['position']['x'] ?? 5),
                    'y' => isset($frame['y']) ? (float)$frame['y'] : (float)($currentFrame['y'] ?? $existing[$id]['position']['y'] ?? 5),
                    'w' => isset($frame['w']) ? (float)$frame['w'] : (float)($currentFrame['w'] ?? $existing[$id]['position']['w'] ?? 40),
                    'h' => isset($frame['h']) ? (float)$frame['h'] : (float)($currentFrame['h'] ?? $existing[$id]['position']['h'] ?? 40),
                    'z' => isset($frame['z']) ? intval($frame['z']) : intval($currentFrame['z'] ?? $existing[$id]['position']['z'] ?? 1),
                    'shape' => $frame['shape'] ?? ($currentFrame['shape'] ?? 'rect'),
                    'ratio' => $frame['ratio'] ?? ($currentFrame['ratio'] ?? null),
                    'borderWidth' => max(0, min(20, intval($frame['borderWidth'] ?? $existing[$id]['borderWidth'] ?? $currentFrame['borderWidth'] ?? 0))),
                    'borderColor' => $frame['borderColor'] ?? ($existing[$id]['borderColor'] ?? $currentFrame['borderColor'] ?? 'white'),
                    'backgroundColor' => $frame['backgroundColor'] ?? ($currentFrame['backgroundColor'] ?? 'white'),
                ];
                unset($existing[$id]['position'], $existing[$id]['borderWidth'], $existing[$id]['borderColor']);
            }
        }
        $page['photos'] = array_values($existing);
    }

    if (isset($input['freePositions'])) {
        $existing = [];
        foreach ($page['photos'] as $p) {
            $existing[$p['id']] = $p;
        }
        foreach ($input['freePositions'] as $fp) {
            $id = $fp['id'] ?? null;
            if ($id && isset($existing[$id])) {
                $existing[$id]['position'] = $fp['position'];
            }
        }
        $page['photos'] = array_values($existing);
    }

    BookManager::updatePage($pageNumber, $page);
    echo json_encode(['success' => true]);
}
