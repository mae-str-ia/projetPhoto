<?php
$pageTitle = 'Vue page';
$book = BookManager::load();
$bookProperties = $book['properties'] ?? BookManager::defaultProperties();

// Get page number from parameter
$pageNum = isset($_GET['num']) ? intval($_GET['num']) : 1;
$pageData = BookManager::getPage($pageNum);

if (!$pageData) {
    http_response_code(404);
    echo "Page not found: $pageNum";
    exit;
}

// Get page side (left or right) - will be used for margins
$pageSide = $pageData['side'] ?? 'right';
$margins = ($pageSide === 'left')
    ? ($bookProperties['leftPageMargins'] ?? ['topCm' => 1, 'rightCm' => 3, 'bottomCm' => 1, 'leftCm' => 1])
    : ($bookProperties['photoPageMargins'] ?? ['topCm' => 1, 'rightCm' => 1, 'bottomCm' => 1, 'leftCm' => 1]);

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

function viewPhotoStyle($photo, $slotW, $slotH) {
    $crop = (isset($photo['crop']) && is_array($photo['crop'])) ? $photo['crop'] : [];
    $fit = $crop['fitMode'] ?? $photo['fit'] ?? 'cover';
    $zoom = floatval($crop['zoom'] ?? $photo['zoom'] ?? 1);
    $panX = floatval($crop['panX'] ?? $photo['panX'] ?? 0);
    $panY = floatval($crop['panY'] ?? $photo['panY'] ?? 0);

    // Calculer baseX et baseY comme l'éditeur
    $imageRatio = ($photo['width'] && $photo['height']) ? floatval($photo['width']) / floatval($photo['height']) : 1;
    $slotRatio = $slotW / $slotH;

    $baseX = 1;
    $baseY = 1;

    if ($fit === 'contain') {
        if ($imageRatio > $slotRatio) {
            $baseY = $slotRatio / $imageRatio;
        } else {
            $baseX = $imageRatio / $slotRatio;
        }
    } else { // 'cover'
        if ($imageRatio > $slotRatio) {
            $baseX = $imageRatio / $slotRatio;
        } else {
            $baseY = $slotRatio / $imageRatio;
        }
    }

    $br  = intval($photo['brightness'] ?? 100);
    $ct  = intval($photo['contrast']   ?? 100);

    $artFilters = ['bw'=>'grayscale(100%)','sepia'=>'sepia(100%)','vintage'=>'sepia(50%) contrast(0.9) brightness(1.1) saturate(0.8)'];
    $art = $artFilters[$photo['filter'] ?? ''] ?? '';

    $base = '';
    if ($br !== 100) $base .= "brightness({$br}%) ";
    if ($ct !== 100) $base .= "contrast({$ct}%) ";
    $filter = trim($base . $art);

    $width = $baseX * 100;
    $height = $baseY * 100;
    $style = "position:absolute;left:calc(50% + {$panX}%);top:calc(50% + {$panY}%);width:{$width}%;height:{$height}%;object-fit:" . h($fit) . ";transform-origin:center center;transform:translate(-50%, -50%) scale({$zoom});";
    if ($filter) $style .= "filter:{$filter};";
    return $style;
}

function viewFrameRadius($photo) {
    // Scale border-radius from 10px to proportional size for 2835px capture
    // Editor displays at ~900px, capture is at 2835px, so scale factor = 2835/900 = 3.15
    $scaleFactor = 2835 / 900;

    $frame = (isset($photo['frame']) && is_array($photo['frame'])) ? $photo['frame'] : [];
    $shape = $frame['shape'] ?? 'rect';
    if ($shape === 'ellipse') return '50%';
    if ($shape === 'rounded') return (10 * $scaleFactor) . 'px';
    return '0';
}

function viewCaption($photo) {
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
    // Scale for 2835px capture (2835/900 = 3.15 scale factor)
    $scaledSize = (int)($size * 3.15);
    $captionHtml = str_replace("\n", '<br>', h($photo['caption']));
    return '<div class="v-caption cap-' . h($pos) . '" style="color:' . $color . ';background:' . $bg . ';font-size:' . $scaledSize . 'px;text-align:' . $align . '">' . $captionHtml . '</div>';
}

function viewCapContainerClass($photo) {
    if (trim($photo['caption'] ?? '') === '') return '';
    $pos = $photo['captionPos'] ?? 'below';
    if ($pos === 'below') return ' cap-out-below';
    if ($pos === 'above') return ' cap-out-above';
    return '';
}

function viewCapIsInside($photo) {
    $pos = $photo['captionPos'] ?? 'below';
    return $pos === 'inside-bottom' || $pos === 'inside-top';
}

