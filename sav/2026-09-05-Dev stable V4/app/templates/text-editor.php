<?php
$pageTitle = 'Edition texte';
$pageNumber = isset($_GET['num']) ? intval($_GET['num']) : 1;

$book = BookManager::load();
$totalPages = $book['totalPages'] ?? TOTAL_PAGES;
if ($pageNumber < 1 || $pageNumber > $totalPages) {
    http_response_code(400); echo "Page invalide"; exit;
}

if ($pageNumber % 2 === 0) {
    $pageNumber = max(1, $pageNumber - 1);
}

$offset = $book['properties']['pageNumberOffset'] ?? 0;
$spreadNumber = max(1, intdiv($pageNumber - 1, 2));
?>

<script>
const TEXT_PAGE_NUMBER = <?php echo intval($pageNumber); ?>;
const TEXT_PAGE_DISPLAY = <?php echo intval($pageNumber + $offset); ?>;
const TEXT_SPREAD_NUMBER = <?php echo intval($spreadNumber); ?>;
</script>

<div class="text-editor-shell">
    <div class="text-editor-toolbar">
        <div class="text-editor-title">
            <strong>Page texte <?php echo h($pageNumber + $offset); ?></strong>
            <span id="textEditorMeta">Chargement...</span>
        </div>
        <div class="text-editor-actions">
            <a href="?page=gallery" class="toolbar-button" id="backToGallery">Livre</a>
            <button class="toolbar-button" id="previousBlockBtn">Bloc précédent</button>
            <button class="toolbar-button" id="nextBlockBtn">Bloc suivant</button>
            <button class="toolbar-button" id="loadPreviousTextBtn">Afficher précédent</button>
            <button class="toolbar-button" id="loadNextTextBtn">Afficher suivant</button>
            <button class="toolbar-button" id="reloadTextBtn">Recharger</button>
            <button class="toolbar-button" id="saveTextBtn">Enregistrer</button>
            <button class="toolbar-button text-primary-button" id="saveGenerateTextBtn">Enregistrer + PDF</button>
        </div>
    </div>

    <div class="text-editor-warning">
        L'extrait est approximatif tant que la carte de pagination n'est pas generee. Il couvre une zone autour de la page courante.
    </div>

    <div class="text-context-panel hidden" id="previousTextPanel">
        <div class="text-context-header">
            <span>Texte précédent</span>
            <button class="text-context-close" data-target="previousTextPanel">Masquer</button>
        </div>
        <textarea id="previousTextContext" class="markdown-context" readonly></textarea>
    </div>

    <textarea id="markdownEditor" class="markdown-editor" spellcheck="true"></textarea>

    <div class="text-context-panel hidden" id="nextTextPanel">
        <div class="text-context-header">
            <span>Texte suivant</span>
            <button class="text-context-close" data-target="nextTextPanel">Masquer</button>
        </div>
        <textarea id="nextTextContext" class="markdown-context" readonly></textarea>
    </div>
</div>

