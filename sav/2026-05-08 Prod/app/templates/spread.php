<?php
$pageTitle = 'Double-page';
$spreadNumber = isset($_GET['spread']) ? intval($_GET['spread']) : 1;
$book = BookManager::load();
$bookProperties = $book['properties'] ?? BookManager::defaultProperties();
$spread = BookManager::getSpread($spreadNumber);
$totalSpreads = TOTAL_PAGES / 2;

if (!$spread['leftPage'] || !$spread['rightPage']) {
    http_response_code(404);
    echo "Spread not found";
    exit;
}
?>

<div class="spread-view-container">
    <div class="spread-navigation">
        <button class="nav-prev" id="spreadPrev" <?php if ($spreadNumber <= 1) echo 'disabled'; ?>>← Précédent</button>
        <span class="spread-counter">Spread <?php echo $spreadNumber; ?> / <?php echo $totalSpreads; ?></span>
        <button class="nav-next" id="spreadNext" <?php if ($spreadNumber >= $totalSpreads) echo 'disabled'; ?>>Suivant →</button>
        <a href="?page=gallery" class="nav-back">Retour au livre</a>
    </div>

    <div class="spread-pages">
        <?php
        $leftPage = $spread['leftPage'];
        $layout   = $leftPage['layout'] ?? 'free';
        $photos   = $leftPage['photos'] ?? [];
        $slots_def = $leftPage['slotAssignments'] ?? [];

        // Index photos par ID pour accès rapide
        $photosById = [];
        foreach ($photos as $p) { $photosById[$p['id']] = $p; }

        // Définitions de slots — identiques à page-editor.js LAYOUTS
        $layouts = [
            'pleine-page' => [['x'=>2,'y'=>2,'w'=>96,'h'=>96]],
            '2-cote'      => [['x'=>2,'y'=>2,'w'=>47,'h'=>96],['x'=>51,'y'=>2,'w'=>47,'h'=>96]],
            '2-haut-bas'  => [['x'=>2,'y'=>2,'w'=>96,'h'=>47],['x'=>2,'y'=>51,'w'=>96,'h'=>47]],
            '1g-2d'       => [['x'=>2,'y'=>2,'w'=>60,'h'=>96],['x'=>64,'y'=>2,'w'=>34,'h'=>47],['x'=>64,'y'=>51,'w'=>34,'h'=>47]],
            '2g-1d'       => [['x'=>2,'y'=>2,'w'=>34,'h'=>47],['x'=>2,'y'=>51,'w'=>34,'h'=>47],['x'=>38,'y'=>2,'w'=>60,'h'=>96]],
            '1h-2b'       => [['x'=>2,'y'=>2,'w'=>96,'h'=>60],['x'=>2,'y'=>64,'w'=>47,'h'=>34],['x'=>51,'y'=>64,'w'=>47,'h'=>34]],
            '3-cote'      => [['x'=>2,'y'=>2,'w'=>30,'h'=>96],['x'=>35,'y'=>2,'w'=>30,'h'=>96],['x'=>68,'y'=>2,'w'=>30,'h'=>96]],
            '4-grille'    => [['x'=>2,'y'=>2,'w'=>47,'h'=>47],['x'=>51,'y'=>2,'w'=>47,'h'=>47],['x'=>2,'y'=>51,'w'=>47,'h'=>47],['x'=>51,'y'=>51,'w'=>47,'h'=>47]],
        ];
        ?>

        <!-- Left page (photo) -->
        <div class="spread-page photo" id="photoPage" data-page-number="<?php echo $leftPage['pageNumber']; ?>">
            <div class="page-header">
                <h3>Planche <?php echo $spreadNumber; ?> <small style="color:#999;font-weight:normal">(p.<?php echo $leftPage['pageNumber']; ?>)</small></h3>
            </div>
            <div class="page-content">
                <div class="spread-photo-page" id="spreadPhotoPage">
                    <?php
                    // Helpers pour rendu photo
                    $artFilters = ['bw'=>'grayscale(100%)','sepia'=>'sepia(100%)','vintage'=>'sepia(50%) contrast(0.9) brightness(1.1) saturate(0.8)'];
                    function spreadPhotoImg($photo) {
                        global $artFilters;
                        $crop = (isset($photo['crop']) && is_array($photo['crop'])) ? $photo['crop'] : [];
                        $fit = $crop['fitMode'] ?? $photo['fit'] ?? 'cover';
                        $zoom = floatval($crop['zoom'] ?? $photo['zoom'] ?? 1);
                        $panX = floatval($crop['panX'] ?? $photo['panX'] ?? 0);
                        $panY = floatval($crop['panY'] ?? $photo['panY'] ?? 0);
                        $tx = $zoom ? $panX / $zoom : 0;
                        $ty = $zoom ? $panY / $zoom : 0;
                        $br  = intval($photo['brightness'] ?? 100);
                        $ct  = intval($photo['contrast']   ?? 100);
                        $art = $artFilters[$photo['filter'] ?? ''] ?? '';
                        $base = '';
                        if ($br !== 100) $base .= "brightness({$br}%) ";
                        if ($ct !== 100) $base .= "contrast({$ct}%) ";
                        $filter = trim($base . $art);
                        $style  = "object-fit:" . h($fit) . ";transform-origin:center center;transform:translate({$tx}%, {$ty}%) scale({$zoom});";
                        if ($filter) $style .= "filter:{$filter};";
                        $style .= 'border-radius:' . spreadFrameRadius($photo) . ';';
                        return '<img src="' . BASE_URL . '/uploads/photos/' . h($photo['filename']) . '" style="' . $style . '">';
                    }
                    function spreadCaption($photo) {
                        if (empty($photo['caption'])) return '';
                        $pos      = $photo['captionPos'] ?? 'below';
                        $alignRaw = $photo['captionAlign'] ?? 'left';
                        $align    = in_array($alignRaw, ['left', 'center', 'right'], true) ? $alignRaw : 'left';
                        $color    = h($photo['captionColor'] ?? 'white');
                        $isInside = ($pos === 'inside-bottom' || $pos === 'inside-top');
                        $bg       = $isInside
                            ? (($photo['captionColor'] ?? 'white') === 'black' ? 'rgba(255,255,255,0.8)' : 'rgba(0,0,0,0.5)')
                            : 'transparent';
                        $size  = intval($photo['captionSize'] ?? 11);
                        return '<div class="spread-caption cap-' . h($pos) . '" style="color:' . $color . ';background:' . $bg . ';font-size:' . $size . 'px;text-align:' . $align . '">' . h($photo['caption']) . '</div>';
                    }
                    function spreadCapContainerClass($photo) {
                        if (trim($photo['caption'] ?? '') === '') return '';
                        $pos = $photo['captionPos'] ?? 'below';
                        if ($pos === 'below') return ' cap-out-below';
                        if ($pos === 'above') return ' cap-out-above';
                        return '';
                    }
                    function spreadCapIsInside($photo) {
                        $pos = $photo['captionPos'] ?? 'below';
                        return $pos === 'inside-bottom' || $pos === 'inside-top';
                    }
                    function spreadFrame($photo) {
                        $frame = (isset($photo['frame']) && is_array($photo['frame'])) ? $photo['frame'] : [];
                        $bw = intval($frame['borderWidth'] ?? $photo['borderWidth'] ?? 0);
                        if ($bw <= 0) return '';
                        $bc = h($frame['borderColor'] ?? $photo['borderColor'] ?? 'white');
                        $radius = spreadFrameRadius($photo);
                        return '<div class="slot-frame" style="box-shadow:inset 0 0 0 ' . $bw . 'px ' . $bc . ';border-radius:' . $radius . ';"></div>';
                    }
                    function spreadFrameRadius($photo) {
                        $frame = (isset($photo['frame']) && is_array($photo['frame'])) ? $photo['frame'] : [];
                        $shape = $frame['shape'] ?? 'rect';
                        if ($shape === 'ellipse') return '50%';
                        if ($shape === 'rounded') return '10px';
                        return '0';
                    }
                    function spreadSlotInMargins($slot, $properties) {
                        $dims = $properties['pageDimensions'] ?? ['widthCm' => 24, 'heightCm' => 16];
                        $m = $properties['leftPageMargins'] ?? ['topCm' => 1, 'rightCm' => 3, 'bottomCm' => 1, 'leftCm' => 1];
                        $width = max(1, floatval($dims['widthCm'] ?? 24));
                        $height = max(1, floatval($dims['heightCm'] ?? 16));
                        $left = max(0, min(90, floatval($m['leftCm'] ?? 1) / $width * 100));
                        $right = max(0, min(90, floatval($m['rightCm'] ?? 3) / $width * 100));
                        $top = max(0, min(90, floatval($m['topCm'] ?? 1) / $height * 100));
                        $bottom = max(0, min(90, floatval($m['bottomCm'] ?? 1) / $height * 100));
                        $contentW = max(1, 100 - $left - $right);
                        $contentH = max(1, 100 - $top - $bottom);
                        return [
                            'x' => $left + $slot['x'] * $contentW / 100,
                            'y' => $top + $slot['y'] * $contentH / 100,
                            'w' => $slot['w'] * $contentW / 100,
                            'h' => $slot['h'] * $contentH / 100,
                        ];
                    }
                    ?>
                    <?php if ($layout === 'free'): ?>
                        <?php foreach ($photos as $photo): ?>
                            <?php if (empty($photo['filename'])) continue; ?>
                            <?php $pos = (isset($photo['frame']) && is_array($photo['frame'])) ? $photo['frame'] : ($photo['position'] ?? ['x'=>5,'y'=>5,'w'=>40,'h'=>40]); ?>
                            <?php $radius = spreadFrameRadius($photo); ?>
                            <div class="spread-slot free-slot<?php echo spreadCapContainerClass($photo); ?>" style="left:<?php echo $pos['x']; ?>%;top:<?php echo $pos['y']; ?>%;width:<?php echo $pos['w']; ?>%;height:<?php echo $pos['h']; ?>%;z-index:<?php echo intval($pos['z'] ?? 1); ?>;border-radius:<?php echo $radius; ?>">
                                <div class="spread-photo-clip" style="border-radius:<?php echo $radius; ?>">
                                    <?php echo spreadPhotoImg($photo); ?>
                                    <?php echo spreadFrame($photo); ?>
                                    <?php if (spreadCapIsInside($photo)) echo spreadCaption($photo); ?>
                                </div>
                                <?php if (!spreadCapIsInside($photo)) echo spreadCaption($photo); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif (isset($layouts[$layout])): ?>
                        <?php foreach ($layouts[$layout] as $i => $slot): ?>
                            <?php
                            $photoId = $slots_def[$i] ?? null;
                            $photo   = $photoId ? ($photosById[$photoId] ?? null) : null;
                            ?>
                            <?php $radius = $photo ? spreadFrameRadius($photo) : '0'; ?>
                            <?php $pageSlot = spreadSlotInMargins($slot, $bookProperties); ?>
                            <div class="spread-slot<?php echo $photo ? spreadCapContainerClass($photo) : ''; ?>" style="left:<?php echo $pageSlot['x']; ?>%;top:<?php echo $pageSlot['y']; ?>%;width:<?php echo $pageSlot['w']; ?>%;height:<?php echo $pageSlot['h']; ?>%;border-radius:<?php echo $radius; ?>">
                                <?php if ($photo): ?>
                                    <div class="spread-photo-clip" style="border-radius:<?php echo $radius; ?>">
                                        <?php echo spreadPhotoImg($photo); ?>
                                        <?php echo spreadFrame($photo); ?>
                                        <?php if (spreadCapIsInside($photo)) echo spreadCaption($photo); ?>
                                    </div>
                                    <?php if (!spreadCapIsInside($photo)) echo spreadCaption($photo); ?>
                                <?php else: ?>
                                    <div class="spread-slot-empty"></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right page (text from PDF) -->
        <div class="spread-page text" id="textPage" data-page-number="<?php echo $spread['rightPage']['pageNumber']; ?>">
            <div class="page-header">
                <h3>Texte <small style="color:#999;font-weight:normal">(PDF p.<?php echo $spread['rightPage']['pdfPage']; ?>)</small></h3>
            </div>
            <div class="page-content" id="pdfPageContent">
                <canvas id="pdfCanvas"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pdfPage = <?php echo intval($spread['rightPage']['pdfPage']); ?>; // 1-based
    const canvas = document.getElementById('pdfCanvas');

    if (typeof PdfViewer !== 'undefined' && canvas) {
        PdfViewer.render(canvas, pdfPage);
    }

    // Navigation
    document.getElementById('spreadPrev')?.addEventListener('click', function() {
        const spread = <?php echo $spreadNumber; ?> - 1;
        window.location = '?page=spread&spread=' + spread;
    });

    document.getElementById('spreadNext')?.addEventListener('click', function() {
        const spread = <?php echo $spreadNumber; ?> + 1;
        window.location = '?page=spread&spread=' + spread;
    });

    // Click to edit
    document.getElementById('photoPage').addEventListener('click', function() {
        window.location = '?page=editor&num=<?php echo $spread['leftPage']['pageNumber']; ?>';
    });
});
</script>

