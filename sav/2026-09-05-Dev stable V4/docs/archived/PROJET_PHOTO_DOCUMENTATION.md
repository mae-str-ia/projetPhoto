# ProjetPhoto - Documentation Complète

## Table des Matières

1. [Vue d'ensemble](#vue-densemble)
2. [Architecture générale](#architecture-générale)
3. [Structure technique](#structure-technique)
4. [Composants principaux](#composants-principaux)
5. [Flux de données](#flux-de-données)
6. [Points forts](#points-forts)
7. [Points faibles et améliorations](#points-faibles-et-améliorations)
8. [Guide d'utilisation](#guide-dutilisation)

---

## Vue d'Ensemble

**ProjetPhoto** est une application web complète de composition de livres photo. Elle permet de créer un livre de 364 pages alternant :
- Pages **texte** (pages de droite) : générées à partir d'un fichier Markdown unique
- Pages **photo** (pages de gauche) : composées en éditeur interactif avec support complet des cadres, filtres, et mises en page

### Caractéristiques principales

- **Dimensions standard** : 24 cm × 16 cm
- **Architecture hybrid** : PHP (backend) + JavaScript vanilla (frontend)
- **Source de vérité unique** : Markdown pour le texte, JSON pour la structure du livre
- **Génération PDF** : Pipeline Markdown → Pandoc → Typst → PDF
- **Édition riche** : Édition des photos avec transformations en temps quasi-réel

---

## Architecture Générale

```
┌─────────────────────────────────────────────────────────┐
│                   Front-End (Browser)                    │
│  ├─ Gallery View (aperçu spreads)                        │
│  ├─ Spread Editor (édition pages photos)                 │
│  ├─ Text Editor (édition Markdown)                       │
│  ├─ Media Manager (gestion des images)                   │
│  └─ View Mode (prévisualisation finale)                  │
└─────────────────────────────────────────────────────────┘
                           ↕ (Fetch API)
┌─────────────────────────────────────────────────────────┐
│              API REST (PHP)                              │
│  ├─ /api/photos.php    → gestion des photos             │
│  ├─ /api/pages.php     → gestion des pages              │
│  ├─ /api/markdown.php  → génération PDF texte           │
│  ├─ /api/media.php     → gestion des médias             │
│  ├─ /api/book.php      → propriétés du livre            │
│  ├─ /api/pdf.php       → pages PDF                      │
│  └─ /api/export.php    → export DOCX                    │
└─────────────────────────────────────────────────────────┘
                           ↕
┌─────────────────────────────────────────────────────────┐
│             Managers (Classes métier)                    │
│  ├─ BookManager           → structure du livre           │
│  ├─ PhotoManager          → gestion des photos           │
│  ├─ ImageProcessor        → transformations d'images     │
│  ├─ PdfManager            → conversion PDF ↔ PNG         │
│  ├─ MarkdownTextManager   → extraction du texte          │
│  ├─ MarkdownPdfManager    → génération PDF depuis MD     │
│  └─ ExportManager         → export Word                  │
└─────────────────────────────────────────────────────────┘
                           ↕
┌─────────────────────────────────────────────────────────┐
│             Stockage et Données                          │
│  ├─ data/book.json                                      │
│  ├─ data/markdown/clean/livre.md                        │
│  ├─ data/markdown/build/                                │
│  ├─ data/pdf/source.pdf, texte.pdf, texte.processed.pdf│
│  ├─ data/uploads/photos/                                │
│  └─ data/pdf-cache/                                     │
└─────────────────────────────────────────────────────────┘
```

---

## Structure Technique

### Hiérarchie des répertoires

```
projetPhoto/
├── app/
│   ├── config/config.php                 # Configuration globale & autoloader
│   ├── src/                              # Classes métier (PSR-4)
│   │   ├── BookManager.php
│   │   ├── PhotoManager.php
│   │   ├── ImageProcessor.php
│   │   ├── PdfManager.php
│   │   ├── MarkdownTextManager.php
│   │   ├── MarkdownPdfManager.php
│   │   └── ExportManager.php
│   ├── public/                           # Point d'entrée web
│   │   ├── index.php                     # Routeur page (GET ?page=)
│   │   ├── api/                          # Endpoints API REST
│   │   ├── js/                           # JavaScript frontend
│   │   ├── css/                          # Stylesheets
│   │   ├── uploads/photos/               # Images importées
│   │   └── pdf-cache/                    # Cache des pages PDF
│   ├── templates/                        # Templates PHP
│   │   ├── layout.php
│   │   ├── gallery.php
│   │   ├── spread.php
│   │   ├── editor.php
│   │   ├── text-editor.php
│   │   ├── media.php
│   │   └── view.php
│   ├── img/                              # Assets (décorations SVG)
│   ├── pdf_postprocess.py                # Post-processing PDF (Python)
│   └── composer.json
├── data/                                 # Données persistantes
│   ├── book.json                         # État du livre
│   ├── markdown/
│   │   ├── source/                       # Markdown original (archivé)
│   │   ├── clean/livre.md                # Markdown éditable (source unique)
│   │   └── build/                        # Fichiers générés (Typst, TOC, etc.)
│   ├── pdf/
│   │   ├── source.pdf                    # PDF utilisé pour prévisualisation
│   │   ├── texte.pdf                     # PDF texte brut
│   │   └── texte.processed.pdf           # PDF texte final (avec pages blanches)
│   ├── uploads/photos/                   # Photos importées
│   └── pdf-cache/                        # Cache PNG des pages PDF
├── capture/                              # Outils Node.js (non utilisé actuellement)
│   └── node_modules/
├── .htaccess & .htpasswd                 # Authentification Apache
└── 3-2026-livre.pdf, 3-livre-reel.pdf   # PDFs générés

```

### Architecture des données

#### **book.json** - Modèle central
```json
{
  "title": "Une Vie en Mouvement",
  "totalPages": 364,
  "pageDimensions": { "width": 240, "height": 160 },
  "properties": {
    "photoPageMargins": { "topCm": 1, "rightCm": 1, "bottomCm": 1, "leftCm": 1 },
    "textPageMargins": { "topCm": 2, "rightCm": 2, "bottomCm": 2, "leftCm": 2 },
    "bindingCm": 2,
    "textBindingCm": 3,
    "pageNumberOffset": 0,
    "defaultLayout": "4-grille"
  },
  "media": [
    {
      "id": "m_123456",
      "filename": "photo_69f330903b0d24.98733175.jpg",
      "width": 4000,
      "height": 3000,
      "uploadedAt": "2026-04-30T15:30:00Z",
      "defaultCaption": "Ma photo"
    }
  ],
  "pages": [
    {
      "pageNumber": 1,
      "side": "right",
      "type": "text",
      "pdfPage": 1
    },
    {
      "pageNumber": 2,
      "side": "left",
      "type": "photo",
      "layout": "4-grille",
      "photos": [
        {
          "id": "photo_69f330903b0d24",
          "filename": "photo_69f330903b0d24.98733175.jpg",
          "mediaId": "m_123456",
          "caption": "Légende personnalisée",
          "captionAlign": "left",
          "rotation": 0,
          "filter": "none",
          "frame": {
            "x": 5, "y": 5, "w": 22, "h": 22, "z": 1,
            "shape": "rect",
            "ratio": "original",
            "borderWidth": 0,
            "borderColor": "white",
            "backgroundColor": "white"
          },
          "crop": {
            "fitMode": "cover",
            "zoom": 1,
            "panX": 0,
            "panY": 0
          },
          "width": 4000,
          "height": 3000
        }
      ]
    }
  ]
}
```

---

## Composants Principaux

### 1. **BookManager** (app/src/BookManager.php)
Gère la structure complète du livre et persiste les données.

**Responsabilités principales:**
- Charger/sauvegarder le livre (JSON atomique)
- Migrer les données (legacy photos → media avec IDs)
- Gérer les pages et spreads
- Gérer les propriétés du livre (marges, dimensions)
- Gérer les médias (ajout, suppression, légende)

**Méthodes clés:**
```php
load()                    // Charger le livre
save($data)              // Sauvegarder de manière atomique
initBook($totalPages)    // Créer un nouveau livre
getPage($pageNumber)     // Récupérer une page
getSpread($spreadNumber) // Récupérer un spread (2 pages)
updatePage($pageNumber, $pageData)
insertSpread($afterSpread)
deleteSpread($spreadNumber, $force)
updateProperties($properties)
getMediaUsage($mediaId)  // Pages utilisant ce média
migrateToMedia($book)    // Migration automatique des photos
```

**Points importants:**
- Les spreads numérotés : spread N = page 2N (left/photo) + page 2N+1 (right/text)
- Sauvegarde atomique avec fichiers temporaires
- Migration transparente des données legacy

---

### 2. **PhotoManager** (app/src/PhotoManager.php)
Gère le cycle de vie des photos importées.

**Responsabilités:**
- Validation et upload de fichiers
- Calcul automatique des dimensions de frame
- Génération de métadonnées

**Méthodes clés:**
```php
upload($file, $pageNumber)           // Importer une photo
getUploadUrl($filename)              // URL d'accès
getUploadPath($filename)             // Chemin disque
validatePhotoOwnership($photoId)     // Validation propriété
delete($photoId, $pageNumber, $deleteFile)
```

**Validation:**
- Types MIME autorisés : JPEG, PNG, WebP
- Taille max : 50 MB
- Détection automatique des dimensions
- Calcul du frame initial basé sur le ratio d'aspect

---

### 3. **ImageProcessor** (app/src/ImageProcessor.php)
Traitement local des images (PHP GD).

**Transformations supportées:**
- **Crop** : support % et pixels
- **Rotation** : 0-360 degrés
- **Filtres** : Grayscale, Sepia, Vintage
- **Frames** : Thin (5px), Thick (15px), Shadow (10px)

**Note:** Ce module est actuellement peu utilisé. Les transformations sont principalement appliquées côté client en CSS/Canvas.

---

### 4. **PdfManager** (app/src/PdfManager.php)
Conversion PDF → PNG et gestion du cache.

**Responsabilités:**
- Détection des outils disponibles (pdftoppm via Calibre, Ghostscript)
- Conversion des pages PDF en PNG (150 dpi)
- Cache local des conversions
- Comptage des pages PDF

**Outils détectés:**
```
Windows:
  ✓ pdftoppm (C:\Program Files\Calibre2\app\bin\)
  ✓ pdfinfo (idem)
  ✓ Ghostscript (gswin64c, gswin32c)
```

**Méthodes clés:**
```php
getPageImage($pageIndex)  // URL PNG du cache
countPages()              // Nombre total de pages
convertPageToPng()        // Conversion à la demande
clearCache()              // Purger le cache
```

---

### 5. **MarkdownTextManager** (app/src/MarkdownTextManager.php)
Extraction d'excerpts du Markdown pour l'édition.

**Responsabilités:**
- Lecture du fichier Markdown unique
- Estimation des pages texte dans le PDF
- Extraction d'excerpts par page
- Sauvegarde des modifications

**Méthodes clés:**
```php
getExcerptForPage($pageNumber)        // Extrait autour d'une page
getContext($start, $end, $direction)  // Contexte avant/après
getAdjacentExcerpt($start, $end, $direction)
saveExcerpt($start, $end, $replacement)
```

**Algorithme de positionnement:**
- Les pages texte comptent pour environ `textPageCount` pages
- L'extrait est centré autour de la page demandée
- Les limites sont alignées aux limites de paragraphes

---

### 6. **MarkdownPdfManager** (app/src/MarkdownPdfManager.php)
**Cœur du pipeline de génération PDF.**

Ce module implémente un processus en 3 passes pour générer un PDF de haute qualité avec table des matières et numérotation correcte.

**Pipeline complet:**

```
1️⃣ PASS 1 (Métadonnées)
   markdown → build.md (marqueurs Pandoc)
   → Pandoc → raw.typ
   → Typst compile → texte.pdf (brouillon)
   → Query métadonnées (sections, titres, pages sources)

2️⃣ PASS 2 (Table des matières)
   Détection des pages blanches dans PDF
   Calcul du mapping source→final (avec pages blanches)
   Génération de la ToC en Markdown Typst
   build.md réécriture avec ToC
   → Pandoc → raw.typ
   → Typst compile → texte.pdf

3️⃣ PASS 3 (Finalisation + post-processing)
   Renumérotation finale des pages
   build.md réécriture (sans métadonnées internes)
   → Pandoc → raw.typ avec pageMap
   → Typst compile → texte.pdf
   → Post-processing Python (ajout pages blanches) → texte.processed.pdf
```

**Caractéristiques avancées:**

```typst
// Running headers (en-têtes courants)
#let running-page-header() = context {
  let source-page = counter(page).get().first()
  let n = final-page-map.at(source-page - 1)
  if calc.odd(n) and n >= 9 {
    place(top + right)[Partie - Chapitre]  // Pages droites impaires
  }
}

// Numérotation physique
#let outside-page-number() = context {
  let final = final-page-map.at(counter(page).get().first() - 1)
  if calc.odd(final) { align(right)[final] }  // Droite impaire
}

// Markers pour styles de blocs (dialogue, courrier)
#block[ /* MARKER:dialogue */ ... ]
```

**Marqueurs Markdown supportés:**
```markdown
<!-- blank-page -->           → Page blanche
<!-- right-page -->           → Pagebreak à page paire
<!-- toc -->                  → Table des matières
<!-- section: id=..., layout=... -->
*****                         → Séparateur (motif SVG)
> citation
> {.dialogue|.courrier}       → Blocks stylisés
```

**Méthodes clés:**
```php
generateTextPdf($copyToSource)      // Génération complète
addBlankPagesToTextPdf()            // Post-processing
queryBuildMetadata($typst)          // Query Typst
buildFinalPageMap($sections, ...)   // Mapping pages
```

**Dépendances externes:**
- `pandoc` : Markdown → Typst
- `typst` : Typst → PDF (compilateur)
- `pdfinfo` : Comptage de pages
- `python` : Post-processing PDF

---

### 7. **ExportManager** (app/src/ExportManager.php)
Export du livre en document Word (DOCX).

**Fonctionnalité:**
- Génération DOCX avec PHPWord
- Sections pour chaque spread
- Inclusion des images des planches
- Légendes associées

**Limitation:** Utilise PHPWord (dépendance lourd), peu maintenu.

---

### 8. **Pipeline PDF Pages Photo** ⭐ (capture/ - Node.js)
**Génération et assemblage du PDF final.**

Deux scripts Node.js qui forment une chaîne complète (mais actuellement manuelle).

#### **capture-pages.js** - Capture pages photo en PNG
```javascript
// Utilise Puppeteer pour capturer l'interface
// - Accède à http://localhost:8081/?page=view&num=PAGE
// - Capture chaque page photo en PNG 300 DPI (2834×1889 px)
// - Sauvegarde : data/screenshots/page-NNN.png
// - Temps : ~4s par page, 182 pages ≈ 12-15 minutes
// - Attend le chargement des images (2-5s par page)
```

**Caractéristiques :**
- Détecte `.v-photo-clip img` dans le DOM
- Vérifie que les images sont complètement chargées
- Gestion d'erreurs par page (continue si une échoue)
- Logs détaillés (temps estimé, progression)

#### **merge-hq-pdf-v2.js** - Fusionne texte + photos
```javascript
// Utilise pdf-lib pour créer le PDF final
// 1. Charge book.json (structure du livre)
// 2. Charge page-map.json (mapping pages texte)
// 3. Charge texte.pdf (pages texte pré-générées)
// 4. Pour chaque page du livre :
//    - Si type=photo : intègre le PNG depuis data/screenshots/
//    - Si type=text : copie la page du texte.pdf
// 5. Sauvegarde : livre.print.pdf
// - Temps : ~10 secondes
```

**Outputs :**
- Rapport de structure (nb pages photo/texte)
- Taille fichier final
- Avertissements si screenshots manquants

#### **Workflow Actuel (MANUEL)**

```bash
# Terminal 1 : Démarrer le serveur PHP
php -S localhost:8081

# Terminal 2 : Capturer les pages
cd capture
npm install  # si pas déjà fait
node capture-pages.js
# → Crée data/screenshots/page-001.png ... page-364.png (12-15 min)

# Terminal 3 : Fusionner PDF
node merge-hq-pdf-v2.js
# → Crée livre.print.pdf (~10 sec)
```

#### **État d'Intégration**

✅ **Code complet et fonctionnel**  
❌ **Pas d'intégration dans l'API PHP**  
❌ **Pas de bouton "Générer PDF final"**  
❌ **Pas de gestion du processus long (12-15 min)**  
❌ **Pas de feedback utilisateur**

---

---

## Flux de Données

### Flux 1 : Édition d'une page photo

```
Frontend (Gallery)
  ↓ Clic spread
Frontend (Spread Editor)
  ↓ GET /api/pages.php?action=getSpread&spread=5
Backend (API)
  ↓ BookManager::getSpread(5)
  ↓ Retourne left (photo) + right (text)
JSON Response
  ↓
Frontend affiche l'éditeur photo
  ↓ Utilisateur ajoute/modifie photos
Frontend
  ↓ POST /api/photos.php (upload, update, delete)
Backend
  ↓ PhotoManager + BookManager::updatePage()
  ↓ Sauvegarder book.json
Frontend
  ↓ Refresh affichage
```

### Flux 2 : Édition du texte Markdown

```
Frontend (Text Editor)
  ↓ GET /api/markdown.php?action=getExcerptForPage&page=45
Backend
  ↓ MarkdownTextManager::getExcerptForPage(45)
  ↓ Estimation position dans livre.md
JSON Response (start, end, excerpt, context)
  ↓
Frontend affiche éditeur Markdown avec contexte
  ↓ Utilisateur modifie texte
Frontend
  ↓ POST /api/markdown.php (saveExcerpt)
Backend
  ↓ MarkdownTextManager::saveExcerpt()
  ↓ Sauvegarde livre.md (atomique)
Frontend
  ↓ Optionnel : POST /api/markdown.php (generateTextPdf)
Backend
  ↓ MarkdownPdfManager::generateTextPdf()
  ↓ Pandoc + Typst (3 passes)
  ↓ Post-processing Python
  ↓ Mise à jour source.pdf
Frontend
  ↓ Refresh pour afficher PDF texte à jour
```

### Flux 3 : Génération PDF final

*Non implémenté actuellement* – nécessite:
1. Génération des planches photo en PDF (Puppeteer ou similaire)
2. Assemblage texte.pdf + photos.pdf avec qpdf/PDF merger
3. Respect de l'ordre des spreads

---

## Points Forts

### ✅ **Architecture modulaire et maintenable**

- Séparation claire Frontend/Backend
- Managers spécialisés (SRP)
- PSR-4 autoloader PHP
- API REST cohérente
- Classes testables isolément

### ✅ **Gestion de données robuste**

- Sauvegarde atomique (fichiers .tmp)
- JSON comme format lisible et éditable
- Migrations de données automatiques (legacy → media)
- Pas de dépendance à une BDD (gain en complexité)

### ✅ **Pipeline PDF sophistiqué (Texte + Photos)**

**Texte (MarkdownPdfManager - 3 passes):**
- Pandoc + Typst = rendu haute qualité, maintainable
- 3 passes assurent ToC et numérotation correctes
- Running headers + numérotation physique
- Support des pages blanches et contrôle fin

**Photos (Puppeteer + pdf-lib - Manuel):**
- Capture haute résolution 300 DPI via Puppeteer
- Fusion texte + photos avec pdf-lib
- Respect exact de book.json pour l'ordre
- Gestion des marges et dimensions exactes
- Code complet et fonctionnel (juste pas intégré à l'API)

### ✅ **Flexibilité des mises en page**

- 9 layouts photo supportés (`pleine-page`, `4-grille`, `2-cote`, `1g-2d`, `free`, etc.)
- Transformations photo (crop, rotation, filtres, cadres)
- Marges et binding indépendants (photo ≠ texte)
- Texte et photos indépendants (modification l'un n'affecte pas l'autre)

### ✅ **Interface utilisateur épurée**

- Navigation simple (Gallery → Spread Editor → Text Editor)
- Aperçu côté client avec CSS (rapide)
- Éditeur Markdown contextuel
- Gestion des médias centralisée

### ✅ **Outils Windows intégrés**

- Détection automatique des chemins Calibre/Ghostscript
- Fallbacks multiples (pdftoppm, Ghostscript, regex)
- Chemins hardcodés couvrent les installations courantes

---

## Points Faibles et Améliorations

### ❌ **1. Pipeline PDF pages photo : Pas d'Intégration API**

**Problème:** Le pipeline complet existe (capture-pages.js + merge-hq-pdf-v2.js) mais n'est pas accessible via l'interface web. Requires lancement manuel de 2 commandes Node.js, sans feedback utilisateur.

**Impact:** 
- Utilisateur ne peut pas générer le PDF final depuis l'UI
- Processus long (12-15 min) sans indication de progression
- Pas de gestion d'erreurs propre
- Demande des connaissances terminal/Node

**Solution recommandée:**
- Wrapper PHP pour lancer les scripts Node.js
- Ajouter API endpoint `/api/export.php?action=generateFinalPdf`
- Ajouter bouton "Générer PDF final" à l'interface
- Gérer le processus long (WebSocket, polling, ou background queue)

**Code à ajouter:**
```php
// app/public/api/export.php - Ajouter action generateFinalPdf
class ExportManager {
    public static function generateFinalPdf() {
        // Vérifier que texte.pdf existe
        // Lancer en background: node capture/capture-pages.js
        // Puis: node capture/merge-hq-pdf-v2.js
        // Retourner: livre.print.pdf URL + status
        // Gérer timeout (12-15 min)
    }
}
```

---

### ❌ **2. Transformations image côté serveur limitées**

**Problème:** ImageProcessor utilise PHP GD (limité en qualité et performance).

**Impact:** Les filtres et cadres ne sont pas appliqués côté serveur. Les transformations ne sont que des previews (CSS/Canvas).

**Solution recommandée:**
- Utiliser **ImageMagick/GraphicsMagick** pour transformations côté serveur
- OU générer les transformations en PDF/Canvas via Puppeteer
- Mettre en cache les images transformées

---

### ❌ **3. Post-processing PDF en Python ad-hoc**

**Problème:** Le script `pdf_postprocess.py` gère une logique métier:
- Détection de pages blanches (regex sur contenu)
- Injection de pages blanches
- Renumérotation à partir de pageMap

C'est une dépendance critique sur Python 3.9.

**Impact:** 
- Dépendance supplémentaire (Python)
- Chemin hardcodé `C:\Devs\Python\Python399\`
- Logique métier dispersée (PHP + Python)

**Solution recommandée:**
- Implémenter en PHP avec **qpdf library** ou **FPDF**
- OU maintenir Python mais avec meilleure gestion de dépendances
- Documenter la dépendance dans composer.json si PHP, ou requirements.txt si Python

---

### ❌ **4. Pas de tests automatisés**

**Problème:** Aucun test unitaire ou d'intégration.

**Impact:**
- Risque de régression élevé
- Réfactorisation risquée
- Bugs détectés tard

**Solution recommandée:**
```bash
composer require --dev phpunit/phpunit
```

**Tests à écrire:**
- BookManager::migrateToMedia()
- MarkdownTextManager::getExcerptForPage()
- MarkdownPdfManager::buildFinalPageMap()
- API endpoints (photos, pages, markdown)

---

### ⚠️ **5. Gestion d'erreurs et logging minimaliste**

**Problème:**
- Pas de logging centralisé
- Erreurs affichées en JSON direct
- Pas de retry logic
- Processes externes peuvent échouer silencieusement

**Impact:** Difficile de debugger en production.

**Solution recommandée:**
```php
class Logger {
    public static function error($message, $context = []) {
        file_put_contents(
            DATA_ROOT . '/logs/error.log',
            date('Y-m-d H:i:s') . " - $message - " . json_encode($context) . "\n",
            FILE_APPEND
        );
    }
}
```

---

### ⚠️ **6. Sécurité des chemins de fichiers**

**Problèmes:**
- Chemins hardcodés Windows (`C:\Program Files\Calibre2\`, `C:\Devs\Python\`)
- Pas d'isolement des fichiers utilisateurs
- Pas de validation stricte des noms de fichiers
- Exécution directe de `exec()` sur des chemins

**Risques:** 
- Path traversal (si noms de fichiers mal validés)
- Injection de commandes (exec)
- Portabilité Windows uniquement

**Solution recommandée:**
```php
// Utiliser constants env pour chemins
$pythonPath = getenv('PYTHON_EXECUTABLE') ?: 'python';

// Valider noms fichiers
if (!preg_match('/^photo_[a-f0-9]{16}\.\d+\.(jpg|png|webp)$/i', $filename)) {
    throw new Exception('Invalid filename');
}

// Utiliser Symfony Process pour commandes externes
use Symfony\Component\Process\Process;
$process = new Process([...$args]);
$process->mustRun();
```

---

### ⚠️ **7. Performance et scalabilité**

**Problèmes:**
- BookManager::load() charge le JSON complet à chaque requête
- Pas de cache Redis ou Memcached
- Recherches linéaires dans les pages (`foreach $pages`)
- Génération PDF = 3 passes + execution process externe

**Impact pour 364 pages + 1000+ photos:
- Temps de réponse API lent (100+ ms par requête)
- Génération PDF très lente (5-10 secondes)

**Solutions recommandées:**
```php
// Cacher book.json une minute
$cache = apcu_fetch('book_json');
if ($cache === false) {
    $cache = BookManager::load();
    apcu_store('book_json', $cache, 60);
}

// Indexer par pageNumber
private static $pageIndex = null;
public static function getPageFast($pageNumber) {
    if (!self::$pageIndex) {
        $book = self::load();
        self::$pageIndex = array_column($book['pages'], null, 'pageNumber');
    }
    return self::$pageIndex[$pageNumber] ?? null;
}
```

---

### ⚠️ **8. Interface utilisateur : pas de validation côté client**

**Problèmes:**
- Pas de vérification des inputs avant envoi
- Pas d'indication du statut des opérations longues
- UX basique (pas de drag-and-drop, animations)

**Impact:** Frustration utilisateur, erreurs non claires.

**Solutions:**
- Ajouter HTML5 validation
- Feedback utilisateur (spinners, toasts, progress bars)
- Drag-and-drop pour photos
- Animations CSS

---

### ⚠️ **9. Absence de gestion des versions / undo**

**Problème:** Aucun historique des modifications.

**Impact:** Impossible d'annuler une action ou de voir l'historique.

**Solution recommandée:**
```php
// Ajouter versionning à book.json
[
    "title": "...",
    "version": 42,
    "versions": {
        "41": {"pages": [...], "checksum": "..."},
        "40": {...}
    }
]
```

---

### ⚠️ **10. Documentation du code minimaliste**

**Problème:** 
- Peu de commentaires
- Pas de docblock explicites
- Logique complexe (MarkdownPdfManager) peu documentée

**Impact:** Difficile pour nouveaux contributeurs.

**Solution:** Ajouter docblocks PHPDoc complets.

---

## Guide d'Utilisation

### Installation et Démarrage

**Prérequis Windows:**
```powershell
# Vérifier l'installation
pandoc --version              # Document markup
typst --version              # PDF compilation
"C:\Program Files\Calibre2\app\bin\pdfinfo.exe"  # PDF utilities
"C:\Devs\Python\Python399\python.exe"            # Post-processing
```

**Installation PHP:**
```bash
cd projetPhoto/app
composer install  # Installe PHPWord pour export
```

**Démarrage serveur local:**
```bash
# Depuis C:\Devs\ProjetCR
php -S localhost:8081

# Accéder à: http://localhost:8081/app/public
```

### Flux de travail typique

#### **1. Créer un nouveau livre**
```
→ http://localhost:8081/app/public
→ Charge PDF source.pdf si présent
→ Initialise book.json avec alternance text/photo
```

#### **2. Ajouter des photos**
```
→ Gallery → Cliquer spread
→ Spread Editor s'ouvre
→ Ajouter photos (glisser-déposer ou upload)
→ Les photos s'ajoutent à la page gauche (photo)
```

#### **3. Éditer les photos**
```
→ Cliquer sur une photo dans Spread Editor
→ Éditer : crop, rotation, filtre, cadre, position
→ Sauvegarder (POST /api/photos.php?action=update)
```

#### **4. Éditer le texte**
```
→ Spread Editor → bouton "Éditer le texte"
→ Text Editor s'ouvre avec extrait Markdown
→ Modifications sauvegardées dans livre.md
→ Optionnel : régénérer PDF texte (POST generateTextPdf)
```

#### **5. Gérer les médias**
```
→ Menu Media
→ Vue centralisée de toutes les photos
→ Voir où elles sont utilisées (usage)
→ Éditer légendes
→ Supprimer (avec vérification)
```

#### **6. Exporter**
```
→ Menu Options → Export Word
→ Génère DOCX avec spreads, images, légendes
```

---

## Patterns et Conventions

### Conventions de nommage

**Fichiers:**
- Classe `BookManager` → fichier `BookManager.php`
- API `/api/photos.php` (plural)
- Templates `gallery.php`, `spread.php` (singular ou plural logique)

**IDs:**
- Photo: `photo_<uniqid>` = `photo_69f330903b0d24`
- Media: `m_<uniqid>` = `m_123456789abcdef`
- Page: numero entier positif `1..364`
- Spread: numero entier `1..182`

**Chemins:**
- Utiliser `/` dans chemins (convertis en `\` pour Windows)
- Chemins absolus : `DATA_ROOT`, `PUBLIC_DIR`, etc.

### Patterns API

Toutes les API retournent JSON:
```json
{
  "success": true|false,
  "data": {...},         // Si success=true
  "error": "message"     // Si success=false
}
```

**Requête:**
```bash
POST /api/photos.php
Content-Type: application/json

{
  "action": "upload|update|delete",
  "page": 10,
  ...
}
```

### Patterns PHP

**Gestion fichiers atomique:**
```php
// ✅ Bon : tmp + rename
$tmp = $filepath . '.tmp';
file_put_contents($tmp, $content);
rename($tmp, $filepath);

// ❌ Mauvais : direct
file_put_contents($filepath, $content);  // Risk: partial write
```

**Autoloading PSR-4:**
```php
// config.php
spl_autoload_register(function ($class) {
    $file = SRC_DIR . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require $file;
});

// Ensuite:
$book = BookManager::load();  // Auto-include src/BookManager.php
```

---

## Résumé Technique

| Aspect | Détail |
|--------|--------|
| **Langage Backend** | PHP 8.0+ |
| **Frontend** | JavaScript vanilla (no framework) |
| **Stockage** | JSON (book.json, livre.md) |
| **Format pages texte** | PDF (Pandoc + Typst) |
| **Conversion PDF→PNG** | Poppler pdftoppm ou Ghostscript |
| **Format pages photo** | ???? (À implémenter) |
| **Export** | DOCX (PHPWord) |
| **Dimensions livre** | 24 cm × 16 cm |
| **Nombre de pages** | 364 (alternance texte/photo) |
| **Authentification** | Apache .htpasswd (optionnel) |
| **Cache** | Fichiers (pdf-cache/), pas de Redis |
| **Dépendances** | pandoc, typst, Calibre, Ghostscript, Python 3.9 |

---

## Prochaines Étapes Prioritaires

### 🔴 **Critique**
1. Implémenter génération des planches photo en PDF
2. Assembler texte + photos dans PDF final
3. Ajouter tests unitaires

### 🟡 **Important**
4. Gestion de logs centralisée
5. Validation côté client (HTML5 + JS)
6. Performance : caching pour book.json
7. Refactoriser post-processing Python en PHP

### 🟢 **Nice to have**
8. Versioning et undo
9. Drag-and-drop photos
10. Animations CSS interface
11. Support responsive (tablettes)
12. API de batch operations

---

**Document généré le 2026-05-06**  
**Auteur:** Claude Code - Analyse automatisée  
**Approche:** Analyse exhaustive du code source, architecture, data models, et processus

