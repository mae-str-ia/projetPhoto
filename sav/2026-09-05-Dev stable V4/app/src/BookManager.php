<?php

class BookManager {
    /**
     * Load book data from JSON
     */
    public static function load() {
        $data = readJsonFile(BOOK_JSON);
        if (!$data) {
            return self::initBook();
        }
        $data = self::withDefaultProperties($data);
        $data = self::migrateToMedia($data);
        return $data;
    }

    /**
     * Migration one-shot : enregistre dans media[] les photos de pages
     * qui n'ont pas encore de mediaId.
     */
    private static function migrateToMedia($book) {
        if (!isset($book['media'])) {
            $book['media'] = [];
        }

        $existingIds = array_column($book['media'], 'id');
        $dirty = false;

        foreach ($book['pages'] as &$page) {
            if (($page['type'] ?? '') !== 'photo') continue;
            foreach ($page['photos'] as &$photo) {
                if (!empty($photo['mediaId'])) continue;
                // Créer l'entrée media
                $mediaId = 'm_' . uniqid('', true);
                $book['media'][] = [
                    'id'             => $mediaId,
                    'filename'       => $photo['filename'],
                    'width'          => $photo['width']  ?? null,
                    'height'         => $photo['height'] ?? null,
                    'uploadedAt'     => $photo['uploadedAt'] ?? date('c'),
                    'defaultCaption' => $photo['caption'] ?? '',
                ];
                $photo['mediaId'] = $mediaId;
                $dirty = true;
            }
            unset($photo);
        }
        unset($page);

        if ($dirty) {
            self::save($book);
        }

        return $book;
    }

    // --- Media CRUD ---

    public static function getMedia() {
        $book = self::load();
        return $book['media'] ?? [];
    }

    public static function addMedia($mediaData) {
        $book = self::load();
        if (!isset($book['media'])) $book['media'] = [];
        $book['media'][] = $mediaData;
        self::save($book);
        return $mediaData;
    }

    public static function updateMediaCaption($mediaId, $caption) {
        $book = self::load();
        foreach ($book['media'] as &$m) {
            if ($m['id'] === $mediaId) {
                $m['defaultCaption'] = $caption;
                self::save($book);
                return $m;
            }
        }
        return null;
    }

    /**
     * Retourne les utilisations d'un media : [pageNumber => [photoIds]]
     */
    public static function getMediaUsage($mediaId) {
        $book = self::load();
        $usage = [];
        foreach ($book['pages'] as $page) {
            if (($page['type'] ?? '') !== 'photo') continue;
            foreach ($page['photos'] as $photo) {
                if (($photo['mediaId'] ?? '') === $mediaId) {
                    $usage[] = $page['pageNumber'];
                    break;
                }
            }
        }
        return $usage;
    }

    public static function deleteMedia($mediaId) {
        $book = self::load();
        foreach ($book['media'] as $i => $m) {
            if ($m['id'] === $mediaId) {
                array_splice($book['media'], $i, 1);
                self::save($book);
                return true;
            }
        }
        return false;
    }

    public static function defaultProperties() {
        return [
            'pageDimensions' => [
                'widthCm' => 24,
                'heightCm' => 16,
            ],
            'photoPageMargins' => [
                'topCm' => 1,
                'rightCm' => 1,
                'bottomCm' => 1,
                'leftCm' => 1,
            ],
            'bindingCm' => 2,
            'textPageMargins' => [
                'topCm' => 2,
                'rightCm' => 2,
                'bottomCm' => 2,
                'leftCm' => 2,
            ],
            'textBindingCm' => 1.5,
            'pageNumberOffset' => 0,
            'defaultLayout' => 'pleine-page',
        ];
    }

    public static function withDefaultProperties($book) {
        $defaults = self::defaultProperties();
        $book['properties'] = array_replace_recursive($defaults, $book['properties'] ?? []);
        $book['properties']['pageDimensions'] = $defaults['pageDimensions'];
        return $book;
    }

