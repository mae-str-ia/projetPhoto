<?php

class MarkdownTextManager {
    private const MARKDOWN_FILE = MARKDOWN_DIR . '/livre.md';

    public static function getExcerptForPage($pageNumber) {
        $text = self::readMarkdown();
        $length = strlen($text);
        $textPageCount = self::estimateTextPageCount();
        $textPageIndex = self::bookPageToTextPageIndex($pageNumber);

        $windowPages = 3;
        $charsPerPage = max(1, (int)ceil($length / max(1, $textPageCount)));
        $center = (int)(($textPageIndex - 0.5) * $charsPerPage);
        $start = max(0, $center - $charsPerPage);
        $end = min($length, $center + ($charsPerPage * 2));

        $start = self::moveStartToParagraphBoundary($text, $start);
        $end = self::moveEndToParagraphBoundary($text, $end);

        return [
            'page' => $pageNumber,
            'textPageIndex' => $textPageIndex,
            'textPageCount' => $textPageCount,
            'start' => $start,
            'end' => $end,
            'excerpt' => substr($text, $start, $end - $start),
            'isApproximate' => true,
            'sourceLength' => $length,
            'windowPages' => $windowPages,
        ];
    }

    public static function getContext($start, $end, $direction, $chars = 3500) {
        $text = self::readMarkdown();
        $length = strlen($text);
        $start = max(0, min($length, (int)$start));
        $end = max($start, min($length, (int)$end));
        $chars = max(500, min(20000, (int)$chars));

        if ($direction === 'previous') {
            if ($start <= 0) {
                return [
                    'direction' => $direction,
                    'start' => 0,
                    'end' => 0,
                    'text' => '',
                    'hasMore' => false,
                ];
            }
            $contextStart = max(0, $start - $chars);
            $contextStart = self::moveStartToParagraphBoundary($text, $contextStart);
            return [
                'direction' => $direction,
                'start' => $contextStart,
                'end' => $start,
                'text' => rtrim(substr($text, $contextStart, $start - $contextStart)),
                'hasMore' => $contextStart > 0,
            ];
        }

        if ($end >= $length) {
            return [
                'direction' => 'next',
                'start' => $length,
                'end' => $length,
                'text' => '',
                'hasMore' => false,
            ];
        }
        $contextEnd = min($length, $end + $chars);
        $contextEnd = self::moveEndToParagraphBoundary($text, $contextEnd);
        return [
            'direction' => 'next',
            'start' => $end,
            'end' => $contextEnd,
            'text' => ltrim(substr($text, $end, $contextEnd - $end)),
            'hasMore' => $contextEnd < $length,
        ];
    }

    public static function getAdjacentExcerpt($start, $end, $direction) {
        $text = self::readMarkdown();
        $length = strlen($text);
        $start = max(0, min($length, (int)$start));
        $end = max($start, min($length, (int)$end));
        $window = max(1500, $end - $start);

        if ($direction === 'previous') {
            if ($start <= 0) {
                return null;
            }
            $nextEnd = $start;
            $nextStart = max(0, $nextEnd - $window);
            $nextStart = self::moveStartToParagraphBoundary($text, $nextStart);
            return self::makeExcerptFromRange($text, $nextStart, $nextEnd, $length);
        }

        if ($end >= $length) {
            return null;
        }
        $nextStart = $end;
        $nextEnd = min($length, $nextStart + $window);
        $nextEnd = self::moveEndToParagraphBoundary($text, $nextEnd);
        return self::makeExcerptFromRange($text, $nextStart, $nextEnd, $length);
    }

    public static function saveExcerpt($start, $end, $replacement) {
        $text = self::readMarkdown();
        $length = strlen($text);
        $start = max(0, min($length, (int)$start));
        $end = max($start, min($length, (int)$end));

        $next = substr($text, 0, $start) . rtrim((string)$replacement) . "\n\n" . substr($text, $end);
        self::writeMarkdown($next);

        return [
            'start' => $start,
            'end' => $end,
            'sourceLength' => strlen($next),
        ];
    }

    public static function getMarkdownPath() {
        return self::MARKDOWN_FILE;
    }

    private static function readMarkdown() {
        if (!file_exists(self::MARKDOWN_FILE)) {
            throw new Exception('Markdown introuvable: ' . self::MARKDOWN_FILE);
        }
        $text = file_get_contents(self::MARKDOWN_FILE);
        if ($text === false) {
            throw new Exception('Impossible de lire ' . self::MARKDOWN_FILE);
        }
        return $text;
    }

    private static function writeMarkdown($text) {
        ensureDir(MARKDOWN_CLEAN_DIR);
        $tmp = self::MARKDOWN_FILE . '.tmp';
        if (file_put_contents($tmp, $text) === false) {
            throw new Exception('Impossible d ecrire ' . $tmp);
        }
        if (!rename($tmp, self::MARKDOWN_FILE)) {
            @unlink($tmp);
            throw new Exception('Impossible de remplacer ' . self::MARKDOWN_FILE);
        }
    }

    private static function estimateTextPageCount() {
        if (file_exists(SOURCE_PDF)) {
            $pages = self::countPdfPages(SOURCE_PDF);
            if ($pages > 0) return $pages;
        }

        return 1;
    }

    private static function countPdfPages($pdfPath) {
        $pdfinfo = 'C:\\Program Files\\Calibre2\\app\\bin\\pdfinfo.exe';
        if (!file_exists($pdfinfo)) return 0;
        $cmd = '"' . $pdfinfo . '" ' . escapeshellarg(str_replace('/', '\\', $pdfPath)) . ' 2>&1';
        $output = [];
        exec($cmd, $output);
        foreach ($output as $line) {
            if (preg_match('/^Pages:\s*(\d+)/i', $line, $m)) {
                return (int)$m[1];
            }
        }
        return 0;
    }

    private static function bookPageToTextPageIndex($pageNumber) {
        $pageNumber = max(1, (int)$pageNumber);
        if ($pageNumber % 2 === 0) {
            $pageNumber = max(1, $pageNumber - 1);
        }
        return (int)(($pageNumber + 1) / 2);
    }

    private static function makeExcerptFromRange($text, $start, $end, $sourceLength) {
        return [
            'page' => null,
            'textPageIndex' => null,
            'textPageCount' => self::estimateTextPageCount(),
            'start' => $start,
            'end' => $end,
            'excerpt' => substr($text, $start, $end - $start),
            'isApproximate' => true,
            'sourceLength' => $sourceLength,
            'windowPages' => null,
        ];
    }

    private static function moveStartToParagraphBoundary($text, $pos) {
        if ($pos <= 0) return 0;
        $boundary = strrpos(substr($text, 0, $pos), "\n\n");
        return $boundary === false ? 0 : $boundary + 2;
    }

    private static function moveEndToParagraphBoundary($text, $pos) {
        $length = strlen($text);
        if ($pos >= $length) return $length;
        $boundary = strpos($text, "\n\n", $pos);
        return $boundary === false ? $length : $boundary;
    }
}
