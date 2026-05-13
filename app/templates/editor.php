<?php
$pageTitle = 'Éditeur';
$pageNumber = isset($_GET['num']) ? intval($_GET['num']) : null;

$book = BookManager::load();
$totalPages = $book['totalPages'] ?? TOTAL_PAGES;

if (!$pageNumber || $pageNumber < 1 || $pageNumber > $totalPages) {
    http_response_code(400); echo "Page invalide"; exit;
}

$page = BookManager::getPage($pageNumber);
if (!$page || $page['type'] !== 'photo') {
    http_response_code(400); echo "Cette page n'est pas une page photo"; exit;
}

// Ensure slotAssignments exists
if (!isset($page['slotAssignments'])) {
    $page['slotAssignments'] = [];
}

// Find next/prev photo page
function findNextPhotoPage($current, $direction = 1) {
    global $book, $totalPages;
    $page = $current + $direction;
    while ($page >= 1 && $page <= $totalPages) {
        $p = BookManager::getPage($page);
        if ($p && $p['type'] === 'photo') {
            return $page;
        }
        $page += $direction;
    }
    return null;
}

$nextPhotoPage = findNextPhotoPage($pageNumber, 1);
$prevPhotoPage = findNextPhotoPage($pageNumber, -1);

// Spread N has left=2N (even) and right=2N+1 (odd)
$spreadNumber = intdiv($pageNumber, 2);
?>

<script>
const PAGE_DATA = <?php echo json_encode($page); ?>;
const BOOK_PROPERTIES = <?php echo json_encode($book['properties'] ?? BookManager::defaultProperties()); ?>;
const TOTAL_PAGES_JS = <?php echo intval($totalPages); ?>;
</script>