    public static function updateProperties($properties) {
        $book = self::load();
        $defaults = self::defaultProperties();
        $current = $book['properties'] ?? $defaults;
        $margins = $properties['photoPageMargins'] ?? [];
        $textMargins = $properties['textPageMargins'] ?? [];

        $book['properties'] = $current;
        foreach (['topCm', 'rightCm', 'bottomCm', 'leftCm'] as $key) {
            if (isset($margins[$key])) {
                $book['properties']['photoPageMargins'][$key] = max(0, min(20, (float)$margins[$key]));
            }
            if (isset($textMargins[$key])) {
                $book['properties']['textPageMargins'][$key] = max(0, min(20, (float)$textMargins[$key]));
            }
        }
        if (isset($properties['bindingCm'])) {
            $book['properties']['bindingCm'] = max(0, min(5, (float)$properties['bindingCm']));
        }
        if (isset($properties['textBindingCm'])) {
            $book['properties']['textBindingCm'] = max(0, min(8, (float)$properties['textBindingCm']));
        }
        if (isset($properties['pageNumberOffset'])) {
            $book['properties']['pageNumberOffset'] = intval($properties['pageNumberOffset']);
        }
        $validLayouts = ['pleine-page', '2-cote', '2-haut-bas', '1g-2d', '2g-1d', '1h-2b', '3-cote', '4-grille', 'free'];
        if (isset($properties['defaultLayout']) && in_array($properties['defaultLayout'], $validLayouts)) {
            $book['properties']['defaultLayout'] = $properties['defaultLayout'];
        }

        self::save($book);
        return $book['properties'];
    }

    /**
     * Save book data to JSON atomically
     */
    public static function save($data) {
        ensureDir(DATA_DIR);
        writeJsonFile(BOOK_JSON, $data);
    }

    /**
     * Initialize empty book with default structure.
     * Uses PDF page count if available, falls back to TOTAL_PAGES constant.
     */
    public static function initBook($totalPages = null) {
        if ($totalPages === null) {
            $pdfCount = PdfManager::countPages();
            // Each spread uses 1 PDF text page (even pages = text, odd = blank).
            // Spreads = ceil(pdfCount / 2), book pages = spreads × 2.
            $totalPages = $pdfCount > 0 ? (int)(ceil($pdfCount / 2) * 2) : TOTAL_PAGES;
        }
        $book = [
            'title' => 'Une Vie en Mouvement',
            'totalPages' => $totalPages,
            'pageDimensions' => [
                'width' => PAGE_WIDTH,
                'height' => PAGE_HEIGHT,
            ],
            'properties' => self::defaultProperties(),
            'pages' => [],
        ];

        // Create alternating text (right) and photo (left) pages
        // PDF alternates: blank page (left) then text page (right).
        // Text pages are at PDF positions 2, 4, 6... (1-based, even pages).
        // Book text page N (1-based) → PDF page N*2.
        for ($i = 1; $i <= $totalPages; $i++) {
            if ($i % 2 === 1) {
                $book['pages'][] = [
                    'pageNumber' => $i,
                    'side' => 'right',
                    'type' => 'text',
                    'pdfPage' => $i,
                ];
            } else {
                // Even pages (2, 4, 6...) are photos (left)
                $book['pages'][] = [
                    'pageNumber' => $i,
                    'side' => 'left',
                    'type' => 'photo',
                    'layout' => '4-grille',
                    'photos' => [],
                ];
            }
        }

        self::save($book);
        return $book;
    }

    /**
     * Extend book if PDF has more pages than currently stored.
     * Preserves existing photo data.
     */
    public static function extendIfNeeded() {
        $pdfCount = PdfManager::countPages();
        if ($pdfCount <= 0) return;

        $targetTotal = (int)(ceil($pdfCount / 2) * 2);

        $book = self::load();
        if ($targetTotal <= $book['totalPages']) return;

        $oldTotal = $book['totalPages'];
        $book['totalPages'] = $targetTotal;

        for ($i = $oldTotal + 1; $i <= $targetTotal; $i++) {
            if ($i % 2 === 1) {
                $book['pages'][] = [
                    'pageNumber' => $i,
                    'side'       => 'right',
                    'type'       => 'text',
                    'pdfPage'    => $i,
                ];
            } else {
                $book['pages'][] = [
                    'pageNumber' => $i,
                    'side'       => 'left',
                    'type'       => 'photo',
                    'layout'     => '4-grille',
                    'photos'     => [],
                ];
            }
        }

        self::save($book);
    }

    /**
     * Get single page by number
     */
    public static function getPage($pageNumber) {
        $book = self::load();
        foreach ($book['pages'] as $page) {
            if ($page['pageNumber'] === $pageNumber) {
                return $page;
            }
        }
        return null;
    }

