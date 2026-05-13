<?php

class PdfManager {
    private static $pdf_file = SOURCE_PDF;
    private static $cache_dir = PDF_CACHE_DIR;

    /**
     * Get page image URL, converting if necessary.
     * CSS cropping is used client-side to show only left or right half.
     * @param int $pageIndex 0-based PDF page number
     * @return string|null URL to PNG image or null if failed
     */
    public static function getPageImage($pageIndex) {
        ensureDir(self::$cache_dir);

        $cacheFile = self::getCacheFilePath($pageIndex);

        if (file_exists($cacheFile)) {
            return self::getCacheUrl($pageIndex);
        }

        if (self::convertPageToPng($pageIndex, $cacheFile)) {
            return self::getCacheUrl($pageIndex);
        }

        return null;
    }

    /**
     * Convert PDF page to PNG using best available tool
     * @param int $pageIndex 0-based page number
     * @param string $outputPath Output PNG file path
     * @return bool Success
     */
    private static function convertPageToPng($pageIndex, $outputPath) {
        if (!file_exists(self::$pdf_file)) {
            throw new Exception("PDF file not found: " . self::$pdf_file);
        }

        // Try pdftoppm (Poppler, bundled with Calibre)
        $pdftoppm = self::findPdftoppm();
        if ($pdftoppm) {
            return self::convertViaPdftoppm($pdftoppm, $pageIndex, $outputPath);
        }

        // Try Ghostscript
        $gs = self::findGhostscript();
        if ($gs) {
            return self::convertViaGhostscript($gs, $pageIndex, $outputPath);
        }

        throw new Exception("Aucun outil de conversion PDF trouvé (Calibre/pdftoppm ou Ghostscript requis)");
    }

    /**
     * Convert via pdftoppm (Poppler utilities, bundled with Calibre)
     */
    private static function convertViaPdftoppm($pdftoppm, $pageIndex, $outputPath) {
        $pageNum = $pageIndex + 1; // 1-based
        // pdftoppm with -singlefile writes <prefix>.png
        $prefix = substr($outputPath, 0, -4); // strip .png

        $isWindows = PHP_OS_FAMILY === 'Windows';
        $pdfPath = $isWindows ? str_replace('/', '\\', self::$pdf_file) : self::$pdf_file;
        $prefixPath = $isWindows ? str_replace('/', '\\', $prefix) : $prefix;

        $cmd = '"' . $pdftoppm . '"'
            . ' -f ' . $pageNum
            . ' -l ' . $pageNum
            . ' -singlefile'
            . ' -r 150'
            . ' -png'
            . ' ' . escapeshellarg($pdfPath)
            . ' ' . escapeshellarg($prefixPath)
            . ' 2>&1';

        $output = null;
        $return = null;
        exec($cmd, $output, $return);

        return $return === 0 && file_exists($outputPath);
    }

    /**
     * Convert via Ghostscript
     */
    private static function convertViaGhostscript($gs, $pageIndex, $outputPath) {
        $pageNum = $pageIndex + 1;
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $outputFile = $isWindows ? str_replace('/', '\\', $outputPath) : $outputPath;
        $pdfPath = $isWindows ? str_replace('/', '\\', self::$pdf_file) : self::$pdf_file;

        $cmd = '"' . $gs . '"'
            . ' -sDEVICE=png16m -dNOPAUSE -dBATCH -dSAFER'
            . ' -r150'
            . ' -dFirstPage=' . $pageNum
            . ' -dLastPage=' . $pageNum
            . ' -sOutputFile=' . escapeshellarg($outputFile)
            . ' ' . escapeshellarg($pdfPath)
            . ' 2>&1';

        $output = null;
        $return = null;
        exec($cmd, $output, $return);

        return $return === 0 && file_exists($outputPath);
    }

