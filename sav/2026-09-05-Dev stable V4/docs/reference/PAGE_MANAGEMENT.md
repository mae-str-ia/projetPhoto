# 📄 Gestion des Pages — projetPhoto

Guide complet sur comment les pages sont gérées, numérotées et synchronisées.

## 🏗️ Architecture des Pages

### Concepts de Base

**Page** : Une unité élémentaire du livre (numérotée 1, 2, 3...)
- Pages **impaires** (1, 3, 5...) = Pages droites = **TEXTE** (PDF)
- Pages **paires** (2, 4, 6...) = Pages gauches = **PHOTO**

**Planche (Spread)** : Deux pages côte à côte
- Planche N = Page gauche `2N` (photo) + Page droite `2N+1` (texte)
- Exemple: Planche 1 = Page 2 (photo) + Page 3 (texte)

### Trois Nombres Importants

```
totalPages        = Nombre total de pages dans le livre (1, 2, 3... N)
printablePages    = Dernière page qui sera imprimée dans le PDF final
pageNumberOffset  = Décalage d'affichage (ex: -2 pour couverture 2 pages)
```

---

## 📂 Fichiers Clés

### 1. **BookManager.php** — Gestion du livre
Charge/sauvegarde `SYNC_DIR/book.json`

```php
class BookManager {
    public static function load()           // Charge le livre
    public static function initBook()       // Crée un nouveau livre
    public static function getPage()        // Récupère une page
    public static function getAllSpreads()  // Retourne toutes les planches
}
```

**Méthodes importantes:**

