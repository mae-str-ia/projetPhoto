# ProjetPhoto - Chaîne Complète de Génération du Livre PDF

## 📋 Vue d'ensemble

ProjetPhoto est une application web permettant de créer et générer un **livre photo professionnel imprimable en 300 DPI** au format **24cm × 16cm** (A5 paysage). La génération implique la capture de pages web via Puppeteer, la fusion avec un PDF texte généré via Typst, et l'assemblage final en un PDF à haute résolution prêt pour l'imprimerie.

**Résultat final:** `livre.print.pdf` - 233 pages, 36.9 MB, 24×16cm à 300 DPI

---

## 🏗️ Architecture Générale

```
┌─────────────────────────────────────────────────────────────┐
│                     Application Web (PHP)                   │
│  - Visualisation des pages et paramétrage                   │
│  - book.json comme source de vérité                         │
│  - Endpoints pour rendu pages (view.php?page=N)             │
└──────────────────────┬──────────────────────────────────────┘
                       │
        ┌──────────────┼──────────────┐
        │              │              │
        ▼              ▼              ▼
    ┌─────────┐  ┌──────────┐  ┌──────────────┐
    │ Puppeteer│  │  Typst   │  │   pdf-lib    │
    │ Capture  │  │ Génération│  │  Fusion PDF  │
    │ PNGs     │  │ PDF texte│  │              │
    └────┬─────┘  └────┬─────┘  └──────┬───────┘
         │             │                │
         ▼             ▼                ▼
    ┌──────────────────────────────────────────┐
    │        Fichiers de sortie                │
    │  - screenshots/ (183 PNG @ 2835×1890)   │
    │  - texte.pdf (120 pages, vectoriel)     │
    │  - page-map.json (mapping positions)    │
    │  - livre.print.pdf (233 pages final)    │
    └──────────────────────────────────────────┘
```

---

## 🛠️ Technologies Utilisées

### Backend
- **PHP 8.5** - Serveur web, logique métier, rendu pages HTML
- **JSON** - Stockage données (book.json pour structure, page-map.json pour positions)
- **Puppeteer (Node.js)** - Capture de pages web en haute résolution PNG

### Frontend
- **HTML/CSS/JavaScript vanilla** - Interface d'édition
- **CSS Aspect-ratio** - Responsive design à proportions constantes

### Génération PDF
- **Typst** - Générateur PDF vectoriel pour les pages texte (markdown → PDF)
- **pdf-lib (Node.js)** - Fusion des photos PNG et pages PDF texte

### Dimensions & Résolution
- **24cm × 16cm** page book (A5 paysage)
- **300 DPI** à l'impression
- **2835 × 1890 pixels** par page photo (capturée à 300 DPI)
- **Vectoriel pour le texte** (pas de rastérisation)

---

## 📊 Structure des Données

### book.json
- **Localisation:** `data/book.json`
- **Rôle:** 📋 **Source de vérité** pour la structure du livre
- **Contenu:** Structure complète du livre (366 pages = 183 photos + 183 texte)
- **Chaque page contient:**
  - `pageNumber`: Numéro de page final (1-366, mais truncated à 233)
  - `type`: `"photo"` ou `"text"`
  - `side`: `"left"` ou `"right"`
  - `layout`: Type de mise en page (ex: `"text-right-only"`, `"text-both"`)
  - `photos`: Array des IDs photos à afficher
  - `photoLayout`: Configuration des positions/tailles des photos
  - Autres: dimensions, marges, paramétrage par section

**⚠️ Important:** book.json déclare 183 pages texte, mais le PDF final n'utilise que jusqu'à la page 233 (déterminé par texte.pdf réel qui contient 120 pages). Les 67 photos au-delà sont ignorées avec un warning.

### page-map.json
- **Localisation:** `data/markdown/build/page-map.json`
- **Généré par:** Process de génération Typst/Markdown
- **Contient:**
  - `sourceToFinal`: Mapping {textPageIndex → finalPageNumber}
    - Exemple: `"1": 1, "2": 2, ... "120": 233`
  - `sectionByPage`: Info de section pour chaque page
  - `blanksBefore`: Pages blanches à insérer
- **Rôle:** Source de vérité pour les positions des pages texte dans le PDF final