function viewSlotInMargins($slot, $properties) {
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

$pageType = $pageData['type'] ?? 'photo';
$pdfPage = $pageData['pdfPage'] ?? $pageNum;
?>

<div class="view-container" id="viewContainer">
    <?php if ($pageType === 'text'): ?>
        <canvas class="view-pdf-canvas" data-pdf-page="<?php echo $pdfPage; ?>"></canvas>
    <?php else: ?>
        <div class="view-page-content">
            <?php
            $photos = $pageData['photos'] ?? [];
            $layout = $pageData['layout'] ?? 'pleine-page';
            $slotAssignments = $pageData['slotAssignments'] ?? [];
            $photosById = [];
            foreach ($photos as $p) { if (!empty($p['filename'])) $photosById[$p['id']] = $p; }
            ?>
            <?php if ($layout === 'free'): ?>
                <?php foreach ($photos as $photo): ?>
                    <?php if (empty($photo['filename'])) continue; ?>
                    <?php $pos = (isset($photo['frame']) && is_array($photo['frame'])) ? $photo['frame'] : ($photo['position'] ?? ['x'=>5,'y'=>5,'w'=>40,'h'=>40]); ?>
                    <?php $radius = viewFrameRadius($photo); ?>
                    <?php $params = getPhotoParams($photo, $pos['w'], $pos['h']); ?>
                    <div class="v-slot free-slot<?php echo viewCapContainerClass($photo); ?>" style="left:<?php echo $pos['x']; ?>%;top:<?php echo $pos['y']; ?>%;width:<?php echo $pos['w']; ?>%;height:<?php echo $pos['h']; ?>%;z-index:<?php echo intval($pos['z'] ?? 1); ?>;border-radius:<?php echo $radius; ?>">
                        <div class="v-photo-clip" style="border-radius:<?php echo $radius; ?>">
                            <img src="<?php echo BASE_URL; ?>/photo.php?file=<?php echo urlencode($photo['filename']); ?>" style="<?php echo viewPhotoStyle($photo, $pos['w'], $pos['h']); ?>border-radius:<?php echo $radius; ?>" alt=""
                                 data-page="<?php echo $pageNum; ?>"
                                 data-layout="free"
                                 data-filename="<?php echo h($photo['filename']); ?>"
                                 data-slot-w="<?php echo $pos['w']; ?>"
                                 data-slot-h="<?php echo $pos['h']; ?>"
                                 data-zoom="<?php echo $params['zoom']; ?>" data-pan-x="<?php echo $params['panX']; ?>" data-pan-y="<?php echo $params['panY']; ?>" data-fit="<?php echo h($params['fit']); ?>"
                                 data-base-x="<?php echo $params['baseX']; ?>" data-base-y="<?php echo $params['baseY']; ?>" data-img-ratio="<?php echo $params['imgRatio']; ?>" data-slot-ratio="<?php echo $params['slotRatio']; ?>">
                            <?php if (viewCapIsInside($photo)) echo viewCaption($photo); ?>
                        </div>
                        <?php if (!viewCapIsInside($photo)) echo viewCaption($photo); ?>
                    </div>
                <?php endforeach; ?>
            <?php elseif (isset($layouts[$layout])): ?>
                <?php $slotCount = 0; foreach ($layouts[$layout] as $i => $slot): $slotCount++; ?>
                    <?php
                    $photoId = $slotAssignments[$i] ?? null;
                    $photo   = $photoId ? ($photosById[$photoId] ?? null) : null;
                    ?>
                    <?php $radius = $photo ? viewFrameRadius($photo) : '0'; ?>
                    <?php $pageSlot = viewSlotInMargins($slot, $bookProperties); ?>
                    <?php $params = $photo ? getPhotoParams($photo, $pageSlot['w'], $pageSlot['h']) : null; ?>
                    <div class="v-slot<?php echo $photo ? viewCapContainerClass($photo) : ''; ?>" style="left:<?php echo $pageSlot['x']; ?>%;top:<?php echo $pageSlot['y']; ?>%;width:<?php echo $pageSlot['w']; ?>%;height:<?php echo $pageSlot['h']; ?>%;border-radius:<?php echo $radius; ?>">
                        <?php if ($photo): ?>
                            <div class="v-photo-clip" style="border-radius:<?php echo $radius; ?>">
                                <img src="<?php echo BASE_URL; ?>/photo.php?file=<?php echo urlencode($photo['filename']); ?>" style="<?php echo viewPhotoStyle($photo, $slot['w'], $slot['h']); ?>border-radius:<?php echo $radius; ?>" alt=""
                                     data-page="<?php echo $pageNum; ?>"
                                     data-layout="<?php echo h($layout); ?>"
                                     data-slot="<?php echo $i; ?>"
                                     data-filename="<?php echo h($photo['filename']); ?>"
                                     data-slot-w="<?php echo round($pageSlot['w'], 1); ?>"
                                     data-slot-h="<?php echo round($pageSlot['h'], 1); ?>"
                                     data-zoom="<?php echo $params['zoom']; ?>" data-pan-x="<?php echo $params['panX']; ?>" data-pan-y="<?php echo $params['panY']; ?>" data-fit="<?php echo h($params['fit']); ?>"
                                     data-base-x="<?php echo $params['baseX']; ?>" data-base-y="<?php echo $params['baseY']; ?>" data-img-ratio="<?php echo $params['imgRatio']; ?>" data-slot-ratio="<?php echo $params['slotRatio']; ?>">
                                <?php if (viewCapIsInside($photo)) echo viewCaption($photo); ?>
                            </div>
                            <?php if (!viewCapIsInside($photo)) echo viewCaption($photo); ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('viewContainer');

    // Handle PDF pages if present
    const canvas = container.querySelector('.view-pdf-canvas');
    if (canvas && typeof pdfjsLib !== 'undefined') {
        const pageNum = parseInt(canvas.dataset.pdfPage);
        PdfViewer._getDoc().then(pdf => {
            pdf.getPage(pageNum).then(page => {
                const w = container.clientWidth || 1200;
                const vp = page.getViewport({ scale: 1 });
                const scale = w / vp.width;
                const scaled = page.getViewport({ scale });
                canvas.width  = scaled.width;
                canvas.height = scaled.height;
                page.render({ canvasContext: canvas.getContext('2d'), viewport: scaled });
            });
        }).catch(err => {
            console.error('Error loading PDF:', err);
        });
    }

    // Display photo params overlay (disabled for PDF capture)
    // Uncomment to enable overlays for debugging
    /*
    const imgs = document.querySelectorAll('.v-photo-clip img');
    console.log('Found images:', imgs.length);
    imgs.forEach((img, i) => {
        console.log(`Image ${i}:`, img.dataset.zoom, img.dataset.panX, img.dataset.panY);
        if (!img.dataset.zoom) return;
        const overlay = document.createElement('div');
        overlay.className = 'v-photo-params';
        const displayText = `zoom: ${img.dataset.zoom} | panX: ${img.dataset.panX} | panY: ${img.dataset.panY} | fit: ${img.dataset.fit} | baseX: ${img.dataset.baseX} | baseY: ${img.dataset.baseY} | imgRatio: ${img.dataset.imgRatio} | slotRatio: ${img.dataset.slotRatio}`;
        const copyText = `[view page ${img.dataset.page}]
Fichier: ${img.dataset.filename}
Layout: ${img.dataset.layout}${img.dataset.slot ? ' (slot ' + img.dataset.slot + ')' : ''}
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
        overlay.title = 'Click to copy';
        overlay.style.pointerEvents = 'auto';
        console.log('Overlay created:', overlay);
        const clickHandler = (e) => {
            console.log('Click event fired!');
            e.stopPropagation();
            e.preventDefault();
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
        };
        overlay.addEventListener('click', clickHandler);
        const clip = img.parentElement;
        if (clip) {
            clip.appendChild(overlay);
            console.log('Added overlay to image', i, 'overlay element:', overlay);
        }
    });
    */

    // Debug: log container dimensions
    const viewPageWidth = container.clientWidth;
    console.log('view.php container width:', viewPageWidth, 'px');
    console.log('Capture size: 2835px, Editor reference size: 900px');

    // Apply border-radius scaling to slot elements
    setTimeout(() => {
        const slots = container.querySelectorAll('[style*="border-radius"]');
        slots.forEach(el => {
            if (el.style.borderRadius === '10px') {
                el.style.borderRadius = (10 * scaleFactor) + 'px';
            }
        });
    }, 100);

    console.log(`Scale factor: ${scaleFactor.toFixed(4)} (view: ${viewPageWidth}px, editor: ${editorPageWidth}px)`);
});
</script>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

