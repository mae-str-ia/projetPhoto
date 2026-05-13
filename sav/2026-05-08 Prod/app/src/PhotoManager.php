<?php

class PhotoManager {
    /**
     * Handle file upload
     * @param array $file $_FILES entry
     * @param int $pageNumber Page number where photo will be placed
     * @return array Photo metadata or null on error
     */
    public static function upload($file, $pageNumber) {
        // Validate file
        if (!isset($file['tmp_name']) || !isset($file['name'])) {
            throw new Exception('Invalid file upload');
        }

        $tmpFile = $file['tmp_name'];
        $origName = $file['name'];
        $mimeType = $file['type'] ?? mime_content_type($tmpFile);
        $fileSize = filesize($tmpFile);

        // Check file size
        if ($fileSize > MAX_FILE_SIZE) {
            throw new Exception('File too large: ' . formatFileSize($fileSize));
        }

        // Check MIME type
        if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
            throw new Exception('Invalid file type: ' . $mimeType);
        }

        // Get extension
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            throw new Exception('Invalid file extension: ' . $ext);
        }

        // Generate unique filename
        $photoId = generateId('photo_');
        $filename = $photoId . '.' . $ext;

        // Ensure upload directory exists
        ensureDir(UPLOAD_DIR);

        $uploadPath = UPLOAD_DIR . '/' . $filename;

        // Move file
        if (!move_uploaded_file($tmpFile, $uploadPath)) {
            throw new Exception('Failed to move uploaded file');
        }

        // Get image dimensions
        $imageSize = getimagesize($uploadPath);
        $width = $imageSize[0] ?? null;
        $height = $imageSize[1] ?? null;
        $ratio = ($width && $height) ? $width / $height : 1;
        $pageRatio = PAGE_HEIGHT / PAGE_WIDTH;
        $frameHeight = 40;
        $frameWidth = $frameHeight * $ratio * $pageRatio;
        if ($frameWidth > 45) {
            $frameWidth = 45;
            $frameHeight = $frameWidth / max($ratio * $pageRatio, 0.001);
        }
        if ($frameWidth < 18) {
            $frameWidth = 18;
            $frameHeight = $frameWidth / max($ratio * $pageRatio, 0.001);
        }
        $frameHeight = max(18, min(55, $frameHeight));

        // Return metadata (no position — slots manage placement)
        return [
            'id'       => $photoId,
            'filename' => $filename,
            'caption'  => '',
            'captionAlign' => 'left',
            'rotation' => 0,
            'filter'   => 'none',
            'frame'    => [
                'x' => 5,
                'y' => 5,
                'w' => round($frameWidth, 4),
                'h' => round($frameHeight, 4),
                'z' => 1,
                'shape' => 'rect',
                'ratio' => 'original',
                'borderWidth' => 0,
                'borderColor' => 'white',
                'backgroundColor' => 'white',
            ],
            'crop' => [
                'fitMode' => 'cover',
                'zoom' => 1,
                'panX' => 0,
                'panY' => 0,
            ],
            'width'    => $width,
            'height'   => $height,
        ];
    }

    /**
     * Get upload URL for a filename
     */
    public static function getUploadUrl($filename) {
        return BASE_URL . '/uploads/photos/' . urlencode($filename);
    }

    /**
     * Get upload path for a filename
     */
    public static function getUploadPath($filename) {
        return UPLOAD_DIR . '/' . $filename;
    }

    /**
     * Validate a photo belongs to a page
     */
    public static function validatePhotoOwnership($photoId, $pageNumber) {
        $page = BookManager::getPage($pageNumber);
        if (!$page || $page['type'] !== 'photo') {
            return false;
        }

        foreach ($page['photos'] as $photo) {
            if ($photo['id'] === $photoId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete a photo (remove from page and optionally from filesystem)
     */
    public static function delete($photoId, $pageNumber, $deleteFile = false) {
        $page = BookManager::getPage($pageNumber);
        if (!$page) {
            throw new Exception('Page not found');
        }

        $updated = false;
        foreach ($page['photos'] as $i => $photo) {
            if ($photo['id'] === $photoId) {
                array_splice($page['photos'], $i, 1);
                $updated = true;

                if ($deleteFile) {
                    $path = self::getUploadPath($photo['filename']);
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
                break;
            }
        }

        if ($updated) {
            BookManager::updatePage($pageNumber, $page);
        }

        return $updated;
    }
}
