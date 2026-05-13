<?php
$pageTitle = 'Livre';
$book = BookManager::load();
$bookProperties = $book['properties'] ?? BookManager::defaultProperties();
$spreads = BookManager::getAllSpreads();

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

<div class="gallery-toolbar" id="galleryToolbar">
    <span class="zoom-label">Zoom :</span>
    <input type="range" id="zoomSlider" min="1" max="5" step="1" value="2" class="zoom-slider">
    <span id="zoomHint" class="zoom-hint">2 planches</span>
</div>

<div class="book-props-modal hidden" id="bookPropsModal">
    <div class="book-props-box">
        <div class="book-props-header">
            <span>Propriétés du livre</span>
            <button class="book-props-close" id="bookPropsClose">×</button>
        </div>
        <div class="book-props-body">
            <div class="book-props-note">Page gauche : <?php echo h($bookProperties['pageDimensions']['widthCm'] ?? 24); ?> × <?php echo h($bookProperties['pageDimensions']['heightCm'] ?? 16); ?> cm</div>
            <label>Haut <input type="number" id="marginTopCm" min="0" max="20" step="0.1" value="<?php echo h($bookProperties['leftPageMargins']['topCm'] ?? 1); ?>"></label>
            <label>Droite / reliure <input type="number" id="marginRightCm" min="0" max="20" step="0.1" value="<?php echo h($bookProperties['leftPageMargins']['rightCm'] ?? 3); ?>"></label>
            <label>Bas <input type="number" id="marginBottomCm" min="0" max="20" step="0.1" value="<?php echo h($bookProperties['leftPageMargins']['bottomCm'] ?? 1); ?>"></label>
            <label>Gauche <input type="number" id="marginLeftCm" min="0" max="20" step="0.1" value="<?php echo h($bookProperties['leftPageMargins']['leftCm'] ?? 1); ?>"></label>
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
<div class="gallery-grid" id="galleryGrid" data-cols="2">

    <?php foreach ($spreads as $spread): ?>
    <?php
    $leftPage = $spread['leftPage'];
    $layout   = $leftPage['layout'] ?? 'free';
    $photos   = $leftPage['photos'] ?? [];
    $slots_def = $leftPage['slotAssignments'] ?? [];
    $photosById = [];
    foreach ($photos as $p) { if (!empty($p['filename'])) $photosById[$p['id']] = $p; }
    $pageNum = $leftPage['pageNumber'];
    $pdfPage = $spread['rightPage']['pdfPage'] ?? 1;
    ?>
    <div class="spread-item" data-spread="<?php echo $spread['spreadNumber']; ?>" data-pdf-page="<?php echo $pdfPage; ?>">

        <!-- Numéro de page : toujours visible -->
        <div class="spread-label">p.<?php echo $spread['rightPage']['pageNumber']; ?></div>

        <!-- Barre du dessus : visible au hover -->
        <div class="spread-bar">
            <a href="?page=editor&num=<?php echo $pageNum; ?>" class="spread-edit-btn">✏️ Éditer</a>
        </div>

        <!-- Double page -->
        <div class="spread-double">

            <!-- Page gauche : photos -->
            <div class="spread-left">
                <?php if ($layout === 'free'): ?>
                    <?php foreach ($photos as $photo): ?>
                        <?php if (empty($photo['filename'])) continue; ?>
                        <?php $pos = (isset($photo['frame']) && is_array($photo['frame'])) ? $photo['frame'] : ($photo['position'] ?? ['x'=>5,'y'=>5,'w'=>40,'h'=>40]); ?>
                        <?php $radius = galleryFrameRadius($photo); ?>
                        <div class="g-slot free-slot<?php echo galleryCapContainerClass($photo); ?>" style="left:<?php echo $pos['x']; ?>%;top:<?php echo $pos['y']; ?>%;width:<?php echo $pos['w']; ?>%;height:<?php echo $pos['h']; ?>%;z-index:<?php echo intval($pos['z'] ?? 1); ?>;border-radius:<?php echo $radius; ?>">
                            <div class="g-photo-clip" style="border-radius:<?php echo $radius; ?>">
                                <img src="<?php echo BASE_URL; ?>/uploads/photos/<?php echo h($photo['filename']); ?>" style="<?php echo galleryPhotoStyle($photo); ?>border-radius:<?php echo $radius; ?>" alt="">
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
                        <div class="g-slot<?php echo $photo ? galleryCapContainerClass($photo) : ''; ?>" style="left:<?php echo $pageSlot['x']; ?>%;top:<?php echo $pageSlot['y']; ?>%;width:<?php echo $pageSlot['w']; ?>%;height:<?php echo $pageSlot['h']; ?>%;border-radius:<?php echo $radius; ?>">
                            <?php if ($photo): ?>
                                <div class="g-photo-clip" style="border-radius:<?php echo $radius; ?>">
                                    <img src="<?php echo BASE_URL; ?>/uploads/photos/<?php echo h($photo['filename']); ?>" style="<?php echo galleryPhotoStyle($photo); ?>border-radius:<?php echo $radius; ?>" alt="">
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
            </div>

            <!-- Page droite : texte PDF (canvas chargé en lazy) -->
            <div class="spread-right">
                <canvas class="pdf-thumb-canvas" data-pdf-page="<?php echo $pdfPage; ?>"></canvas>
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
                const toolbarH = document.getElementById('galleryToolbar').offsetHeight;
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
                leftPageMargins: {
                    topCm: parseFloat(document.getElementById('marginTopCm').value) || 0,
                    rightCm: parseFloat(document.getElementById('marginRightCm').value) || 0,
                    bottomCm: parseFloat(document.getElementById('marginBottomCm').value) || 0,
                    leftCm: parseFloat(document.getElementById('marginLeftCm').value) || 0,
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
                const toolbarH = document.getElementById('galleryToolbar').offsetHeight;
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
    document.querySelectorAll('.spread-edit-btn').forEach(a => {
        a.addEventListener('click', () => {
            sessionStorage.setItem(STORE_SCROLL, window.scrollY);
        });
    });
    document.querySelectorAll('.spread-item').forEach(item => {
        item.addEventListener('dblclick', e => {
            if (e.target.closest('a, button, input, textarea, select')) return;
            const editLink = item.querySelector('.spread-edit-btn');
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

/* Chaque planche */
.spread-item {
    display: flex;
    flex-direction: column;
    gap: 0;
    cursor: pointer;
}

/* Numéro de page : toujours visible, discret */
.spread-label {
    font-size: 0.7rem;
    color: #bbb;
    font-weight: 600;
    padding: 1px 4px;
    text-align: right;
    line-height: 1;
    margin-bottom: 1px;
}
.spread-item:hover .spread-label { color: #888; }

/* Barre du dessus : bouton Éditer, visible au hover */
.spread-bar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    padding: 2px 4px;
    background: transparent;
    opacity: 0;
    transition: opacity 0.15s;
    font-size: 0.78rem;
    min-height: 22px;
    margin-bottom: 1px;
}
.spread-item:hover .spread-bar { opacity: 1; }

.spread-edit-btn {
    font-size: 0.75rem;
    padding: 2px 8px;
    background: #007bff;
    color: white;
    border-radius: 3px;
    text-decoration: none;
    white-space: nowrap;
}
.spread-edit-btn:hover { background: #0056b3; text-decoration: none; color: white; }

/* Double page */
.spread-double {
    display: flex;
    width: 100%;
    aspect-ratio: 48 / 16; /* deux pages 24×16 côte à côte */
    border-radius: 0 0 3px 3px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.18);
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
</style>
