/**
 * Page Editor
 * Modèle : photos (toutes les photos de la page) + slotAssignments (qui est dans quelle case)
 */

const PageEditor = {

    // --- État ---
    pageNumber: null,
    currentLayout: 'free',
    photos: [],           // [{id, filename, caption, rotation, filter, zoom, panX, panY, brightness, contrast}, ...]
    slotAssignments: [],  // [photoId|null, ...] — un par slot du layout actuel
    _history: [],
    _future: [],
    _zoomSaveTimer: null,
    _modalPhoto: null,    // Photo en édition dans la modal
    _modalOrigState: null, // État initial pour annulation
    _modalPanning: false,
    _modalPanStart: null,

    // --- Filtres CSS (partie artistique uniquement) ---
    FILTERS: {
        'none':    '',
        'bw':      'grayscale(100%)',
        'sepia':   'sepia(100%)',
        'vintage': 'sepia(50%) contrast(0.9) brightness(1.1) saturate(0.8)',
    },
    FILTER_LABELS: { 'none': 'orig', 'bw': 'N/B', 'sepia': 'sépia', 'vintage': 'vint.' },

    // Construit la chaîne CSS filter complète (luminosité + contraste + effet artistique)
    buildFilter(photo) {
        const br  = photo.brightness ?? 100;
        const ct  = photo.contrast   ?? 100;
        const art = this.FILTERS[photo.filter || 'none'] || '';
        const base = (br !== 100 || ct !== 100) ? `brightness(${br}%) contrast(${ct}%) ` : '';
        return (base + art).trim();
    },

    // --- Layouts ---
    LAYOUTS: {
        'pleine-page': {
            label: 'Pleine page',
            slots: [{ x:2, y:2, w:96, h:96 }]
        },
        '2-cote': {
            label: '2 côte à côte',
            slots: [
                { x:2,  y:2, w:47, h:96 },
                { x:51, y:2, w:47, h:96 },
            ]
        },
        '2-haut-bas': {
            label: '2 haut / bas',
            slots: [
                { x:2, y:2,  w:96, h:47 },
                { x:2, y:51, w:96, h:47 },
            ]
        },
        '1g-2d': {
            label: 'Grande gauche',
            slots: [
                { x:2,  y:2,  w:60, h:96 },
                { x:64, y:2,  w:34, h:47 },
                { x:64, y:51, w:34, h:47 },
            ]
        },
        '2g-1d': {
            label: 'Grande droite',
            slots: [
                { x:2,  y:2,  w:34, h:47 },
                { x:2,  y:51, w:34, h:47 },
                { x:38, y:2,  w:60, h:96 },
            ]
        },
        '1h-2b': {
            label: 'Grande haut',
            slots: [
                { x:2, y:2,  w:96, h:60 },
                { x:2, y:64, w:47, h:34 },
                { x:51,y:64, w:47, h:34 },
            ]
        },
        '3-cote': {
            label: '3 colonnes',
            slots: [
                { x:2,  y:2, w:30, h:96 },
                { x:35, y:2, w:30, h:96 },
                { x:68, y:2, w:30, h:96 },
            ]
        },
        '4-grille': {
            label: '4 en grille',
            slots: [
                { x:2,  y:2,  w:47, h:47 },
                { x:51, y:2,  w:47, h:47 },
                { x:2,  y:51, w:47, h:47 },
                { x:51, y:51, w:47, h:47 },
            ]
        },
        'free': {
            label: 'Libre',
            slots: []
        },
    },

    // --- Init ---

    init() {
        this.pageNumber      = PAGE_DATA.pageNumber;
        const savedLayout    = PAGE_DATA.layout;
        const defaultLayout  = BOOK_PROPERTIES?.defaultLayout || 'pleine-page';
        this.currentLayout   = savedLayout || defaultLayout;
        this.bookProperties  = typeof BOOK_PROPERTIES !== 'undefined' ? BOOK_PROPERTIES : null;
        this.photos          = PAGE_DATA.photos
            ? JSON.parse(JSON.stringify(PAGE_DATA.photos)).map(p => this._normalizePhotoModel(p))
            : [];
        this.slotAssignments = PAGE_DATA.slotAssignments ? [...PAGE_DATA.slotAssignments] : [];

        // Normaliser slotAssignments selon le layout courant
        this._normalizeSlots();

        this.buildLayoutPicker(this.currentLayout);
        this.render();
        this.setupToolbar();
        this.setupUpload();
        this.setupKeyboard();
        this._setupMediaPicker();
        this._setupAdjustPanel();
        this._setupEditModal();
        this._updateUndoRedoButtons();
    },

    _photoPageMarginsPercent() {
        const props = this.bookProperties || {};
        const dims = props.pageDimensions || {};
        const margins = props.photoPageMargins || {};
        const binding = parseFloat(props.bindingCm ?? 2);
        const width = parseFloat(dims.widthCm || 24) || 24;
        const height = parseFloat(dims.heightCm || 16) || 16;
        return {
            left: Math.max(0, Math.min(90, (parseFloat(margins.leftCm ?? 1) / width) * 100)),
            right: Math.max(0, Math.min(90, ((parseFloat(margins.rightCm ?? 1) + binding) / width) * 100)),
            top: Math.max(0, Math.min(90, (parseFloat(margins.topCm ?? 1) / height) * 100)),
            bottom: Math.max(0, Math.min(90, (parseFloat(margins.bottomCm ?? 1) / height) * 100)),
        };
    },

    _slotInMargins(slot) {
        const m = this._photoPageMarginsPercent();
        const contentW = Math.max(1, 100 - m.left - m.right);
        const contentH = Math.max(1, 100 - m.top - m.bottom);
        return {
            x: m.left + slot.x * contentW / 100,
            y: m.top + slot.y * contentH / 100,
            w: slot.w * contentW / 100,
            h: slot.h * contentH / 100,
        };
    },

    _renderMarginGuide(preview) {
        const m = this._photoPageMarginsPercent();
        const guide = document.createElement('div');
        guide.className = 'page-margin-guide';
        guide.style.left = m.left + '%';
        guide.style.top = m.top + '%';
        guide.style.width = Math.max(0, 100 - m.left - m.right) + '%';
        guide.style.height = Math.max(0, 100 - m.top - m.bottom) + '%';
        preview.appendChild(guide);
    },

    _normalizePhotoModel(photo) {
        const p = { ...photo };
        const legacyFrame = (p.frame && typeof p.frame === 'object') ? p.frame : (p.position || null);
        const frame = legacyFrame || this._defaultFrameForPhoto(p);
        p.frame = {
            x: parseFloat(frame.x ?? 5),
            y: parseFloat(frame.y ?? 5),
            w: parseFloat(frame.w ?? 40),
            h: parseFloat(frame.h ?? 40),
            z: parseInt(frame.z ?? 1),
            shape: frame.shape || 'rect',
            borderWidth: frame.borderWidth ?? p.borderWidth ?? 0,
            borderColor: frame.borderColor || p.borderColor || 'white',
            backgroundColor: frame.backgroundColor || 'white',
        };

        const crop = (p.crop && typeof p.crop === 'object') ? p.crop : {};
        p.crop = {
            fitMode: crop.fitMode || crop.fit || p.fit || 'cover',
            zoom: crop.zoom ?? p.zoom ?? 1,
            panX: crop.panX ?? p.panX ?? 0,
            panY: crop.panY ?? p.panY ?? 0,
        };

        this._syncLegacyPhotoFields(p);
        return p;
    },

    _defaultFrameForPhoto(photo) {
        const ratio = (photo.width && photo.height) ? photo.width / photo.height : 1;
        const pageRatio = 16 / 24;
        let h = 40;
        let w = h * ratio * pageRatio;
        if (w > 45) {
            w = 45;
            h = w / (ratio * pageRatio || 1);
        }
        if (w < 18) {
            w = 18;
            h = w / (ratio * pageRatio || 1);
        }
        h = Math.max(18, Math.min(55, h));
        return { x: 5, y: 5, w, h, z: 1 };
    },

    _syncLegacyPhotoFields(photo) {
        if (!photo.frame || typeof photo.frame !== 'object') return photo;
        if (!photo.crop || typeof photo.crop !== 'object') photo.crop = {};

        photo.position = {
            x: photo.frame.x,
            y: photo.frame.y,
            w: photo.frame.w,
            h: photo.frame.h,
            z: photo.frame.z ?? 1,
        };
        photo.fit = photo.crop.fitMode || 'cover';
        photo.zoom = photo.crop.zoom ?? 1;
        photo.panX = photo.crop.panX ?? 0;
        photo.panY = photo.crop.panY ?? 0;
        photo.borderWidth = photo.frame.borderWidth ?? 0;
        photo.borderColor = photo.frame.borderColor || 'white';
        photo.captionAlign = ['left', 'center', 'right'].includes(photo.captionAlign) ? photo.captionAlign : 'left';
        return photo;
    },

    _captionPosClass(photo) {
        return 'cap-' + (photo.captionPos || 'below');
    },

    _containerCapClass(photo) {
        if (!String(photo.caption || '').trim()) return '';
        const pos = photo.captionPos || 'below';
        if (pos === 'below') return 'cap-out-below';
        if (pos === 'above') return 'cap-out-above';
        return '';
    },

    _captionStyle(photo) {
        const pos = photo.captionPos || 'below';
        const color = photo.captionColor || 'white';
        const size = photo.captionSize || 11;
        const align = photo.captionAlign || 'left';
        const isInside = pos === 'inside-bottom' || pos === 'inside-top';
        const bg = isInside
            ? (color === 'black' ? 'rgba(255,255,255,0.8)' : 'rgba(0,0,0,0.5)')
            : 'transparent';
        return `color:${color};background:${bg};font-size:${size}px;text-align:${align}`;
    },

    _getFrame(photo) {
        if (!photo.frame || typeof photo.frame !== 'object') {
            return this._normalizePhotoModel(photo).frame;
        }
        return photo.frame;
    },

    _getCrop(photo) {
        if (!photo.crop || typeof photo.crop !== 'object') {
            return this._normalizePhotoModel(photo).crop;
        }
        return photo.crop;
    },

    _setCrop(photo, changes) {
        Object.assign(this._getCrop(photo), changes);
        this._syncLegacyPhotoFields(photo);
        return photo.crop;
    },

    _cropRenderMetrics(photo, targetRatio = null) {
        const crop = this._getCrop(photo);
        const frame = this._getFrame(photo);
        const zoom = crop.zoom || 1;
        const imageRatio = (photo.width && photo.height) ? photo.width / photo.height : this._frameAspectRatioNumber(frame);
        const frameRatio = targetRatio || this._frameAspectRatioNumber(frame);
        let baseX = 1;
        let baseY = 1;

        if ((crop.fitMode || 'cover') === 'contain') {
            if (imageRatio > frameRatio) {
                baseY = frameRatio / imageRatio;
            } else {
                baseX = imageRatio / frameRatio;
            }
        } else if (imageRatio > frameRatio) {
            baseX = imageRatio / frameRatio;
        } else {
            baseY = frameRatio / imageRatio;
        }

        return { baseX, baseY, zoom };
    },

    _cropPanBounds(photo, targetRatio = null) {
        const m = this._cropRenderMetrics(photo, targetRatio);
        return {
            x: Math.max(0, (m.baseX * m.zoom - 1) * 50),
            y: Math.max(0, (m.baseY * m.zoom - 1) * 50),
        };
    },

    _canPanPhoto(photo, targetRatio = null) {
        const bounds = this._cropPanBounds(photo, targetRatio);
        return bounds.x > 0.1 || bounds.y > 0.1;
    },

    _clampCropPan(photo, targetRatio = null) {
        const crop = this._getCrop(photo);
        const bounds = this._cropPanBounds(photo, targetRatio);
        return this._setCrop(photo, {
            panX: Math.max(-bounds.x, Math.min(bounds.x, crop.panX || 0)),
            panY: Math.max(-bounds.y, Math.min(bounds.y, crop.panY || 0)),
        });
    },

    _setFrame(photo, changes) {
        Object.assign(this._getFrame(photo), changes);
        this._syncLegacyPhotoFields(photo);
        return photo.frame;
    },

    _frameBorderRadius(frame) {
        const shape = frame?.shape || 'rect';
        if (shape === 'ellipse') return '50%';
        if (shape === 'rounded') return '10px';
        return '0';
    },

    _frameAspectRatio(frame) {
        const ratio = this._frameAspectRatioNumber(frame);
        return `${Math.round(ratio * 1000)} / 1000`;
    },

    _frameAspectRatioNumber(frame) {
        const w = parseFloat(frame?.w || 40);
        const h = parseFloat(frame?.h || 40);
        return Math.max(0.1, (w * 24) / Math.max(1, h * 16));
    },

    _modalCaptionExtraHeight(width) {
        const photo = this._modalPhoto;
        const caption = document.getElementById('modalCaptionPreview');
        if (!photo || !caption || !photo.caption) return 0;

        const pos = photo.captionPos || 'below';
        if (pos === 'inside-bottom' || pos === 'inside-top') return 0;

        const previousWidth = caption.style.width;
        caption.style.width = Math.max(0, Math.round(width)) + 'px';
        const style = window.getComputedStyle(caption);
        const margin = pos === 'above'
            ? parseFloat(style.marginBottom || 0)
            : parseFloat(style.marginTop || 0);
        const height = caption.scrollHeight + (Number.isFinite(margin) ? margin : 0);
        caption.style.width = previousWidth;
        return Math.ceil(height);
    },

    _resizeModalFramePreview(frame) {
        const preview = document.getElementById('modalPreview');
        if (!preview) return;

        const body = preview.closest('.photo-modal-body');
        const controls = document.querySelector('.photo-modal-controls');
        const bodyRect = body ? body.getBoundingClientRect() : null;
        const bodyStyle = body ? window.getComputedStyle(body) : null;
        const controlsW = controls ? controls.getBoundingClientRect().width : 280;
        const ratio = this._frameAspectRatioNumber(frame);
        const gap = parseFloat(bodyStyle?.columnGap || bodyStyle?.gap || 16) || 16;
        const paddingX = (parseFloat(bodyStyle?.paddingLeft || 0) || 0) + (parseFloat(bodyStyle?.paddingRight || 0) || 0);
        const paddingY = (parseFloat(bodyStyle?.paddingTop || 0) || 0) + (parseFloat(bodyStyle?.paddingBottom || 0) || 0);
        const room = 24;

        const availableW = (bodyRect?.width || window.innerWidth * 0.85) - controlsW - gap - paddingX - room;
        const availableH = (bodyRect?.height || window.innerHeight * 0.65) - paddingY - room;
        const maxW = Math.max(220, availableW);
        const maxHBase = Math.max(160, Math.min(availableH, window.innerHeight * 0.68));

        let width = maxW;
        let captionExtra = this._modalCaptionExtraHeight(width);
        let maxH = Math.max(120, maxHBase - captionExtra);
        let height = width / ratio;
        if (height > maxH) {
            height = maxH;
            width = height * ratio;
        }
        captionExtra = this._modalCaptionExtraHeight(width);
        maxH = Math.max(120, maxHBase - captionExtra);
        if (height > maxH) {
            height = maxH;
            width = height * ratio;
        }

        const w = Math.round(width);
        const h = Math.round(height);
        preview.style.width = w + 'px';
        preview.style.height = h + 'px';
        preview.style.flex = '0 0 auto';
        const outer = document.getElementById('modalPreviewOuter');
        if (outer) {
            const pos = this._modalPhoto?.captionPos || 'below';
            const reserve = this._modalCaptionExtraHeight(w);
            outer.style.width = w + 'px';
            outer.style.height = h + 'px';
            outer.style.marginTop = pos === 'above' ? reserve + 'px' : '0';
            outer.style.marginBottom = pos === 'below' ? reserve + 'px' : '0';
        }
    },

    _frameRatioValue(photo, ratioKey) {
        if (ratioKey === 'original') {
            return (photo.width && photo.height) ? photo.width / photo.height : null;
        }
        const parts = String(ratioKey || '').split(':').map(v => parseFloat(v));
        if (parts.length !== 2 || !parts[0] || !parts[1]) return null;
        return parts[0] / parts[1];
    },

    _applyFrameRatio(photo, ratioKey) {
        const ratio = this._frameRatioValue(photo, ratioKey);
        if (!ratio) return;

        const frame = this._getFrame(photo);
        const pageRatio = 16 / 24;
        const centerX = frame.x + frame.w / 2;
        const centerY = frame.y + frame.h / 2;
        let nextW = frame.h * ratio * pageRatio;
        let nextH = frame.h;

        if (nextW > 96) {
            nextW = 96;
            nextH = nextW / (ratio * pageRatio);
        }
        if (nextH > 96) {
            nextH = 96;
            nextW = nextH * ratio * pageRatio;
        }

        this._setFrame(photo, {
            w: Math.max(5, nextW),
            h: Math.max(5, nextH),
        });
        const updated = this._getFrame(photo);
        this._setFrame(photo, {
            x: Math.max(0, Math.min(100 - updated.w, centerX - updated.w / 2)),
            y: Math.max(0, Math.min(100 - updated.h, centerY - updated.h / 2)),
            ratio: ratioKey,
        });
    },

    _applyFrameShapeTo(el, frame) {
        if (!el || !frame) return;
        const radius = this._frameBorderRadius(frame);
        el.style.borderRadius = radius;
        el.style.setProperty('--frame-radius', radius);
        el.querySelectorAll('.slot-img, .photo-img-clip, .slot-frame').forEach(child => {
            child.style.borderRadius = radius;
        });
    },

    // --- Undo / Redo ---

    snapshot() {
        this._history.push(this._captureState());
        this._future = [];
        this._updateUndoRedoButtons();
    },

    undo() {
        if (!this._history.length) return;
        this._future.push(this._captureState());
        this._applyState(this._history.pop());
        this._updateUndoRedoButtons();
        this._savePage();
    },

    redo() {
        if (!this._future.length) return;
        this._history.push(this._captureState());
        this._applyState(this._future.pop());
        this._updateUndoRedoButtons();
        this._savePage();
    },

    _captureState() {
        return {
            photos:          JSON.parse(JSON.stringify(this.photos)),
            slotAssignments: [...this.slotAssignments],
            layout:          this.currentLayout,
        };
    },

    _applyState(state) {
        this.photos          = state.photos;
        this.slotAssignments = state.slotAssignments;
        this.currentLayout   = state.layout;
        this._setActiveLayoutBtn(this.currentLayout);
        this.render();
    },

    _updateUndoRedoButtons() {
        const u = document.getElementById('undoBtn');
        const r = document.getElementById('redoBtn');
        if (u) u.disabled = !this._history.length;
        if (r) r.disabled = !this._future.length;
    },

    // --- Render ---

    render() {
        this.renderCanvas();
        this.renderSidebar();
    },

    renderCanvas() {
        const preview = document.getElementById('pagePreview');
        preview.innerHTML = '';
        this._renderMarginGuide(preview);

        if (this.currentLayout === 'free') {
            this._renderFreeMode(preview);
        } else {
            this._renderSlots(preview);
        }
    },

    _renderSlots(preview) {
        const def = this.LAYOUTS[this.currentLayout];
        if (!def) return;

        def.slots.forEach((slot, i) => {
            const photoId = this.slotAssignments[i] || null;
            const photo   = photoId ? this.photos.find(p => p.id === photoId) : null;

            const el = document.createElement('div');
            el.className = 'page-slot' + (photo ? ' slot-filled' : ' slot-empty');
            el.dataset.slotIndex = i;
            const pageSlot = this._slotInMargins(slot);
            el.style.left   = pageSlot.x + '%';
            el.style.top    = pageSlot.y + '%';
            el.style.width  = pageSlot.w + '%';
            el.style.height = pageSlot.h + '%';
            const _dims = (this.bookProperties?.pageDimensions) || {};
            const _pW = parseFloat(_dims.widthCm || 24) || 24;
            const _pH = parseFloat(_dims.heightCm || 16) || 16;
            el.dataset.slotRatio = String((pageSlot.w * _pW) / (pageSlot.h * _pH));

            if (photo) {
                const cc = this._containerCapClass(photo);
                if (cc) el.classList.add(cc);
                const filterCss = this.buildFilter(photo);
                const frame = this._getFrame(photo);
                const crop = this._getCrop(photo);
                const caption   = photo.caption  || '';
                const bw = frame.borderWidth || 0;
                const bc = frame.borderColor || 'white';
                const frameStyle = bw > 0 ? `box-shadow:inset 0 0 0 ${bw}px ${bc};` : '';
                const radius = this._frameBorderRadius(frame);

                // Calculate render metrics for overlay
                const metrics = this._cropRenderMetrics(photo, (pageSlot.w * _pW) / (pageSlot.h * _pH));
                const zoom = crop.zoom || 1;
                const panX = crop.panX || 0;
                const panY = crop.panY || 0;
                const imageRatio = (photo.width && photo.height) ? photo.width / photo.height : 1;
                const slotRatio = (pageSlot.w * _pW) / (pageSlot.h * _pH);

                el.innerHTML = `
                    <div class="photo-img-clip" style="border-radius:${radius}">
                        <img class="slot-img" src="${BASE_URL}/uploads/photos/${photo.filename}"
                             data-rotation="${photo.rotation || 0}"
                             data-zoom="${zoom.toFixed(2)}"
                             data-pan-x="${panX.toFixed(1)}"
                             data-pan-y="${panY.toFixed(1)}"
                             data-fit="${crop.fitMode || 'cover'}"
                             data-base-x="${metrics.baseX.toFixed(2)}"
                             data-base-y="${metrics.baseY.toFixed(2)}"
                             data-img-ratio="${imageRatio.toFixed(2)}"
                             data-slot-ratio="${slotRatio.toFixed(2)}"
                             style="filter:${filterCss};object-fit:${crop.fitMode || 'cover'};border-radius:${radius}">
                        <div class="slot-frame" style="${frameStyle};border-radius:${radius}"></div>
                    </div>
                    <div class="slot-top-bar">
                        <div class="bar-row">
                            <button class="bar-btn edit-btn" title="Éditer">✎</button>
                            <span class="bar-flex"></span>
                            <button class="slot-remove" title="Retirer du layout">✕</button>
                        </div>
                    </div>
                    <div class="slot-caption-edit ${this._captionPosClass(photo)}${caption.trim() ? '' : ' no-caption'}" contenteditable="true" spellcheck="false"
                         data-placeholder="Légende…"
                         style="${this._captionStyle(photo)}">${caption.replace(/\n/g, '<br>')}</div>
                `;
                this._setupSlotPhotoActions(el, photo, i);

                // Add params overlay
                const imgEl = el.querySelector('.slot-img');
                const overlay = document.createElement('div');
                overlay.className = 'editor-photo-params';
                const displayText = `zoom: ${zoom.toFixed(2)} | panX: ${panX.toFixed(1)} | panY: ${panY.toFixed(1)} | fit: ${crop.fitMode || 'cover'} | baseX: ${metrics.baseX.toFixed(2)} | baseY: ${metrics.baseY.toFixed(2)} | imgRatio: ${imageRatio.toFixed(2)} | slotRatio: ${slotRatio.toFixed(2)}`;
                const copyText = `[editor page ${this.pageNumber}]
Fichier: ${photo.filename}
Layout: ${this.currentLayout} (slot ${i})
Dimensions slot: w:${pageSlot.w.toFixed(1)}% h:${pageSlot.h.toFixed(1)}%
zoom: ${zoom.toFixed(2)}
panX: ${panX.toFixed(1)}
panY: ${panY.toFixed(1)}
fit: ${crop.fitMode || 'cover'}
baseX: ${metrics.baseX.toFixed(2)}
baseY: ${metrics.baseY.toFixed(2)}
imgRatio: ${imageRatio.toFixed(2)}
slotRatio: ${slotRatio.toFixed(2)}`;
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
                const clip = imgEl.parentElement;
                if (clip) {
                    clip.appendChild(overlay);
                }
                const _slotRatio = (pageSlot.w * _pW) / (pageSlot.h * _pH);
                this._applyImgTransform(el.querySelector('.slot-img'), photo, _slotRatio);
                this._applyFrameShapeTo(el, frame);
            } else {
                el.innerHTML = `<div class="slot-placeholder"><span>Glisser une photo ici</span></div>`;
            }

            // Drop zone
            el.addEventListener('dragover', e => { e.preventDefault(); el.classList.add('slot-dragover'); });
            el.addEventListener('dragleave', () => el.classList.remove('slot-dragover'));
            el.addEventListener('drop', e => {
                e.preventDefault();
                el.classList.remove('slot-dragover');
                const photoId = e.dataTransfer.getData('photoId');
                if (photoId) this._assignToSlot(photoId, i);
            });

            preview.appendChild(el);
        });
    },

    _setupSlotPhotoActions(el, photo, slotIndex) {
        // Retirer du slot
        el.querySelector('.slot-remove').addEventListener('click', e => {
            e.stopPropagation();
            this.snapshot();
            this.slotAssignments[slotIndex] = null;
            this._hideAdjustPanel();
            this.render();
            this._savePage();
        });

        // Bouton éditer
        el.querySelector('.edit-btn').addEventListener('click', e => {
            e.stopPropagation();
            this._openEditModal(photo);
        });

        // Clic sur le slot = ouvrir le panneau d'ajustement
        el.addEventListener('click', e => {
            if (e.target.classList.contains('slot-caption-edit') ||
                e.target.closest('.slot-caption-edit')) return;
            this._showAdjustPanel(photo, el.querySelector('.slot-img'));
            // Marquer comme sélectionné
            document.querySelectorAll('.page-slot').forEach(s => s.classList.remove('slot-selected'));
            el.classList.add('slot-selected');
        });

        // Légende (contenteditable, avec retour à la ligne autorisé)
        const captionEl = el.querySelector('.slot-caption-edit');
        captionEl.addEventListener('keydown', e => {
            if (e.key === 'Enter' && e.ctrlKey) { e.preventDefault(); captionEl.blur(); }
            e.stopPropagation();
        });
        captionEl.addEventListener('blur', () => {
            const newCaption = this._captionFromHtml(captionEl);
            if (newCaption !== (photo.caption || '')) {
                this.snapshot();
                this._updatePhotoMeta(photo.id, { caption: newCaption });
            }
        });
        captionEl.addEventListener('mousedown', e => e.stopPropagation());
        captionEl.addEventListener('click', e => e.stopPropagation());

        // Pan (glisser l'image pour choisir le cadrage)
        this._setupPanZoom(el.querySelector('.slot-img'), photo, el);
    },

    // ---- Panneau d'ajustement (sidebar) ----

    _adjustPhoto: null,   // photo en cours d'ajustement
    _adjustImgEl: null,   // img DOM correspondante

    _showAdjustPanel(photo, imgEl) {
        this._adjustPhoto = photo;
        this._adjustImgEl = imgEl;
        const panel = document.getElementById('adjustPanel');
        const hint  = document.getElementById('adjustHint');
        if (!panel) return;
        panel.classList.remove('hidden');

        const crop = this._getCrop(photo);
        const zoom = crop.zoom || 1;
        const adjZoom = document.getElementById('adjZoom');
        if (adjZoom) adjZoom.value = Math.round(zoom * 10);
        document.getElementById('adjZoomVal').textContent = Math.round(zoom * 100) + '%';
        if (hint) hint.style.display = zoom > 1 ? 'block' : 'none';
    },

    _hideAdjustPanel() {
        this._adjustPhoto = null;
        this._adjustImgEl = null;
        const panel = document.getElementById('adjustPanel');
        if (panel) panel.classList.add('hidden');
        document.querySelectorAll('.page-slot').forEach(s => s.classList.remove('slot-selected'));
    },

    _setupAdjustPanel() {
        const zoomSlider = document.getElementById('adjZoom');
        const zoomVal  = document.getElementById('adjZoomVal');
        const hint     = document.getElementById('adjustHint');
        if (!zoomSlider) return;

        const getPhoto = () => this._adjustPhoto;
        const getImg   = () => this._adjustImgEl;

        // Slider zoom : valeur interne 5–40, zoom réel = val/10 (0.5× à 4.0× = 50% à 400%)
        zoomSlider.addEventListener('input', () => {
            const p = getPhoto(); if (!p) return;
            const newZoom = parseInt(zoomSlider.value) / 10;
            const zoomPercent = Math.round(newZoom * 100);
            zoomVal.textContent = zoomPercent + '%';
            this._setCrop(p, newZoom === 1
                ? { zoom: newZoom, panX: 0, panY: 0 }
                : { zoom: newZoom });
            const img = getImg(); if (img) this._applyImgTransform(img, p);
            if (hint) hint.style.display = newZoom > 1 ? 'block' : 'none';
            clearTimeout(this._zoomSaveTimer);
            this._zoomSaveTimer = setTimeout(() => {
                App.api('photos.php', { action: 'update', page: this.pageNumber, photoId: p.id,
                    crop: p.crop });
            }, 400);
        });
        zoomSlider.addEventListener('change', () => { const p = getPhoto(); if (p) this.snapshot(); });

        // Zoom reset
        document.getElementById('adjZoomReset')?.addEventListener('click', () => {
            const p = getPhoto(); if (!p) return;
            this._setCrop(p, { zoom: 1, panX: 0, panY: 0 });
            zoomSlider.value = 10;
            zoomVal.textContent = '100%';
            const img = getImg(); if (img) this._applyImgTransform(img, p);
            if (hint) hint.style.display = 'none';
            this.snapshot();
            App.api('photos.php', { action: 'update', page: this.pageNumber, photoId: p.id,
                crop: p.crop });
        });

        // Clic en dehors d'un slot = fermer le panneau
        document.getElementById('pagePreview')?.addEventListener('click', e => {
            if (!e.target.closest('.page-slot')) this._hideAdjustPanel();
        });
    },

    // --- Modal d'édition photo ---

    _setupMediaPicker() {
        document.getElementById('mediaPickerClose')?.addEventListener('click', () => {
            document.getElementById('mediaPickerOverlay').classList.add('hidden');
        });
        document.getElementById('mediaPickerOverlay')?.addEventListener('click', e => {
            if (e.target === e.currentTarget) e.currentTarget.classList.add('hidden');
        });
        document.getElementById('mediaPickerBtn')?.addEventListener('click', () => this._openMediaPicker());
    },

    async _openMediaPicker() {
        const overlay = document.getElementById('mediaPickerOverlay');
        const grid = document.getElementById('mediaPickerGrid');
        grid.innerHTML = '<div style="padding:2rem;text-align:center;color:#999">Chargement…</div>';
        overlay.classList.remove('hidden');

        try {
            const data = await App.api('media.php', { action: 'list' });
            grid.innerHTML = '';
            if (!data.media || !data.media.length) {
                grid.innerHTML = '<div style="padding:2rem;text-align:center;color:#999">Médiathèque vide.</div>';
                return;
            }
            // Filtrer les photos déjà sur cette page
            const pageMediaIds = new Set(this.photos.map(p => p.mediaId).filter(Boolean));

            data.media.forEach(m => {
                const div = document.createElement('div');
                div.className = 'media-picker-item' + (pageMediaIds.has(m.id) ? ' already-used' : '');
                div.innerHTML = `
                    <img src="${BASE_URL}/uploads/photos/${m.filename}" alt="" loading="lazy">
                    <div class="mpi-caption">${m.defaultCaption || ''}</div>
                    ${pageMediaIds.has(m.id) ? '<div class="mpi-badge">Déjà ajoutée</div>' : ''}
                `;
                if (!pageMediaIds.has(m.id)) {
                    div.addEventListener('click', () => {
                        document.getElementById('mediaPickerOverlay').classList.add('hidden');
                        this._addFromMedia(m);
                    });
                }
                grid.appendChild(div);
            });
        } catch(e) {
            grid.innerHTML = `<div style="padding:2rem;color:red">Erreur : ${e.message}</div>`;
        }
    },

    async _addFromMedia(mediaEntry) {
        const photoId = 'p_' + Date.now() + '_' + Math.random().toString(36).slice(2);
        const photo = this._normalizePhotoModel({
            id:       photoId,
            mediaId:  mediaEntry.id,
            filename: mediaEntry.filename,
            width:    mediaEntry.width,
            height:   mediaEntry.height,
            caption:  mediaEntry.defaultCaption || '',
        });

        try {
            await App.api('photos.php', { action: 'addFromMedia', page: this.pageNumber, photo });
        } catch(e) {
            console.error('addFromMedia error', e);
            App.notify('Erreur : impossible d\'ajouter la photo');
            return;
        }

        this.photos.push(photo);
        this.render();
        this._savePage();
    },

    _setupEditModal() {
        const modal = document.getElementById('photoEditModal');
        if (!modal) return;

        // Move page overlay close
        document.getElementById('movePageClose')?.addEventListener('click', () => {
            document.getElementById('movePageOverlay').classList.add('hidden');
        });
        document.getElementById('movePageOverlay')?.addEventListener('click', e => {
            if (e.target === e.currentTarget) e.currentTarget.classList.add('hidden');
        });

        // Fermeture
        document.getElementById('modalClose')?.addEventListener('click', () => this._closeEditModal(true));
        document.getElementById('modalCancel')?.addEventListener('click', () => this._closeEditModal(true));
        document.getElementById('modalValidate')?.addEventListener('click', () => this._closeEditModal(false));

        // Zoom slider
        document.getElementById('modalZoom')?.addEventListener('input', (e) => {
            if (!this._modalPhoto) return;
            const newZoom = parseInt(e.target.value) / 10;
            this._setCrop(this._modalPhoto, { zoom: newZoom });
            this._clampCropPan(this._modalPhoto);
            document.getElementById('modalZoomVal').textContent = Math.round(newZoom * 100) + '%';
            this._applyModalTransform();
        });

        // Zoom reset
        document.getElementById('modalZoomReset')?.addEventListener('click', () => {
            if (!this._modalPhoto) return;
            this._setCrop(this._modalPhoto, { zoom: 1, panX: 0, panY: 0 });
            document.getElementById('modalZoom').value = 10;
            document.getElementById('modalZoomVal').textContent = '100%';
            this._applyModalTransform();
        });

        // Légende
        document.getElementById('modalCaption')?.addEventListener('input', (e) => {
            if (!this._modalPhoto) return;
            this._modalPhoto.caption = e.target.value;
            this._updateModalCaptionPreview();
        });

        // Toggle couleur légende
        document.getElementById('captionColorToggle')?.addEventListener('click', () => {
            if (!this._modalPhoto) return;
            this._modalPhoto.captionColor = this._modalPhoto.captionColor === 'black' ? 'white' : 'black';
            this._updateModalCaptionPreview();
        });

        // Taille légende
        document.getElementById('modalCaptionSize')?.addEventListener('input', (e) => {
            if (!this._modalPhoto) return;
            this._modalPhoto.captionSize = parseInt(e.target.value);
            document.getElementById('modalCaptionSizeVal').textContent = this._modalPhoto.captionSize + 'px';
            this._updateModalCaptionPreview();
        });

        // Position légende
        document.querySelectorAll('.modal-cappos-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!this._modalPhoto) return;
                this._modalPhoto.captionPos = btn.dataset.pos;
                document.querySelectorAll('.modal-cappos-btn').forEach(b =>
                    b.classList.toggle('active', b.dataset.pos === btn.dataset.pos));
                this._updateModalCaptionPreview();
            });
        });

        // Cadre — largeur
        document.querySelectorAll('.modal-capalign-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!this._modalPhoto) return;
                this._modalPhoto.captionAlign = btn.dataset.align;
                document.querySelectorAll('.modal-capalign-btn').forEach(b =>
                    b.classList.toggle('active', b.dataset.align === btn.dataset.align));
                this._updateModalCaptionPreview();
            });
        });

        document.querySelectorAll('.modal-frame-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!this._modalPhoto) return;
                this._setFrame(this._modalPhoto, { borderWidth: parseInt(btn.dataset.w) });
                this._updateModalFramePreview();
            });
        });

        // Cadre — couleur
        document.querySelectorAll('.modal-frame-color').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!this._modalPhoto) return;
                this._setFrame(this._modalPhoto, { borderColor: btn.dataset.c });
                this._updateModalFramePreview();
            });
        });

        // Cadre — forme
        document.querySelectorAll('.modal-shape-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!this._modalPhoto) return;
                this._setFrame(this._modalPhoto, { shape: btn.dataset.shape || 'rect' });
                this._updateModalFramePreview();
            });
        });

        // Cadre — proportion
        document.querySelectorAll('.modal-ratio-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!this._modalPhoto) return;
                this._applyFrameRatio(this._modalPhoto, btn.dataset.ratio);
                this._updateModalFramePreview();
                this._applyModalTransform();
            });
        });
        this._setupModalFrameResize();
        this._setupModalZoom();

        // Fit (cover / contain)
        document.getElementById('fitCover')?.addEventListener('click', () => {
            if (!this._modalPhoto) return;
            this._setCrop(this._modalPhoto, { fitMode: 'cover' });
            document.getElementById('fitCover').classList.add('active');
            document.getElementById('fitContain').classList.remove('active');
        });
        document.getElementById('fitContain')?.addEventListener('click', () => {
            if (!this._modalPhoto) return;
            this._setCrop(this._modalPhoto, { fitMode: 'contain' });
            document.getElementById('fitContain').classList.add('active');
            document.getElementById('fitCover').classList.remove('active');
        });

        // Brightness
        document.getElementById('modalBr')?.addEventListener('input', (e) => {
            if (!this._modalPhoto) return;
            this._modalPhoto.brightness = parseInt(e.target.value);
            document.getElementById('modalBrVal').textContent = this._modalPhoto.brightness + '%';
            this._applyModalTransform();
        });
        document.getElementById('modalBrReset')?.addEventListener('click', () => {
            if (!this._modalPhoto) return;
            this._modalPhoto.brightness = 100;
            document.getElementById('modalBr').value = 100;
            document.getElementById('modalBrVal').textContent = '100%';
            this._applyModalTransform();
        });

        // Contrast
        document.getElementById('modalCt')?.addEventListener('input', (e) => {
            if (!this._modalPhoto) return;
            this._modalPhoto.contrast = parseInt(e.target.value);
            document.getElementById('modalCtVal').textContent = this._modalPhoto.contrast + '%';
            this._applyModalTransform();
        });
        document.getElementById('modalCtReset')?.addEventListener('click', () => {
            if (!this._modalPhoto) return;
            this._modalPhoto.contrast = 100;
            document.getElementById('modalCt').value = 100;
            document.getElementById('modalCtVal').textContent = '100%';
            this._applyModalTransform();
        });

        // Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !document.getElementById('photoEditModal')?.classList.contains('hidden')) {
                this._closeEditModal(true);
            }
        });

        // Overlay click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) this._closeEditModal(true);
        });

        window.addEventListener('resize', () => {
            if (!this._modalPhoto || modal.classList.contains('hidden')) return;
            this._updateModalFramePreview();
            this._applyModalTransform();
        });
    },

    _openEditModal(photo) {
        this._modalPhoto = this._normalizePhotoModel(JSON.parse(JSON.stringify(photo)));
        this._modalOrigState = JSON.parse(JSON.stringify(photo));
        const modal = document.getElementById('photoEditModal');
        if (!modal) return;

        // Hydrate contrôles
        const crop = this._getCrop(this._modalPhoto);
        const zoom = crop.zoom || 1;
        const br = this._modalPhoto.brightness ?? 100;
        const ct = this._modalPhoto.contrast ?? 100;
        const filter = this._modalPhoto.filter || 'none';
        const fit = crop.fitMode || 'cover';
        const captionColor = this._modalPhoto.captionColor || 'white';

        document.getElementById('modalZoom').value = Math.round(zoom * 10);
        document.getElementById('modalZoomVal').textContent = Math.round(zoom * 100) + '%';
        document.getElementById('modalBr').value = br;
        document.getElementById('modalBrVal').textContent = br + '%';
        document.getElementById('modalCt').value = ct;
        document.getElementById('modalCtVal').textContent = ct + '%';
        document.getElementById('fitCover').classList.toggle('active', fit === 'cover');
        document.getElementById('fitContain').classList.toggle('active', fit === 'contain');
        const captionSize = this._modalPhoto.captionSize || 11;
        const captionPos = this._modalPhoto.captionPos || 'below';
        const captionAlign = this._modalPhoto.captionAlign || 'left';
        document.getElementById('modalCaption').value = this._modalPhoto.caption || '';
        document.getElementById('modalCaptionSize').value = captionSize;
        document.getElementById('modalCaptionSizeVal').textContent = captionSize + 'px';
        document.querySelectorAll('.modal-cappos-btn').forEach(b =>
            b.classList.toggle('active', b.dataset.pos === captionPos));
        document.querySelectorAll('.modal-capalign-btn').forEach(b =>
            b.classList.toggle('active', b.dataset.align === captionAlign));
        this._updateModalCaptionPreview();
        this._updateModalFramePreview();

        // Filtres
        const filterDiv = document.getElementById('modalFilters');
        filterDiv.innerHTML = Object.keys(this.FILTER_LABELS).map(k =>
            `<button class="modal-filter-btn${k === filter ? ' active' : ''}" data-filter="${k}">${this.FILTER_LABELS[k]}</button>`
        ).join('');
        filterDiv.querySelectorAll('.modal-filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this._modalPhoto.filter = btn.dataset.filter;
                filterDiv.querySelectorAll('.modal-filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this._applyModalTransform();
            });
        });

        // Afficher modal
        modal.classList.remove('hidden');
        const cropOverlay = document.getElementById('cropOverlay');
        if (cropOverlay) {
            cropOverlay.classList.remove('hidden');
            cropOverlay.classList.add('active');
        }

        // Préview image
        const modalImg = document.getElementById('modalImg');
        let imageReady = false;

        const onImageReady = () => {
            if (imageReady) return;
            imageReady = true;
            this._applyModalTransform();
        };

        modalImg.addEventListener('load', onImageReady, {once: true});
        modalImg.src = BASE_URL + '/uploads/photos/' + photo.filename;

        // If image is cached, check after a frame
        if (modalImg.complete) {
            onImageReady();
        }

    },

    async _closeEditModal(cancel) {
        const modal = document.getElementById('photoEditModal');
        if (!modal) return;

        if (cancel) {
            modal.classList.add('hidden');
            const cropOverlay = document.getElementById('cropOverlay');
            if (cropOverlay) {
                cropOverlay.classList.add('hidden');
                cropOverlay.classList.remove('active');
            }
            this.photos = this.photos.map(p => p.id === this._modalOrigState.id ? { ...this._modalOrigState } : p);
            this.render();
        } else {
            const validateBtn = document.getElementById('modalValidate');
            if (validateBtn) validateBtn.disabled = true;
            const photoId = this._modalPhoto.id;
            this._syncLegacyPhotoFields(this._modalPhoto);
            const changes = {
                rotation: this._modalPhoto.rotation,
                crop: this._modalPhoto.crop,
                brightness: this._modalPhoto.brightness,
                contrast: this._modalPhoto.contrast,
                filter: this._modalPhoto.filter,
                caption: this._modalPhoto.caption || '',
                captionColor: this._modalPhoto.captionColor || 'white',
                captionSize: this._modalPhoto.captionSize || 11,
                captionPos: this._modalPhoto.captionPos || 'below',
                captionAlign: this._modalPhoto.captionAlign || 'left',
                frame: this._modalPhoto.frame,
            };
            this.snapshot();
            try {
                await this._updatePhotoMeta(photoId, changes);
                modal.classList.add('hidden');
                const cropOverlay = document.getElementById('cropOverlay');
                if (cropOverlay) {
                    cropOverlay.classList.add('hidden');
                    cropOverlay.classList.remove('active');
                }
            } catch (e) {
                alert('Erreur de sauvegarde: ' + e.message);
                if (validateBtn) validateBtn.disabled = false;
                return;
            }
            if (validateBtn) validateBtn.disabled = false;
        }

        this._modalPhoto = null;
        this._modalOrigState = null;
    },

    _updateModalCaptionPreview() {
        const p = this._modalPhoto;
        if (!p) return;
        const el = document.getElementById('modalCaptionPreview');
        const toggle = document.getElementById('captionColorToggle');
        if (!el) return;

        const color = p.captionColor || 'white';
        const size = p.captionSize || 11;
        const pos = p.captionPos || 'below';
        const align = p.captionAlign || 'left';
        const isInside = pos === 'inside-bottom' || pos === 'inside-top';
        const bg = isInside
            ? (color === 'black' ? 'rgba(255,255,255,0.8)' : 'rgba(0,0,0,0.5)')
            : 'transparent';

        el.className = 'modal-caption-preview cap-' + pos;
        el.textContent = p.caption || '';
        el.style.color = isInside ? color : (color === 'white' ? '#333' : color);
        el.style.background = bg;
        el.style.fontSize = size + 'px';
        el.style.textAlign = align;
        el.style.display = p.caption ? 'block' : 'none';
        this._resizeModalFramePreview(this._getFrame(p));

        if (toggle) {
            toggle.style.background = color === 'black' ? 'white' : 'black';
            toggle.style.color = color === 'black' ? 'black' : 'white';
            toggle.style.borderColor = color === 'black' ? '#aaa' : '#555';
        }
    },

    _updateModalFramePreview() {
        const p = this._modalPhoto;
        if (!p) return;
        const frame = this._getFrame(p);
        const bw = frame.borderWidth ?? 0;
        const bc = frame.borderColor || 'white';
        const shape = frame.shape || 'rect';
        const ratio = frame.ratio || '';

        // Appliquer l'aperçu du cadre sur le div preview (pas sur <img>, élément remplacé)
        const preview = document.getElementById('modalPreview');
        if (preview) {
            const radius = this._frameBorderRadius(frame);
            preview.style.boxShadow = bw > 0 ? `inset 0 0 0 ${bw * 2}px ${bc}` : '';
            preview.style.borderRadius = radius;
            preview.style.setProperty('--modal-frame-radius', radius);
            preview.style.clipPath = `inset(0 round ${radius})`;
            preview.style.aspectRatio = this._frameAspectRatio(frame);
            this._resizeModalFramePreview(frame);
        }

        // Boutons largeur
        document.querySelectorAll('.modal-frame-btn').forEach(b =>
            b.classList.toggle('active', parseInt(b.dataset.w) === bw));

        // Boutons couleur
        document.querySelectorAll('.modal-frame-color').forEach(b =>
            b.classList.toggle('active', b.dataset.c === bc));

        document.querySelectorAll('.modal-shape-btn').forEach(b =>
            b.classList.toggle('active', b.dataset.shape === shape));

        document.querySelectorAll('.modal-ratio-btn').forEach(b =>
            b.classList.toggle('active', !!ratio && b.dataset.ratio === ratio));

        // Afficher/masquer la rangée couleur
        const colorRow = document.getElementById('frameColorRow');
        if (colorRow) colorRow.style.display = bw > 0 ? '' : 'none';
    },

    _setupModalFrameResize() {
        const preview = document.getElementById('modalPreview');
        if (!preview) return;

        preview.querySelectorAll('.modal-frame-handle').forEach(handle => {
            handle.addEventListener('mousedown', e => {
                if (!this._modalPhoto) return;
                e.preventDefault();
                e.stopPropagation();

                const startFrame = { ...this._getFrame(this._modalPhoto) };
                const rect = preview.getBoundingClientRect();
                const centerX = startFrame.x + startFrame.w / 2;
                const centerY = startFrame.y + startFrame.h / 2;
                const mode = handle.dataset.handle || '';
                const start = {
                    mx: e.clientX,
                    my: e.clientY,
                    w: startFrame.w,
                    h: startFrame.h,
                    rectW: Math.max(1, rect.width),
                    rectH: Math.max(1, rect.height),
                };

                const onMove = ev => {
                    const dx = (ev.clientX - start.mx) / start.rectW * start.w;
                    const dy = (ev.clientY - start.my) / start.rectH * start.h;
                    let nextW = start.w;
                    let nextH = start.h;

                    if (mode.includes('e')) nextW = start.w + dx;
                    if (mode.includes('w')) nextW = start.w - dx;
                    if (mode.includes('s')) nextH = start.h + dy;
                    if (mode.includes('n')) nextH = start.h - dy;

                    nextW = Math.max(5, Math.min(100, nextW));
                    nextH = Math.max(5, Math.min(100, nextH));
                    nextW = Math.min(nextW, 100);
                    nextH = Math.min(nextH, 100);

                    this._setFrame(this._modalPhoto, {
                        w: nextW,
                        h: nextH,
                        x: Math.max(0, Math.min(100 - nextW, centerX - nextW / 2)),
                        y: Math.max(0, Math.min(100 - nextH, centerY - nextH / 2)),
                        ratio: 'custom',
                    });
                    this._updateModalFramePreview();
                    this._applyModalTransform();
                };

                const onUp = () => {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                };

                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        });
    },

    _applyModalTransform() {
        if (!this._modalPhoto) return;
        const preview = document.getElementById('modalPreview');
        const img = document.getElementById('modalImg');
        if (!img) return;

        img.style.filter = this.buildFilter(this._modalPhoto);
        img.style.width = '100%';
        img.style.height = '100%';

        const frame = this._getFrame(this._modalPhoto);
        if (preview) {
            const radius = this._frameBorderRadius(frame);
            preview.style.overflow = 'hidden';
            preview.style.borderRadius = radius;
            preview.style.setProperty('--modal-frame-radius', radius);
            preview.style.clipPath = `inset(0 round ${radius})`;
            preview.style.aspectRatio = this._frameAspectRatio(frame);
            this._resizeModalFramePreview(frame);
        }

        const frameRatio = this._frameAspectRatioNumber(frame);
        this._clampCropPan(this._modalPhoto, frameRatio);
        this._applyImgTransform(img, this._modalPhoto, frameRatio);
        img.style.borderRadius = this._frameBorderRadius(frame);
        img.style.cursor = this._canPanPhoto(this._modalPhoto, frameRatio) ? 'grab' : '';
    },

    _setupModalZoom() {
        const preview = document.getElementById('modalPreview');
        const img = document.getElementById('modalImg');
        if (!preview || !img) return;

        // Molette zoom
        img.addEventListener('wheel', (e) => {
            e.preventDefault();
            if (!this._modalPhoto) return;
            const steps = Math.max(-5, Math.min(5, Math.round(-e.deltaY / 100) || (e.deltaY < 0 ? 1 : -1)));
            const delta = steps * 0.1;
            const crop = this._getCrop(this._modalPhoto);
            const newZoom = Math.max(0.5, Math.min(4, (crop.zoom || 1) + delta));
            this._setCrop(this._modalPhoto, { zoom: Math.round(newZoom * 10) / 10 });
            this._clampCropPan(this._modalPhoto);
            document.getElementById('modalZoom').value = Math.round(this._modalPhoto.crop.zoom * 10);
            document.getElementById('modalZoomVal').textContent = Math.round(this._modalPhoto.crop.zoom * 100) + '%';
            this._applyModalTransform();
        }, {passive: false});

        // Pan la photo quand le zoom ou le ratio du cadre crée un recadrage
        img.addEventListener('mousedown', (e) => {
            if (e.target.closest('.modal-frame-handle')) return;
            const cropStart = this._modalPhoto ? this._getCrop(this._modalPhoto) : null;
            const frameRatio = this._frameAspectRatioNumber(this._getFrame(this._modalPhoto));
            if (!cropStart || !this._canPanPhoto(this._modalPhoto, frameRatio) || e.button !== 0) return;
            e.preventDefault();
            const startX = e.clientX;
            const startY = e.clientY;
            const zoom = cropStart.zoom || 1;
            const startPX = cropStart.panX || 0;
            const startPY = cropStart.panY || 0;
            img.style.cursor = 'grabbing';

            const previewRect = preview.getBoundingClientRect();

            const onMove = (e) => {
                if (!this._modalPhoto) return;
                const crop = this._getCrop(this._modalPhoto);
                const zoom = crop.zoom || 1;
                const dx = (e.clientX - startX) / previewRect.width * 100;
                const dy = (e.clientY - startY) / previewRect.height * 100;
                this._setCrop(this._modalPhoto, {
                    panX: startPX + dx,
                    panY: startPY + dy,
                });
                this._clampCropPan(this._modalPhoto, frameRatio);
                this._applyModalTransform();
            };

            const onUp = () => {
                if (this._modalPhoto) {
                    img.style.cursor = this._canPanPhoto(this._modalPhoto, frameRatio) ? 'grab' : '';
                }
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });

        img.style.cursor = 'grab';
    },

    _applyPhotoZoom(photo, newZoom, imgEl) {
        newZoom = Math.round(Math.max(0.5, Math.min(4, newZoom)) * 10) / 10;
        this._setCrop(photo, newZoom === 1
            ? { zoom: newZoom, panX: 0, panY: 0 }
            : { zoom: newZoom });
        // Synchroniser le panneau d'ajustement si c'est la photo sélectionnée
        if (this._adjustPhoto && this._adjustPhoto.id === photo.id) {
            const sl = document.getElementById('adjZoom');
            if (sl) sl.value = Math.round(newZoom * 10);
            const zv = document.getElementById('adjZoomVal');
            if (zv) zv.textContent = newZoom.toFixed(1) + '×';
            const hint = document.getElementById('adjustHint');
            if (hint) hint.style.display = newZoom > 1 ? 'block' : 'none';
        }
        if (imgEl) this._applyImgTransform(imgEl, photo);
        clearTimeout(this._zoomSaveTimer);
        this._zoomSaveTimer = setTimeout(() => {
            App.api('photos.php', { action: 'update', page: this.pageNumber, photoId: photo.id,
                crop: photo.crop });
        }, 400);
    },

    // Applique le transform complet (pan + zoom) sur l'élément img
    _elementFrameRatio(imgEl, fallbackPhoto) {
        const container = imgEl?.parentElement;
        const rect = container ? container.getBoundingClientRect() : null;
        if (rect && rect.width > 0 && rect.height > 0) return rect.width / rect.height;
        const slotEl = container?.closest('[data-slot-ratio]');
        if (slotEl?.dataset.slotRatio) {
            const r = parseFloat(slotEl.dataset.slotRatio);
            if (r > 0) return r;
        }
        return this._frameAspectRatioNumber(this._getFrame(fallbackPhoto));
    },

    _applyImgTransform(imgEl, photo, targetRatio = null) {
        const crop = this._getCrop(photo);
        const metrics = this._cropRenderMetrics(photo, targetRatio || this._elementFrameRatio(imgEl, photo));
        const zoom = metrics.zoom || 1;
        const panX = crop.panX || 0;
        const panY = crop.panY || 0;
        imgEl.style.position = 'absolute';
        imgEl.style.left = `calc(50% + ${panX}%)`;
        imgEl.style.top = `calc(50% + ${panY}%)`;
        imgEl.style.width = (metrics.baseX * 100) + '%';
        imgEl.style.height = (metrics.baseY * 100) + '%';
        imgEl.style.transform  = `translate(-50%, -50%) scale(${zoom})`;
        imgEl.style.clipPath   = '';
        imgEl.style.cursor     = '';
        imgEl.style.transformOrigin = 'center center';
    },

    _setupPanZoom(imgEl, photo, slotEl) {
        if (!imgEl) return;

        // Calculer et stocker dimensions si manquantes
        if (!photo.width || !photo.height) {
            imgEl.addEventListener('load', () => {
                if (!photo.width || !photo.height) {
                    photo.width = imgEl.naturalWidth;
                    photo.height = imgEl.naturalHeight;
                    if (photo.width && photo.height) {
                        App.api('photos.php', { action: 'update', page: this.pageNumber, photoId: photo.id,
                            width: photo.width, height: photo.height });
                    }
                }
            }, {once: true});
        }

        // Appliquer le transform initial
        const _initSlotRatio = slotEl?.dataset?.slotRatio ? parseFloat(slotEl.dataset.slotRatio) : null;
        this._applyImgTransform(imgEl, photo, _initSlotRatio || undefined);

        let isPanning = false, startX, startY, startPX, startPY;

        imgEl.addEventListener('mousedown', e => {
            const cropStart = this._getCrop(photo);
            const slotW = slotEl.clientWidth  || 1;
            const slotH = slotEl.clientHeight || 1;
            const slotRatio = slotW / slotH;
            if (!this._canPanPhoto(photo, slotRatio) || e.button !== 0) return;
            e.preventDefault(); e.stopPropagation();
            isPanning = true;
            startX = e.clientX; startY = e.clientY;
            startPX = cropStart.panX || 0; startPY = cropStart.panY || 0;
            imgEl.style.cursor = 'grabbing';

            const onMove = e => {
                if (!isPanning) return;
                const crop = this._getCrop(photo);
                const zoom = crop.zoom || 1;
                const dx = (e.clientX - startX) / slotW * 100;
                const dy = (e.clientY - startY) / slotH * 100;
                this._setCrop(photo, {
                    panX: startPX + dx,
                    panY: startPY + dy,
                });
                this._clampCropPan(photo, slotRatio);
                this._applyImgTransform(imgEl, photo, slotRatio);
            };

            const onUp = () => {
                if (!isPanning) return;
                isPanning = false;
                imgEl.style.cursor = 'grab';
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                App.api('photos.php', { action: 'update', page: this.pageNumber, photoId: photo.id,
                    crop: photo.crop });
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    },

    // Extrait le texte de la légende depuis un contenteditable (préserve les sauts de ligne)
    _captionFromHtml(el) {
        let html = el.innerHTML;
        html = html.replace(/<br\s*\/?>/gi, '\n');
        html = html.replace(/<\/div>/gi, '\n').replace(/<div[^>]*>/gi, '');
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        return tmp.textContent.replace(/\n{3,}/g, '\n\n').trim();
    },

    async _updatePhotoMeta(photoId, changes) {
        // Mettre à jour en mémoire
        const photo = this.photos.find(p => p.id === photoId);
        if (!photo) return;
        Object.assign(photo, changes);
        this._syncLegacyPhotoFields(photo);

        // Envoyer au serveur
        await App.api('photos.php', { action: 'update', page: this.pageNumber, photoId, ...changes });

        // Re-render (pas de savePage nécessaire pour caption/rotation/filter — géré par photos.php)
        this.render();
    },

    _renderFreeMode(preview) {
        const grid = document.createElement('div');
        grid.id = 'editorPhotoGrid';
        grid.style.cssText = 'position:absolute;inset:0;';
        grid.addEventListener('click', () => this._deselectFreePhoto());
        preview.appendChild(grid);

        this.photos.forEach(photo => {
            const pos = this._getFrame(photo);
            const el = this._createFreePhotoEl(photo, pos);
            grid.appendChild(el);
        });

        this._setupFreeModeMouseEvents();
    },

    _updateFreeSlotRatio(el, pos) {
        const dims = (this.bookProperties?.pageDimensions) || {};
        const pW = parseFloat(dims.widthCm || 24) || 24;
        const pH = parseFloat(dims.heightCm || 16) || 16;
        el.dataset.slotRatio = String((pos.w * pW) / (pos.h * pH));
    },

    _createFreePhotoEl(photo, pos) {
        const el = document.createElement('div');
        const cc = this._containerCapClass(photo);
        el.className = 'photo-item' + (cc ? ' ' + cc : '');
        el.dataset.photoId = photo.id;
        el.style.left    = pos.x + '%';
        el.style.top     = pos.y + '%';
        el.style.width   = pos.w + '%';
        el.style.height  = pos.h + '%';
        el.style.zIndex  = pos.z || 1;
        this._updateFreeSlotRatio(el, pos);

        const filterCss = this.buildFilter(photo);
        const crop = this._getCrop(photo);
        const frame = this._getFrame(photo);
        const caption   = photo.caption || '';
        const bw = frame.borderWidth || 0;
        const bc = frame.borderColor || 'white';
        const frameStyle = bw > 0 ? `box-shadow:inset 0 0 0 ${bw}px ${bc};` : '';
        const radius = this._frameBorderRadius(frame);

        el.innerHTML = `
            <div class="photo-img-clip" style="border-radius:${radius}">
                <img class="slot-img" src="${BASE_URL}/uploads/photos/${photo.filename}"
                     data-rotation="${photo.rotation || 0}" style="filter:${filterCss};object-fit:${crop.fitMode || 'cover'};border-radius:${radius}">
                <div class="slot-frame" style="${frameStyle};border-radius:${radius}"></div>
            </div>
            <div class="slot-top-bar free-bar">
                <div class="bar-row">
                    <button class="bar-btn edit-btn" title="Éditer">✎</button>
                    <button class="bar-btn bring-front-btn" title="Mettre au premier plan">⬆</button>
                    <span class="bar-flex"></span>
                    <button class="slot-remove" title="Supprimer">🗑</button>
                </div>
            </div>
            <div class="slot-caption-edit ${this._captionPosClass(photo)}${caption.trim() ? '' : ' no-caption'}" contenteditable="true" spellcheck="false"
                 data-placeholder="Légende…"
                 style="${this._captionStyle(photo)}">${caption.replace(/\n/g, '<br>')}</div>
            <div class="resize-handles">
                <div class="resize-handle handle-nw"></div>
                <div class="resize-handle handle-ne"></div>
                <div class="resize-handle handle-sw"></div>
                <div class="resize-handle handle-se"></div>
            </div>
        `;

        el.addEventListener('click', e => {
            if (!this._freeDragging && !this._freeResizing) {
                this._selectFreePhoto(el);
                if (!e.target.closest('.slot-caption-edit')) {
                    this._showAdjustPanel(photo, el.querySelector('.slot-img'));
                }
            }
            e.stopPropagation();
        });

        el.addEventListener('mousedown', e => {
            const tag = e.target.tagName;
            const cls = e.target.classList;
            if (e.button !== 0 || cls.contains('resize-handle') || cls.contains('slot-remove') ||
                cls.contains('bar-btn') || cls.contains('slot-caption-edit') || tag === 'BUTTON') return;
            this._selectFreePhoto(el);
            this.snapshot();
            this._freeDragging = true;
            this._freeDragStart = { mx: e.clientX, my: e.clientY, x: parseFloat(el.style.left), y: parseFloat(el.style.top) };
            e.preventDefault();
        });

        el.querySelectorAll('.resize-handle').forEach(h => {
            h.addEventListener('mousedown', e => {
                this._selectFreePhoto(el);
                this.snapshot();
                this._freeResizing = true;
                this._freeResizeStart = {
                    mx: e.clientX, my: e.clientY,
                    x: parseFloat(el.style.left), y: parseFloat(el.style.top),
                    w: parseFloat(el.style.width), h: parseFloat(el.style.height),
                    handle: h.classList[1],
                };
                e.preventDefault(); e.stopPropagation();
            });
        });

        el.querySelector('.slot-remove').addEventListener('click', e => {
            e.stopPropagation();
            if (confirm('Supprimer cette photo de la page ?')) {
                this.snapshot();
                this._deletePhoto(photo.id);
            }
        });

        // Bouton éditer
        el.querySelector('.edit-btn').addEventListener('click', e => {
            e.stopPropagation();
            this._openEditModal(photo);
        });

        // Bouton premier plan
        el.querySelector('.bring-front-btn').addEventListener('click', e => {
            e.stopPropagation();
            const maxZ = Math.max(1, ...this.photos.map(p => this._getFrame(p).z || 1));
            const newZ = maxZ + 1;
            this._setFrame(photo, { z: newZ });
            el.style.zIndex = newZ;
            App.api('pages.php', { action: 'save', page: this.pageNumber,
                freeFrames: this.photos.map(p => ({ id: p.id, frame: this._getFrame(p) })) });
        });

        // Légende (retour à la ligne autorisé)
        const captionEl = el.querySelector('.slot-caption-edit');
        captionEl.addEventListener('keydown', e => {
            if (e.key === 'Enter' && e.ctrlKey) { e.preventDefault(); captionEl.blur(); }
            e.stopPropagation();
        });
        captionEl.addEventListener('blur', () => {
            const newCaption = this._captionFromHtml(captionEl);
            if (newCaption !== (photo.caption || '')) {
                this.snapshot();
                this._updatePhotoMeta(photo.id, { caption: newCaption });
            }
        });
        captionEl.addEventListener('mousedown', e => e.stopPropagation());
        captionEl.addEventListener('click', e => e.stopPropagation());

        // Appliquer transform initial (rotation, pas de pan/zoom en mode libre)
        this._applyImgTransform(el.querySelector('.slot-img'), photo);
        this._applyFrameShapeTo(el, frame);

        return el;
    },

    _selectedFreeEl: null,
    _freeDragging: false,
    _freeResizing: false,
    _freeDragStart: null,
    _freeResizeStart: null,
    _freeMouseSetup: false,

    _selectFreePhoto(el) {
        document.querySelectorAll('.photo-item').forEach(p => {
            p.classList.remove('selected');
            const ctrl = p.querySelector('.photo-controls');
            if (ctrl) ctrl.style.display = 'none';
        });
        el.classList.add('selected');
        this._selectedFreeEl = el;
        const ctrl = el.querySelector('.photo-controls');
        if (ctrl) ctrl.style.display = 'flex';
    },

    _deselectFreePhoto() {
        document.querySelectorAll('.photo-item').forEach(p => p.classList.remove('selected'));
        this._selectedFreeEl = null;
        this._hideAdjustPanel();
    },

    _setupFreeModeMouseEvents() {
        if (this._freeMouseSetup) return;
        this._freeMouseSetup = true;

        document.addEventListener('mousemove', e => {
            const el = this._selectedFreeEl;
            if (!el) return;
            const preview = document.getElementById('pagePreview');
            if (!preview) return;
            const W = preview.offsetWidth, H = preview.offsetHeight;

            if (this._freeDragging && this._freeDragStart) {
                const dx = (e.clientX - this._freeDragStart.mx) / W * 100;
                const dy = (e.clientY - this._freeDragStart.my) / H * 100;
                let nx = Math.max(0, Math.min(100 - parseFloat(el.style.width),  this._freeDragStart.x + dx));
                let ny = Math.max(0, Math.min(100 - parseFloat(el.style.height), this._freeDragStart.y + dy));
                el.style.left = nx + '%';
                el.style.top  = ny + '%';
            }

            if (this._freeResizing && this._freeResizeStart) {
                const s = this._freeResizeStart;
                const dx = (e.clientX - s.mx) / W * 100;
                const dy = (e.clientY - s.my) / H * 100;
                let { x, y, w, h, handle } = s;
                if (handle === 'handle-se') { w += dx; h += dy; }
                else if (handle === 'handle-sw') { w -= dx; h += dy; x += dx; }
                else if (handle === 'handle-ne') { w += dx; h -= dy; y += dy; }
                else if (handle === 'handle-nw') { w -= dx; h -= dy; x += dx; y += dy; }
                w = Math.max(10, w); h = Math.max(10, h);
                x = Math.max(0, Math.min(100 - w, x));
                y = Math.max(0, Math.min(100 - h, y));
                el.style.left = x + '%'; el.style.top  = y + '%';
                el.style.width = w + '%'; el.style.height = h + '%';
            }
        });

        document.addEventListener('mouseup', () => {
            if (this._freeDragging || this._freeResizing) this._saveFreeModePositions();
            this._freeDragging = false;
            this._freeResizing = false;
        });
    },

    _saveFreeModePositions() {
        const freeFrames = [];
        document.querySelectorAll('.photo-item').forEach(el => {
            const z = parseInt(el.style.zIndex) || 1;
            const frame = {
                x: parseFloat(el.style.left),
                y: parseFloat(el.style.top),
                w: parseFloat(el.style.width),
                h: parseFloat(el.style.height),
                z,
            };
            const p = this.photos.find(p => p.id === el.dataset.photoId);
            if (p) {
                this._setFrame(p, frame);
                freeFrames.push({ id: p.id, frame: this._getFrame(p) });
            }
        });
        App.api('pages.php', { action: 'save', page: this.pageNumber, freeFrames });
    },

    // --- Sidebar ---

    renderSidebar() {
        const thumbs = document.getElementById('sidebarThumbs');
        if (!thumbs) return;
        thumbs.innerHTML = '';

        if (!this.photos.length) {
            thumbs.innerHTML = '<p class="sidebar-empty">Aucune photo.<br>T&eacute;l&eacute;chargez ou collez une image pour commencer.</p>';
            return;
        }

        this.photos.forEach(photo => {
            const inSlot  = this.slotAssignments.includes(photo.id);
            const isGrayed = this.currentLayout !== 'free' && !inSlot;

            const div = document.createElement('div');
            div.className = 'sidebar-thumb' + (isGrayed ? ' thumb-grayed' : '');
            div.dataset.photoId = photo.id;

            div.innerHTML = `
                <img src="${BASE_URL}/uploads/photos/${photo.filename}" alt="">
                <div class="sidebar-thumb-caption">${photo.caption || ''}</div>
                <div class="sidebar-thumb-actions">
                    <button class="thumb-edit" title="Éditer">✎</button>
                    <button class="thumb-move-prev" title="Déplacer à la planche précédente">←</button>
                    <button class="thumb-move-next" title="Déplacer à la planche suivante">→</button>
                    <button class="thumb-move-pick" title="Déplacer vers une autre page…">⊞</button>
                    <button class="thumb-delete" title="Supprimer la photo de la page">🗑</button>
                </div>
            `;

            // Drag vers un slot
            if (this.currentLayout !== 'free') {
                div.draggable = true;
                div.addEventListener('dragstart', e => {
                    e.dataTransfer.setData('photoId', photo.id);
                    div.classList.add('dragging');
                });
                div.addEventListener('dragend', () => div.classList.remove('dragging'));
            }

            // Éditer la photo
            div.querySelector('.thumb-edit').addEventListener('click', e => {
                e.stopPropagation();
                this._openEditModal(photo);
            });

            // Déplacer vers page précédente / suivante
            const prevPage = this._adjacentPhotoPage(-2);
            const nextPage = this._adjacentPhotoPage(+2);
            const prevBtn = div.querySelector('.thumb-move-prev');
            const nextBtn = div.querySelector('.thumb-move-next');
            if (!prevPage) prevBtn.disabled = true;
            if (!nextPage) nextBtn.disabled = true;
            prevBtn.addEventListener('click', e => { e.stopPropagation(); if (prevPage) this._movePhotoToPage(photo, prevPage); });
            nextBtn.addEventListener('click', e => { e.stopPropagation(); if (nextPage) this._movePhotoToPage(photo, nextPage); });
            div.querySelector('.thumb-move-pick').addEventListener('click', e => { e.stopPropagation(); this._openMovePagePicker(photo); });

            // Supprimer de la page
            div.querySelector('.thumb-delete').addEventListener('click', e => {
                e.stopPropagation();
                if (confirm('Supprimer cette photo de la page ?')) {
                    this.snapshot();
                    this._deletePhoto(photo.id);
                }
            });

            thumbs.appendChild(div);
        });
    },

    // --- Actions ---

    _assignToSlot(photoId, slotIndex) {
        this.snapshot();
        // Si cette photo était déjà dans un autre slot, la libérer
        const prev = this.slotAssignments.indexOf(photoId);
        if (prev !== -1) this.slotAssignments[prev] = null;
        // Si le slot cible avait une photo, elle revient dans la sidebar (grisée)
        this.slotAssignments[slotIndex] = photoId;
        this.render();
        this._savePage();
    },

    _adjacentPhotoPage(direction) {
        // Find the next/previous even (photo) page that belongs to a spread (2..total-1)
        const total = typeof TOTAL_PAGES_JS !== 'undefined' ? TOTAL_PAGES_JS : BOOK_CONFIG.totalPages;
        const step = direction > 0 ? 2 : -2;
        let target = this.pageNumber + step;
        while (target >= 2 && target <= total - 1) {
            if (target % 2 === 0) return target; // even = photo page
            target += step;
        }
        return null;
    },

    _movePhotoToPage(photo, targetPageNumber) {
        App.api('photos.php', {
            action: 'move_to_page',
            page: this.pageNumber,
            photoId: photo.id,
            targetPage: targetPageNumber
        }).then(data => {
            if (!data.success) { alert('Erreur : ' + (data.error || 'inconnue')); return; }
            // Remove from local state (slot + photo list)
            this.slotAssignments = this.slotAssignments.map(id => id === photo.id ? null : id);
            this.photos = this.photos.filter(p => p.id !== photo.id);
            this.render();
            this._savePage();
        });
    },

    _openMovePagePicker(photo) {
        const total = typeof TOTAL_PAGES_JS !== 'undefined' ? TOTAL_PAGES_JS : BOOK_CONFIG.totalPages;
        const offset = BOOK_CONFIG.pageNumberOffset || 0;
        const list = document.getElementById('movePageList');
        list.innerHTML = '';
        for (let p = 2; p <= total - 1; p += 2) {
            if (p === this.pageNumber) continue;
            const btn = document.createElement('button');
            btn.className = 'move-page-option';
            btn.textContent = `Page ${p + offset}–${p + 1 + offset}`;
            btn.addEventListener('click', () => {
                document.getElementById('movePageOverlay').classList.add('hidden');
                this._movePhotoToPage(photo, p);
            });
            list.appendChild(btn);
        }
        document.getElementById('movePageOverlay').classList.remove('hidden');
    },

    _deletePhoto(photoId) {
        // Retirer des slots
        this.slotAssignments = this.slotAssignments.map(id => id === photoId ? null : id);
        // Retirer de la liste
        this.photos = this.photos.filter(p => p.id !== photoId);

        App.api('photos.php', { action: 'delete', page: this.pageNumber, photoId })
            .then(() => { this.render(); this._savePage(); });
    },

    applyLayout(layoutKey) {
        const def = this.LAYOUTS[layoutKey];
        if (!def) return;
        this.snapshot();
        this.currentLayout = layoutKey;

        if (layoutKey === 'free') {
            this.slotAssignments = [];
        } else {
            // Conserver les assignments existants dans la limite des nouveaux slots
            const prev = [...this.slotAssignments];
            this.slotAssignments = def.slots.map((_, i) => prev[i] || null);
        }

        this._setActiveLayoutBtn(layoutKey);
        this.render();
        this._savePage();
    },

    _normalizeSlots() {
        const def = this.LAYOUTS[this.currentLayout];
        if (!def || def.slots.length === 0) { this.slotAssignments = []; return; }
        // S'assurer que le tableau a exactement le bon nombre d'entrées
        while (this.slotAssignments.length < def.slots.length) this.slotAssignments.push(null);
        this.slotAssignments = this.slotAssignments.slice(0, def.slots.length);
    },

    // --- Layout picker ---

    buildLayoutPicker(activeLayout) {
        const picker = document.getElementById('layoutPicker');
        if (!picker) return;
        picker.innerHTML = '';
        Object.entries(this.LAYOUTS).forEach(([key, def]) => {
            const btn = document.createElement('button');
            btn.className = 'layout-btn' + (key === activeLayout ? ' active' : '');
            btn.dataset.layout = key;
            btn.title = def.label;
            btn.innerHTML = this._layoutSvg(def.slots) + `<span>${def.label}</span>`;
            btn.addEventListener('click', () => this.applyLayout(key));
            picker.appendChild(btn);
        });
    },

    _layoutSvg(slots) {
        const rects = slots.map(s =>
            `<rect x="${s.x * 0.42}" y="${s.y * 0.42}" width="${s.w * 0.42}" height="${s.h * 0.42}" rx="1"/>`
        ).join('');
        return `<svg viewBox="0 0 44 44" width="44" height="44" xmlns="http://www.w3.org/2000/svg">
            <rect x="0" y="0" width="44" height="44" rx="2" fill="#e8e8e8"/>
            <g fill="#888">${rects}</g>
        </svg>`;
    },

    _setActiveLayoutBtn(key) {
        document.querySelectorAll('.layout-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.layout === key);
        });
    },

    // --- Toolbar ---

    setupToolbar() {
        document.getElementById('undoBtn')?.addEventListener('click', () => this.undo());
        document.getElementById('redoBtn')?.addEventListener('click', () => this.redo());
    },

    // --- Upload ---

    setupUpload() {
        const btn   = document.getElementById('uploadBtn');
        const input = document.getElementById('photoUpload');
        if (!btn || !input) return;
        btn.addEventListener('click', () => input.click());
        input.addEventListener('change', e => {
            if (e.target.files.length) this._uploadPhoto(e.target.files[0]);
            e.target.value = '';
        });

        // Coller depuis le presse-papier (Ctrl+V)
        document.addEventListener('paste', e => {
            const items = e.clipboardData?.items;
            if (!items) return;
            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    const file = item.getAsFile();
                    if (file) {
                        this._uploadPhoto(file);
                        this._setClipboardButtonState('has-image');
                    }
                    e.preventDefault();
                    break;
                }
            }
        });

        // Drag & drop depuis l'extérieur (bureau, explorateur)
        this._setupClipboardButton();

        const body = document.body;
        body.addEventListener('dragover', e => {
            if (e.dataTransfer.types.includes('Files')) {
                e.preventDefault();
                this._showDropZone(true);
            }
        });
        body.addEventListener('dragleave', e => {
            if (e.relatedTarget === null) this._showDropZone(false);
        });
        body.addEventListener('drop', e => {
            e.preventDefault();
            this._showDropZone(false);
            const file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) {
                this._uploadPhoto(file);
            }
        });
    },

    _setClipboardButtonState(state) {
        const btn = document.getElementById('pasteClipboardBtn');
        if (!btn) return;
        const hasImage = state === 'has-image';
        btn.classList.toggle('disabled', !hasImage);
        btn.classList.toggle('has-image', hasImage);
        btn.classList.toggle('unknown', state === 'unknown');
        btn.setAttribute('aria-disabled', hasImage ? 'false' : 'true');
        if (hasImage) {
            btn.title = "Coller l'image depuis le presse-papier";
        } else if (state === 'empty') {
            btn.title = "Aucune image detectee dans le presse-papier";
        } else {
            btn.title = "Cliquer pour verifier et coller depuis le presse-papier";
        }
    },

    _setupClipboardButton() {
        const btn = document.getElementById('pasteClipboardBtn');
        if (!btn) return;

        const getClipboardImage = async () => {
            if (!navigator.clipboard?.read) {
                return null;
            }
            try {
                const items = await navigator.clipboard.read();
                for (const item of items) {
                    const type = item.types.find(t => t.startsWith('image/'));
                    if (!type) continue;
                    const blob = await item.getType(type);
                    return new File([blob], 'presse-papier.png', { type: blob.type || type });
                }
            } catch (e) {
                return null;
            }
            return null;
        };

        const refresh = async (source = 'refresh') => {
            const file = await getClipboardImage();
            const hasImage = Boolean(file);
            this._setClipboardButtonState(hasImage ? 'has-image' : 'unknown');
        };

        btn.addEventListener('click', async () => {
            const file = await getClipboardImage();
            if (file) {
                this._setClipboardButtonState('has-image');
                this._uploadPhoto(file);
            } else {
                this._setClipboardButtonState('empty');
                App.notify("Aucune image lisible dans le presse-papier", 'info');
            }
        });

        window.addEventListener('focus', () => {
            window.setTimeout(() => refresh('window focus'), 100);
        });
        window.addEventListener('pageshow', () => refresh('pageshow'));
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refresh('visibilitychange');
        });
        btn.addEventListener('mouseenter', () => refresh('mouseenter'));
        this._setClipboardButtonState('unknown');
    },

    _dropOverlay: null,

    _showDropZone(show) {
        if (show && !this._dropOverlay) {
            const el = document.createElement('div');
            el.id = 'dropOverlay';
            el.innerHTML = '<div class="drop-message">📸 Déposer la photo ici</div>';
            document.body.appendChild(el);
            this._dropOverlay = el;
        } else if (!show && this._dropOverlay) {
            this._dropOverlay.remove();
            this._dropOverlay = null;
        }
    },

    _uploadPhoto(file) {
        const formData = new FormData();
        formData.append('action', 'upload');
        formData.append('page', this.pageNumber);
        formData.append('photo', file);

        const spinner = this._showSpinner('Téléchargement en cours…');

        fetch(BASE_URL + '/api/photos.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            spinner.remove();

            if (!data.success) { App.notify('Erreur: ' + data.error, 'error'); return; }

            this.snapshot();
            data.photo = this._normalizePhotoModel(data.photo);
            this.photos.push(data.photo);

            // Auto-assigner au premier slot vide si layout actif
            if (this.currentLayout !== 'free') {
                const empty = this.slotAssignments.indexOf(null);
                if (empty !== -1) this.slotAssignments[empty] = data.photo.id;
            }

            this.render();
            this._savePage();
            App.notify('Photo ajoutée');
        })
        .catch(err => {
            spinner.remove();
            App.notify('Erreur: ' + err.message, 'error');
        });
    },

    _showSpinner(label) {
        const el = document.createElement('div');
        el.className = 'upload-spinner-modal';
        el.innerHTML = `
            <div class="upload-spinner-box">
                <div class="upload-spinner-ring"></div>
                <div class="upload-spinner-label">${label}</div>
            </div>
        `;
        document.body.appendChild(el);
        return el;
    },

    // --- Sauvegarde ---

    _savePage() {
        App.api('pages.php', {
            action: 'save',
            page: this.pageNumber,
            layout: this.currentLayout,
            slotAssignments: this.slotAssignments,
        });
    },

    // --- Clavier ---

    setupKeyboard() {
        document.addEventListener('keydown', e => {
            if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key === 'z') { e.preventDefault(); this.undo(); }
            if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.shiftKey && e.key === 'z'))) { e.preventDefault(); this.redo(); }
        });
    },
};

