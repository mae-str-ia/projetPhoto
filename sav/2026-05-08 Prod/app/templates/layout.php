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
                <div class="nav-dropdown<?php echo in_array($currentPage, ['options','export']) ? ' nav-link--active' : ''; ?>">
                    <button class="nav-link nav-dropdown-toggle">Options ▾</button>
                    <div class="nav-dropdown-menu">
                        <a href="?page=gallery#props" class="nav-dropdown-item" id="openPropsLink">Paramètres</a>
                        <a href="#" class="nav-dropdown-item" id="exportBtn">Exporter Word</a>
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
</body>
</html>
