<?php
$pageTitle = 'Médiathèque';
$mediaList = BookManager::getMedia();

// Enrichir avec l'usage
foreach ($mediaList as &$m) {
    $m['usedOnPages'] = BookManager::getMediaUsage($m['id']);
}
unset($m);
?>

<div class="media-toolbar">
    <span class="media-count"><?php echo count($mediaList); ?> photo<?php echo count($mediaList) > 1 ? 's' : ''; ?></span>
    <button class="media-upload-btn" id="mediaUploadBtn">+ Ajouter des photos</button>
    <input type="file" id="mediaUploadInput" accept="image/jpeg,image/png,image/webp" multiple style="display:none">
</div>

<div class="media-grid" id="mediaGrid">
<?php if (empty($mediaList)): ?>
    <div class="media-empty">Aucune photo dans la médiathèque. Uploadez des photos depuis l'éditeur ou via le bouton ci-dessus.</div>
<?php else: ?>
    <?php foreach ($mediaList as $m): ?>
    <div class="media-card" data-id="<?php echo h($m['id']); ?>">
        <div class="media-thumb">
            <img src="<?php echo BASE_URL; ?>/uploads/photos/<?php echo h($m['filename']); ?>" alt="" loading="lazy">
        </div>
        <div class="media-info">
            <input type="text" class="media-caption-input"
                   value="<?php echo h($m['defaultCaption'] ?? ''); ?>"
                   placeholder="Légende par défaut…"
                   data-id="<?php echo h($m['id']); ?>">
            <div class="media-usage">
                <?php if (empty($m['usedOnPages'])): ?>
                    <span class="media-unused">Non utilisée</span>
                <?php else: ?>
                    <span class="media-used-label">Page<?php echo count($m['usedOnPages']) > 1 ? 's' : ''; ?> :</span>
                    <?php foreach ($m['usedOnPages'] as $pn): ?>
                        <a href="?page=editor&num=<?php echo intval($pn); ?>" class="media-page-link"><?php echo intval($pn) - 1; ?>–<?php echo intval($pn); ?></a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <button class="media-add-to-page-btn" data-id="<?php echo h($m['id']); ?>"
                data-filename="<?php echo h($m['filename']); ?>"
                data-caption="<?php echo h($m['defaultCaption'] ?? ''); ?>"
                title="Ajouter à une page">+ Page</button>
        <button class="media-delete-btn<?php echo !empty($m['usedOnPages']) ? ' disabled' : ''; ?>"
                data-id="<?php echo h($m['id']); ?>"
                <?php echo !empty($m['usedOnPages']) ? 'disabled title="Utilisée sur une page"' : 'title="Supprimer"'; ?>>✕</button>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<!-- Modal sélection de page -->
<div class="media-page-picker-overlay hidden" id="mediaPagePickerOverlay">
    <div class="move-page-box">
        <div class="move-page-header">
            <span>Ajouter à une page</span>
            <button class="move-page-close" id="mediaPagePickerClose">✕</button>
        </div>
        <div class="move-page-list" id="mediaPagePickerList"></div>
    </div>
</div>