### texte.pdf
- **Localisation:** `data/pdf/texte.pdf`
- **Généré par:** Typst (markdown → PDF vectoriel)
- **Dimensions:** 24cm × 16cm (680.31 × 453.54 points)
- **Pages:** 120 pages
- **Contenu:** Pages de remerciements, table des matières, corps du texte
- **Qualité:** Vectoriel, texte sélectionnable, pas de rastérisation

---

## 🎬 Chaîne Complète (Workflow)

### Phase 1: Interface Web & Configuration (PHP)
1. **Accès:** `http://localhost:port/app/`
2. **Données source:** `data/book.json` (structure complète du livre en JSON)
3. **Actions possibles:**
   - Visualiser la structure du livre page par page
   - Éditer les paramètres des photos (taille, position, rotation, légende)
   - Éditer les sections et layouts
   - Paramétrer les marges et dimensions de page
4. **Stockage:** Modifications sauvegardées dans `data/book.json`
5. **Endpoint de rendu:** `GET /app/view.php?page=N` - Rendu HTML/CSS de la page N via PHP
   - Charge les données depuis book.json
   - Construit la mise en page avec CSS
   - Prêt pour capture Puppeteer

### Phase 2: Génération Markdown & Typst
1. **Entrée:** book.json + structure markdown
2. **Process:**
   - Traverser structure du livre
   - Générer fichier `.md` pour chaque section
   - Injecter métadonnées de section (layout, page number)
3. **Output:** Fichiers markdown en `data/markdown/`
4. **Compilation Typst:**
   - Input: Fichiers markdown
   - Typst processes markdown → PDF vectoriel
   - Output: `texte.pdf` (120 pages)
   - Side effect: Génère `page-map.json` avec mapping des positions

### Phase 3: Capture des Pages Photos (Puppeteer)

**Script:** `capture/capture-pages.js`

```bash
node capture-pages.js
```

