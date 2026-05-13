<?php
set_time_limit(600); // 10 minutes timeout for PDF generation

header('Content-Type: application/json');

register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }

    echo json_encode([
        'success' => false,
        'error' => $error['message'] . ' | ' . $error['file'] . ':' . $error['line'],
        'type' => 'fatal',
    ], JSON_UNESCAPED_UNICODE);
});

try {
    $configPath = __DIR__ . '/../../config/config.php';
    if (!file_exists($configPath)) {
        throw new Exception('Config file not found: ' . $configPath);
    }

    require $configPath;

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $input['action'] ?? null;

    switch ($action) {
        case 'getExcerpt':
            $page = intval($input['page'] ?? 1);
            $excerpt = MarkdownTextManager::getExcerptForPage($page);
            echo json_encode(['success' => true, 'excerpt' => $excerpt], JSON_UNESCAPED_UNICODE);
            break;

        case 'getContext':
            $start = intval($input['start'] ?? 0);
            $end = intval($input['end'] ?? 0);
            $direction = ($input['direction'] ?? '') === 'previous' ? 'previous' : 'next';
            $chars = intval($input['chars'] ?? 3500);
            $context = MarkdownTextManager::getContext($start, $end, $direction, $chars);
            echo json_encode(['success' => true, 'context' => $context], JSON_UNESCAPED_UNICODE);
            break;

        case 'getAdjacentExcerpt':
            $start = intval($input['start'] ?? 0);
            $end = intval($input['end'] ?? 0);
            $direction = ($input['direction'] ?? '') === 'previous' ? 'previous' : 'next';
            $excerpt = MarkdownTextManager::getAdjacentExcerpt($start, $end, $direction);
            echo json_encode([
                'success' => true,
                'excerpt' => $excerpt,
                'hasExcerpt' => $excerpt !== null,
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'saveExcerpt':
            $start = intval($input['start'] ?? 0);
            $end = intval($input['end'] ?? 0);
            $text = $input['text'] ?? '';
            $result = MarkdownTextManager::saveExcerpt($start, $end, $text);
            $generated = null;
            if (!empty($input['regenerate'])) {
                $generated = MarkdownPdfManager::generateTextPdf(true);
            }
            echo json_encode([
                'success' => true,
                'result' => $result,
                'generated' => $generated,
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'generateTextPdf':
            if (PHP_OS_FAMILY !== 'Windows') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'La génération du PDF texte ne peut être lancée que depuis l\'ordinateur local (Windows).'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $copyToSource = array_key_exists('copyToSource', $input) ? (bool)$input['copyToSource'] : true;
            $result = MarkdownPdfManager::generateTextPdf($copyToSource);
            echo json_encode(['success' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    $error = $e->getMessage() ?: '(empty error message)';
    $error .= ' | ' . $e->getFile() . ':' . $e->getLine();
    $error .= ' | Type: ' . get_class($e);
    echo json_encode(['success' => false, 'error' => $error, 'trace' => $e->getTraceAsString()], JSON_UNESCAPED_UNICODE);
}