#### `initBook($totalPages = null)`
- **Entrée**: Nombre total de pages (auto-calculé depuis PDF s'il existe)
- **Processus**:
  1. Compte les pages du PDF via `PdfManager::countPages()`
  2. Crée `totalPages = ceil(pdfCount / 2) * 2` (pair)
  3. Génère pages alternées: impaires=texte, paires=photo
  4. Sauvegarde dans `SYNC_DIR/book.json`

```php
// Exemple: PDF de 10 pages
$pdfCount = 10
$totalPages = ceil(10/2) * 2 = 10

// Crée pages:
// Page 1 (odd)  = TEXTE (right)  → PDF page 1
// Page 2 (even) = PHOTO (left)
// Page 3 (odd)  = TEXTE (right)  → PDF page 3
// ...
```

#### `extendIfNeeded()`
- Si le PDF a plus de pages que le livre actuel
- Ajoute automatiquement les pages manquantes
- Préserve les données photo existantes

### 2. **MarkdownPdfManager.php** — Synchronisation PDF
Gère la génération du PDF et la synchronisation des pages

#### `syncBookPdfPages()`
Appelée après chaque génération PDF. Effectue:

```
1. Récupère le page-map du markdown généré
2. Définit printablePages = dernière page du PDF
3. Ajuste totalPages (doit être pair)
4. Crée/met à jour les entrées pages dans book.json
5. Assigne les types (texte/photo) selon le page-map
```

**Logique:**
```php
$maxPage = max(array_keys($pageTypes));  // Dernière page du PDF
$book['printablePages'] = $maxPage;      // Pages imprimables

// totalPages doit être >= maxPage ET pair
if ($book['totalPages'] % 2 === 0) {
    $book['totalPages']++;  // Rend impair → pair
}
```

### 3. **book.json** — Format des Pages

```json
{
  "title": "Une Vie en Mouvement",
  "totalPages": 251,
  "printablePages": 234,
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
      "photos": [],
      "slotAssignments": {}
    },
    ...
  ],
  "properties": {
    "pageNumberOffset": 0,
    ...
  }
}
```

---

## 🔢 Numérotation des Pages

### Affichage avec Offset

**Dans la galerie** (gallery.php):
```php
$offset = $bookProperties['pageNumberOffset'] ?? 0;  // Ex: -2

// Affichage:
<span>p.<?php echo $leftPage['pageNumber'] + $offset; ?></span>
// Page 1 interne → affichée "p.-1" (couverture avant)
// Page 3 interne → affichée "p.1" (première page texte)
```

**Use case**: Livre avec 2 pages de couverture
- Pages 1-2 = couverture (offset -2)
- Pages 3-234 = contenu
- Affichées comme p.-1 à p.232

### Localisation dans Code

**gallery.php**:
- Ligne 5: `$printablePages` — Détermine les pages ignorées
- Ligne 217-218: Marque pages > printablePages comme "ignorées"

**Affichage des pages ignorées**:
```php
$showIgnoredSeparator = $isIgnoredSpread && $spreadFirstPage === $printablePages + 1;
// Affiche "Fin du fichier PDF texte" à la limite
```

---

## 🔄 Flux de Synchronisation

### 1. Génération PDF (Markdown → PDF)

```
markdown/livre.md
    ↓
MarkdownPdfManager::generate()
    ↓ (Pandoc + Typst)
outputs/texte.pdf (10 pages)
    ↓
outputs/texte.pdf (page-map.json)
    ↓
MarkdownPdfManager::syncBookPdfPages()
    ↓
SYNC_DIR/book.json
  - printablePages: 10
  - totalPages: 251 (inchangé)
  - pages[0..251]: types/pdfPage mis à jour
```

### 2. Édition du Livre

```
Édition gallery.php
    ↓
pages.php API (insertSpread, deleteSpread, etc.)
    ↓
BookManager::save()
    ↓
SYNC_DIR/book.json (photos/layouts mis à jour)
```

### 3. Génération PDF Final (Capture + Merge)

```
capture/capture-pages.js
    ↓ (Puppeteer, 300 DPI)
data/cache/screenshots/
    ↓
capture/merge-hq-pdf-v2.js
    ↓ (pdf-lib)
capture/livre.print.pdf
```

---

## ⚙️ Constantes Clés

**app/config/config.php**:

```php
define('PAGE_WIDTH', 240);        // Largeur page (mm)
define('PAGE_HEIGHT', 160);       // Hauteur page (mm)
define('TOTAL_PAGES', 251);       // Pages par défaut au démarrage
define('MARKDOWN_DIR', ...);      // Markdown source
define('BOOK_JSON', ...);         // book.json (livre + métadatas)
define('SOURCE_PDF', ...);        // texte.pdf (PDF généré)
```

**layout.php** (BOOK_CONFIG JavaScript):

```javascript
const BOOK_CONFIG = {
    pageWidth: 240,
    pageHeight: 160,
    totalPages: 251,           // Total pages
    printablePages: 234,       // Pages à imprimer
    pageNumberOffset: 0,       // Offset affichage
    pdfVersion: 1778238904,    // Timestamp pour cache
};
```

---

## 🔍 Exemple: Ajouter une Page

### Étape 1: UI (gallery.php)
```html
<button class="spread-insert-btn">+ Insérer une planche après</button>
```

### Étape 2: API (pages.php)
```javascript
App.api('pages.php', { action: 'insertSpread', spread: 5, page: 0 })
```

### Étape 3: Backend (pages.php)
```php
case 'insertSpread':
    // Crée 2 nouvelles pages (photo + texte)
    // Décale les pages existantes
    // Sauvegarde book.json
```

### Résultat:
- `totalPages` augmente de 2
- Nouvelles pages insérées aux bons numéros
- Toutes les références PageNumber mises à jour

---

## 🚨 Problèmes Courants

### Problème: printablePages = 0 ou vide
**Cause**: Aucun PDF généré
**Solution**: Générer le PDF via MarkdownPdfManager

### Problème: Pages manquantes dans book.json
**Cause**: Désynchronisation après PDF update
**Solution**: Appeler `BookManager::extendIfNeeded()`

### Problème: Offset pageNumberOffset ne s'applique pas
**Cause**: Offset défini dans properties mais non appliqué au template
**Vérifier**: gallery.php ligne 251 utilise bien `$offset`

---

## 📊 Résumé

| Concept | Fichier | Clé |
|---------|---------|-----|
| Structure livre | `SYNC_DIR/book.json` | `pages[]` |
| Pages imprimables | `book.json` | `printablePages` |
| Pages totales | `book.json` | `totalPages` |
| Numéro offset | `book.json` properties | `pageNumberOffset` |
| Génération PDF | `MarkdownPdfManager.php` | `syncBookPdfPages()` |
| Modification pages | `BookManager.php` | `getPage(), editPage()` |
| Affichage galerie | `gallery.php` | Ligne 217-252 |