<script>
const TextEditor = {
    state: null,

    init() {
        document.getElementById('reloadTextBtn')?.addEventListener('click', () => this.load());
        document.getElementById('previousBlockBtn')?.addEventListener('click', () => this.loadAdjacent('previous'));
        document.getElementById('nextBlockBtn')?.addEventListener('click', () => this.loadAdjacent('next'));
        document.getElementById('loadPreviousTextBtn')?.addEventListener('click', () => this.loadContext('previous'));
        document.getElementById('loadNextTextBtn')?.addEventListener('click', () => this.loadContext('next'));
        document.getElementById('saveTextBtn')?.addEventListener('click', () => this.save(false));
        document.getElementById('saveGenerateTextBtn')?.addEventListener('click', () => this.save(true));
        document.querySelectorAll('.text-context-close').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById(btn.dataset.target)?.classList.add('hidden');
            });
        });
        document.getElementById('backToGallery')?.addEventListener('click', () => {
            sessionStorage.setItem('gallery_focus_spread', TEXT_SPREAD_NUMBER);
            sessionStorage.setItem('gallery_zoom', '1');
        });
        this.load();
    },

    async load() {
        this.setBusy(true, 'Chargement...');
        try {
            const data = await App.api('markdown.php', {
                action: 'getExcerpt',
                page: TEXT_PAGE_NUMBER,
            });
            if (!data.success) throw new Error(data.error || 'Erreur inconnue');
            this.state = data.excerpt;
            document.getElementById('markdownEditor').value = this.state.excerpt || '';
            document.getElementById('previousTextPanel')?.classList.add('hidden');
            document.getElementById('nextTextPanel')?.classList.add('hidden');
            this.updateMeta();
        } catch (e) {
            alert('Erreur: ' + e.message);
        } finally {
            this.setBusy(false);
        }
    },

    async loadContext(direction) {
        if (!this.state) return;
        this.setBusy(true, direction === 'previous' ? 'Chargement du texte precedent...' : 'Chargement du texte suivant...');
        try {
            const data = await App.api('markdown.php', {
                action: 'getContext',
                start: this.state.start,
                end: this.state.end,
                direction,
            });
            if (!data.success) throw new Error(data.error || 'Erreur inconnue');
            const isPrevious = direction === 'previous';
            const panel = document.getElementById(isPrevious ? 'previousTextPanel' : 'nextTextPanel');
            const textarea = document.getElementById(isPrevious ? 'previousTextContext' : 'nextTextContext');
            textarea.value = data.context.text || '';
            panel.classList.toggle('hidden', !data.context.text);
            if (!data.context.text) {
                App.notify(isPrevious ? 'Aucun texte precedent' : 'Aucun texte suivant', 'info');
            }
        } catch (e) {
            alert('Erreur: ' + e.message);
        } finally {
            this.setBusy(false);
        }
    },

    async loadAdjacent(direction) {
        if (!this.state) return;
        if (document.getElementById('markdownEditor').value !== (this.state.excerpt || '')) {
            const ok = confirm('Le bloc courant a ete modifie. Changer de bloc sans enregistrer ?');
            if (!ok) return;
        }

        this.setBusy(true, direction === 'previous' ? 'Chargement du bloc precedent...' : 'Chargement du bloc suivant...');
        try {
            const data = await App.api('markdown.php', {
                action: 'getAdjacentExcerpt',
                start: this.state.start,
                end: this.state.end,
                direction,
            });
            if (!data.success) throw new Error(data.error || 'Erreur inconnue');
            if (!data.hasExcerpt || !data.excerpt) {
                App.notify(direction === 'previous' ? 'Aucun bloc precedent' : 'Aucun bloc suivant', 'info');
                return;
            }
            this.state = data.excerpt;
            document.getElementById('markdownEditor').value = this.state.excerpt || '';
            document.getElementById('previousTextPanel')?.classList.add('hidden');
            document.getElementById('nextTextPanel')?.classList.add('hidden');
            this.updateMeta();
        } catch (e) {
            alert('Erreur: ' + e.message);
        } finally {
            this.setBusy(false);
        }
    },

    async save(regenerate) {
        if (!this.state) return;
        const label = regenerate ? 'Enregistrement et generation PDF...' : 'Enregistrement...';
        this.setBusy(true, label);
        try {
            const data = await App.api('markdown.php', {
                action: 'saveExcerpt',
                start: this.state.start,
                end: this.state.end,
                text: document.getElementById('markdownEditor').value,
                regenerate,
            });
            if (!data.success) throw new Error(data.error || 'Erreur inconnue');
            App.notify(regenerate ? 'Texte enregistre et PDF regenere' : 'Texte enregistre', 'success');
            if (regenerate) {
                window.setTimeout(() => window.location.reload(), 500);
            } else {
                await this.load();
            }
        } catch (e) {
            alert('Erreur: ' + e.message);
        } finally {
            this.setBusy(false);
        }
    },

    updateMeta() {
        const meta = document.getElementById('textEditorMeta');
        if (!meta || !this.state) return;
        meta.textContent = `page texte ${this.state.textPageIndex}/${this.state.textPageCount}, caracteres ${this.state.start}-${this.state.end}`;
    },

    setBusy(isBusy, label = '') {
        document.querySelectorAll('#reloadTextBtn, #previousBlockBtn, #nextBlockBtn, #loadPreviousTextBtn, #loadNextTextBtn, #saveTextBtn, #saveGenerateTextBtn').forEach(btn => {
            btn.disabled = isBusy;
        });
        const meta = document.getElementById('textEditorMeta');
        if (isBusy && meta) meta.textContent = label;
    },
};

document.addEventListener('DOMContentLoaded', () => TextEditor.init());
</script>

<style>
.text-editor-shell {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.text-editor-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 0.75rem;
}
.text-editor-title {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.text-editor-title span {
    color: #777;
    font-size: 0.8rem;
}
.text-editor-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}
.text-primary-button {
    background: #007bff;
    border-color: #007bff;
    color: white;
}
.text-primary-button:hover {
    background: #0056b3;
}
.text-editor-warning {
    background: #fff8e5;
    border: 1px solid #f0d58a;
    border-radius: 6px;
    padding: 0.65rem 0.75rem;
    color: #6f5200;
    font-size: 0.9rem;
}
.markdown-editor {
    width: 100%;
    min-height: calc(100vh - 230px);
    resize: vertical;
    border: 1px solid #ccc;
    border-radius: 6px;
    padding: 1rem;
    font-family: Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 0.95rem;
    line-height: 1.55;
    background: white;
    color: #222;
}
.text-context-panel {
    border: 1px solid #ddd;
    border-radius: 6px;
    background: white;
    overflow: hidden;
}
.text-context-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.45rem 0.75rem;
    background: #f7f7f7;
    border-bottom: 1px solid #e5e5e5;
    font-size: 0.85rem;
    font-weight: 700;
    color: #555;
}
.text-context-close {
    border: 1px solid #ccc;
    background: white;
    border-radius: 4px;
    padding: 0.2rem 0.5rem;
    cursor: pointer;
    font-size: 0.8rem;
}
.markdown-context {
    width: 100%;
    min-height: 170px;
    resize: vertical;
    border: 0;
    padding: 0.85rem 1rem;
    font-family: Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 0.88rem;
    line-height: 1.5;
    background: #fafafa;
    color: #555;
}
.markdown-context:focus {
    outline: none;
}
.markdown-editor:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.12);
}
button:disabled {
    opacity: 0.55;
    cursor: wait;
}
</style>
