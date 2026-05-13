# ProjetPhoto - Recommandations d'Amélioration Priorisées

## 📊 Matrice Priorité / Effort

```
        EFFORT
         ↑
    FACILE | MODÉRÉ | DIFFICILE
         |         |
 CRITIQUE├─────────┼─────────┐
         | ✅ 1,2  | 🔧 3,4  | 🚀 5
         |         |         |
  IMPORTANT├─────────┼─────────┤
         | 6,7,8  | 9,10,11 | 12
         |         |         |
    UTILE ├─────────┼─────────┤
         | 13,14  | 15,16   | 17,18
         |         |         |
         └─────────┴─────────┘
```

---

## 🔴 CRITIQUE - À FAIRE EN PREMIER

### 1. ✅ **Intégrer Pipeline PDF Pages Photo dans l'API**

**Statut:** ✅ Code existe (capture-pages.js + merge-hq-pdf-v2.js)  
**Problème:** ❌ Lancement manuel, pas d'intégration API, pas de UI

**Pourquoi:** 
- Pipeline complet existe mais pas accessible depuis l'interface
- Utilisateur doit lancer 2 commandes Node manuellement
- Processus long (12-15 min) sans feedback
- Pas de gestion d'erreurs propre

**Solution recommandée: Wrapper PHP pour orchestration**

```php
// app/public/api/export.php - Ajouter action

class ExportManager {
    public static function generateFinalPdf() {
        // Vérifier fichiers nécessaires
        if (!file_exists(SOURCE_PDF)) {
            throw new Exception('source.pdf manquant');
        }
        
        // Lancer capture-pages.js en background
        $captureCmd = 'cd ../capture && node capture-pages.js 2>&1';
        exec($captureCmd, $output, $return);
        if ($return !== 0) {
            Logger::error('Capture failed', ['output' => $output]);
            throw new Exception('Capture pages échouée');
        }
        
        // Lancer merge-hq-pdf-v2.js
        $mergeCmd = 'cd ../capture && node merge-hq-pdf-v2.js 2>&1';
        exec($mergeCmd, $output, $return);
        if ($return !== 0) {
            throw new Exception('Merge PDF échoué');
        }
        
        // Retourner PDF généré
        return [
            'success' => true,
            'pdf' => '/livre.print.pdf',
            'pages' => countPages(),
            'fileSize' => filesize(ROOT_DIR . '/../livre.print.pdf'),
            'time' => '~15 minutes'
        ];
    }
}
```

**Effort:** ⭐⭐⭐ (2-3 jours)
- Wrapper PHP pour lancer Node.js
- Gestion du timeout long (12-15 min)
- Feedback utilisateur (progress, logs)
- Gestion d'erreurs + logging
- Bouton UI

**Bénéfice:** ⭐⭐⭐⭐⭐ (Déblocage complet, produit livrable)

---

### 2. ✅ **Ajouter Bouton "Générer PDF Final" à l'Interface**

