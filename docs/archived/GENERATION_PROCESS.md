# ProjetPhoto - Documentation des Processus de Génération

## Vue d'ensemble

ProjetPhoto comporte **deux processus de génération distincts**:

1. **Génération du PDF texte** - Crée `texte.pdf` (120 pages de contenu texte vectoriel)
2. **Génération du PDF final** - Crée `livre.print.pdf` (233 pages: photos + texte fusionnés)

---

## 🔤 PROCESSUS 1: Génération du PDF Texte

### Déclenchement

**Interface:** Menu > Options > "Régénérer PDF texte"

**Ou en CLI:**
```bash
# Pas de script CLI direct - uniquement via l'interface web
```

### Flux d'exécution détaillé

#### Étape 1: Appel API Web
```
User click: "Régénérer PDF texte"
         ↓
POST /app/api/markdown.php
  action: 'generateTextPdf'
  copyToSource: true
```

#### Étape 2: Fonction PHP - MarkdownPdfManager::generateTextPdf()
**Fichier:** `app/src/MarkdownPdfManager.php` (ligne 13)

**Exécution:**
```php
1. self::ensureInputs()
   └─ Vérifie que livre.md existe

2. self::writeBuildMarkdown(null, true)
   └─ Prépare le markdown pour compilation

3. self::runPandoc($pandoc)
   └─ Exécute: pandoc livre.build.md --from markdown --to typst --output texte.raw.typ

4. self::writeTypstFile(true)
   └─ Génère fichier Typst avec métadonnées

5. self::run([$typst, 'compile', texte.typ, texte.pdf])
   └─ Premier passage Typst (génère texte.pdf brut)

6. self::detectBlankPdfPages(texte.pdf)
   └─ Détecte les pages blanches

7. self::buildFinalPageMap($metadata, countPdfPages(texte.pdf), $blankSourcePages)
   └─ Construit le mapping pages texte → pages finales
   └─ Génère: page-map.json (avec sourceToFinal, sectionByPage, finalPageCount)

8. [2e passage: Markdown + Typst avec TOC] - Répète étapes 2-7

9. [3e passage: Génération finale] - Répète étapes 2-6 avec configuration finale

10. self::postProcessPdfWithPageMap($pageMap)
    └─ Post-traite le PDF (ajoute pages blanches si nécessaire)

11. $pageCount = self::countPdfPages(texte.pdf)
    └─ Compte les pages finales

12. if ($copyToSource) {
      copy(texte.pdf, SOURCE_PDF)
      └─ Copie vers source.pdf pour utilisation dans l'app
    }

13. self::syncBookPdfPages()
    ├─ Charge page-map.json
    ├─ Récupère finalPageCount depuis page-map.json
    ├─ Construit $pageTypes à partir de sourceToFinal
    └─ Met à jour book.json:
        ├─ totalPages = max(finalPageCount)
        └─ Synchronise pages[].type et pages[].pdfPage
```

### Fichiers générés

| Fichier | Contenu | Taille | Réutilisé |
|---------|---------|--------|-----------|
| `data/pdf/texte.pdf` | PDF texte brut (avant post-traitement) | ~5 MB | Oui (copié en source.pdf) |
| `data/pdf/texte.processed.pdf` | PDF texte final avec pages blanches | ~5 MB | SOURCE_PDF |
| `data/markdown/build/page-map.json` | Mapping positions pages: `sourceToFinal`, `finalPageCount=233`, `sectionByPage` | 10 KB | ✓ Essentiel pour merge |
| `data/markdown/build/toc.json` | Table des matières structurée | 5 KB | App display |
| `data/book.json` | Synchronisé avec new `totalPages` + types | ~50 KB | ✓ Essentiel pour merge |

### Résumé du flux
```
Markdown (livre.md)
    ↓
Pandoc → Typst
    ↓
Typst compile (3 passages)
    ↓
texte.pdf (120 pages)
    ↓
buildFinalPageMap() → page-map.json (finalPageCount=233)
    ↓
syncBookPdfPages() → book.json updated
    ↓
✅ COMPLET
```

### Durée estimée
**30-60 secondes** (selon complexité du contenu)

---

