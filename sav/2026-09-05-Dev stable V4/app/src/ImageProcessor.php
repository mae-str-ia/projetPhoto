<?php

class ImageProcessor {
    /**
     * Apply all transformations to an image
     * @param string $srcPath Source image path
     * @param array $ops Operations array with keys: crop, rotation, filter, frame
     * @param string $destPath Destination path (optional)
     * @return resource|false GD image resource
     */
    public static function process($srcPath, $ops = [], $destPath = null) {
        if (!file_exists($srcPath)) {
            throw new Exception("Image not found: $srcPath");
        }

        // Load image
        $image = self::loadImage($srcPath);
        if (!$image) {
            throw new Exception("Could not load image: $srcPath");
        }

        // Apply crop
        if (!empty($ops['crop'])) {
            $image = self::applyCrop($image, $ops['crop']);
        }

        // Apply rotation
        if (!empty($ops['rotation'])) {
            $image = self::applyRotation($image, $ops['rotation']);
        }

        // Apply filter
        if (!empty($ops['filter']) && $ops['filter'] !== 'none') {
            $image = self::applyFilter($image, $ops['filter']);
        }

        // Apply frame
        if (!empty($ops['frame']) && $ops['frame'] !== 'none') {
            $image = self::applyFrame($image, $ops['frame']);
        }

        // Save if destination provided
        if ($destPath) {
            self::saveImage($image, $destPath);
        }

        return $image;
    }

    /**
     * Load image into GD resource
     */
    private static function loadImage($path) {
        $info = @getimagesize($path);
        if (!$info) {
            return false;
        }

        $type = $info[2];
        switch ($type) {
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return @imagecreatefrompng($path);
            case IMAGETYPE_WEBP:
                return @imagecreatefromwebp($path);
            default:
                return false;
        }
    }

    /**
     * Save GD image to file
     */
    private static function saveImage($image, $path, $quality = 90) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                return imagejpeg($image, $path, $quality);
            case 'png':
                return imagepng($image, $path);
            case 'webp':
                return imagewebp($image, $path, $quality);
            default:
                return imagejpeg($image, $path, $quality);
        }
    }

    /**
     * Apply crop to image
     * @param resource $image GD image
     * @param array $crop [x, y, w, h] in percentages or pixels
     */
    private static function applyCrop($image, $crop) {
        $width = imagesx($image);
        $height = imagesy($image);

        // Parse crop dimensions (can be % or absolute)
        $x = self::parseDimension($crop['x'] ?? 0, $width);
        $y = self::parseDimension($crop['y'] ?? 0, $height);
        $w = self::parseDimension($crop['w'] ?? $width, $width);
        $h = self::parseDimension($crop['h'] ?? $height, $height);

        // Create new image
        $cropped = imagecrop($image, [
            'x' => max(0, $x),
            'y' => max(0, $y),
            'width' => min($w, $width - $x),
            'height' => min($h, $height - $y),
        ]);

        if ($cropped) {
            imagedestroy($image);
            return $cropped;
        }

        return $image;
    }

    /**
     * Apply rotation to image
     * @param resource $image GD image
     * @param int $degrees Rotation in degrees (0-360)
     */
    private static function applyRotation($image, $degrees) {
        $degrees = intval($degrees) % 360;
        if ($degrees === 0) {
            return $image;
        }

        // For 90, 180, 270 degree rotations, use imagerotate
        $angle = 360 - $degrees; // imagerotate works counter-clockwise
        $rotated = imagerotate($image, $angle, 0);

        if ($rotated) {
            imagedestroy($image);
            return $rotated;
        }

        return $image;
    }

    /**
     * Apply color filter to image
     */
    private static function applyFilter($image, $filter) {
        switch ($filter) {
            case 'bw':
            case 'grayscale':
                imagefilter($image, IMG_FILTER_GRAYSCALE);
                break;

            case 'sepia':
                // Convert to grayscale first
                imagefilter($image, IMG_FILTER_GRAYSCALE);
                // Apply sepia-like colorization
                imagefilter($image, IMG_FILTER_COLORIZE, 39, 26, 8);
                break;

            case 'vintage':
                // Reduce saturation and add slight sepia
                imagefilter($image, IMG_FILTER_GRAYSCALE);
                imagefilter($image, IMG_FILTER_COLORIZE, 20, 10, 0);
                imagefilter($image, IMG_FILTER_BRIGHTNESS, 10);
                break;
        }

        return $image;
    }

    /**
     * Apply frame to image
     */
    private static function applyFrame($image, $frameType) {
        $width = imagesx($image);
        $height = imagesy($image);

        $borderSize = match ($frameType) {
            'thin' => 5,
            'thick' => 15,
            'shadow' => 10,
            default => 0,
        };

        if ($borderSize === 0) {
            return $image;
        }

        // Create new image with border
        $newWidth = $width + ($borderSize * 2);
        $newHeight = $height + ($borderSize * 2);
        $framed = imagecreatetruecolor($newWidth, $newHeight);

        // Fill with color
        if ($frameType === 'shadow') {
            $bgColor = imagecolorallocate($framed, 200, 200, 200);
        } else {
            $bgColor = imagecolorallocate($framed, 255, 255, 255);
        }
        imagefill($framed, 0, 0, $bgColor);

        // Copy original image onto new canvas
        imagecopy($framed, $image, $borderSize, $borderSize, 0, 0, $width, $height);

        imagedestroy($image);
        return $framed;
    }

    /**
     * Parse dimension (can be "50%", "100", etc.)
     */
    private static function parseDimension($value, $maxSize) {
        if (is_string($value) && strpos($value, '%') !== false) {
            return intval($value) * $maxSize / 100;
        }
        return intval($value);
    }
}
