<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(APP_NAME); ?> - <?php echo h($pageTitle ?? 'Album'); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/app.css">
    <?php $book = BookManager::load(); ?>
    <script>
        const BASE_URL = '<?php echo BASE_URL; ?>';
        const BOOK_CONFIG = {
            pageWidth: <?php echo PAGE_WIDTH; ?>,
            pageHeight: <?php echo PAGE_HEIGHT; ?>,
            totalPages: <?php echo intval($book['totalPages'] ?? TOTAL_PAGES); ?>,
            printablePages: <?php echo intval($book['printablePages'] ?? ($book['totalPages'] ?? TOTAL_PAGES)); ?>,
            pageNumberOffset: <?php echo intval($book['properties']['pageNumberOffset'] ?? 0); ?>,
            pdfVersion: <?php echo file_exists(SOURCE_PDF) ? filemtime(SOURCE_PDF) : time(); ?>,
        };
    </script>
</head>
<body>
    <?php
    $currentPage = $_GET['page'] ?? 'gallery';
    $isEditor = ($currentPage === 'editor');
    ?>
    <header class="app-header<?php echo $isEditor ? ' app-header--compact' : ''; ?>">
        <div class="header-content">
            <a href="?page=gallery" class="app-title"><?php echo h(APP_NAME); ?></a>
            <nav class="nav-links">
                <a href="?page=gallery" class="nav-link<?php echo $currentPage === 'gallery' ? ' nav-link--active' : ''; ?>">Livre</a>
                <a href="?page=media"   class="nav-link<?php echo $currentPage === 'media'   ? ' nav-link--active' : ''; ?>">Médiathèque</a>
                <div class="nav-dropdown<?php echo $currentPage === 'options' ? ' nav-link--active' : ''; ?>">
                    <button class="nav-link nav-dropdown-toggle">Options ▾</button>
                    <div class="nav-dropdown-menu">
                        <a href="?page=gallery#props" class="nav-dropdown-item" id="openPropsLink">Paramètres</a>
                        <?php if (IS_LOCAL): ?>
                        <a href="#" class="nav-dropdown-item" id="generateTextPdfBtn">Régénérer PDF texte</a>
                        <a href="#" class="nav-dropdown-item" id="generateFinalPdfBtn">Générer PDF complet</a>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <main class="app-main<?php echo $isEditor ? ' app-main--compact-header' : ''; ?>">
        <?php include $template; ?>
    </main>

    <footer class="app-footer">
        <p>&copy; 2026 projetPhoto</p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
    </script>
    <script src="<?php echo BASE_URL; ?>/js/pdf-viewer.js"></script>
    <script src="<?php echo BASE_URL; ?>/js/app.js"></script>
    <script>
    // Lien "Paramètres" : ouvre le modal si on est sur la galerie, sinon redirige
    document.getElementById('openPropsLink')?.addEventListener('click', function(e) {
        e.preventDefault();
        const modal = document.getElementById('bookPropsModal');
        if (modal) {
            modal.classList.remove('hidden');
        } else {
            window.location.href = '?page=gallery&openProps=1';
        }
    });
    // Auto-ouvrir le modal si redirigé depuis Options
    <?php if (($currentPage ?? '') === 'gallery' && isset($_GET['openProps'])): ?>
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('bookPropsModal')?.classList.remove('hidden');
    });
    <?php endif; ?>
    </script>

    <?php if (IS_LOCAL): ?>
    <!-- Bouton réduit Mode Local -->
    <button id="local-menu-toggle" style="position: fixed; bottom: 20px; right: 20px; width: 50px; height: 50px; border-radius: 50%; background: #007bff; color: white; border: none; font-size: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,123,255,0.4); z-index: 9998; transition: all 0.3s; hover {transform: scale(1.1);}">
        🎯
    </button>

    <!-- Menu étendu Mode Local (caché par défaut) -->
    <div id="local-menu" style="position: fixed; bottom: 20px; right: 20px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.2); z-index: 9999; font-family: system-ui, -apple-system, sans-serif; max-width: 280px; display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0; font-size: 14px; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; flex: 1;">🎯 Mode Local</h3>
            <button id="local-menu-close" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #999; margin-left: 10px;">×</button>
        </div>

        <div style="margin-bottom: 15px;">
            <h4 style="margin: 0 0 8px 0; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Génération PDF</h4>
            <button onclick="localGenerateTextPdf()" style="display: block; width: 100%; padding: 8px; margin: 5px 0; cursor: pointer; border: 1px solid #ddd; background: #f9f9f9; border-radius: 4px; font-size: 12px; transition: all 0.2s;">
                📄 Générer texte.pdf
            </button>
            <button onclick="localCapturePagesDialog()" style="display: block; width: 100%; padding: 8px; margin: 5px 0; cursor: pointer; border: 1px solid #ddd; background: #f9f9f9; border-radius: 4px; font-size: 12px; transition: all 0.2s;">
                📸 Capturer pages photo
            </button>
            <button onclick="localGenerateFinalPdf()" style="display: block; width: 100%; padding: 8px; margin: 5px 0; cursor: pointer; border: 1px solid #ddd; background: #f9f9f9; border-radius: 4px; font-size: 12px; transition: all 0.2s;">
                🔗 Générer PDF final
            </button>
        </div>

        <div>
            <h4 style="margin: 0 0 8px 0; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Synchronisation</h4>
            <button onclick="localUploadTextPdf()" style="display: block; width: 100%; padding: 8px; margin: 5px 0; cursor: pointer; border: 1px solid #ddd; background: #f9f9f9; border-radius: 4px; font-size: 12px; transition: all 0.2s;">
                ⬆️ Upload texte.pdf
            </button>
            <button onclick="localDownloadFromServer()" style="display: block; width: 100%; padding: 8px; margin: 5px 0; cursor: pointer; border: 1px solid #ddd; background: #f9f9f9; border-radius: 4px; font-size: 12px; transition: all 0.2s;">
                ⬇️ Download du serveur
            </button>
            <button onclick="localShowVersions()" style="display: block; width: 100%; padding: 8px; margin: 5px 0; cursor: pointer; border: 1px solid #ddd; background: #f9f9f9; border-radius: 4px; font-size: 12px; transition: all 0.2s;">
                📋 Voir versions
            </button>
        </div>

        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; font-size: 11px; color: #999;">
            APP_MODE: <strong><?php echo APP_MODE; ?></strong>
        </div>
    </div>

    <script>
    // Toggle menu Mode Local
    document.getElementById('local-menu-toggle')?.addEventListener('click', function() {
        const menu = document.getElementById('local-menu');
        const toggle = document.getElementById('local-menu-toggle');
        if (menu.style.display === 'none') {
            menu.style.display = 'block';
            toggle.style.display = 'none';
        }
    });

    document.getElementById('local-menu-close')?.addEventListener('click', function() {
        const menu = document.getElementById('local-menu');
        const toggle = document.getElementById('local-menu-toggle');
        menu.style.display = 'none';
        toggle.style.display = 'flex';
    });
    </script>

    <script>
    const SYNC_TOKEN = '<?php echo SYNC_TOKEN; ?>';
    const API_BASE = '<?php echo BASE_URL; ?>/api';

    async function localGenerateTextPdf() {
        alert('Fonctionnalité en développement: Générer texte.pdf');
        // TODO: Appel à un endpoint de génération
    }

    async function localCapturePagesDialog() {
        alert('Fonctionnalité en développement: Capturer pages');
        // TODO: Ouvrir un dialog de configuration (DPI, etc.)
    }

    async function localGenerateFinalPdf() {
        alert('Fonctionnalité en développement: Générer PDF final');
        // TODO: Appel à un endpoint de merge
    }

    async function localUploadTextPdf() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'application/pdf';
        input.onchange = async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('pdf', file);

            try {
                const response = await fetch(`${API_BASE}/upload-pdf.php?token=${SYNC_TOKEN}`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (response.ok) {
                    alert(`✓ Fichier uploadé\nVersion ${data.version}\nTaille: ${data.size_formatted}`);
                } else {
                    alert(`✗ Erreur: ${data.error}`);
                }
            } catch (e) {
                alert(`✗ Erreur: ${e.message}`);
            }
        };
        input.click();
    }

    async function localDownloadFromServer() {
        alert('Fonctionnalité en développement: Download du serveur');
        // TODO: Récupérer book.json et markdown du serveur
    }

    async function localShowVersions() {
        try {
            const response = await fetch(`${API_BASE}/versions.php?action=list&token=${SYNC_TOKEN}`);
            const data = await response.json();
            if (data.versions && data.versions.length > 0) {
                let msg = `📋 Versions (${data.count}):\n\n`;
                data.versions.slice(0, 5).forEach(v => {
                    msg += `v${String(v.version).padStart(3, '0')} (${v.size_formatted})\n`;
                });
                if (data.count > 5) msg += `... et ${data.count - 5} autres`;
                alert(msg);
            } else {
                alert('Aucune version disponible');
            }
        } catch (e) {
            alert(`✗ Erreur: ${e.message}`);
        }
    }
    </script>
    <?php endif; ?>
</body>
</html>