document.addEventListener('DOMContentLoaded', () => PageEditor.init());

// CSS spinner + drop zone
const uploadCss = document.createElement('style');
uploadCss.textContent = `
/* Spinner modal */
.upload-spinner-modal {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
.upload-spinner-box {
    background: white;
    border-radius: 16px;
    padding: 2.5rem 3rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.5rem;
    box-shadow: 0 8px 40px rgba(0,0,0,0.25);
    min-width: 220px;
}
.upload-spinner-ring {
    width: 64px;
    height: 64px;
    border: 6px solid #e0e0e0;
    border-top-color: #007bff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.upload-spinner-label {
    font-size: 1rem;
    color: #444;
    font-weight: 500;
    text-align: center;
}

/* Drop zone overlay */
#dropOverlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 123, 255, 0.12);
    border: 4px dashed #007bff;
    z-index: 9998;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}
.drop-message {
    background: white;
    border-radius: 12px;
    padding: 2rem 3rem;
    font-size: 1.5rem;
    color: #007bff;
    font-weight: 600;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
`;
document.head.appendChild(uploadCss);

// --- CSS ---
const editorStyle = document.createElement('style');
editorStyle.textContent = `
#pagePreview {
    position: relative;
    background: white;
    border: 2px solid #ddd;
    border-radius: 4px;
    aspect-ratio: 24 / 16;
    width: 100%;
    max-width: 900px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    overflow: hidden;
}

.page-margin-guide {
    position: absolute;
    box-sizing: border-box;
    border: 1px dashed rgba(0,0,0,0.22);
    pointer-events: none;
    z-index: 20;
}

/* Slots (layout mode) */
.page-slot {
    position: absolute;
    box-sizing: border-box;
    border: 2px dashed #ccc;
    border-radius: 3px;
    overflow: visible;
    transition: border-color 0.15s, background 0.15s;
}
.page-slot.slot-filled { border-style: solid; border-color: #bbb; }
.page-slot.slot-dragover { border-color: #007bff; background: rgba(0,123,255,0.07); }
.page-slot img, .slot-img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
    transform-origin: center center;
    /* pointer-events gérés par JS selon zoom */
}

.slot-placeholder {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    color: #bbb;
    font-size: 12px;
    text-align: center;
    padding: 4px;
    pointer-events: none;
}

.slot-remove {
    background: rgba(220,53,69,0.7);
    color: white;
    border: none;
    border-radius: 3px;
    width: 20px; height: 18px;
    cursor: pointer;
    font-size: 11px;
    line-height: 18px;
    text-align: center;
    padding: 0;
    flex-shrink: 0;
}
.slot-remove:hover { background: rgba(220,53,69,1); }

/* Barre d'actions photo */
.slot-top-bar {
    position: absolute;
    top: 0; left: 0; right: 0;
    display: flex;
    flex-direction: column;
    background: rgba(0,0,0,0.7);
    opacity: 0;
    transition: opacity 0.15s;
    z-index: 10;
}
.page-slot:hover .slot-top-bar,
.photo-item:hover .slot-top-bar,
.photo-item.selected .slot-top-bar { opacity: 1; }
.free-bar { opacity: 0; }

.bar-row {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 2px 4px;
    flex-wrap: nowrap;
    min-height: 22px;
}
.bar-row2 { border-top: 1px solid rgba(255,255,255,0.15); }

.bar-btn {
    background: rgba(255,255,255,0.15);
    color: white;
    border: none;
    border-radius: 3px;
    padding: 1px 4px;
    font-size: 10px;
    cursor: pointer;
    line-height: 1.4;
    white-space: nowrap;
    flex-shrink: 0;
}
.bar-btn:hover { background: rgba(255,255,255,0.35); }
.flt-active { background: rgba(255,255,255,0.55) !important; color: #000; font-weight: 700; }
.bar-sep { width: 1px; height: 14px; background: rgba(255,255,255,0.25); flex-shrink: 0; margin: 0 1px; }
.bar-flex { flex: 1; }
.bar-lbl { color: rgba(255,255,255,0.7); font-size: 10px; flex-shrink: 0; }
.zoom-val { color: white; font-size: 10px; font-weight: 600; min-width: 28px; text-align: center; }
.bar-slider {
    -webkit-appearance: none;
    height: 3px;
    border-radius: 2px;
    background: rgba(255,255,255,0.3);
    outline: none;
    flex: 1;
    min-width: 30px;
    max-width: 60px;
    cursor: pointer;
}
.bar-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 10px; height: 10px;
    border-radius: 50%;
    background: white;
    cursor: pointer;
}

/* Légende éditable */
.slot-caption-edit {
    position: absolute;
    top: 100%; bottom: auto; left: 0; right: 0;
    margin-top: 3px;
    font-size: 11px;
    padding: 2px 6px;
    min-height: 18px;
    outline: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: text;
    z-index: 2;
}
.slot-caption-edit.cap-above { top: auto; bottom: 100%; margin-top: 0; margin-bottom: 3px; }
.slot-caption-edit.cap-inside-bottom { top: auto; bottom: 0; margin: 0; }
.slot-caption-edit.cap-inside-top { top: 0; bottom: auto; margin: 0; }
.slot-caption-edit.no-caption:not(:focus) {
    display: none;
}
.slot-caption-edit:empty:not(:focus)::before {
    content: attr(data-placeholder);
    color: rgba(255,255,255,0.4);
    pointer-events: none;
}
.slot-caption-edit.cap-below:empty:not(:focus)::before,
.slot-caption-edit.cap-above:empty:not(:focus)::before {
    color: rgba(0,0,0,0.25);
}
.slot-caption-edit:focus {
    background: rgba(0,0,0,0.75) !important;
    white-space: normal;
    overflow: visible;
    outline: 1px solid rgba(255,255,255,0.4);
}

/* Overlay de cadre (inset box-shadow) — présent dans layout et free mode */
.slot-frame { position: absolute; inset: 0; pointer-events: none; z-index: 1; }

/* Free mode */
.photo-item {
    position: absolute;
    border: 2px solid #ccc;
    border-radius: 2px;
    background: white;
    cursor: move;
    box-sizing: border-box;
    overflow: visible;
}
.photo-img-clip { position: absolute; inset: 0; overflow: hidden; border-radius: 1px; }
.photo-item img { width: 100%; height: 100%; object-fit: cover; display: block; pointer-events: none; }

/* Légende extérieure incluse dans la hauteur du cadre */
.cap-out-below, .cap-out-above { display: flex !important; }
.cap-out-below { flex-direction: column; }
.cap-out-above { flex-direction: column-reverse; }
.cap-out-below .photo-img-clip,
.cap-out-above .photo-img-clip {
    position: relative !important;
    inset: auto !important;
    flex: 1;
    width: 100%;
    min-height: 0;
}
.cap-out-below .slot-caption-edit,
.cap-out-above .slot-caption-edit {
    position: relative !important;
    flex-shrink: 0;
    top: auto !important;
    bottom: auto !important;
    margin: 0 !important;
    white-space: normal;
    overflow: hidden;
}
.photo-item.selected { border-color: #007bff; }
.photo-controls {
    position: absolute; top: 4px; right: 4px;
    display: flex; gap: 4px;
}
.resize-handles { position: absolute; inset: 0; pointer-events: none; z-index: 10; }
.resize-handle {
    position: absolute;
    width: 18px; height: 18px;
    background: #007bff;
    border: 2px solid white;
    border-radius: 50%;
    pointer-events: all;
    display: none;
    z-index: 10;
}
.resize-handle::after {
    content: '';
    position: absolute;
    inset: -8px;
}
.photo-item.selected .resize-handle { display: block; }
.handle-nw { top:-9px; left:-9px; cursor:nwse-resize; }
.handle-ne { top:-9px; right:-9px; cursor:nesw-resize; }
.handle-sw { bottom:-9px; left:-9px; cursor:nesw-resize; }
.handle-se { bottom:-9px; right:-9px; cursor:nwse-resize; }

/* Sidebar */
.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.6rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: #555;
    text-transform: uppercase;
    border-bottom: 1px solid #eee;
    background: #fafafa;
}

.sidebar-header-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.sidebar-action-btn {
    background: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    width: 26px; height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
    flex-shrink: 0;
}
.sidebar-action-btn:hover { background: #0056b3; }
.paste-clipboard-btn.disabled {
    background: #e6e6e6;
    color: #aaa;
}
.paste-clipboard-btn.disabled:hover {
    background: #ddd;
    color: #888;
}
.paste-clipboard-btn.unknown {
    background: #f2f2f2;
    color: #666;
}
.paste-clipboard-btn.unknown:hover {
    background: #e8f2ff;
    color: #007bff;
}
.paste-clipboard-btn.has-image {
    background: #28a745;
    color: white;
}
.paste-clipboard-btn.has-image:hover {
    background: #218838;
}
.sidebar-action-icon {
    position: relative;
    display: block;
    width: 15px;
    height: 15px;
}
.upload-computer-icon::before {
    content: '';
    position: absolute;
    left: 1px; right: 1px; bottom: 1px;
    height: 8px;
    border: 1.5px solid currentColor;
    border-radius: 2px;
}
.upload-computer-icon::after {
    content: '';
    position: absolute;
    left: 5px; top: 0;
    width: 5px; height: 9px;
    border-left: 1.5px solid currentColor;
    border-top: 1.5px solid currentColor;
    transform: rotate(45deg);
}
.paste-image-icon::before {
    content: '';
    position: absolute;
    left: 3px; top: 1px;
    width: 9px; height: 12px;
    border: 1.5px solid currentColor;
    border-radius: 2px;
}
.paste-image-icon::after {
    content: '';
    position: absolute;
    left: 5px; bottom: 4px;
    width: 6px; height: 4px;
    border-left: 1.5px solid currentColor;
    border-bottom: 1.5px solid currentColor;
    transform: skew(-20deg);
}

.sidebar-thumb {
    position: relative;
    border-radius: 4px;
    overflow: hidden;
    border: 2px solid transparent;
    cursor: grab;
    transition: border-color 0.15s, opacity 0.15s;
}
.sidebar-thumb:hover { border-color: #007bff; }
.sidebar-thumb.dragging { opacity: 0.5; }
.sidebar-thumb.thumb-grayed { opacity: 0.4; filter: grayscale(0.6); cursor: grab; }
.sidebar-thumb.thumb-grayed:hover { opacity: 0.7; border-color: #aaa; }

.sidebar-thumb img { width: 100%; height: 90px; object-fit: cover; display: block; pointer-events: none; }

.sidebar-thumb-caption {
    font-size: 10px; color: #666;
    padding: 2px 4px;
    background: #fafafa;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.sidebar-thumb-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 2px 4px;
    background: #f0f0f0;
}
.thumb-tag { font-size: 9px; color: #007bff; font-weight: 600; }
.thumb-delete, .thumb-edit, .thumb-move-prev, .thumb-move-next, .thumb-move-pick {
    background: none; border: none;
    cursor: pointer; font-size: 13px;
    padding: 0 2px; line-height: 1;
    color: #999;
}
.thumb-delete { margin-left: auto; }
.thumb-delete:hover { color: #dc3545; }
.thumb-edit:hover, .thumb-move-prev:hover, .thumb-move-next:hover, .thumb-move-pick:hover { color: #007bff; }
.thumb-move-prev:disabled, .thumb-move-next:disabled { opacity: 0.25; cursor: default; pointer-events: none; }

#undoBtn:disabled, #redoBtn:disabled { opacity: 0.4; cursor: not-allowed; }
`;
document.head.appendChild(editorStyle);