**Process:**
1. Lance navigateur headless (Chromium)
2. Pour chaque page photo (N=1, 2, 6, 8, ... jusqu'à N=360):
   - Navigue vers `http://localhost:port/app/view.php?page=N`
   - Attend que toutes les images `.v-photo-clip img` soient complètement chargées
   - Timeout: 3000ms pour assurer chargement images
   - Viewport: **2835 × 1890 pixels** (300 DPI @ 24×16cm)
   - Prend screenshot PNG avec **backgroundColor: white**
   - Sauvegarde: `data/screenshots/page-XXX.png`

**Résultat:** 183 fichiers PNG de 2835×1890 pixels chacun

**Points critiques:**
- Viewport EXACT: 2835×1890 (pas de scrolling, page complète)
- Attendre que `img.complete === true` pour chaque image
- Background blanc (pas transparent)
- DPI implicite: 2835px ÷ 24cm = 300 DPI

### Phase 4: Fusion PDF (pdf-lib)

**Script principal:** `capture/merge-hq-pdf-v2.js`

```bash
node merge-hq-pdf-v2.js
```

**Process:**
1. **Charge les données:**
   - `book.json` - structure du livre
   - `page-map.json` - mapping positions texte
   - `texte.pdf` - pages texte (120 pages)
   - `screenshots/` - images photos (183 PNGs)

2. **Crée document PDF vide** avec dimensions **2835 × 1890 points** (300 DPI)

3. **Pour chaque page finale (1 à 233):**
   - Récupère type de page depuis `book.json`
   - **Si page texte:**
     - Récupère index texte depuis `page-map.json` (sourceToFinal)
     - Copie page depuis `texte.pdf` via `pdfDoc.copyPages()`
     - Scale par 4.167× (680.31 → 2835 points)
     - Ajoute au document
   - **Si page photo:**
     - Charge PNG depuis `screenshots/page-XXX.png`
     - Embed image dans PDF
     - Scale pour remplir page (2835×1890 points)
     - Ajoute au document
   - **Skip:** Pages au-delà de 233 (67 photos non utilisées)

4. **Sauvegarde:** `livre.print.pdf` (233 pages, ~36.9 MB)

### Phase 5: Correction des Dimensions

**Script:** `capture/fix-pdf-size.js`

```bash
node fix-pdf-size.js
```

**Problème:** PDF généré avec pages de 2835×1890 points (300 DPI) mais visualiseurs supposent 72 DPI → affiche 100×66cm

**Solution:**
- Scale toutes les pages par **0.24** (= 680.31 / 2835)
- Ramène pages à 680×453 points (72 DPI)
- Affichage corrigé: **24cm × 16cm**
- Qualité préservée: pixels restent à 2835×1890 interne

**Résultat final:** `livre.print.pdf` - 24cm × 16cm @ 300 DPI, prêt pour imprimerie

---

## 📂 Arborescence Clé

```
projetPhoto/
├── app/
│   ├── public/
│   │   ├── css/app.css         # Styles, définit aspect-ratio: 24/16
│   │   └── photo.php           # Handler images (sécurité, MIME types)
│   └── templates/
│       └── view.php            # Page rendue PHP capturée par Puppeteer
├── capture/
│   ├── capture-pages.js        # Script Puppeteer → PNGs
│   ├── merge-hq-pdf-v2.js      # Fusion PNG + PDF texte
│   ├── fix-pdf-size.js         # Correction dimensions PDF
│   └── test-merge.js           # Version test (page 6 + 7)
├── data/
│   ├── book.json               # 📋 Source de vérité: structure du livre (366 pages)
│   ├── screenshots/            # 📸 183 × PNG @ 2835×1890px
│   │   └── page-001.png
│   ├── uploads/photos/         # 📷 Photos originales uploadées
│   ├── markdown/
│   │   └── build/
│   │       ├── page-map.json   # 🗺️ Mapping positions texte (120 pages)
│   │       ├── sections.json   # Info sections
│   │       ├── toc.json        # Table des matières
│   │       └── *.md            # Fichiers markdown générés
│   └── pdf/
│       └── texte.pdf           # 📄 PDF texte Typst (120 pages, vectoriel)
└── livre.print.pdf             # ✅ PDF FINAL (233 pages, 24×16cm, 300 DPI)
```

---

## ⚙️ Paramétrages Spécifiques

### Page Book (CSS)
- **Fichier:** `app/public/css/app.css` (ligne 1408+)
- **Aspect ratio:** `24 / 16` (responsive, adapte à la taille écran)
- **Dimensions réelles:** 24cm × 16cm
- **Affichage:** Position fixed, remplit viewport
- **Debug overlays:** `.editor-photo-params`, `.g-photo-params`, `.v-photo-params` → `display: none`

### Capture Puppeteer
- **Viewport:** 2835 × 1890 pixels (exact, pas flexible)
- **DPI implicite:** 300 DPI (2835 ÷ 24cm)
- **Padding/margin:** 0 (page remplit exactement viewport)
- **Background:** white
- **Format:** PNG (pas JPEG, préserve qualité)
- **Image loading:** Attend `img.complete` avec timeout 3000ms

### PDF Merge
- **Page dimensions:** 2835 × 1890 points (avant fix) = 680 × 453 points (après fix)
- **Mode scale texte:** 4.167× (proportionnel largeur/hauteur)
- **Mode scale images:** Remplit exactement la page
- **Max pages:** 233 (déterminé par texte.pdf + page-map.json)
- **Skip:** Photos au-delà de maxTextFinalPage
- **Format image:** PNG embedded (vectoriel pour texte)

### PDF Final
- **Résolution affichage:** 24cm × 16cm (72 DPI affichage)
- **Résolution impression:** 300 DPI (2835 pixels ÷ 24cm)
- **Pages:** 233 (113 photos + 120 texte)
- **Taille fichier:** ~36.9 MB
- **Qualité:** Lossless (PNG + PDF vectoriel)
- **Texte:** Vectoriel, sélectionnable

---

## 🔑 Éléments Importants à Retenir

### 1. **Mismatch book.json vs texte.pdf**
- book.json déclare 183 pages texte
- texte.pdf n'en contient que 120
- Solution: Utiliser `page-map.json` comme source de vérité
- Générer seulement jusqu'à final page 233 (max de sourceToFinal)
- Émettre warning pour les 67 photos non utilisées

### 2. **Résolution 300 DPI**
- **À la capture:** 2835 pixels × 24cm = 300 DPI
- **À l'affichage écran:** Visualiseur suppose 72 DPI (standards PDF)
- **À l'impression:** 300 DPI réel (pixels sont là)
- Ne pas confondre unité écran (cm) avec résolution (DPI)

### 3. **Chaîne de facteurs d'échelle**
- **PHP template (view.php):** Applique scaling × 3.15 pour captions/borders
  - Formula: `2835px (capture) ÷ 900px (editor display) = 3.15×`
- **Merge initial:** Pages @ 2835×1890 points (300 DPI encoding)
- **Fix final:** Scale × 0.24 pour ramener à 72 DPI affichage standard
- **Résultat:** Pages affichent 24×16cm, impriment 300 DPI

### 4. **Vectoriel vs Rasterisé**
- **Texte PDF (Typst):** Vectoriel ✓ (scalable, sélectionnable, qualité infinie)
- **Images photos (Puppeteer):** PNG rasterisé (2835×1890 pixels fixe)
- Pas de conversion CMYK actuellement (RGB only)
- Texte reste texte lors du scaling PDF

### 5. **Ordre des pages texte**
- Ne pas supposer ordre séquentiel (1, 2, 3...)
- Utiliser **sourceToFinal** de page-map.json
- Text page N peut aller à final page N+X (dépend des blanks/sections)
- Exemple: Text page 8 → Final page 9 (1 page blanche avant)

### 6. **Gestion des erreurs de capture**
- Photos non trouvées → Warning, skip page
- Images non chargées → Timeout 3000ms
- Vérifier viewport exact (2835×1890), pas de scrolling

### 7. **Optimisation fichier**
- 36.9 MB pour 233 pages haute résolution = normal
- PNG embedding direct (plus compact que JPEG)
- PDF vectoriel pour texte (pas d'inflation)
- Compressible via ghostscript si besoin (trade-off qualité)

---

## 🚀 Lancer la Génération Complète

```bash
# 1. Capturer toutes les pages photos
cd projetPhoto/capture
node capture-pages.js
# ↓ Output: 183 × PNG en data/screenshots/

# 2. Générer texte PDF via Typst (depuis editeur ou CLI)
# (Dépend du setup Typst - génère texte.pdf + page-map.json)

# 3. Fusionner photos + texte
node merge-hq-pdf-v2.js
# ↓ Output: livre.print.pdf (100×66cm @ 300 DPI internally)

# 4. Corriger dimensions d'affichage
node fix-pdf-size.js
# ↓ Final: livre.print.pdf (24×16cm affichage, 300 DPI impression)
```

---

## 📋 Checklist Avant Impression

- ✅ Toutes les photos uploadées et paramétrées
- ✅ Textes et sections validés dans Typst
- ✅ `capture-pages.js` terminé sans erreurs (183 PNGs)
- ✅ `texte.pdf` généré avec bonnes dimensions (680×453 points)
- ✅ `page-map.json` contient sourceToFinal pour 120 pages
- ✅ `merge-hq-pdf-v2.js` génère 233 pages sans warnings critiques
- ✅ `fix-pdf-size.js` appliqué (pages @ 24×16cm)
- ✅ `livre.print.pdf` visualisé: dimensions correctes, contenu lisible
- ✅ Couleurs RGB (CMYK non appliqué actuellement)
- ✅ Texte vectoriel (pas rasterisé) - vérifier avec Acrobat

---

## 🔧 Débogage Courant

| Problème | Cause | Solution |
|----------|-------|----------|
| PDF 100×66cm | Pas de fix-pdf-size.js appliqué | Lancer `node fix-pdf-size.js` |
| Photos floues | Viewport Puppeteer ≠ 2835×1890 | Vérifier capture-pages.js viewport |
| Pages différentes tailles | Texte pas rescalé | Vérifier scaleRatio dans merge |
| Texte rasterisé | Embedding PNG au lieu de PDF copy | Utiliser `copyPages()` pas `embedPng()` |
| Images non chargées | Timeout trop court | Augmenter timeout en capture-pages.js |
| Pages 121-183 manquantes | texte.pdf incomplet | Régénérer avec Typst, vérifier page-map.json |

---

## 📝 Notes de Maintenance

- **book.json:** Peut devenir obsolète vs texte.pdf réel → toujours vérifier page-map.json
- **Capture temps:** ~12 minutes pour 183 pages (150ms par page)
- **Merge temps:** ~30 secondes
- **Fix temps:** ~20 secondes
- **Espace disque:** ~200 MB PNGs + 5 MB PDFs

---

Généré: 2026-05-03 | ProjetPhoto v1.0
