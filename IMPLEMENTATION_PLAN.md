# 📋 Plan d'Implémentation - Phase 5 Restructuration

**Document détaillé:** Actions précises à faire pour Phase 5  
**À lire:** Avant de commencer la restructuration

---

## Phase 5.1: Créer Répertoires

### 5.1.1 Créer `tools/`
```
tools/
├── README.md                    (guide utilisation)
├── font-tester/
│   ├── README.md
│   ├── test-fonts.php          (teste différentes polices)
│   ├── test-fonts.html         (aperçu navigateur)
│   └── samples/                (PDFs générés)
│
├── typst-tester/
│   ├── README.md
│   ├── test-styles.typ         (différents styles Typst)
│   ├── generate-test.php       (génère PDFs)
│   └── outputs/
│
└── markdown-tester/
    ├── README.md
    ├── samples/
    │   ├── test-headings.md
    │   ├── test-formatting.md
    │   └── test-special.md
    └── preview.php             (affiche rendu)
```

**Action:** Créer structure répertoires (pas de contenu pour l'instant)

---

### 5.1.2 Restructurer `docs/`
```
docs/
├── README.md                   (index, liste tous les docs)
├── QUICK_START.md             (5 min pour démarrer)
├── STATUS.md                  (pointer vers ce document)
│
├── guides/
│   ├── INSTALLATION.md        (installer localement)
│   ├── CONFIGURATION.md       (config.php, .env)
│   ├── LOCAL_MODE.md          (utiliser en mode local)
│   ├── SERVER_MODE.md         (déployer sur serveur)
│   └── SYNC_WORKFLOW.md       (workflow sync)
│
├── api/
│   ├── endpoints.md           (liste des endpoints)
│   ├── sync.md                (détail /api/sync.php)
│   ├── upload-pdf.md          (détail /api/upload-pdf.php)
│   └── versions.md            (détail /api/versions.php)
│
├── reference/
│   ├── STYLES.md              (styles PDF texte - copier depuis QUICK_STYLES_GUIDE.md)
│   ├── FONTS.md               (polices disponibles)
│   ├── BOOK_JSON_FORMAT.md    (structure book.json)
│   ├── MARKDOWN_FORMAT.md     (format markdown accepté)
│   └── ARCHITECTURE.md        (architecture technique)
│
├── troubleshooting/
│   ├── SETUP_ISSUES.md        (problèmes installation)
│   ├── PDF_GENERATION.md      (problèmes PDF)
│   ├── SYNC_ISSUES.md         (problèmes sync)
│   └── FONT_ISSUES.md         (problèmes polices)
│
└── archived/
    └── (anciens docs si besoin)
```

**Action:** 
1. Créer structure répertoires
2. Copier `projetPhoto/docs/*.md` existants vers nouveaux emplacements
3. Créer fichiers stubs pour les docs manquantes

---

### 5.1.3 Créer `data/` subdirs
```
data/
├── LOCAL_ONLY/                (ne jamais syncer)
│   ├── markdown/
│   │   └── livre.md           (source éditable locale)
│   ├── outputs/
│   │   └── texte.pdf          (généré localement)
│   └── cache/
│       └── (cache local)
│
├── SYNC/                       (sync via FTP: local ↔ serveur)
│   ├── book.json              (positions photos du serveur)
│   ├── photos/                (photos du serveur)
│   └── versions/              (historique book.json)
│
└── trash/
    ├── markdown/
    │   ├── clean/             (ancien répertoire)
    │   └── source/            (ancien répertoire)
    └── pdf/
        ├── source.pdf         (ancien - doublon de texte.pdf)
        └── texte.processed.pdf (ancien - intermédiaire)
```

**Action:** Créer structure vide

---

## Phase 5.2: Nettoyer Données Existantes

### 5.2.1 Déplacer anciens fichiers vers trash/
```bash
# Fichiers à déplacer:
data/pdf/source.pdf                → data/trash/pdf/source.pdf
data/pdf/texte.processed.pdf       → data/trash/pdf/texte.processed.pdf
data/markdown/clean/               → data/trash/markdown/clean/
data/markdown/source/              → data/trash/markdown/source/
```

**Action:** 
1. Copier les fichiers (pas supprimer)
2. Garder la structure dans trash/ pour rollback

### 5.2.2 Déplacer livre.md
```bash
# De:
data/markdown/clean/livre.md

# À:
data/markdown/livre.md
```

**Action:** Copier fichier

### 5.2.3 Organiser cache et screenshots
```bash
# De:
data/pdf-cache/       → À: data/cache/pdf-pages/
data/screenshots/     → À: data/cache/screenshots/
```

**Action:** Renommer répertoires

### 5.2.4 Créer outputs/
```bash
# Créer:
mkdir -p data/outputs/

# Les fichiers suivants iront ici (lors de génération):
data/outputs/texte.pdf        (généré par MarkdownPdfManager)
```

**Action:** Créer répertoire

---

## Phase 5.3: Mettre à Jour Chemins dans Code

### 5.3.1 `app/config/config.php`
**À modifier:**
```php
// AVANT:
define('MARKDOWN_DIR', DATA_ROOT . '/markdown');
define('MARKDOWN_SOURCE_DIR', MARKDOWN_DIR . '/source');
define('MARKDOWN_CLEAN_DIR', MARKDOWN_DIR . '/clean');
define('MARKDOWN_BUILD_DIR', MARKDOWN_DIR . '/build');
define('PDF_CACHE_DIR', DATA_ROOT . '/pdf-cache');

// APRÈS:
define('MARKDOWN_DIR', DATA_ROOT . '/markdown');
// SUPPRIME: MARKDOWN_SOURCE_DIR
// SUPPRIME: MARKDOWN_CLEAN_DIR
define('MARKDOWN_BUILD_DIR', MARKDOWN_DIR . '/build');
define('PDF_CACHE_DIR', DATA_ROOT . '/cache/pdf-pages');
define('SCREENSHOTS_DIR', DATA_ROOT . '/cache/screenshots');
define('OUTPUTS_DIR', DATA_ROOT . '/outputs');
define('SYNC_DIR', DATA_ROOT . '/SYNC');
define('LOCAL_ONLY_DIR', DATA_ROOT . '/LOCAL_ONLY');

// AJOUTER:
define('APP_MODE', getenv('APP_MODE') ?: (PHP_OS_FAMILY === 'Windows' ? 'local' : 'server'));
define('IS_LOCAL', APP_MODE === 'local');
define('IS_SERVER', APP_MODE === 'server');
```

**Fichiers affectés:**
- Tout ce qui utilise MARKDOWN_SOURCE_DIR → supprimer
- Tout ce qui utilise MARKDOWN_CLEAN_DIR → utiliser MARKDOWN_DIR . '/livre.md'
- Tout ce qui utilise PDF_CACHE_DIR → mettre à jour chemin

---

### 5.3.2 `app/src/MarkdownTextManager.php`
**À modifier:**
```php
// AVANT:
private const MARKDOWN_FILE = MARKDOWN_CLEAN_DIR . '/livre.md';

// APRÈS:
private const MARKDOWN_FILE = MARKDOWN_DIR . '/livre.md';
```

---

### 5.3.3 `app/src/MarkdownPdfManager.php`
**À modifier:**
```php
// AVANT:
private const MARKDOWN_FILE = MARKDOWN_CLEAN_DIR . '/livre.md';
private const BUILD_MARKDOWN_FILE = MARKDOWN_BUILD_DIR . '/livre.build.md';
private const RAW_TYPST_FILE = MARKDOWN_BUILD_DIR . '/texte.raw.typ';
// ...
define('SOURCE_PDF', DATA_ROOT . '/pdf/source.pdf');
// ...
copy($pdfPath, SOURCE_PDF);  // ← cette ligne doit changer

// APRÈS:
private const MARKDOWN_FILE = MARKDOWN_DIR . '/livre.md';
private const BUILD_MARKDOWN_FILE = MARKDOWN_BUILD_DIR . '/livre.build.md';
private const RAW_TYPST_FILE = MARKDOWN_BUILD_DIR . '/texte.raw.typ';
// ...
define('SOURCE_PDF', OUTPUTS_DIR . '/texte.pdf');  // ← nouveau chemin
// ...
copy($pdfPath, SOURCE_PDF);  // ← reste pareil, chemin changé par config
```

---

### 5.3.4 `app/src/PdfManager.php`
**À modifier:**
```php
// AVANT:
private static $cache_dir = PDF_CACHE_DIR;
// Utilise PDF_CACHE_DIR partout

// APRÈS:
private static $cache_dir = PDF_CACHE_DIR;  // → data/cache/pdf-pages
// Rien d'autre ne change, config.php gère le chemin
```

---

### 5.3.5 `capture/capture-pages.js`
**À modifier:**
```javascript
// AVANT:
const OUTPUT_DIR = '../data/screenshots';

// APRÈS:
const OUTPUT_DIR = '../data/cache/screenshots';
```

---

### 5.3.6 `capture/merge-hq-pdf-v2.js`
**À modifier:**
```javascript
// AVANT:
const PDF_PATH = '../data/pdf/texte.pdf';
const SCREENSHOTS_DIR = '../data/screenshots';
// ...
const OUTPUT_PDF = 'livre.print.pdf';

// APRÈS:
const PDF_PATH = '../data/outputs/texte.pdf';
const SCREENSHOTS_DIR = '../data/cache/screenshots';
// ...
const OUTPUT_PDF = './livre.print.pdf';  // Reste dans capture/
```

---

### 5.3.7 `app/public/api/sync.php`
**À modifier:**
```php
// AVANT:
if (file_exists(MARKDOWN_CLEAN_DIR . '/livre.md')) {
    $markdownContent = file_get_contents(MARKDOWN_CLEAN_DIR . '/livre.md');
}

// APRÈS:
if (file_exists(MARKDOWN_DIR . '/livre.md')) {
    $markdownContent = file_get_contents(MARKDOWN_DIR . '/livre.md');
}
```

---

## Phase 5.4: Ajouter Mode Local/Serveur

### 5.4.1 Ajouter constantes dans `config.php`
✅ Déjà fait en Phase 5.3.1:
```php
define('APP_MODE', getenv('APP_MODE') ?: (PHP_OS_FAMILY === 'Windows' ? 'local' : 'server'));
define('IS_LOCAL', APP_MODE === 'local');
define('IS_SERVER', APP_MODE === 'server');
```

### 5.4.2 Créer `app/public/api/upload-pdf.php` (NOUVEAU)
**Fichier à créer:**
```php
<?php
// Accepte upload de texte.pdf depuis local
// Protégé par token

require '../../config/config.php';
header('Content-Type: application/json');

if (!IS_LOCAL && !IS_SERVER) {
    http_response_code(400);
    echo json_encode(['error' => 'Mode not set']);
    exit;
}

$token = $_GET['token'] ?? '';
if ($token !== SYNC_TOKEN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_FILES['pdf'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No PDF file provided']);
    exit;
}

$file = $_FILES['pdf'];
$targetPath = OUTPUTS_DIR . '/texte.pdf';

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Upload failed: ' . $file['error']]);
    exit;
}

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save PDF']);
    exit;
}

// Créer version
$versionNum = count(glob(SYNC_DIR . '/versions/book.v*.json')) + 1;
$versionFile = SYNC_DIR . '/versions/book.v' . str_pad($versionNum, 3, '0', STR_PAD_LEFT) . '.json';

echo json_encode([
    'success' => true,
    'message' => 'PDF uploaded successfully',
    'path' => $targetPath,
    'size' => filesize($targetPath),
    'version' => $versionNum
]);
```

### 5.4.3 Créer `app/public/api/versions.php` (NOUVEAU)
**Fichier à créer:**
```php
<?php
// Retourne l'historique des versions de book.json

require '../../config/config.php';
header('Content-Type: application/json');

$token = $_GET['token'] ?? '';
if ($token !== SYNC_TOKEN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $versions = glob(SYNC_DIR . '/versions/book.v*.json');
    rsort($versions);  // Plus récent en premier
    
    $data = [];
    foreach ($versions as $file) {
        $data[] = [
            'file' => basename($file),
            'timestamp' => filemtime($file),
            'size' => filesize($file)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'versions' => $data
    ]);
}

if ($action === 'get') {
    $version = $_GET['version'] ?? '';  // Ex: book.v001.json
    $file = SYNC_DIR . '/versions/' . basename($version);
    
    if (!file_exists($file)) {
        http_response_code(404);
        echo json_encode(['error' => 'Version not found']);
        exit;
    }
    
    header('Content-Type: application/json');
    readfile($file);
}
```

### 5.4.4 Ajouter menus UI dans templates
**Fichier: `app/public/editor.php`** et **`app/public/text-editor.php`**

Ajouter à la fin avant `</body>`:
```php
<?php if (IS_LOCAL): ?>
<div id="local-menu" style="position: fixed; bottom: 20px; right: 20px; background: #f0f0f0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 1000;">
  <h3>🎯 Mode Local</h3>
  
  <div style="margin-bottom: 15px;">
    <h4>Génération PDF</h4>
    <button onclick="generateTextPdf()" style="display: block; width: 100%; padding: 8px; margin: 5px 0; cursor: pointer;">
      📄 Générer texte.pdf
    </button>
    <button onclick="capturePages()" style="display: block; width: 100%; padding: 8px; margin: 5px 0; cursor: pointer;">
      📸 Capturer pages photo
    </button>
    <button onclick="generateFinalPdf()" style="display: block; width: 100%; padding: 8px; margin: 5px 0; cursor: pointer;">
      🔗 Générer PDF final
    </button>
  </div>
  
  <div>
    <h4>Synchronisation</h4>
    <button onclick="uploadTextPdf()" style="display: block; width: 100%; padding: 8px; margin: 5px 0; cursor: pointer;">
      ⬆️ Upload texte.pdf
    </button>
    <button onclick="downloadFromServer()" style="display: block; width: 100%; padding: 8px; margin: 5px 0; cursor: pointer;">
      ⬇️ Download du serveur
    </button>
    <button onclick="showVersions()" style="display: block; width: 100%; padding: 8px; margin: 5px 0; cursor: pointer;">
      📋 Voir versions
    </button>
  </div>
</div>

<script>
function generateTextPdf() {
  alert('Générer texte.pdf...');
  // Appel API
}

function capturePages() {
  alert('Capturer pages...');
  // Appel API
}

function generateFinalPdf() {
  alert('Générer PDF final...');
  // Appel API
}

function uploadTextPdf() {
  alert('Upload texte.pdf...');
  // Upload vers /api/upload-pdf.php
}

function downloadFromServer() {
  alert('Download du serveur...');
  // Download depuis /api/sync.php
}

function showVersions() {
  alert('Versions...');
  // Afficher /api/versions.php
}
</script>
<?php endif; ?>
```

---

## Phase 5.5: Créer Tools (contenu)

### 5.5.1 `tools/font-tester/test-fonts.php`
**À créer:** PHP qui génère PDF de test avec différentes polices

### 5.5.2 `tools/typst-tester/test-styles.typ`
**À créer:** Fichier Typst avec différents styles (H1, H2, couleurs, etc.)

### 5.5.3 `tools/markdown-tester/samples/`
**À créer:** Fichiers markdown d'exemple

---

## Phase 5.6: Documenter et .gitignore

### 5.6.1 Créer `.gitignore` complet
```
# Données et caches
data/LOCAL_ONLY/
data/SYNC/
data/cache/
data/outputs/
data/trash/
data/markdown/build/

# Configuration locale
capture/.env
.env

# Node modules
node_modules/

# Éditeurs
.vscode/
.idea/

# OS
.DS_Store
Thumbs.db

# Anciens fichiers
pdf/
screenshots/
```

### 5.6.2 Mettre à jour `docs/README.md`
**Pointer vers:**
- guides/
- api/
- reference/
- troubleshooting/

---

## Checklist d'Implémentation

- [ ] Phase 5.1 - Créer répertoires
- [ ] Phase 5.2 - Nettoyer données
- [ ] Phase 5.3 - Mettre à jour chemins (7 fichiers)
- [ ] Phase 5.4 - Ajouter mode local/serveur
  - [ ] 5.4.1 - config.php (constantes)
  - [ ] 5.4.2 - Créer upload-pdf.php
  - [ ] 5.4.3 - Créer versions.php
  - [ ] 5.4.4 - Ajouter menus UI
- [ ] Phase 5.5 - Créer tools
- [ ] Phase 5.6 - Documenter + .gitignore

---

## Notes

- Tous les chemins changent de `MARKDOWN_CLEAN_DIR` à `MARKDOWN_DIR`
- `SOURCE_PDF` pointe maintenant à `OUTPUTS_DIR` au lieu de `PDF_DIR`
- Créer `SYNC_DIR` et `LOCAL_ONLY_DIR` dans config.php
- Bien tester en local ET sur serveur après chaque phase

**Durée estimée:** 4-6 heures pour tout faire