**Prérequis:** Intégration API complétée (#1)

**Solution: Bouton UI + Feedback long process**

```html
<!-- Templates/view.php ou layout.php -->
<button id="generateFinalPdfBtn" class="btn btn-primary">
  🖨️ Générer PDF Final
</button>

<div id="pdfProgress" style="display: none;">
  <p id="progressMsg">Capture des pages photo (étape 1/2)...</p>
  <progress id="progressBar" value="0" max="100"></progress>
  <p id="elapsedTime">Temps: 0s</p>
</div>
```

```javascript
// public/js/app.js
document.getElementById('generateFinalPdfBtn').addEventListener('click', async () => {
    const progressDiv = document.getElementById('pdfProgress');
    const msgEl = document.getElementById('progressMsg');
    const startTime = Date.now();
    let elapsedInterval;
    
    try {
        progressDiv.style.display = 'block';
        
        // Appeler API avec long timeout
        const response = await fetch('/api/export.php?action=generateFinalPdf', {
            method: 'POST',
            timeout: 20 * 60 * 1000  // 20 minutes max
        });
        
        const data = await response.json();
        
        if (!data.success) {
            msgEl.textContent = '❌ Erreur: ' + data.error;
            return;
        }
        
        msgEl.textContent = '✅ PDF généré!';
        document.getElementById('progressBar').value = 100;
        
        // Télécharger
        window.location = data.pdf;
        
    } finally {
        clearInterval(elapsedInterval);
        // Bouton redevient cliquable après 5s
        setTimeout(() => {
            progressDiv.style.display = 'none';
        }, 5000);
    }
});
```

**Effort:** ⭐⭐ (1 jour)
- HTML + CSS pour UI
- JavaScript pour lancer API + feedback
- Gestion timeout long (WebSocket ou polling optionnel)
- Intégration avec API #1

**Bénéfice:** ⭐⭐⭐⭐ (Utilisateur peut générer depuis interface)

---

### 3. 🔧 **Gérer le Processus Long (12-15 minutes)**

**Problème:** generateFinalPdf() prend 12-15 minutes. Sans feedback, utilisateur pense que ça a planté.

**Solutions possibles (par priorité):**

#### Option A: Simple Polling (Recommandée pour MVP)
```php
// POST /api/export.php?action=getPdfStatus
// Retourne: { status: 'generating', progress: 40, eta: '8 min' }

// public/js/app.js
async function pollStatus() {
    const response = await fetch('/api/export.php?action=getPdfStatus');
    const data = await response.json();
    
    if (data.status === 'complete') {
        window.location = data.pdf;
    } else if (data.status === 'generating') {
        document.getElementById('progressMsg').textContent = 
            `Capture page ${data.currentPage}/${data.totalPages}...`;
        document.getElementById('progressBar').value = data.progress;
    }
    
    // Repoll toutes les 2 secondes
    setTimeout(pollStatus, 2000);
}
```

#### Option B: WebSocket (Meilleur UX)
```javascript
// Streaming progress en temps réel
// Besoin: composer require textalk/websocket
// Plus complexe mais UX excellent
```

#### Option C: Background Task Queue (Production)
```php
// Utiliser: composer require symfony/messenger
// Ajouter job GeneratePdfJob à queue
// Retourner job ID, user peut checker status plus tard
```

**Effort:**
- Option A (Polling): ⭐⭐ (2h)
- Option B (WebSocket): ⭐⭐⭐ (1 jour)
- Option C (Queue): ⭐⭐⭐ (1-2 jours)

**Recommandation:** Commencer par Option A (simple + efficace), évoluer vers B/C en production

**Bénéfice:** ⭐⭐⭐⭐ (UX pro, utilisateur sait que ça fonctionne)

---

### 4. 🔧 **Ajouter tests unitaires complets**

**Pourquoi:** Aucun test actuellement. Risque de régression très élevé.

**Structure recommandée:**
```bash
projetPhoto/app/tests/
├── Unit/
│   ├── BookManagerTest.php
│   ├── MarkdownTextManagerTest.php
│   ├── PhotoManagerTest.php
│   └── PdfManagerTest.php
└── Integration/
    ├── ApiPhotosTest.php
    └── MarkdownPipelineTest.php
```

**Tests critiques à couvrir:**
```php
// BookManagerTest.php
testMigrateToMedia()                 // Conversion legacy
testGetSpread()                      // Spread logic
testInsertSpread()                   // Renumbering
testDeleteSpread()                   // Force delete

// MarkdownTextManagerTest.php
testGetExcerptForPage()              // Positioning algo
testSaveExcerpt()                    // File handling

// MarkdownPdfManager (logique complexe!)
testGenerateTextPdfThreePasses()     // Full pipeline
testBuildFinalPageMap()              // Page mapping
testDetectBlankPages()               // Blank detection
```

**Configuration:**
```bash
cd app
composer require --dev phpunit/phpunit
vendor/bin/phpunit
```

**Effort:** ⭐⭐⭐ (4 jours - tests complets)
- Fixtures et setup
- Mocking outils externes (Pandoc, Typst)
- Integration tests réels

**Bénéfice:** ⭐⭐⭐⭐ (confiance = refactoring sûr)

---

### 5. 🔧 **Refactoriser post-processing PDF en PHP**

**Problème actuel:**
```
MarkdownPdfManager (PHP) → exec() → pdf_postprocess.py (Python)
```

Logique métier dispersée, dépendance Python en dur.

**Solution: Utiliser qpdf en PHP**

```php
// Remplacer pdf_postprocess.py par:
class PdfPostProcessor {
    public static function insertBlankPages(
        $inputPdf,
        $outputPdf,
        array $pagesToInsertAfter  // [5 => 'blank', 10 => 'blank']
    ) {
        // Utiliser qpdf pour insérer pages blanches
        // Ou: PhpPdf, Spatie\Pdf, etc.
    }
}
```

**Avantages:**
- Logique métier en une langue (PHP)
- Dépendance unique (qpdf via PATH)
- Pas de chemin hardcodé Python
- Easier to test

**Effort:** ⭐⭐⭐ (2 jours)
- Étudier qpdf CLI
- Implémenter wrapper PHP
- Tester avec vrais PDFs

**Bénéfice:** ⭐⭐⭐ (maintenabilité +50%)

---

## 🟡 IMPORTANT - À FAIRE RAPIDEMENT

### 5. 🚀 **Implémenter caching pour book.json**

**Problème:** `BookManager::load()` relit JSON complet à chaque requête.

**Impact sur 364 pages:**
- Lecture disque : ~5-10ms par requête
- × 100 requêtes/session = 500-1000ms perdu
- Avec photos : 1000+ entrées = parsing JSON lent

**Solution simple: APCu ou file-based cache**

```php
class BookManager {
    const CACHE_KEY = 'projet_photo_book_v1';
    const CACHE_TTL = 60;  // 1 minute

    public static function load() {
        // ✅ APCu cache (si disponible)
        if (function_exists('apcu_fetch')) {
            $cached = apcu_fetch(self::CACHE_KEY);
            if ($cached !== false) return $cached;
        }

        // Fallback : file-based simple cache
        $cacheFile = DATA_ROOT . '/.book.cache.json';
        if (file_exists($cacheFile) && 
            filemtime($cacheFile) > time() - self::CACHE_TTL) {
            return json_decode(file_get_contents($cacheFile), true);
        }

        // Load + cache
        $data = readJsonFile(BOOK_JSON);
        $data = self::withDefaultProperties($data);
        $data = self::migrateToMedia($data);

        if (function_exists('apcu_store')) {
            apcu_store(self::CACHE_KEY, $data, self::CACHE_TTL);
        }
        file_put_contents($cacheFile, json_encode($data));

        return $data;
    }

    public static function save($data) {
        ensureDir(DATA_DIR);
        writeJsonFile(BOOK_JSON, $data);
        
        // ✅ Invalider cache
        if (function_exists('apcu_delete')) {
            apcu_delete(self::CACHE_KEY);
        }
        @unlink(DATA_ROOT . '/.book.cache.json');
    }
}
```

**Résultat:**
- Premier load : 10ms (disque)
- Loads suivants : <1ms (APCu)
- 10× plus rapide en moyenne

**Effort:** ⭐ (1 heure)

**Bénéfice:** ⭐⭐⭐⭐ (ressenti 10× plus rapide)

---

### 6. ✅ **Logging centralisé**

**Problème:** Aucun log. Erreurs visibles que via réponse JSON.

**Solution simple:**

```php
// src/Logger.php
class Logger {
    const LOG_FILE = DATA_ROOT . '/logs/app.log';
    const ERROR_LOG = DATA_ROOT . '/logs/error.log';

    public static function init() {
        if (!is_dir(dirname(self::LOG_FILE))) {
            mkdir(dirname(self::LOG_FILE), 0755, true);
        }
    }

    public static function info($message, array $context = []) {
        self::write(self::LOG_FILE, 'INFO', $message, $context);
    }

    public static function error($message, array $context = []) {
        self::write(self::ERROR_LOG, 'ERROR', $message, $context);
        error_log($message); // Aussi en PHP error_log
    }

    private static function write($file, $level, $message, $context) {
        $line = json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? null,
        ]) . "\n";
        file_put_contents($file, $line, FILE_APPEND);
    }
}

// config.php
Logger::init();

// Utilisation
try {
    $book = BookManager::load();
} catch (Exception $e) {
    Logger::error('Failed to load book', ['error' => $e->getMessage()]);
}
```

**Effort:** ⭐ (30 minutes)

**Bénéfice:** ⭐⭐⭐ (debug 100× plus facile)

---

### 7. ✅ **Validation côté client HTML5**

**Problème:** Pas de validation. Mauvais inputs envoyés au serveur.

**Solution:**

```html
<!-- public/js/validation.js -->
function validatePhotoForm(formData) {
    const errors = [];
    
    if (!formData.file) errors.push('Fichier requis');
    if (!formData.page || formData.page < 1 || formData.page > 364) {
        errors.push('Page invalide');
    }
    if (formData.file?.size > 50 * 1024 * 1024) {
        errors.push('Fichier trop gros (max 50MB)');
    }
    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowed.includes(formData.file?.type)) {
        errors.push('Type fichier invalide');
    }
    
    return errors;
}

// Avant POST
const errors = validatePhotoForm(formData);
if (errors.length) {
    showErrorMessage(errors.join('\n'));
    return;
}

// Proceed avec POST
fetch(...)
```

**Effort:** ⭐⭐ (2 heures)

**Bénéfice:** ⭐⭐⭐ (erreurs claires, UX meilleure)

---

### 8. ✅ **Feedback utilisateur pour opérations longues**

**Problème:** Générationen PDF prend 5-10 secondes. Utilisateur ne sait pas ce qui se passe.

**Solution:**

```javascript
// public/js/app.js
async function generateTextPdf() {
    const btn = document.getElementById('generateTextPdfBtn');
    const original = btn.innerHTML;
    
    try {
        btn.disabled = true;
        btn.innerHTML = '⏳ Génération en cours...';
        showProgressBar(0);

        const data = await this.api('markdown.php', {
            action: 'generateTextPdf',
            copyToSource: true,
        });

        if (!data.success) throw new Error(data.error);

        showSuccessToast('PDF texte généré!');
        refreshPdfPreview();
    } catch (err) {
        showErrorToast(err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
        hideProgressBar();
    }
}
```

**Effort:** ⭐ (1 heure)

**Bénéfice:** ⭐⭐⭐ (UX pro)

---

### 9. 🔧 **Sécurité fichiers: Valider chemins**

**Problème actuel:**
```php
// RISQUE: Injection si $filename vient de l'user
$path = UPLOAD_DIR . '/' . $_GET['filename'];
```

**Solution:**

```php
class FileValidator {
    public static function validatePhotoFilename($filename) {
        if (!preg_match('/^photo_[a-f0-9]{16}\.\d+\.(jpg|jpeg|png|webp)$/i', $filename)) {
            throw new Exception('Invalid filename format');
        }
        
        // Vérifier le fichier existe et est dans le bon répertoire
        $path = realpath(UPLOAD_DIR . '/' . $filename);
        $uploadDir = realpath(UPLOAD_DIR);
        
        if (!$path || strpos($path, $uploadDir) !== 0) {
            throw new Exception('File not in upload directory');
        }
        
        return $path;
    }
}

// Utilisation
$validPath = FileValidator::validatePhotoFilename($_GET['filename']);
$image = imagecreatefrompng($validPath);
```

**Effort:** ⭐ (30 minutes)

**Bénéfice:** ⭐⭐⭐ (sécurité critique)

---

### 10. 🔧 **Documentation code : Docblocks PHPDoc**

**Problème:** Classe `MarkdownPdfManager` = 1000 lignes. Logique complexe. Peu de comments.

**Solution:**

```php
/**
 * Génère un PDF texte haute qualité depuis un fichier Markdown unique.
 * 
 * Utilise un processus en 3 passes pour assurer:
 * 1. Extraction des métadonnées (titres, pages)
 * 2. Génération de la table des matières
 * 3. Rendu final avec numérotation et en-têtes courrants
 * 
 * Pipeline: Markdown → Pandoc → Typst → PDF → Python post-processing
 * 
 * @example
 *     $result = MarkdownPdfManager::generateTextPdf(true);
 *     echo "PDF généré: " . $result['pdf'];
 * 
 * @param bool $copyToSource Si true, copie le PDF final à source.pdf
 * @return array ['pdf' => path, 'finalPageCount' => int, 'sourcePdfUpdated' => bool]
 * @throws Exception Si outils manquants (pandoc, typst) ou autres erreurs
 */
public static function generateTextPdf($copyToSource = true) {
    // ...
}
```

**Effort:** ⭐⭐ (4 heures pour couverture complète)

**Bénéfice:** ⭐⭐⭐ (onboarding 5× plus rapide)

---

## 🟢 NICE-TO-HAVE

### 11. **Drag-and-drop photos**

```javascript
// photo-uploader.js
const dropZone = document.getElementById('photo-grid');

dropZone.addEventListener('dragover', e => {
    e.preventDefault();
    dropZone.classList.add('drag-over');
});

dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    
    const files = e.dataTransfer.files;
    for (const file of files) {
        uploadPhoto(file, currentPage);
    }
});
```

**Effort:** ⭐ (1 heure)  
**Bénéfice:** ⭐⭐

---

### 12. **Versioning & Undo**

```php
class BookVersionManager {
    public static function save($data) {
        $current = BookManager::load();
        $version = [
            'timestamp' => time(),
            'data' => $current,
            'hash' => md5(json_encode($current)),
        ];
        
        // Garder derniers 20 versions
        $history = json_decode(file_get_contents(HISTORY_FILE), true) ?: [];
        array_unshift($history, $version);
        $history = array_slice($history, 0, 20);
        
        file_put_contents(HISTORY_FILE, json_encode($history));
        BookManager::save($data);
    }
    
    public static function rollback($versionIndex) {
        $history = json_decode(file_get_contents(HISTORY_FILE), true);
        BookManager::save($history[$versionIndex]['data']);
    }
}
```

**Effort:** ⭐⭐ (3 heures)  
**Bénéfice:** ⭐⭐⭐

---

### 13. **Animations CSS (pour UX)**

```css
/* public/css/animations.css */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal { animation: slideInUp 0.3s ease-out; }

@keyframes spin {
    to { transform: rotate(360deg); }
}

.spinner { animation: spin 1s linear infinite; }
```

**Effort:** ⭐ (1 heure)  
**Bénéfice:** ⭐⭐

---

### 14. **API: Batch operations**

```php
// POST /api/photos.php?action=batchUpdate
{
  "operations": [
    {"photoId": 1, "rotation": 90},
    {"photoId": 2, "filter": "sepia"},
    {"photoId": 3, "action": "delete"}
  ]
}
```

**Effort:** ⭐⭐ (2 heures)  
**Bénéfice:** ⭐⭐

---

## 📋 Checklist Implémentation

```markdown
## Phase 1 : Intégration PDF (Semaine 1-2) ⭐ CRITIQUE
- [ ] #1: API wrapper pour lancer capture-pages.js + merge-hq-pdf-v2.js
- [ ] #2: Bouton "Générer PDF final" à l'interface
- [ ] #3: Gestion processus long (polling + feedback)
- [ ] Tester workflow complet: UI → API → Node.js → livre.print.pdf

## Phase 2 : Fondations robustes (Semaine 3)
- [ ] #4: Tests unitaires basiques
- [ ] #6: Logging centralisé
- [ ] #7: Validation côté client
- [ ] #9: Sécurité chemins fichiers

## Phase 3 : Performance & Maintenance (Semaine 4)
- [ ] #5: Caching book.json
- [ ] #5bis: Refactoriser post-processing (optionnel - déjà en Node)
- [ ] #10: Documentation code complète
- [ ] Tests d'intégration

## Phase 4 : UX & Polish (Semaine 5+)
- [ ] #8: Feedback utilisateur (spinners pour opérations longues)
- [ ] #11: Drag-and-drop
- [ ] #12: Versioning/Undo
- [ ] #13-18: Animations CSS, batch ops, etc.
```

---

## Outils Recommandés

### Obligatoires (si générer PDF photo)
```bash
npm install puppeteer
npm install html2pdf
```

### Recommandés
```bash
# Tests
composer require --dev phpunit/phpunit

# Logging
composer require monolog/monolog

# Validation
composer require respect/validation

# PDF manipulation
composer require setasign/fpdf
# OU installer qpdf système: choco install qpdf
```

---

## Effort Total Estimé

| Phase | Tâches | Effort | Durée |
|-------|--------|--------|-------|
| **1 - Intégration PDF** ⭐ | #1-3 | ⭐⭐⭐ | 1-2 semaines |
| **2 - Fondations** | #4,6,7,9 | ⭐⭐⭐ | 1 semaine |
| **3 - Performance** | #5,10 | ⭐⭐ | 1 semaine |
| **4 - UX/Polish** | #8,11-18+ | ⭐⭐ | 2+ semaines |
| **TOTAL** | 19+ | ⭐⭐⭐⭐ | **3-5 semaines** (vs 6-8 avant!) |

**Réduction de 40%** car le code PDF pages photo existe déjà !

---

## Risques et Mitigation

| Risque | Probabilité | Mitigation |
|--------|------------|-----------|
| Pandoc/Typst cassé après update | 🟡 Moyen | Verrouiller versions, tests CI |
| PDF final 500MB+ (images) | 🔴 Haut | Compression images, tests taille |
| Puppeteer memory leak | 🟡 Moyen | Cleanup après chaque page, monitoring |
| Migration code échoue | 🟡 Moyen | Tests exhaustifs d'abord |

---

**Dernière mise à jour:** 2026-05-06

