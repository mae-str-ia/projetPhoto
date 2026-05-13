<?php

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

class ExportManager {
    /**
     * Generate Word document with all pages
     * @return string Path to generated DOCX file
     */
    public static function generateWord() {
        // Check if PHPWord is available
        if (!class_exists('PhpOffice\PhpWord\PhpWord')) {
            throw new Exception('PHPWord library not installed. Run: composer install');
        }

        $book = BookManager::load();
        $phpWord = new PhpWord();

        // Set document properties
        $phpWord->getProperties()->setTitle($book['title']);
        $phpWord->getProperties()->setCreated(time());

        // Add sections for each spread
        $totalSpreads = intdiv($book['totalPages'], 2);

        for ($spreadNum = 1; $spreadNum <= $totalSpreads; $spreadNum++) {
            $spread = BookManager::getSpread($spreadNum);

            if (!$spread['leftPage'] || !$spread['rightPage']) {
                continue;
            }

            // Add text page (right)
            $section = $phpWord->addSection([
                'orientation' => 'portrait',
                'marginLeft' => 1440,
                'marginRight' => 1440,
                'marginTop' => 1440,
                'marginBottom' => 1440,
            ]);

            // Add PDF page as background/image if available
            $pdfImage = PdfManager::getPageImage($spread['rightPage']['pdfPageIndex']);
            if ($pdfImage) {
                try {
                    $section->addText('Page ' . $spread['rightPage']['pageNumber']);
                    // Would add image here but requires file path, not URL
                    // $section->addImage(...);
                } catch (Exception $e) {
                    // Silently skip if can't add image
                }
            }

            // Add photos from left page
            if (!empty($spread['leftPage']['photos'])) {
                $section->addTextBreak();
                $section->addText('Photographies - Page ' . $spread['leftPage']['pageNumber'], [
                    'bold' => true,
                    'size' => 14,
                ]);

                foreach ($spread['leftPage']['photos'] as $photo) {
                    $photoPath = PhotoManager::getUploadPath($photo['filename']);
                    if (file_exists($photoPath)) {
                        try {
                            $section->addTextBreak();
                            $section->addImage($photoPath, [
                                'width' => 400,
                                'height' => 300,
                                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                            ]);

                            if (!empty($photo['caption'])) {
                                $section->addText($photo['caption'], [
                                    'italic' => true,
                                    'size' => 11,
                                    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                                ]);
                            }
                        } catch (Exception $e) {
                            // Silently skip problem photos
                        }
                    }
                }
            } else {
                $section->addText('(Aucune photo sur cette page)', ['italic' => true]);
            }

            // Add page break between spreads
            $section->addPageBreak();
        }

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'photobook_') . '.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * Generate simple document without external dependencies
     * This is a fallback method that creates a minimal document
     */
    public static function generateWordSimple() {
        // For systems without PHPWord, we could implement a basic Word XML writer
        // For now, we require PHPWord
        throw new Exception('PHPWord library is required. Run: composer install');
    }
}
