<?php
/**
 * CLI script for generating text PDF
 * Usage: php cli-generate-text.php
 *
 * This script is called by the local PDF generation orchestrator (generate-pdf.js)
 * and generates the text portion of the PDF in a standalone manner.
 */

require __DIR__ . '/../../config/config.php';

try {
    // Validate we're running locally
    if (PHP_OS_FAMILY !== 'Windows') {
        echo json_encode([
            'success' => false,
            'error' => 'This script only runs on Windows (requires pandoc, typst, Python)'
        ]) . "\n";
        exit(1);
    }

    // Call MarkdownPdfManager to generate the text PDF
    $copyToSource = true;
    $result = MarkdownPdfManager::generateTextPdf($copyToSource);

    echo json_encode([
        'success' => true,
        'result' => $result
    ]) . "\n";
    exit(0);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]) . "\n";
    exit(1);
}