    /**
     * Get spread (2 consecutive pages: left=photo even, right=text odd)
     * $spreadNumber: 1-based. Spread N = left page 2N (even) + right page 2N+1 (odd).
     * Page 1 is a standalone cover page, not part of any spread.
     */
    public static function getSpread($spreadNumber) {
        $book = self::load();
        $leftPageNum  = $spreadNumber * 2;
        $rightPageNum = $spreadNumber * 2 + 1;

        $left = null;
        $right = null;

        foreach ($book['pages'] as $page) {
            if ($page['pageNumber'] === $leftPageNum) {
                $left = $page;
            }
            if ($page['pageNumber'] === $rightPageNum) {
                $right = $page;
            }
        }

        return [
            'spreadNumber' => $spreadNumber,
            'leftPage' => $left,
            'rightPage' => $right,
        ];
    }

    /**
     * Update page layout and photos
     */
    public static function updatePage($pageNumber, $pageData) {
        $book = self::load();

        foreach ($book['pages'] as &$page) {
            if ($page['pageNumber'] === $pageNumber) {
                // Merge updated data into existing page
                $page = array_merge($page, $pageData);
                break;
            }
        }
        unset($page);

        self::save($book);
        return true;
    }

    /**
     * Insert a new spread (2 pages) after $afterSpread (1-based).
     * All following pages are renumbered +2.
     */
    public static function insertSpread($afterSpread) {
        $book = self::load();
        $insertAfterPage = $afterSpread * 2 + 1; // last page of spread N = right/odd page 2N+1

        // Renumber pages that come after the insertion point
        foreach ($book['pages'] as &$page) {
            if ($page['pageNumber'] > $insertAfterPage) {
                $page['pageNumber'] += 2;
                if (isset($page['pdfPage'])) {
                    $page['pdfPage'] = $page['pageNumber'];
                }
            }
        }
        unset($page);

        $newLeft  = $insertAfterPage + 1; // even
        $newRight = $insertAfterPage + 2; // odd

        $book['pages'][] = [
            'pageNumber' => $newLeft,
            'side'       => 'left',
            'type'       => 'photo',
            'layout'     => 'pleine-page',
            'photos'     => [],
        ];
        $book['pages'][] = [
            'pageNumber' => $newRight,
            'side'       => 'right',
            'type'       => 'text',
            'pdfPage'    => $newRight,
        ];

        // Sort pages by pageNumber
        usort($book['pages'], fn($a, $b) => $a['pageNumber'] - $b['pageNumber']);

        $book['totalPages'] += 2;
        self::save($book);
        return true;
    }

    /**
     * Delete the spread $spreadNumber (1-based).
     * Returns false if the photo page has photos (safety check).
     * Pass $force=true to delete anyway.
     */
    public static function deleteSpread($spreadNumber, $force = false) {
        $book = self::load();
        $leftPageNum  = $spreadNumber * 2;
        $rightPageNum = $spreadNumber * 2 + 1;

        // Safety check
        if (!$force) {
            foreach ($book['pages'] as $page) {
                if ($page['pageNumber'] === $leftPageNum && !empty($page['photos'])) {
                    return false;
                }
                if ($page['pageNumber'] === $rightPageNum && ($page['type'] ?? '') === 'photo' && !empty($page['photos'])) {
                    return false;
                }
            }
        }

        // Remove the two pages
        $book['pages'] = array_values(array_filter($book['pages'], function($p) use ($leftPageNum, $rightPageNum) {
            return $p['pageNumber'] !== $leftPageNum && $p['pageNumber'] !== $rightPageNum;
        }));

        // Renumber following pages
        foreach ($book['pages'] as &$page) {
            if ($page['pageNumber'] > $rightPageNum) {
                $page['pageNumber'] -= 2;
                if (isset($page['pdfPage'])) {
                    $page['pdfPage'] = $page['pageNumber'];
                }
            }
        }
        unset($page);

        $book['totalPages'] -= 2;
        self::save($book);
        return true;
    }

    /**
     * Toggle a page between 'text' and 'photo' type.
     * Works for both left (even) and right (odd) pages.
     */
    public static function setPageType($pageNumber, $type) {
        $book = self::load();
        foreach ($book['pages'] as &$page) {
            if ($page['pageNumber'] === $pageNumber) {
                $page['type'] = $type;
                if ($type === 'photo' && !isset($page['photos'])) {
                    $page['photos'] = [];
                    $page['layout'] = 'pleine-page';
                }
                self::save($book);
                return true;
            }
        }
        unset($page);
        return false;
    }

    /**
     * Get all spreads for gallery view
     */
    public static function getAllSpreads() {
        $book = self::load();
        $spreads = [];
        $totalSpreads = intdiv($book['totalPages'] - 1, 2);

        for ($i = 1; $i <= $totalSpreads; $i++) {
            $spreads[] = self::getSpread($i);
        }

        return $spreads;
    }
}
