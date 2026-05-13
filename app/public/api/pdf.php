<?php
set_time_limit(600); // 10 minutes timeout for PDF generation

require '../../config/config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;

    if ($action === 'get_page') {
        $pdfPage = intval($input['pdfPage'] ?? 0);
        $pdfHalf = in_array($input['pdfHalf'] ?? 'right', ['left', 'right']) ? $input['pdfHalf'] : 'right';

        if ($pdfPage < 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid page number']);
            exit;
        }

        $imageUrl = PdfManager::getPageImage($pdfPage);

        if (!$imageUrl) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Could not convert PDF page']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'url' => $imageUrl,
            'pdfPage' => $pdfPage,
            'pdfHalf' => $pdfHalf,
        ]);
    } elseif ($action === 'generateFinalPdf') {
        // Generate final PDF with photos and text merged (Windows only)
        if (PHP_OS_FAMILY !== 'Windows') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'La génération du PDF final ne peut être lancée que depuis l\'ordinateur local (Windows).'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $captureDir = dirname(__DIR__, 3) . '/capture';
        $nodeExe = 'C:\\Program Files\\nodejs\\node.exe';

        if (!is_dir($captureDir)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Capture directory not found']);
            exit;
        }

        if (!file_exists($nodeExe)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Node.js not found']);
            exit;
        }

        // Run the scripts in sequence in background
        $logFile = dirname(__DIR__, 3) . '/data/pdf-generation.log';
        $cmd = "cd \"$captureDir\" && \"$nodeExe\" capture-pages.js && \"$nodeExe\" merge-hq-pdf-v2.js && \"$nodeExe\" fix-pdf-size.js";

        // Launch in background and detach process
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['file', $logFile, 'a'],  // stdout
            2 => ['file', $logFile, 'a'],  // stderr
        ];

        $proc = proc_open($cmd, $descriptorspec, $pipes);

        if (is_resource($proc)) {
            fclose($pipes[0]);  // Close stdin
            proc_close($proc);  // Detach and close immediately

            echo json_encode([
                'success' => true,
                'message' => 'Génération du PDF en cours en arrière-plan',
                'file' => 'livre.print.pdf',
                'status' => 'processing'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Impossible de lancer la génération'
            ]);
        }
    } elseif ($action === 'status') {
        // Check PDF generation status
        $pdfFile = dirname(__DIR__, 3) . '/livre.print.pdf';
        $logFile = dirname(__DIR__, 3) . '/data/pdf-generation.log';

        $status = 'idle';
        $lastUpdate = null;
        $fileSize = null;

        if (file_exists($logFile)) {
            $logTime = filemtime($logFile);
            $lastUpdate = $logTime;

            // Check if log has recent activity (within last 2 minutes)
            if (time() - $logTime < 120) {
                $status = 'processing';
            }
        }

        if (file_exists($pdfFile)) {
            $fileSize = filesize($pdfFile);
            $pdfTime = filemtime($pdfFile);

            // If PDF was modified recently (within last 2 minutes), it's just finished
            if (time() - $pdfTime < 120) {
                $status = 'completed';
            } else {
                $status = 'idle';
            }
        }

        echo json_encode([
            'success' => true,
            'status' => $status,
            'fileSize' => $fileSize,
            'lastUpdate' => $lastUpdate,
            'timestamp' => time(),
        ], JSON_UNESCAPED_UNICODE);

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