/* Hide and remove layout elements for clean PDF capture */
header, nav, footer, .navbar, .topbar, [class*="header"], [class*="toolbar"], [class*="footer"] {
    display: none !important;
    height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
}

html, body {
    width: 100%;
    height: 100%;
    background: white;
    margin: 0;
    padding: 0;
}

.app-main {
    padding: 0 !important;
    margin: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    max-width: 100% !important;
}

#viewContainer {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden;
    background: white;
    border: none;
    display: block;
    box-sizing: border-box;
}

.view-page-content {
    position: relative;
    width: 100%;
    height: 100%;
    background: white;
}

.view-pdf-canvas {
    max-width: 100vw;
    max-height: 100vh;
    display: block;
}

.v-slot {
    position: absolute;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.v-photo-clip {
    width: 100%;
    height: 100%;
    position: relative;
}

.v-photo-clip img {
    width: 100%;
    height: 100%;
    display: block;
}

.v-slot.free-slot {
    position: absolute;
}

.v-caption {
    line-height: 1.3;
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
}

.v-caption.cap-below {
    padding: 4px 2px 0 2px;
}

.v-caption.cap-above {
    padding: 0 2px 4px 2px;
}

.v-caption.cap-inside-bottom {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
    padding: 4px;
}

.v-caption.cap-inside-top {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    width: 100%;
    padding: 4px;
}

.v-slot.cap-out-below {
    flex-direction: column;
    align-items: stretch;
}

.v-slot.cap-out-below .v-photo-clip {
    flex: 1;
}

.v-slot.cap-out-below .v-caption {
    flex-shrink: 0;
}

.v-slot.cap-out-above {
    flex-direction: column-reverse;
    align-items: stretch;
}

.v-slot.cap-out-above .v-photo-clip {
    flex: 1;
}

.v-slot.cap-out-above .v-caption {
    flex-shrink: 0;
}

.view-empty-hint {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
    font-size: 18px;
}

.v-photo-params {
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