    /**
     * Find pdftoppm executable (Poppler, available via Calibre on Windows, system on Linux)
     */
    private static function findPdftoppm() {
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $whereCmd = $isWindows ? 'where' : 'which';
        $nullDevice = $isWindows ? '2>nul' : '2>/dev/null';

        $candidates = $isWindows ? [
            'C:\\Program Files\\Calibre2\\app\\bin\\pdftoppm.exe',
            'C:\\Program Files (x86)\\Calibre2\\app\\bin\\pdftoppm.exe',
            'pdftoppm',
        ] : [
            '/usr/bin/pdftoppm',
            '/usr/local/bin/pdftoppm',
            'pdftoppm',
        ];

        foreach ($candidates as $cmd) {
            if (file_exists($cmd) || !empty(shell_exec($whereCmd . ' ' . escapeshellarg($cmd) . ' ' . $nullDevice))) {
                return $cmd;
            }
        }

        return null;
    }

    /**
     * Find Ghostscript executable
     */
    private static function findGhostscript() {
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $whereCmd = $isWindows ? 'where' : 'which';
        $nullDevice = $isWindows ? '2>nul' : '2>/dev/null';

        $candidates = $isWindows ? [
            'gswin64c',
            'gswin32c',
            'gs',
        ] : [
            'gs',
        ];

        foreach ($candidates as $cmd) {
            $check = trim(shell_exec($whereCmd . ' ' . escapeshellarg($cmd) . ' ' . $nullDevice) ?? '');
            if ($check) {
                return $check;
            }
        }

        // Common Windows installation paths
        if ($isWindows) {
            $paths = glob('C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe');
            if (!empty($paths)) {
                return $paths[0];
            }
        }

        return null;
    }

    /**
     * Get cache file path for page
     */
    private static function getCacheFilePath($pageIndex) {
        return self::$cache_dir . '/page_' . str_pad($pageIndex, 3, '0', STR_PAD_LEFT) . '.png';
    }

    /**
     * Get cache URL for page
     */
    private static function getCacheUrl($pageIndex) {
        return BASE_URL . '/pdf-cache/page_' . str_pad($pageIndex, 3, '0', STR_PAD_LEFT) . '.png';
    }

    /**
     * Count PDF pages using pdfinfo (Calibre) or regex fallback.
     * Returns 0 if PDF not found or unparseable.
     */
    public static function countPages() {
        if (!file_exists(self::$pdf_file)) return 0;

        // Prefer pdfinfo (ships with Calibre)
        $pdfinfo = self::findPdfinfo();
        if ($pdfinfo) {
            $cmd = '"' . $pdfinfo . '" ' . escapeshellarg(str_replace('/', '\\', self::$pdf_file)) . ' 2>&1';
            $output = null;
            exec($cmd, $output);
            foreach ($output as $line) {
                if (preg_match('/^Pages:\s*(\d+)/i', $line, $m)) {
                    return (int) $m[1];
                }
            }
        }

        // Fallback: scan the PDF bytes for /Count (works for uncompressed PDFs)
        $content = file_get_contents(self::$pdf_file);
        if (preg_match_all('/\/Count\s+(\d+)/', $content, $m)) {
            return (int) max($m[1]);
        }

        return 0;
    }

    private static function findPdfinfo() {
        $candidates = [
            'C:\\Program Files\\Calibre2\\app\\bin\\pdfinfo.exe',
            'C:\\Program Files (x86)\\Calibre2\\app\\bin\\pdfinfo.exe',
        ];
        foreach ($candidates as $p) {
            if (file_exists($p)) return $p;
        }
        return null;
    }

    /**
     * Get total pages in PDF (if available)
     */
    public static function getTotalPages() {
        $n = self::countPages();
        return $n > 0 ? $n : TOTAL_PAGES;
    }

    /**
     * Clear cache
     */
    public static function clearCache() {
        if (is_dir(self::$cache_dir)) {
            $files = glob(self::$cache_dir . '/*.png');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }
}