<div class="editor-container">
    <div class="editor-toolbar">
        <div class="toolbar-group">
            <span class="toolbar-label">Disposition :</span>
            <div class="layout-picker" id="layoutPicker"></div>
        </div>

        <div class="toolbar-group">
            <button class="toolbar-button" id="undoBtn" disabled title="Annuler (Ctrl+Z)">↩ Annuler</button>
            <button class="toolbar-button" id="redoBtn" disabled title="Rétablir (Ctrl+Y)">↪ Rétablir</button>
        </div>

        <div class="toolbar-group toolbar-nav-group">
            <?php if ($prevPhotoPage !== null): ?>
                <a href="?page=editor&num=<?php echo $prevPhotoPage; ?>" class="toolbar-button toolbar-icon-button" id="prevSpread" title="Page photo précédente" aria-label="Page photo précédente">‹</a>
            <?php endif; ?>
            <?php $offset = $book['properties']['pageNumberOffset'] ?? 0; ?>
            <span class="page-ref-label">Page <?php echo $pageNumber + $offset; ?></span>
            <?php if ($nextPhotoPage !== null): ?>
                <a href="?page=editor&num=<?php echo $nextPhotoPage; ?>" class="toolbar-button toolbar-icon-button" id="nextSpread" title="Page photo suivante" aria-label="Page photo suivante">›</a>
            <?php endif; ?>
            <a href="?page=gallery" class="toolbar-button" id="backToGallery">Livre →</a>
        </div>
    </div>

    <div class="editor-body">
        <!-- Canvas central -->
        <div class="editor-canvas">
            <div class="page-preview" id="pagePreview" data-page-number="<?php echo $pageNumber; ?>">
                <!-- Contenu rendu par JS (slots ou mode libre) -->
            </div>
        </div>

        <!-- Panneau latéral -->
        <div class="photo-sidebar">
            <div class="sidebar-header">
                <span>Photos de la page</span>
                <div class="sidebar-header-actions">
                    <button id="uploadBtn" class="sidebar-action-btn upload-icon-btn" title="Télécharger depuis l'ordinateur" aria-label="Télécharger depuis l'ordinateur">
                        <span class="sidebar-action-icon upload-computer-icon"></span>
                    </button>
                    <button id="pasteClipboardBtn" class="sidebar-action-btn paste-clipboard-btn disabled" title="Coller l'image depuis le presse-papier" aria-label="Coller l'image depuis le presse-papier" aria-disabled="true">
                        <span class="sidebar-action-icon paste-image-icon"></span>
                    </button>
                    <button id="mediaPickerBtn" class="sidebar-action-btn" title="Ajouter depuis la médiathèque" aria-label="Ajouter depuis la médiathèque" style="font-size:16px;padding:0 4px;">⊞</button>
                </div>
                <input type="file" id="photoUpload" accept="image/jpeg,image/png,image/webp" style="display:none">
            </div>
            <div class="sidebar-thumbs" id="sidebarThumbs"></div>

            <!-- Panneau d'ajustement de la photo sélectionnée (zoom uniquement) -->
            <div class="adjust-panel hidden" id="adjustPanel">
                <div class="adjust-title">Zoom</div>
                <div class="adjust-row">
                    <span class="adjust-lbl">Zoom</span>
                    <input type="range" id="adjZoom" min="5" max="40" value="10" step="1" class="adj-slider">
                    <span class="adj-val" id="adjZoomVal">100%</span>
                    <button class="modal-btn-reset" id="adjZoomReset" title="Remettre à 100%">⟲</button>
                </div>
                <div class="adjust-hint" id="adjustHint" style="display:none">Glisser la photo pour cadrer</div>
            </div>
        </div>
    </div>

    <!-- Modal picker médiathèque -->
    <div class="media-picker-overlay hidden" id="mediaPickerOverlay">
        <div class="media-picker-box">
            <div class="media-picker-header">
                <span>Ajouter depuis la médiathèque</span>
                <button class="media-picker-close" id="mediaPickerClose">✕</button>
            </div>
            <div class="media-picker-grid" id="mediaPickerGrid"></div>
        </div>
    </div>

    <!-- Modal de déplacement de photo -->
    <div class="move-page-overlay hidden" id="movePageOverlay">
        <div class="move-page-box">
            <div class="move-page-header">
                <span>Déplacer vers une autre page</span>
                <button class="move-page-close" id="movePageClose">✕</button>
            </div>
            <div class="move-page-list" id="movePageList"></div>
        </div>
    </div>

    <!-- Modal d'édition photo -->
    <div class="photo-modal-overlay hidden" id="photoEditModal">
        <div class="photo-modal-box">
            <div class="photo-modal-header">
                <span class="photo-modal-title" id="modalTitle">Édition photo</span>
                <button class="photo-modal-close" id="modalClose">✕</button>
            </div>
            <div class="photo-modal-body">
                <div class="modal-preview-outer" id="modalPreviewOuter">
                    <div class="photo-modal-preview" id="modalPreview">
                        <img id="modalImg" src="" alt="" draggable="false">
                        <button class="modal-frame-handle handle-n" data-handle="n" aria-label="Redimensionner le cadre par le haut"></button>
                        <button class="modal-frame-handle handle-e" data-handle="e" aria-label="Redimensionner le cadre par la droite"></button>
                        <button class="modal-frame-handle handle-s" data-handle="s" aria-label="Redimensionner le cadre par le bas"></button>
                        <button class="modal-frame-handle handle-w" data-handle="w" aria-label="Redimensionner le cadre par la gauche"></button>
                        <button class="modal-frame-handle handle-nw" data-handle="nw" aria-label="Redimensionner le cadre"></button>
                        <button class="modal-frame-handle handle-ne" data-handle="ne" aria-label="Redimensionner le cadre"></button>
                        <button class="modal-frame-handle handle-sw" data-handle="sw" aria-label="Redimensionner le cadre"></button>
                        <button class="modal-frame-handle handle-se" data-handle="se" aria-label="Redimensionner le cadre"></button>
                    </div>
                    <div class="modal-caption-preview" id="modalCaptionPreview"></div>
                </div>
                <div class="photo-modal-controls">
                    <div class="modal-section">
                        <div class="modal-section-title">Zoom</div>
                        <div class="modal-ctrl-row">
                            <span class="adjust-lbl">Zoom</span>
                            <input type="range" id="modalZoom" min="5" max="40" value="10" step="1" class="adj-slider">
                            <span class="adj-val" id="modalZoomVal">100%</span>
                            <button class="modal-btn-reset" id="modalZoomReset" title="Remettre à 100%">⟲</button>
                        </div>
                    </div>
                    <div class="modal-section">
                        <div class="modal-section-title">Lumière</div>
                        <div class="modal-ctrl-row">
                            <span class="adjust-lbl">☀ Lumière</span>
                            <input type="range" id="modalBr" min="50" max="200" value="100" class="adj-slider">
                            <span class="adj-val" id="modalBrVal">100%</span>
                            <button class="modal-btn-reset" id="modalBrReset" title="Remettre à 100%">⟲</button>
                        </div>
                        <div class="modal-ctrl-row">
                            <span class="adjust-lbl">◑ Contraste</span>
                            <input type="range" id="modalCt" min="50" max="200" value="100" class="adj-slider">
                            <span class="adj-val" id="modalCtVal">100%</span>
                            <button class="modal-btn-reset" id="modalCtReset" title="Remettre à 100%">⟲</button>
                        </div>
                    </div>
                    <div class="modal-section">
                        <div class="modal-section-title">Légende</div>
                        <textarea id="modalCaption" class="modal-caption-input" rows="2" placeholder="Légende…"></textarea>
                        <div class="modal-ctrl-row modal-caption-tools" style="margin-top:0.4rem">
                            <button class="modal-caption-color-toggle" id="captionColorToggle" title="Basculer couleur du texte">A</button>
                            <button class="modal-capalign-btn active" data-align="left" title="Aligner à gauche"><span class="align-icon align-left"><span></span><span></span><span></span></span></button>
                            <button class="modal-capalign-btn" data-align="center" title="Centrer"><span class="align-icon align-center"><span></span><span></span><span></span></span></button>
                            <button class="modal-capalign-btn" data-align="right" title="Aligner à droite"><span class="align-icon align-right"><span></span><span></span><span></span></span></button>
                            <input type="range" id="modalCaptionSize" min="8" max="28" value="11" step="1" class="adj-slider">
                            <span class="adj-val" id="modalCaptionSizeVal">11px</span>
                        </div>
                        <div class="modal-ctrl-row" style="margin-top:0.4rem">
                            <button class="modal-cappos-btn" data-pos="above" title="Au-dessus de la photo">↑ Dessus</button>
                            <button class="modal-cappos-btn" data-pos="inside-top" title="Intégrée en haut">⬆ Int. haut</button>
                            <button class="modal-cappos-btn active" data-pos="below" title="Sous la photo">↓ Dessous</button>
                            <button class="modal-cappos-btn" data-pos="inside-bottom" title="Intégrée en bas">⬇ Int. bas</button>
                        </div>
                    </div>
                    <div class="modal-section">
                        <div class="modal-section-title">Remplissage</div>
                        <div class="modal-ctrl-row">
                            <button class="modal-fit-btn" id="fitCover" title="Remplit le cadre, bords coupés">⬛ Remplir</button>
                            <button class="modal-fit-btn" id="fitContain" title="Tout visible, blanc possible">⬜ Contenir</button>
                        </div>
                    </div>
                    <div class="modal-section">
                        <div class="modal-section-title">Cadre</div>
                        <div class="modal-ctrl-row">
                            <button class="modal-shape-btn active" data-shape="rect">Rect</button>
                            <button class="modal-shape-btn" data-shape="rounded">Arrondi</button>
                            <button class="modal-shape-btn" data-shape="ellipse">Ovale</button>
                        </div>
                        <div class="modal-ctrl-row">
                            <button class="modal-ratio-btn" data-ratio="original">Photo</button>
                            <button class="modal-ratio-btn" data-ratio="1:1">1:1</button>
                            <button class="modal-ratio-btn" data-ratio="4:3">4:3</button>
                            <button class="modal-ratio-btn" data-ratio="3:2">3:2</button>
                            <button class="modal-ratio-btn" data-ratio="16:9">16:9</button>
                        </div>
                        <div class="modal-ctrl-row">
                            <button class="modal-frame-btn active" data-w="0">Aucun</button>
                            <button class="modal-frame-btn" data-w="2">Fin</button>
                            <button class="modal-frame-btn" data-w="5">Normal</button>
                            <button class="modal-frame-btn" data-w="10">Épais</button>
                        </div>
                        <div class="modal-ctrl-row" id="frameColorRow" style="display:none">
                            <span class="adjust-lbl">Couleur</span>
                            <button class="modal-frame-color" data-c="white" title="Blanc"></button>
                            <button class="modal-frame-color" data-c="#ccc" title="Gris clair"></button>
                            <button class="modal-frame-color" data-c="#666" title="Gris foncé"></button>
                            <button class="modal-frame-color" data-c="black" title="Noir"></button>
                        </div>
                    </div>
                    <div class="modal-section">
                        <div class="modal-section-title">Filtres</div>
                        <div class="modal-filters" id="modalFilters"></div>
                    </div>
                </div>
            </div>
            <div class="photo-modal-footer">
                <button class="modal-btn modal-btn-cancel" id="modalCancel">Annuler</button>
                <button class="modal-btn modal-btn-primary" id="modalValidate">✓ Valider</button>
            </div>
        </div>
    </div>
</div>

<script>
// Retour galerie : mémoriser la planche courante et passer en zoom 1
document.getElementById('backToGallery').addEventListener('click', function() {
    sessionStorage.setItem('gallery_focus_spread', <?php echo $spreadNumber; ?>);
    sessionStorage.setItem('gallery_zoom', '1');
});
document.getElementById('nextSpread')?.addEventListener('click', function() {
    sessionStorage.setItem('gallery_focus_spread', <?php echo $spreadNumber + 1; ?>);
});
document.getElementById('prevSpread')?.addEventListener('click', function() {
    sessionStorage.setItem('gallery_focus_spread', <?php echo max(1, $spreadNumber - 1); ?>);
});
</script>
<script src="<?php echo BASE_URL; ?>/js/page-editor.js"></script>
