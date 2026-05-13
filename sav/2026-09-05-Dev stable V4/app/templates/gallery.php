<?php
$pageTitle = 'Livre';
$book = BookManager::load();
$bookProperties = $book['properties'] ?? BookManager::defaultProperties();
$printablePages = (int)($book['printablePages'] ?? ($book['totalPages'] ?? TOTAL_PAGES));
$frontPage = BookManager::getPage(1);
$spreads = BookManager::getAllSpreads();
?><div class="gallery-toolbar" id="galleryToolbar">
    <span class="zoom-label">Zoom :</span>
    <input type="range" id="zoomSlider" min="1" max="5" step="1" value="2" class="zoom-slider">
    <span id="zoomHint" class="zoom-hint">2 planches</span>
</div>
<?php

// Mêmes définitions de slots que l'éditeur et spread.php
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

<div class="book-props-modal hidden" id="bookPropsModal">
    <div class="book-props-box">
        <div class="book-props-header">
            <span>Propriétés du livre</span>
            <button class="book-props-close" id="bookPropsClose">×</button>
        </div>
        <div class="book-props-body">
            <div class="book-props-note">Page gauche : <?php echo h($bookProperties['pageDimensions']['widthCm'] ?? 24); ?> × <?php echo h($bookProperties['pageDimensions']['heightCm'] ?? 16); ?> cm</div>
            <label>Reliure <input type="number" id="bindingCm" min="0" max="5" step="0.1" value="<?php echo h($bookProperties['bindingCm'] ?? 2); ?>"> cm</label>
            <label>Décalage numérotation <input type="number" id="pageNumberOffset" min="-50" max="50" step="1" value="<?php echo h($bookProperties['pageNumberOffset'] ?? 0); ?>"> <small>(ex. -2 si 2 pages de couverture)</small></label>
            <div class="book-props-section-title">Marges photos</div>
            <label>Haut <input type="number" id="marginTopCm" min="0" max="20" step="0.1" value="<?php echo h($bookProperties['photoPageMargins']['topCm'] ?? 1); ?>"></label>
            <label>Droite <input type="number" id="marginRightCm" min="0" max="20" step="0.1" value="<?php echo h($bookProperties['photoPageMargins']['rightCm'] ?? 1); ?>"></label>
            <label>Bas <input type="number" id="marginBottomCm" min="0" max="20" step="0.1" value="<?php echo h($bookProperties['photoPageMargins']['bottomCm'] ?? 1); ?>"></label>
            <label>Gauche <input type="number" id="marginLeftCm" min="0" max="20" step="0.1" value="<?php echo h($bookProperties['photoPageMargins']['leftCm'] ?? 1); ?>"></label>
            <div class="book-props-section-title">Marges texte</div>
            <label>Reliure texte <input type="number" id="textBindingCm" min="0" max="8" step="0.1" value="<?php echo h($bookProperties['textBindingCm'] ?? 3); ?>"> cm</label>
            <label>Haut <input type="number" id="textMarginTopCm" min="0" max="20" step="0.1" value="<?php echo h($bookProperties['textPageMargins']['topCm'] ?? 2); ?>"></label>
            <label>Droite <input type="number" id="textMarginRightCm" min="0" max="20" step="0.1" value="<?php echo h($bookProperties['textPageMargins']['rightCm'] ?? 2); ?>"></label>
            <label>Bas <input type="number" id="textMarginBottomCm" min="0" max="20" step="0.1" value="<?php echo h($bookProperties['textPageMargins']['bottomCm'] ?? 2); ?>"></label>
            <label>Gauche <input type="number" id="textMarginLeftCm" min="0" max="20" step="0.1" value="<?php echo h($bookProperties['textPageMargins']['leftCm'] ?? 2); ?>"></label>
            <label>Disposition par défaut
                <select id="defaultLayout">
                    <?php
                    $layoutLabels = [
                        'pleine-page' => 'Pleine page',
                        '2-cote'      => '2 côte à côte',
                        '2-haut-bas'  => '2 haut / bas',
                        '1g-2d'       => 'Grande gauche',
                        '2g-1d'       => 'Grande droite',
                        '1h-2b'       => 'Grande haut',
                        '3-cote'      => '3 colonnes',
                        '4-grille'    => '4 en grille',
                        'free'        => 'Libre (aucun)',
                    ];
                    $currentDefault = $bookProperties['defaultLayout'] ?? 'pleine-page';
                    foreach ($layoutLabels as $key => $label):
                    ?>
                        <option value="<?php echo h($key); ?>"<?php echo $key === $currentDefault ? ' selected' : ''; ?>><?php echo h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="book-props-footer">
            <button class="book-props-cancel" id="bookPropsCancel">Annuler</button>
            <button class="book-props-save" id="bookPropsSave">Enregistrer</button>
        </div>
    </div>
</div>