## 📖 PROCESSUS 2: Génération du PDF Final (livre.print.pdf)

### Déclenchement

**Interface:** Menu > Options > "Générer PDF complet"

**Ou en CLI:**
```bash
cd projetPhoto/capture
node capture-pages.js          # ~12-15 minutes
node merge-hq-pdf-v2.js        # ~1-2 minutes
node fix-pdf-size.js           # ~20 secondes
```

### Flux d'exécution détaillé

#### Étape 1: Appel API Web
```
User click: "Générer PDF complet"
         ↓
POST /app/api/pdf.php
  action: 'generateFinalPdf'
```

**Fichier:** `app/public/api/pdf.php` (ligne 24)

#### Étape 2A: Capture des pages photos
**Script:** `capture/capture-pages.js`

```javascript
// Pseudocode du processus
1. Launch Puppeteer headless browser

2. for each photoPage in bookData.pages where type === 'photo':
   a. Navigate to: http://localhost:PORT/app/view.php?page=N
   b. Wait for images to load:
      - querySelector('.v-photo-clip img')
      - img.complete === true
      - Timeout: 3000ms
   c. Screenshot with:
      - Format: PNG
      - Viewport: 2835 × 1890 pixels (exact, no scaling)
      - Background: white
   d. Save to: data/screenshots/page-XXX.png

3. Close browser
4. Output: 183 × PNG files (one per photo page)
```

**Paramètres clés:**
- **Viewport:** `2835 × 1890 pixels` (300 DPI @ 24×16cm)
- **Format:** PNG (lossless)
- **Output dir:** `data/screenshots/`
- **Files:** `page-001.png` to `page-183.png`

**Durée:** ~12-15 minutes (150ms par page)

#### Étape 2B: Fusion des photos et du texte
**Script:** `capture/merge-hq-pdf-v2.js`

```javascript
// Processus en détail
1. Load data sources:
   - bookData = JSON.parse(data/book.json)
   - pageMapData = JSON.parse(data/markdown/build/page-map.json)
   - textPdfDoc = PDFDocument.load(data/pdf/texte.pdf)
   - textPageCount = textPdfDoc.getPageCount() → 120

2. Create new PDF document with 300 DPI encoding:
   - PAGE_WIDTH_POINTS = 24cm × (1/2.54) × 300 = 2834.65 points
   - PAGE_HEIGHT_POINTS = 16cm × (1/2.54) × 300 = 1889.76 points

3. Build page mapping:
   - sourceToFinal = pageMapData.sourceToFinal
     (Maps: textPage 1 → finalPage 1, textPage 2 → finalPage 2, ..., textPage 120 → finalPage 233)
   - Calculate: maxTextFinalPage = max(sourceToFinal.values) = 233

4. Process pages 1 to 233:
   for pageNum = 1 to maxTextFinalPage:
     a. Get pageData from bookData.pages[pageNum]
     
     if pageData.type === 'text':
       - Find: textPageIndex = sourceToFinal[pageNum]  // e.g., 1
       - Copy page from textPdfDoc[textPageIndex - 1]  // 0-indexed
       - Scale: scaleRatio = PAGE_WIDTH_POINTS / 680.31 = 4.167
       - Add scaled page to pdfDoc
     
     else if pageData.type === 'photo':
       - Load: data/screenshots/page-XXX.png
       - Embed PNG in pdfDoc
       - Draw at: (0, 0) size (PAGE_WIDTH_POINTS, PAGE_HEIGHT_POINTS)
     
     else:
       - Skip page with warning

5. Save PDF:
   - pdfBytes = await pdfDoc.save()
   - Write to: livre.print.pdf (at 300 DPI encoding internally)

6. Output:
   - livre.print.pdf (~36.8 MB, 233 pages)
```

**Fichiers input:**
- `data/book.json` (pour structure)
- `data/markdown/build/page-map.json` (pour mapping)
- `data/pdf/texte.pdf` (120 pages)
- `data/screenshots/page-*.png` (183 PNGs @ 2835×1890)

**Fichier output:**
- `livre.print.pdf` (233 pages @ 2835×1890 points internal, scales to 24×16cm @ 72 DPI display)

**Durée:** ~1-2 minutes