<script>
const mediaApp = {
    _pendingMedia: null,

    init() {
        document.getElementById('mediaUploadBtn').addEventListener('click', () => {
            document.getElementById('mediaUploadInput').click();
        });
        document.getElementById('mediaUploadInput').addEventListener('change', e => {
            [...e.target.files].forEach(f => this.upload(f));
            e.target.value = '';
        });

        document.getElementById('mediaGrid').addEventListener('click', e => {
            const addBtn = e.target.closest('.media-add-to-page-btn');
            if (addBtn) { this.openPagePicker(addBtn); return; }

            const btn = e.target.closest('.media-delete-btn');
            if (btn && !btn.disabled) {
                const id = btn.dataset.id;
                if (confirm('Supprimer cette photo de la médiathèque ?')) this.deleteMedia(id);
            }
        });

        document.getElementById('mediaPagePickerClose').addEventListener('click', () => {
            document.getElementById('mediaPagePickerOverlay').classList.add('hidden');
        });
        document.getElementById('mediaPagePickerOverlay').addEventListener('click', e => {
            if (e.target === e.currentTarget) e.currentTarget.classList.add('hidden');
        });

        // Légende par défaut — save on blur
        document.getElementById('mediaGrid').addEventListener('blur', e => {
            if (e.target.classList.contains('media-caption-input')) {
                this.updateCaption(e.target.dataset.id, e.target.value);
            }
        }, true);
        document.getElementById('mediaGrid').addEventListener('keydown', e => {
            if (e.target.classList.contains('media-caption-input') && e.key === 'Enter') {
                e.preventDefault();
                e.target.blur();
            }
        });
    },

    async upload(file) {
        const fd = new FormData();
        fd.append('photo', file);
        fd.append('action', 'upload');
        try {
            const data = await App.apiForm('media.php', fd);
            if (data.success) this.addCard(data.media);
            else alert('Erreur upload : ' + (data.error || ''));
        } catch(e) { alert('Erreur : ' + e.message); }
    },

    async deleteMedia(id) {
        try {
            const data = await App.api('media.php', { action: 'delete', mediaId: id });
            if (data.success) {
                document.querySelector(`.media-card[data-id="${id}"]`)?.remove();
                this.updateCount();
            } else alert('Erreur : ' + (data.error || ''));
        } catch(e) { alert('Erreur : ' + e.message); }
    },

    async updateCaption(id, caption) {
        try {
            await App.api('media.php', { action: 'updateCaption', mediaId: id, caption });
        } catch(e) { console.error(e); }
    },

    openPagePicker(btn) {
        this._pendingMedia = {
            id: btn.dataset.id,
            filename: btn.dataset.filename,
            caption: btn.dataset.caption,
        };
        const list = document.getElementById('mediaPagePickerList');
        list.innerHTML = '';
        const total = BOOK_CONFIG.totalPages;
        for (let p = 2; p <= total; p += 2) {
            const opt = document.createElement('button');
            opt.className = 'move-page-option';
            opt.textContent = `Page ${p - 1}–${p}`;
            opt.addEventListener('click', () => {
                document.getElementById('mediaPagePickerOverlay').classList.add('hidden');
                this.addToPage(this._pendingMedia, p);
            });
            list.appendChild(opt);
        }
        document.getElementById('mediaPagePickerOverlay').classList.remove('hidden');
    },

    async addToPage(media, pageNumber) {
        const photoId = 'p_' + Date.now() + '_' + Math.random().toString(36).slice(2);
        const photo = {
            id: photoId,
            mediaId: media.id,
            filename: media.filename,
            caption: media.caption || '',
            captionAlign: 'left',
            rotation: 0,
            filter: 'none',
            frame: { shape: 'rect', borderWidth: 0, borderColor: 'white', ratio: 'original' },
            crop: { fitMode: 'cover', zoom: 1, panX: 0, panY: 0 },
        };
        try {
            const data = await App.api('photos.php', { action: 'addFromMedia', page: pageNumber, photo });
            if (data.success) {
                // Mettre à jour le badge d'usage sur la carte
                this.refreshCardUsage(media.id, pageNumber);
            } else {
                alert('Erreur : ' + (data.error || 'inconnue'));
            }
        } catch(e) { alert('Erreur : ' + e.message); }
    },

    refreshCardUsage(mediaId, addedPage) {
        const card = document.querySelector(`.media-card[data-id="${mediaId}"]`);
        if (!card) return;
        const usageDiv = card.querySelector('.media-usage');
        // Retirer "Non utilisée"
        usageDiv.querySelector('.media-unused')?.remove();
        // Ajouter un label si pas encore là
        if (!usageDiv.querySelector('.media-used-label')) {
            const lbl = document.createElement('span');
            lbl.className = 'media-used-label';
            lbl.textContent = 'Pages :';
            usageDiv.prepend(lbl);
        }
        // Ajouter le lien de page si pas déjà présent
        const existing = [...usageDiv.querySelectorAll('.media-page-link')].map(a => a.dataset.page);
        if (!existing.includes(String(addedPage))) {
            const a = document.createElement('a');
            a.className = 'media-page-link';
            a.href = `?page=editor&num=${addedPage}`;
            a.dataset.page = addedPage;
            a.textContent = `${addedPage - 1}–${addedPage}`;
            usageDiv.appendChild(a);
        }
        // Désactiver le bouton supprimer
        const delBtn = card.querySelector('.media-delete-btn');
        if (delBtn) { delBtn.disabled = true; delBtn.classList.add('disabled'); }
    },

    addCard(m) {
        const grid = document.getElementById('mediaGrid');
        grid.querySelector('.media-empty')?.remove();
        const div = document.createElement('div');
        div.className = 'media-card';
        div.dataset.id = m.id;
        div.innerHTML = `
            <div class="media-thumb"><img src="${BASE_URL}/uploads/photos/${m.filename}" alt="" loading="lazy"></div>
            <div class="media-info">
                <input type="text" class="media-caption-input" value="" placeholder="Légende par défaut…" data-id="${m.id}">
                <div class="media-usage"><span class="media-unused">Non utilisée</span></div>
            </div>
            <button class="media-delete-btn" data-id="${m.id}" title="Supprimer">✕</button>
        `;
        grid.appendChild(div);
        this.updateCount();
    },

    updateCount() {
        const n = document.querySelectorAll('.media-card').length;
        document.querySelector('.media-count').textContent = n + ' photo' + (n > 1 ? 's' : '');
    }
};

document.addEventListener('DOMContentLoaded', () => mediaApp.init());
</script>