<?php
$_artFilters = ['bw'=>'grayscale(100%)','sepia'=>'sepia(100%)','vintage'=>'sepia(50%) contrast(0.9) brightness(1.1) saturate(0.8)'];
function galleryPhotoStyle($photo) {
    global $_artFilters;
    $crop = (isset($photo['crop']) && is_array($photo['crop'])) ? $photo['crop'] : [];
    $fit = $crop['fitMode'] ?? $photo['fit'] ?? 'cover';
    $zoom = floatval($crop['zoom'] ?? $photo['zoom'] ?? 1);
    $panX = floatval($crop['panX'] ?? $photo['panX'] ?? 0);
    $panY = floatval($crop['panY'] ?? $photo['panY'] ?? 0);
    $tx = $zoom ? $panX / $zoom : 0;
    $ty = $zoom ? $panY / $zoom : 0;
    $br  = intval($photo['brightness'] ?? 100);
    $ct  = intval($photo['contrast']   ?? 100);
    $art = $_artFilters[$photo['filter'] ?? ''] ?? '';
    $base = '';
    if ($br !== 100) $base .= "brightness({$br}%) ";
    if ($ct !== 100) $base .= "contrast({$ct}%) ";
    $filter = trim($base . $art);
    $style  = "object-fit:" . h($fit) . ";transform-origin:center center;transform:translate({$tx}%, {$ty}%) scale({$zoom});";
    if ($filter) $style .= "filter:{$filter};";
    return $style;
}
function galleryFrame($photo) {
    $frame = (isset($photo['frame']) && is_array($photo['frame'])) ? $photo['frame'] : [];
    $bw = intval($frame['borderWidth'] ?? $photo['borderWidth'] ?? 0);
    if ($bw <= 0) return '';
    $bc = h($frame['borderColor'] ?? $photo['borderColor'] ?? 'white');
    $radius = galleryFrameRadius($photo);
    return '<div class="g-slot-frame" style="box-shadow:inset 0 0 0 ' . $bw . 'px ' . $bc . ';border-radius:' . $radius . ';"></div>';
}
function galleryFrameRadius($photo) {
    $frame = (isset($photo['frame']) && is_array($photo['frame'])) ? $photo['frame'] : [];
    $shape = $frame['shape'] ?? 'rect';
    if ($shape === 'ellipse') return '50%';
    if ($shape === 'rounded') return '10px';
    return '0';
}
function galleryCaption($photo) {
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
    return '<div class="g-caption cap-' . h($pos) . '" style="color:' . $color . ';background:' . $bg . ';--caption-size:' . $size . 'px;text-align:' . $align . '">' . h($photo['caption']) . '</div>';
}
function galleryCapContainerClass($photo) {
    if (trim($photo['caption'] ?? '') === '') return '';
    $pos = $photo['captionPos'] ?? 'below';
    if ($pos === 'below') return ' cap-out-below';
    if ($pos === 'above') return ' cap-out-above';
    return '';
}
function galleryCapIsInside($photo) {
    $pos = $photo['captionPos'] ?? 'below';
    return $pos === 'inside-bottom' || $pos === 'inside-top';
}
function gallerySlotInMargins($slot, $properties) {
    $dims = $properties['pageDimensions'] ?? ['widthCm' => 24, 'heightCm' => 16];
    $m = $properties['photoPageMargins'] ?? ['topCm' => 1, 'rightCm' => 1, 'bottomCm' => 1, 'leftCm' => 1];
    $binding = max(0, floatval($properties['bindingCm'] ?? 2));
    $width = max(1, floatval($dims['widthCm'] ?? 24));
    $height = max(1, floatval($dims['heightCm'] ?? 16));
    $left = max(0, min(90, floatval($m['leftCm'] ?? 1) / $width * 100));
    $right = max(0, min(90, (floatval($m['rightCm'] ?? 1) + $binding) / $width * 100));
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
function getPhotoParams($photo, $slotW, $slotH) {
    $crop = (isset($photo['crop']) && is_array($photo['crop'])) ? $photo['crop'] : [];
    $fit = $crop['fitMode'] ?? $photo['fit'] ?? 'cover';
    $zoom = floatval($crop['zoom'] ?? $photo['zoom'] ?? 1);
    $panX = floatval($crop['panX'] ?? $photo['panX'] ?? 0);
    $panY = floatval($crop['panY'] ?? $photo['panY'] ?? 0);
    $imageRatio = ($photo['width'] && $photo['height']) ? floatval($photo['width']) / floatval($photo['height']) : 1;
    $pageDimRatio = 24.0 / 16.0;
    $slotRatio = $slotW && $slotH ? ($slotW / $slotH) * $pageDimRatio : 1;
    $baseX = 1;
    $baseY = 1;
    if ($fit === 'contain') {
        if ($imageRatio > $slotRatio) {
            $baseY = $slotRatio / $imageRatio;
        } else {
            $baseX = $imageRatio / $slotRatio;
        }
    } else {
        if ($imageRatio > $slotRatio) {
            $baseX = $imageRatio / $slotRatio;
        } else {
            $baseY = $slotRatio / $imageRatio;
        }
    }
    return [
        'zoom' => round($zoom, 2),
        'panX' => round($panX, 1),
        'panY' => round($panY, 1),
        'fit' => $fit,
        'baseX' => round($baseX, 2),
        'baseY' => round($baseY, 2),
        'imgRatio' => round($imageRatio, 2),
        'slotRatio' => round($slotRatio, 2),
    ];
}
?>
<div class="gallery-grid" id="galleryGrid" data-cols="2">
    <?php if ($frontPage): ?>
    <?php
    $offset = $bookProperties['pageNumberOffset'] ?? 0;
    $frontType = $frontPage['type'] ?? 'photo';
    $frontPdfPage = $frontPage['pdfPage'] ?? $frontPage['pageNumber'];
    ?>
    <div class="spread-item frontmatter-item" data-spread="0" data-left-page="" data-right-page="<?php echo $frontPage['pageNumber']; ?>" data-right-type="<?php echo h($frontType); ?>">
        <div class="spread-bar">
            <span class="spread-label">p.<?php echo $frontPage['pageNumber'] + $offset; ?></span>
            <?php if ($frontType === 'text'): ?>
                <a href="?page=text-editor&num=<?php echo $frontPage['pageNumber']; ?>" class="spread-text-edit-btn">Texte</a>
            <?php endif; ?>
        </div>
        <div class="single-page-preview">
            <?php if ($frontType === 'text'): ?>
                <canvas class="pdf-thumb-canvas" data-pdf-page="<?php echo $frontPdfPage; ?>"></canvas>
            <?php else: ?>
                <div class="spread-empty-hint"></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php foreach ($spreads as $spread): ?>
    <?php
    $leftPage = $spread['leftPage'];
    $rightPage = $spread['rightPage'];
    $spreadFirstPage = min((int)($leftPage['pageNumber'] ?? PHP_INT_MAX), (int)($rightPage['pageNumber'] ?? PHP_INT_MAX));
    $isIgnoredSpread = $spreadFirstPage > $printablePages;
    $showIgnoredSeparator = $isIgnoredSpread && $spreadFirstPage === $printablePages + 1;
    $leftType = $leftPage['type'] ?? 'photo';
    $rightType = $rightPage['type'] ?? 'text';
    $layout   = $leftType === 'photo' ? ($leftPage['layout'] ?? 'free') : 'free';
    $photos   = $leftType === 'photo' ? ($leftPage['photos'] ?? []) : [];
    $slots_def = $leftPage['slotAssignments'] ?? [];
    $photosById = [];
    foreach ($photos as $p) { if (!empty($p['filename'])) $photosById[$p['id']] = $p; }
    $pageNum = $leftPage['pageNumber'];
    $leftPdfPage = $leftPage['pdfPage'] ?? $leftPage['pageNumber'];
    $pdfPage = $rightPage['pdfPage'] ?? $rightPage['pageNumber'];
    ?>
    <?php if ($showIgnoredSeparator): ?>
        <div class="gallery-print-end">
            <div class="gallery-print-end-line"></div>
            <div class="gallery-print-end-label">Fin du fichier PDF texte</div>
            <div class="gallery-print-end-note">Les planches suivantes restent éditables mais seront ignorées à la génération du PDF final.</div>
        </div>
    <?php endif; ?>
    <div class="spread-item<?php echo $isIgnoredSpread ? ' spread-item--ignored' : ''; ?>" data-spread="<?php echo $spread['spreadNumber']; ?>" data-pdf-page="<?php echo $pdfPage; ?>"
         data-left-page="<?php echo $leftPage['pageNumber']; ?>"
         data-right-page="<?php echo $rightPage['pageNumber']; ?>"
         data-left-type="<?php echo h($leftType); ?>"
         data-right-type="<?php echo h($rightType); ?>">

        <!-- Barre : label à gauche, boutons à droite (toujours visible, boutons au hover) -->
        <div class="spread-bar">
            <?php $offset = $bookProperties['pageNumberOffset'] ?? 0; ?>
            <span class="spread-label">p.<?php echo $leftPage['pageNumber'] + $offset; ?>–<?php echo $spread['rightPage']['pageNumber'] + $offset; ?></span>
            <?php if ($isIgnoredSpread): ?>
                <span class="spread-ignored-badge">Ignorée PDF final</span>
            <?php endif; ?>
            <a href="?page=editor&num=<?php echo $pageNum; ?>" class="spread-edit-btn">Éditer</a>
            <?php if (($spread['rightPage']['type'] ?? 'text') === 'text'): ?>
                <a href="?page=text-editor&num=<?php echo $spread['rightPage']['pageNumber']; ?>" class="spread-text-edit-btn">Texte</a>
            <?php endif; ?>
            <button class="spread-insert-btn" title="Insérer une planche après">+</button>
            <button class="spread-delete-btn" title="Supprimer cette planche">✕</button>
            <button class="spread-toggle-right-btn" title="<?php echo ($spread['rightPage']['type'] ?? 'text') === 'photo' ? 'Page droite → texte' : 'Page droite → photo'; ?>">
                <?php echo ($spread['rightPage']['type'] ?? 'text') === 'photo' ? '📄' : '🖼'; ?>
            </button>
        </div>

        <!-- Double page -->
        <div class="spread-double">

            <!-- Page gauche : photos -->
            <div class="spread-left">
                <?php if ($leftType === 'text'): ?>
                    <canvas class="pdf-thumb-canvas" data-pdf-page="<?php echo $leftPdfPage; ?>"></canvas>
                <?php else: ?>
                <?php if ($layout === 'free'): ?>
                    <?php foreach ($photos as $photo): ?>
                        <?php if (empty($photo['filename'])) continue; ?>
                        <?php $pos = (isset($photo['frame']) && is_array($photo['frame'])) ? $photo['frame'] : ($photo['position'] ?? ['x'=>5,'y'=>5,'w'=>40,'h'=>40]); ?>
                        <?php $radius = galleryFrameRadius($photo); ?>
                        <?php $params = getPhotoParams($photo, $pos['w'], $pos['h']); ?>
                        <div class="g-slot free-slot<?php echo galleryCapContainerClass($photo); ?>" style="left:<?php echo $pos['x']; ?>%;top:<?php echo $pos['y']; ?>%;width:<?php echo $pos['w']; ?>%;height:<?php echo $pos['h']; ?>%;z-index:<?php echo intval($pos['z'] ?? 1); ?>;border-radius:<?php echo $radius; ?>">
                            <div class="g-photo-clip" style="border-radius:<?php echo $radius; ?>">
                                <img src="<?php echo BASE_URL; ?>/uploads/photos/<?php echo h($photo['filename']); ?>" style="<?php echo galleryPhotoStyle($photo); ?>border-radius:<?php echo $radius; ?>" alt=""
                                     data-page="<?php echo $leftPage['pageNumber']; ?>"
                                     data-layout="free"
                                     data-filename="<?php echo h($photo['filename']); ?>"
                                     data-slot-w="<?php echo $pos['w']; ?>"
                                     data-slot-h="<?php echo $pos['h']; ?>"
                                     data-zoom="<?php echo $params['zoom']; ?>" data-pan-x="<?php echo $params['panX']; ?>" data-pan-y="<?php echo $params['panY']; ?>" data-fit="<?php echo h($params['fit']); ?>"
                                     data-base-x="<?php echo $params['baseX']; ?>" data-base-y="<?php echo $params['baseY']; ?>" data-img-ratio="<?php echo $params['imgRatio']; ?>" data-slot-ratio="<?php echo $params['slotRatio']; ?>">
                                <?php echo galleryFrame($photo); ?>
                                <?php if (galleryCapIsInside($photo)) echo galleryCaption($photo); ?>
                            </div>
                            <?php if (!galleryCapIsInside($photo)) echo galleryCaption($photo); ?>
                        </div>
                    <?php endforeach; ?>
                <?php elseif (isset($layouts[$layout])): ?>
                    <?php foreach ($layouts[$layout] as $i => $slot): ?>
                        <?php
                        $photoId = $slots_def[$i] ?? null;
                        $photo   = $photoId ? ($photosById[$photoId] ?? null) : null;
                        ?>
                        <?php $radius = $photo ? galleryFrameRadius($photo) : '0'; ?>
                        <?php $pageSlot = gallerySlotInMargins($slot, $bookProperties); ?>
                        <?php $params = $photo ? getPhotoParams($photo, $slot['w'], $slot['h']) : null; ?>
                        <div class="g-slot<?php echo $photo ? galleryCapContainerClass($photo) : ''; ?>" style="left:<?php echo $pageSlot['x']; ?>%;top:<?php echo $pageSlot['y']; ?>%;width:<?php echo $pageSlot['w']; ?>%;height:<?php echo $pageSlot['h']; ?>%;border-radius:<?php echo $radius; ?>">
                            <?php if ($photo): ?>
                                <div class="g-photo-clip" style="border-radius:<?php echo $radius; ?>">
                                    <img src="<?php echo BASE_URL; ?>/uploads/photos/<?php echo h($photo['filename']); ?>" style="<?php echo galleryPhotoStyle($photo); ?>border-radius:<?php echo $radius; ?>" alt=""
                                         data-page="<?php echo $leftPage['pageNumber']; ?>"
                                         data-layout="<?php echo h($layout); ?>"
                                         data-slot="<?php echo $i; ?>"
                                         data-filename="<?php echo h($photo['filename']); ?>"
                                         data-slot-w="<?php echo round($pageSlot['w'], 1); ?>"
                                         data-slot-h="<?php echo round($pageSlot['h'], 1); ?>"
                                         data-zoom="<?php echo $params['zoom']; ?>" data-pan-x="<?php echo $params['panX']; ?>" data-pan-y="<?php echo $params['panY']; ?>" data-fit="<?php echo h($params['fit']); ?>"
                                         data-base-x="<?php echo $params['baseX']; ?>" data-base-y="<?php echo $params['baseY']; ?>" data-img-ratio="<?php echo $params['imgRatio']; ?>" data-slot-ratio="<?php echo $params['slotRatio']; ?>">
                                    <?php echo galleryFrame($photo); ?>
                                    <?php if (galleryCapIsInside($photo)) echo galleryCaption($photo); ?>
                                </div>
                                <?php if (!galleryCapIsInside($photo)) echo galleryCaption($photo); ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (empty(array_filter($photos, fn($p) => !empty($p['filename'])))): ?>
                    <div class="spread-empty-hint">Aucune photo</div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Page droite : texte PDF ou photo -->
            <div class="spread-right">
                <?php if ($rightType === 'photo'): ?>
                    <?php
                    $rPhotos = $rightPage['photos'] ?? [];
                    $rLayout = $rightPage['layout'] ?? 'pleine-page';
                    $rSlots  = $rightPage['slotAssignments'] ?? [];
                    $rById   = [];
                    foreach ($rPhotos as $p) { if (!empty($p['filename'])) $rById[$p['id']] = $p; }
                    ?>
                    <div class="spread-right-photo-badge">Photo</div>
                    <?php if (isset($layouts[$rLayout])): ?>
                        <?php foreach ($layouts[$rLayout] as $i => $slot): ?>
                            <?php $photo = ($rSlots[$i] ?? null) ? ($rById[$rSlots[$i]] ?? null) : null; ?>
                            <?php $pageSlot = gallerySlotInMargins($slot, $bookProperties); ?>
                            <div class="g-slot" style="left:<?php echo $pageSlot['x']; ?>%;top:<?php echo $pageSlot['y']; ?>%;width:<?php echo $pageSlot['w']; ?>%;height:<?php echo $pageSlot['h']; ?>%">
                                <?php if ($photo): ?>
                                    <div class="g-photo-clip">
                                        <img src="<?php echo BASE_URL; ?>/uploads/photos/<?php echo h($photo['filename']); ?>" style="<?php echo galleryPhotoStyle($photo); ?>" alt="">
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (empty(array_filter($rPhotos, fn($p) => !empty($p['filename'])))): ?>
                        <div class="spread-empty-hint">Aucune photo</div>
                    <?php endif; ?>
                <?php else: ?>
                    <canvas class="pdf-thumb-canvas" data-pdf-page="<?php echo $pdfPage; ?>"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const grid   = document.getElementById('galleryGrid');
    const slider = document.getElementById('zoomSlider');
    const hint   = document.getElementById('zoomHint');
    const propsModal = document.getElementById('bookPropsModal');
    const STORE_ZOOM   = 'gallery_zoom';
    const STORE_SCROLL = 'gallery_scroll';
    const STORE_FOCUS  = 'gallery_focus_spread';

    const labels = {
        '1': '1 planche',
        '2': '2 planches',
        '3': '3 planches',
        '4': '4 planches',
        '5': '5 planches',
    };

    // Restaurer zoom sauvegardé
    const savedZoom = labels[sessionStorage.getItem(STORE_ZOOM)] ? sessionStorage.getItem(STORE_ZOOM) : '2';
    slider.value = savedZoom;

    /**
     * Trouver l'élément .spread-item le plus proche du coin haut-gauche visible
     */
    function getTopLeftSpread() {
        const toolbarH = document.getElementById('galleryToolbar').offsetHeight;
        const items = document.querySelectorAll('.spread-item');
        for (const item of items) {
            const rect = item.getBoundingClientRect();
            if (rect.bottom > toolbarH) return item;
        }
        return items[0] || null;
    }

    function applyZoom(val, anchor) {
        if (!anchor) anchor = getTopLeftSpread();

        grid.dataset.cols = val;
        hint.textContent  = labels[val];
        sessionStorage.setItem(STORE_ZOOM, val);

        // Après reflow : repositionner l'ancre + recalculer les canvas déjà rendus
        if (anchor) {
            requestAnimationFrame(() => {
                const toolbarH = document.querySelector('.header-zoom-control')?.offsetHeight || 0;
                const rect = anchor.getBoundingClientRect();
                window.scrollBy({ top: rect.top - toolbarH - 8, behavior: 'instant' });
                rerenderLoadedCanvases();
            });
        } else {
            requestAnimationFrame(rerenderLoadedCanvases);
        }
    }

    /**
     * Re-render les canvas PDF déjà chargés à la nouvelle taille du conteneur.
     */
    function rerenderLoadedCanvases() {
        if (typeof pdfjsLib === 'undefined') return;
        document.querySelectorAll('.pdf-thumb-canvas[data-loaded]').forEach(canvas => {
            const container = canvas.parentElement;
            const w = container.clientWidth;
            if (!w) return;
            const pageNum = parseInt(canvas.dataset.pdfPage);
            PdfViewer._getDoc().then(pdf =>
                pdf.getPage(pageNum).then(page => {
                    const vp = page.getViewport({ scale: 1 });
                    const scale = w / vp.width;
                    const scaled = page.getViewport({ scale });
                    canvas.width  = scaled.width;
                    canvas.height = scaled.height;
                    page.render({ canvasContext: canvas.getContext('2d'), viewport: scaled });
                })
            ).catch(() => {});
        });
    }

    slider.addEventListener('input', () => {
        const anchor = getTopLeftSpread();
        applyZoom(slider.value, anchor);
    });

    function closeBookProps() {
        propsModal?.classList.add('hidden');
    }

    document.getElementById('bookPropsClose')?.addEventListener('click', closeBookProps);
    document.getElementById('bookPropsCancel')?.addEventListener('click', closeBookProps);
    propsModal?.addEventListener('click', e => {
        if (e.target === propsModal) closeBookProps();
    });
    document.getElementById('bookPropsSave')?.addEventListener('click', async () => {
        const payload = {
            action: 'saveProperties',
            properties: {
                bindingCm: parseFloat(document.getElementById('bindingCm').value) || 0,
                pageNumberOffset: parseInt(document.getElementById('pageNumberOffset').value) || 0,
                photoPageMargins: {
                    topCm: parseFloat(document.getElementById('marginTopCm').value) || 0,
                    rightCm: parseFloat(document.getElementById('marginRightCm').value) || 0,
                    bottomCm: parseFloat(document.getElementById('marginBottomCm').value) || 0,
                    leftCm: parseFloat(document.getElementById('marginLeftCm').value) || 0,
                },
                textBindingCm: parseFloat(document.getElementById('textBindingCm').value) || 0,
                textPageMargins: {
                    topCm: parseFloat(document.getElementById('textMarginTopCm').value) || 0,
                    rightCm: parseFloat(document.getElementById('textMarginRightCm').value) || 0,
                    bottomCm: parseFloat(document.getElementById('textMarginBottomCm').value) || 0,
                    leftCm: parseFloat(document.getElementById('textMarginLeftCm').value) || 0,
                },
                defaultLayout: document.getElementById('defaultLayout').value,
            }
        };
        try {
            await App.api('book.php', payload);
            closeBookProps();
            window.location.reload();
        } catch (e) {
            alert('Erreur: ' + e.message);
        }
    });

    // Vérifier si on revient de l'éditeur (focus sur une planche)
    const focusSpread = sessionStorage.getItem(STORE_FOCUS);
    sessionStorage.removeItem(STORE_FOCUS);

    if (focusSpread) {
        // Retour éditeur : appliquer le zoom (déjà mis à '1' par l'éditeur) puis scroller sur la bonne planche
        applyZoom(savedZoom, null);
        requestAnimationFrame(() => {
            const target = document.querySelector(`.spread-item[data-spread="${focusSpread}"]`);
            if (target) {
                const toolbarH = document.querySelector('.header-zoom-control')?.offsetHeight || 0;
                const rect = target.getBoundingClientRect();
                window.scrollBy({ top: rect.top - toolbarH - 16, behavior: 'instant' });
                // Flash bref pour repérer la planche
                target.classList.add('spread-focus');
                setTimeout(() => target.classList.remove('spread-focus'), 1500);
            }
        });
    } else {
        // Navigation normale : restaurer zoom + scroll
        applyZoom(savedZoom, null);
        const savedScroll = sessionStorage.getItem(STORE_SCROLL);
        if (savedScroll) {
            requestAnimationFrame(() => window.scrollTo({ top: parseInt(savedScroll), behavior: 'instant' }));
        }
    }

    // Sauvegarder scroll en quittant
    window.addEventListener('beforeunload', () => {
        sessionStorage.setItem(STORE_SCROLL, window.scrollY);
    });
    document.querySelectorAll('.spread-edit-btn, .spread-text-edit-btn').forEach(a => {
        a.addEventListener('click', () => {
            sessionStorage.setItem(STORE_SCROLL, window.scrollY);
        });
    });
    document.querySelectorAll('.spread-item').forEach(item => {
        item.addEventListener('dblclick', e => {
            if (e.target.closest('a, button, input, textarea, select')) return;
            const editLink = item.dataset.rightType === 'text'
                ? item.querySelector('.spread-text-edit-btn')
                : item.querySelector('.spread-edit-btn');
            if (!editLink) return;
            sessionStorage.setItem(STORE_SCROLL, window.scrollY);
            window.location = editLink.href;
        });
    });

    // Lazy-load PDF pages via IntersectionObserver
    if (typeof pdfjsLib === 'undefined') return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            const canvas = entry.target;
            if (canvas.dataset.loaded) return;
            canvas.dataset.loaded = '1';
            observer.unobserve(canvas);

            const pageNum = parseInt(canvas.dataset.pdfPage);
            PdfViewer._getDoc().then(pdf => {
                pdf.getPage(pageNum).then(page => {
                    const container = canvas.parentElement;
                    const w = container.clientWidth || 200;
                    const vp = page.getViewport({ scale: 1 });
                    const scale = w / vp.width;
                    const scaled = page.getViewport({ scale });
                    canvas.width  = scaled.width;
                    canvas.height = scaled.height;
                    page.render({ canvasContext: canvas.getContext('2d'), viewport: scaled });
                });
            }).catch(() => {});
        });
    }, { rootMargin: '200px' });

    document.querySelectorAll('.pdf-thumb-canvas').forEach(c => observer.observe(c));

    // Display photo params overlay
    const imgs = document.querySelectorAll('.g-photo-clip img');
    console.log('Found gallery images:', imgs.length);
    imgs.forEach((img, i) => {
        console.log(`Image ${i}:`, img.dataset.zoom, img.dataset.panX, img.dataset.panY);
        if (!img.dataset.zoom) return;
        const overlay = document.createElement('div');
        overlay.className = 'g-photo-params';
        const displayText = `zoom: ${img.dataset.zoom} | panX: ${img.dataset.panX} | panY: ${img.dataset.panY} | fit: ${img.dataset.fit} | baseX: ${img.dataset.baseX} | baseY: ${img.dataset.baseY} | imgRatio: ${img.dataset.imgRatio} | slotRatio: ${img.dataset.slotRatio}`;
        const copyText = `[gallery page ${img.dataset.page}]
Fichier: ${img.dataset.filename}
Layout: ${img.dataset.layout} (slot ${img.dataset.slot})
Dimensions slot: w:${img.dataset.slotW}% h:${img.dataset.slotH}%
zoom: ${img.dataset.zoom}
panX: ${img.dataset.panX}
panY: ${img.dataset.panY}
fit: ${img.dataset.fit}
baseX: ${img.dataset.baseX}
baseY: ${img.dataset.baseY}
imgRatio: ${img.dataset.imgRatio}
slotRatio: ${img.dataset.slotRatio}`;
        overlay.innerHTML = displayText;
        overlay.style.cursor = 'pointer';
        overlay.addEventListener('click', (e) => {
            e.stopPropagation();
            console.log('Clicked overlay, copying:', copyText);
            navigator.clipboard.writeText(copyText).then(() => {
                console.log('Successfully copied!');
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
            const origBg = overlay.style.backgroundColor;
            overlay.style.backgroundColor = 'rgba(0, 255, 0, 0.5)';
            setTimeout(() => {
                overlay.style.backgroundColor = origBg;
            }, 300);
        });
        const clip = img.parentElement;
        if (clip) {
            clip.appendChild(overlay);
            console.log('Added overlay to gallery image', i);
        }
    });

    // --- Actions planche ---

    document.getElementById('galleryGrid').addEventListener('click', async e => {
        const item = e.target.closest('.spread-item');
        if (!item) return;
        const spread = parseInt(item.dataset.spread);

        if (e.target.closest('.spread-insert-btn')) {
            if (!confirm(`Insérer une planche vide après la planche ${spread} ?`)) return;
            await App.api('pages.php', { action: 'insertSpread', spread, page: 0 });
            window.location.reload();
            return;
        }

        if (e.target.closest('.spread-delete-btn')) {
            if (!confirm(`Supprimer la planche ${spread} ?`)) return;
            const data = await App.api('pages.php', { action: 'deleteSpread', spread, page: 0 });
            if (!data.success && data.hasPhotos) {
                if (!confirm('Cette planche contient des photos. Supprimer quand même ?')) return;
                await App.api('pages.php', { action: 'deleteSpread', spread, page: 0, force: true });
            }
            window.location.reload();
            return;
        }

        if (e.target.closest('.spread-toggle-right-btn')) {
            const rightPage = parseInt(item.dataset.rightPage);
            const currentType = item.dataset.rightType;
            const newType = currentType === 'photo' ? 'text' : 'photo';
            const label = newType === 'photo' ? 'photo' : 'texte';
            if (!confirm(`Passer la page droite ${rightPage} en mode ${label} ?`)) return;
            await App.api('pages.php', { action: 'setPageType', page: rightPage, pageType: newType });
            window.location.reload();
        }
    });
});
</script>