<style>
.spread-navigation {
    margin-bottom: 2rem;
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.nav-prev, .nav-next, .nav-back {
    padding: 0.5rem 1rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
    cursor: pointer;
    font-size: 0.875rem;
}

.nav-prev:disabled, .nav-next:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.spread-counter {
    font-weight: 600;
    color: #666;
}

.page-header {
    padding: 0.5rem 1rem;
    border-bottom: 1px solid #eee;
    background: #fafafa;
    flex-shrink: 0;
}

.page-header h3 {
    margin: 0;
    font-size: 0.9rem;
}

.spread-photo-page {
    position: relative;
    width: 100%;
    height: 100%;
    background: white;
}

.spread-slot {
    position: absolute;
    box-sizing: border-box;
    overflow: visible;
    background: transparent;
}
.spread-photo-clip { position: absolute; inset: 0; overflow: hidden; background: white; }
.spread-slot .slot-frame { position: absolute; inset: 0; pointer-events: none; z-index: 1; }

.spread-slot img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.spread-slot-empty {
    width: 100%;
    height: 100%;
    background: #f5f5f5;
    border: 1px dashed #ddd;
}

.spread-caption {
    position: absolute;
    top: 100%; bottom: auto; left: 0; right: 0;
    margin-top: 3px;
    font-size: 11px;
    padding: 2px 5px;
    line-height: 1.2;
    white-space: pre-line;
    overflow: hidden;
    max-height: 45%;
    z-index: 2;
}
.spread-caption.cap-inside-bottom { top: auto; bottom: 0; margin: 0; }
.spread-caption.cap-inside-top    { top: 0; bottom: auto; margin: 0; }
.spread-slot.cap-out-below, .spread-slot.cap-out-above { display: flex !important; }
.spread-slot.cap-out-below { flex-direction: column; }
.spread-slot.cap-out-above { flex-direction: column-reverse; }
.spread-slot.cap-out-below .spread-photo-clip,
.spread-slot.cap-out-above .spread-photo-clip {
    position: relative !important;
    inset: auto !important;
    flex: 1; width: 100%; min-height: 0;
}
.spread-slot.cap-out-below .spread-caption,
.spread-slot.cap-out-above .spread-caption {
    position: relative !important;
    flex-shrink: 0;
    top: auto !important; bottom: auto !important; margin: 0 !important;
}
.spread-slot.free-slot { box-shadow: 0 0 0 2px white; }

.page-content {
    flex: 1;
    position: relative;
    overflow: hidden;
    min-height: 0;
}

#pdfPageContent {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

#pdfCanvas {
    max-width: 100%;
    max-height: 100%;
    display: block;
}
</style>