#### Étape 2C: Correction des dimensions d'affichage
**Script:** `capture/fix-pdf-size.js`

```javascript
// Processus
1. Load: livre.print.pdf
   - Current: 2835×1890 points (300 DPI encoding)
   - Displays as: 100cm × 66cm @ 72 DPI (standard PDF interpretation)

2. For each page in pdfDoc.getPages():
   a. Apply scale:
      - scaleX = 680.31 / 2835 = 0.24
      - scaleY = 453.54 / 1890 = 0.24
   b. Result: Page becomes 680.31 × 453.54 points

3. Save PDF:
   - Overwrite: livre.print.pdf
   - Now displays as: 24cm × 16cm @ 72 DPI (correct!)
   - Internal quality still: 2835×1890 pixels (300 DPI)

// Mathématique
Original:  2835 points ÷ 72 DPI = 39.375 inches = 100 cm ❌
Fixed:     680.31 points ÷ 72 DPI = 9.45 inches = 24 cm ✅

Impression: 24 cm × (2835 pixels / 24 cm) = 2835 pixels / 9.45 inches = 300 DPI ✅
```

**Fichier modifié:** `livre.print.pdf` (in-place rescale)

**Durée:** ~20 secondes

---

## 📋 Tableau Récapitulatif: Ordre et Dépendances

### Processus 1: PDF Texte

| # | Étape | Script/Fonction | Input | Output | Durée |
|---|-------|-----------------|-------|--------|-------|
| 1 | Pandoc | `MarkdownPdfManager::runPandoc()` | `livre.md` | `texte.raw.typ` | 5s |
| 2 | Typst compilation (×3) | `typst compile` | `texte.typ` | `texte.pdf` | 20s |
| 3 | Détection pages blanches | `MarkdownPdfManager::detectBlankPdfPages()` | `texte.pdf` | `blankSourcePages[]` | 2s |
| 4 | Build page-map | `MarkdownPdfManager::buildFinalPageMap()` | `metadata, pageCount` | `page-map.json` | 1s |
| 5 | Sync book | `MarkdownPdfManager::syncBookPdfPages()` | `page-map.json` | `book.json` (updated) | 1s |
| **TOTAL** | | | | | **30-60s** |

**Sortie clé:** `page-map.json` avec `finalPageCount=233` et `sourceToFinal` mapping

---

### Processus 2: PDF Final

| # | Étape | Script | Input | Output | Durée | Dépend de |
|---|-------|--------|-------|--------|-------|-----------|
| 2A | Capture photos | `capture-pages.js` | `view.php?page=N` | `page-*.png` (183 files) | 12-15m | Aucune |
| 2B | Merge photos + texte | `merge-hq-pdf-v2.js` | `book.json`, `page-map.json`, `texte.pdf`, `page-*.png` | `livre.print.pdf` (233 pages @ 300 DPI encoding) | 1-2m | Étape 2A, Process 1 |
| 2C | Fix dimensions | `fix-pdf-size.js` | `livre.print.pdf` | `livre.print.pdf` (scaled to 24×16cm display) | 20s | Étape 2B |
| **TOTAL** | | | | | **13-18m** | Tous |

---

## 🔗 Dépendances: Flow Complet

```
PROCESS 1: PDF Texte (30-60s)
├─ Input: livre.md
├─ Pandoc + Typst
├─ Output: texte.pdf (120 pages)
├─ Output: page-map.json (finalPageCount=233, sourceToFinal)
└─ Output: book.json (updated totalPages)
    │
    ↓
PROCESS 2: PDF Final (13-18 minutes)
├─ Étape 2A: Capture photos (12-15m)
│  └─ Input: view.php (uses book.json)
│  └─ Output: data/screenshots/ (183 PNGs)
│
├─ Étape 2B: Merge (1-2m)
│  ├─ Input: book.json (from Process 1)
│  ├─ Input: page-map.json (from Process 1)
│  ├─ Input: texte.pdf (from Process 1)
│  ├─ Input: screenshots/ (from Étape 2A)
│  └─ Output: livre.print.pdf (233 pages, 36.8 MB)
│
└─ Étape 2C: Fix dimensions (20s)
   ├─ Input: livre.print.pdf (from Étape 2B)
   └─ Output: livre.print.pdf (rescaled, same file)
```