<style>
.gallery-toolbar {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(245, 245, 245, 0.95);
    backdrop-filter: blur(4px);
    margin: -2rem -2rem 1.5rem -2rem;
    border-bottom: 1px solid #e0e0e0;
}
.zoom-label { font-size: 0.85rem; color: #666; }
.zoom-slider { width: 100px; accent-color: #007bff; cursor: pointer; }
.zoom-hint   { font-size: 0.8rem; color: #999; min-width: 80px; }
.book-props-btn {
    margin-left: auto;
    border: 1px solid #ccc;
    background: white;
    border-radius: 4px;
    padding: 0.35rem 0.7rem;
    cursor: pointer;
}
.book-props-btn:hover { border-color: #007bff; color: #007bff; }

.book-props-modal {
    position: fixed;
    inset: 0;
    z-index: 1000;
    background: rgba(0,0,0,0.45);
    display: flex;
    align-items: center;
    justify-content: center;
}
.book-props-modal.hidden { display: none; }
.book-props-box {
    width: min(92vw, 420px);
    background: white;
    border-radius: 6px;
    box-shadow: 0 18px 50px rgba(0,0,0,0.3);
}
.book-props-header, .book-props-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.9rem 1rem;
    border-bottom: 1px solid #e5e5e5;
}
.book-props-footer { border-top: 1px solid #e5e5e5; border-bottom: 0; justify-content: flex-end; gap: 0.5rem; }
.book-props-close { border: 0; background: transparent; font-size: 1.4rem; cursor: pointer; }
.book-props-body { display: grid; gap: 0.75rem; padding: 1rem; }
.book-props-note { font-size: 0.85rem; color: #666; }
.book-props-section-title {
    margin-top: 0.35rem;
    padding-top: 0.65rem;
    border-top: 1px solid #eee;
    font-size: 0.78rem;
    color: #777;
    font-weight: 700;
    text-transform: uppercase;
}
.book-props-body label { display: flex; justify-content: space-between; align-items: center; gap: 1rem; font-size: 0.9rem; }
.book-props-body input { width: 90px; padding: 0.35rem; border: 1px solid #ccc; border-radius: 4px; }
.book-props-cancel, .book-props-save { border: 1px solid #ccc; border-radius: 4px; padding: 0.45rem 0.8rem; cursor: pointer; }
.book-props-save { background: #007bff; color: white; border-color: #007bff; }

.gallery-grid {
    display: grid;
    gap: 2rem;
    align-items: start;
    --gallery-caption-scale: 1;
}
.gallery-grid[data-cols="1"] { grid-template-columns: 1fr; --gallery-caption-scale: 1; }
.gallery-grid[data-cols="2"] { grid-template-columns: 1fr 1fr; --gallery-caption-scale: 0.5; }
.gallery-grid[data-cols="3"] { grid-template-columns: 1fr 1fr 1fr; --gallery-caption-scale: 0.333; }
.gallery-grid[data-cols="4"] { grid-template-columns: repeat(4, 1fr); --gallery-caption-scale: 0.25; }
.gallery-grid[data-cols="5"] { grid-template-columns: repeat(5, 1fr); --gallery-caption-scale: 0.2; }

.gallery-print-end {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 0.75rem;
    color: #5f6368;
    margin: 0.25rem 0 -0.5rem;
}
.gallery-print-end-line {
    grid-column: 1 / -1;
    height: 1px;
    background: #c8ccd0;
}
.gallery-print-end-label {
    grid-column: 1 / -1;
    justify-self: center;
    margin-top: -0.75rem;
    padding: 0.2rem 0.65rem;
    background: #f5f6f7;
    border: 1px solid #c8ccd0;
    border-radius: 4px;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
}
.gallery-print-end-note {
    grid-column: 1 / -1;
    justify-self: center;
    font-size: 0.82rem;
}

/* Chaque planche */
.spread-item {
    display: flex;
    flex-direction: column;
    gap: 0;
    cursor: pointer;
}
.spread-item--ignored {
    opacity: 0.72;
}
.spread-item--ignored .spread-double {
    outline: 2px dashed #b6bcc2;
    outline-offset: 3px;
}
.spread-ignored-badge {
    font-size: 0.68rem;
    padding: 2px 6px;
    background: #6c757d;
    color: white;
    border-radius: 3px;
    white-space: nowrap;
}

/* Barre : toujours visible, boutons apparaissent au hover */
.spread-bar {
    display: flex;
    align-items: center;
    padding: 2px 4px;
    gap: 3px;
    min-height: 22px;
    margin-bottom: 1px;
}
.spread-label {
    flex: 1;
    font-size: 0.7rem;
    color: #bbb;
    font-weight: 600;
    line-height: 1;
    white-space: nowrap;
}
.spread-item:hover .spread-label { color: #888; }
.spread-edit-btn, .spread-text-edit-btn, .spread-insert-btn, .spread-delete-btn, .spread-toggle-right-btn {
    opacity: 0;
    transition: opacity 0.15s;
}
.spread-item:hover .spread-edit-btn,
.spread-item:hover .spread-text-edit-btn,
.spread-item:hover .spread-insert-btn,
.spread-item:hover .spread-delete-btn,
.spread-item:hover .spread-toggle-right-btn { opacity: 1; }

.spread-edit-btn, .spread-text-edit-btn {
    font-size: 0.75rem;
    padding: 2px 8px;
    background: #007bff;
    color: white;
    border-radius: 3px;
    text-decoration: none;
    white-space: nowrap;
}
.spread-edit-btn:hover { background: #0056b3; text-decoration: none; color: white; }
.spread-text-edit-btn { background: #6f42c1; }
.spread-text-edit-btn:hover { background: #56339a; text-decoration: none; color: white; }

.spread-insert-btn, .spread-delete-btn, .spread-toggle-right-btn {
    font-size: 0.75rem;
    padding: 2px 6px;
    border-radius: 3px;
    border: none;
    cursor: pointer;
}
.spread-insert-btn { background: #28a745; color: white; }
.spread-insert-btn:hover { background: #1e7e34; }
.spread-delete-btn { background: #dc3545; color: white; }
.spread-delete-btn:hover { background: #a71d2a; }
.spread-toggle-right-btn { background: #6c757d; color: white; }
.spread-toggle-right-btn:hover { background: #495057; }

.spread-right-photo-badge {
    position: absolute;
    top: 4px; left: 4px;
    background: rgba(0,123,255,0.8);
    color: white;
    font-size: 0.6rem;
    padding: 1px 4px;
    border-radius: 2px;
    z-index: 2;
}

/* Double page */
.spread-double {
    display: flex;
    width: 100%;
    aspect-ratio: 48 / 16; /* deux pages 24×16 côte à côte */
    border-radius: 0 0 3px 3px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.18);
}

.single-page-preview {
    width: 50%;
    margin-left: auto;
    aspect-ratio: 24 / 16;
    background: #f9f8f6;
    border-radius: 0 0 3px 3px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.18);
}

.frontmatter-item .spread-label {
    color: #777;
}

.spread-left, .spread-right {
    flex: 1;
    position: relative;
    overflow: hidden;
}

.spread-left  { background: #fff; }
.spread-right { background: #f9f8f6; }

/* Séparation centrale fine */
.spread-left { border-right: 1px solid #ddd; }

/* Slots de photos */
.g-slot {
    position: absolute;
    overflow: visible;
    box-sizing: border-box;
    background: transparent;
}
.g-slot.free-slot { box-shadow: 0 0 0 2px white; }
.g-photo-clip { position: absolute; inset: 0; overflow: hidden; background: white; }
.g-slot img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
}
.g-slot-frame { position: absolute; inset: 0; pointer-events: none; z-index: 1; }

.g-caption {
    position: absolute;
    left: 0; right: 0;
    top: 100%; bottom: auto;
    margin-top: 2px;
    z-index: 2;
    padding: max(1px, calc(2px * var(--gallery-caption-scale))) max(1px, calc(5px * var(--gallery-caption-scale)));
    font-size: max(4px, calc(var(--caption-size, 11px) * var(--gallery-caption-scale)));
    line-height: 1.2;
    white-space: pre-line;
    overflow: hidden;
    max-height: 45%;
}
.g-caption.cap-inside-bottom { top: auto; bottom: 0; margin: 0; }
.g-caption.cap-inside-top    { top: 0; bottom: auto; margin: 0; }
.g-slot.cap-out-below, .g-slot.cap-out-above { display: flex !important; }
.g-slot.cap-out-below { flex-direction: column; }
.g-slot.cap-out-above { flex-direction: column-reverse; }
.g-slot.cap-out-below .g-photo-clip,
.g-slot.cap-out-above .g-photo-clip {
    position: relative !important;
    inset: auto !important;
    flex: 1; width: 100%; min-height: 0;
}
.g-slot.cap-out-below .g-caption,
.g-slot.cap-out-above .g-caption {
    position: relative !important;
    flex-shrink: 0;
    top: auto !important; bottom: auto !important; margin: 0 !important;
}

.spread-empty-hint {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    color: #ccc;
}

/* Canvas PDF */
.pdf-thumb-canvas {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}

/* Flash au retour de l'éditeur */
@keyframes spreadFlash {
    0%   { box-shadow: 0 0 0 3px #007bff; }
    60%  { box-shadow: 0 0 0 3px #007bff; }
    100% { box-shadow: none; }
}
.spread-focus .spread-double {
    animation: spreadFlash 1.5s ease-out forwards;
}

.g-photo-params {
    position: absolute;
    top: 2px;
    left: 2px;
    background: rgba(0, 0, 0, 0.9);
    color: #0f0;
    font-size: 9px;
    font-family: monospace;
    padding: 3px 5px;
    border-radius: 2px;
    pointer-events: none;
    z-index: 20;
    line-height: 1.2;
    overflow: visible;
    border: 1px solid #0f0;
}
</style>