---

## ⚠️ Points Critiques

### Synchronisation des pages
- **page-map.json contient `finalPageCount`** - C'est la source de vérité pour le nombre total de pages
- **book.json est synchronisé automatiquement** après génération du PDF texte
- **merge-hq-pdf-v2.js lit `sourceToFinal` dynamiquement** - Pas de valeur codée en dur

### Si on change le PDF texte:
1. Le nombre de pages peut changer (ex: 120 → 121)
2. `page-map.json` est régénéré avec le nouveau `finalPageCount`
3. `book.json` est automatiquement synchronisé
4. Le PDF final utilisera automatiquement les bonnes dimensions

### Ordre d'exécution obligatoire:
```
❌ Ne pas faire: Générer le PDF final sans regénérer le PDF texte d'abord
   (Risque: page-map.json stale, dimensions incorrectes)

✅ Faire: 
   1. Régénérer PDF texte (Process 1) → met à jour page-map.json + book.json
   2. Générer PDF final (Process 2) → utilise les données à jour
```

---

## 🖥️ Commandes CLI Exactes

### Process 1: PDF Texte (via web seulement)
```bash
# Pas accessible en ligne de commande directement
# Utiliser l'interface: Menu > Options > "Régénérer PDF texte"
```

### Process 2: PDF Final (CLI)
```bash
cd C:\Devs\ProjetCR\projetPhoto\capture

# Étape 2A: Capturer photos (~12-15 minutes)
"C:\Program Files\nodejs\node.exe" capture-pages.js

# Étape 2B: Merger photos + texte (~1-2 minutes)
"C:\Program Files\nodejs\node.exe" merge-hq-pdf-v2.js

# Étape 2C: Corriger dimensions (~20 secondes)
"C:\Program Files\nodejs\node.exe" fix-pdf-size.js

# Résultat final
# ✅ C:\Devs\ProjetCR\projetPhoto\livre.print.pdf (233 pages, 24×16cm, 300 DPI)
```

### Process 2: PDF Final (via Interface Web)
```
Menu > Options > "Générer PDF complet"
  → Affiche confirmation
  → Lance capture-pages.js (12-15 min)
  → Lance merge-hq-pdf-v2.js (1-2 min)
  → Lance fix-pdf-size.js (20 sec)
  → Notification de succès
```

---

## 📊 Variables Clés et Leurs Sources

| Variable | Valeur | Défini dans | Régénéré par |
|----------|--------|-------------|--------------|
| `totalPages` | 366 (ou plus) | `book.json` | Process 1 (syncBookPdfPages) |
| `finalPageCount` | 233 | `page-map.json` | Process 1 (buildFinalPageMap) |
| `sourceToFinal` | {1→1, 2→2, ... 120→233} | `page-map.json` | Process 1 |
| Text PDF pages | 120 | `texte.pdf` | Process 1 (Typst) |
| Photo PNG files | 183 | `data/screenshots/` | Process 2A (Puppeteer) |
| Output PDF pages | 233 | `livre.print.pdf` | Process 2B (merge) |
| Page width (points) | 2835 (300 DPI) → 680 (72 DPI) | Code merge/fix | Dynamic (math-based) |

---

## ✅ Vérification Finale

Après génération complète, vérifier:

```bash
# 1. Fichier texte PDF existe et a les bonnes pages
wc -l data/pdf/texte.pdf
# → Doit avoir ~120 pages

# 2. page-map.json a finalPageCount correct
grep finalPageCount data/markdown/build/page-map.json
# → Doit afficher: "finalPageCount": 233

# 3. book.json synchronisé
grep totalPages data/book.json
# → Doit afficher: "totalPages": 233

# 4. PDF final généré avec bon nombre de pages
pdfinfo livre.print.pdf | grep Pages
# → Doit afficher: Pages: 233

# 5. PDF final affiche bonnes dimensions
pdfinfo livre.print.pdf | grep "Page size"
# → Doit afficher: ~24cm × 16cm (680pt × 453pt)
```

---

**Documentation complétée le 2026-05-03**
