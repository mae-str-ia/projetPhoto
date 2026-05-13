# ProjetPhoto - Architecture Détaillée & Diagrammes

## 1. Diagramme d'Architecture Globale

```
┌───────────────────────────────────────────────────────────────────┐
│                        FRONTEND (HTML/JS)                          │
│  ┌─────────────┬──────────────┬──────────┬────────────┬─────────┐ │
│  │   Gallery   │   Spread     │   Text   │   Media    │   View  │ │
│  │   (Spreads) │   Editor     │  Editor  │  Manager   │ (Preview)│ │
│  └─────────────┴──────────────┴──────────┴────────────┴─────────┘ │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │  app.js | page-editor.js | pdf-viewer.js | CSS             │  │
│  └─────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
                            ↕ (JSON + Fetch)
                     REST API (JSON)
┌─────────────────────────────────────────────────────────────────────┐
│                    API LAYER (PHP)                                  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  index.php (Router) → ?page=gallery|spread|editor|text-ed... │  │
│  └──────────────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │              API Endpoints (JSON)                            │  │
│  ├─────────────────────────────────────────────────────────────┤  │
│  │ /api/photos.php      POST {action, page, ...}               │  │
│  │ /api/pages.php       GET {action, pageNumber|spreadNumber}  │  │
│  │ /api/markdown.php    GET/POST {action, ...}                │  │
│  │ /api/media.php       GET/POST {action, ...}                │  │
│  │ /api/book.php        GET/POST {action, ...}                │  │
│  │ /api/pdf.php         GET {pageIndex}                        │  │
│  │ /api/export.php      POST {action: 'export'}               │  │
│  └──────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
                            ↕
┌─────────────────────────────────────────────────────────────────────┐
│              BUSINESS LOGIC LAYER (Managers)                        │
│  ┌────────────────┬──────────────┬────────────────────────────┐   │
│  │ BookManager    │ PhotoManager │ ImageProcessor             │   │
│  │ ├─ load/save  │ ├─ upload    │ ├─ crop                    │   │
│  │ ├─ media CRUD │ ├─ delete    │ ├─ rotate                  │   │
│  │ ├─ pages CRUD │ └─ validate  │ ├─ filter (GD)             │   │
│  │ └─ spreads    │              │ └─ frame                   │   │
│  └────────────────┴──────────────┴────────────────────────────┘   │
│  ┌──────────────────────┬──────────────────────────────────────┐  │
│  │ MarkdownTextManager  │ MarkdownPdfManager (1000 lines)      │  │
│  │ ├─ getExcerptForPage │ ├─ generateTextPdf (3 passes)       │  │
│  │ ├─ getContext        │ ├─ writeBuildMarkdown              │  │
│  │ ├─ saveExcerpt       │ ├─ runPandoc                        │  │
│  │ └─ readMarkdown      │ ├─ writeTypstFile                  │  │
│  │                      │ ├─ queryBuildMetadata              │  │
│  │                      │ ├─ buildFinalPageMap               │  │
│  │                      │ ├─ detectBlankPdfPages             │  │
│  │                      │ └─ postProcessPdfWithPageMap       │  │
│  └──────────────────────┴──────────────────────────────────────┘  │
│  ┌────────────────────┬──────────────────────────────────────┐   │
│  │ PdfManager         │ ExportManager                        │   │
│  │ ├─ getPageImage    │ ├─ generateWord (PHPWord)           │   │
│  │ ├─ convertPageToPng│ └─ generateWordSimple               │   │
│  │ ├─ countPages      │                                      │   │
│  │ └─ clearCache      │                                      │   │
│  └────────────────────┴──────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
                            ↕
┌─────────────────────────────────────────────────────────────────────┐
│              DATA LAYER (Persistent Storage)                        │
│  ┌──────────────┬──────────────┬──────────────┬──────────────┐    │
│  │ book.json    │ livre.md     │ PDF Files    │ Photo Files  │    │
│  │ (structure)  │ (text content)│ (generated) │ (uploads)    │    │
│  └──────────────┴──────────────┴──────────────┴──────────────┘    │
│  ┌────────────────────────────────────────────────────────────┐   │
│  │ data/                                                      │   │
│  │ ├─ book.json                          [Master Structure]   │   │
│  │ ├─ markdown/clean/livre.md            [Text Source]        │   │
│  │ ├─ markdown/build/                    [Generated]          │   │
│  │ │  ├─ livre.build.md, texte.raw.typ, toc.json            │   │
│  │ │  └─ page-map.json                                       │   │
│  │ ├─ pdf/                                                   │   │
│  │ │  ├─ source.pdf                      [For Preview]       │   │
│  │ │  ├─ texte.pdf, texte.processed.pdf  [Generated]        │   │
│  │ │  └─ [MISSING: page-1.pdf...364.pdf] [Photo pages]      │   │
│  │ ├─ uploads/photos/                    [User Uploads]      │   │
│  │ └─ pdf-cache/page_*.png               [PNG Cache]         │   │
│  └────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
                            ↕
┌─────────────────────────────────────────────────────────────────────┐
│          EXTERNAL TOOLS & DEPENDENCIES                              │
│  ┌──────────────┬──────────────┬──────────────┬──────────────┐    │
│  │ pandoc       │ typst        │ Calibre      │ Ghostscript  │    │
│  │ (Markdown    │ (PDF         │ (pdfinfo,    │ (PDF render) │    │
│  │  → Typst)    │  compiler)   │  pdftoppm)   │              │    │
│  └──────────────┴──────────────┴──────────────┴──────────────┘    │
│  ┌──────────────────────────────────┐                            │
│  │ Python 3.9 (pdf_postprocess.py)  │  [TO BE REMOVED]         │
│  │ ├─ Detect blank pages            │                            │
│  │ ├─ Insert blank pages            │                            │
│  │ └─ Reorder pages with pageMap    │                            │
│  └──────────────────────────────────┘                            │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 2. Flux de Données - Édition Photo

```
USER ACTION: "Ajouter/Éditer photo sur page 10"
    ↓
Frontend - Spread Editor
    ├─ Display: GET /api/pages.php?action=getSpread&spread=5
    │   ↓
    │ Backend: BookManager::getSpread(5)
    │   ├─ Read book.json
    │   ├─ Return leftPage (pageNumber=10, type=photo)
    │   └─ Return rightPage (pageNumber=11, type=text)
    │   ↓
    ├─ Display left page (photo) + right page (text)
    ↓
USER: "Upload photo.jpg"
    ↓
Frontend - Form Submit
    ├─ POST /api/photos.php
    │   {
    │     "action": "upload",
    │     "page": 10,
    │     "file": <binary>
    │   }
    ↓
Backend - photos.php
    ├─ PhotoManager::upload($file, 10)
    │   ├─ Validate MIME type, size
    │   ├─ Move to /uploads/photos/photo_69f330903b0d24.jpg
    │   ├─ Get dimensions via getimagesize()
    │   ├─ Calculate initial frame size (based on aspect ratio)
    │   └─ Return photo metadata
    ├─ BookManager::updatePage(10, {...photos...})
    │   ├─ Load book.json
    │   ├─ Find page.pageNumber == 10
    │   ├─ Append new photo to page['photos'][]
    │   └─ Save book.json atomically
    ↓
Response JSON
    {
      "success": true,
      "data": {
        "id": "photo_69f330903b0d24",
        "filename": "photo_69f330903b0d24.jpg",
        "width": 4000,
        "height": 3000,
        "frame": { "x": 5, "y": 5, "w": 22, "h": 22, ... },
        ...
      }
    }
    ↓
Frontend
    ├─ Receive response
    ├─ Add photo to DOM in Spread Editor
    ├─ Photo now visible on page with frame, position
    └─ User can drag/rotate/crop without saving (client-side)
    ↓
USER: "Save changes"
    ↓
Frontend
    ├─ POST /api/photos.php
    │   {
    │     "action": "update",
    │     "page": 10,
    │     "photoId": "photo_69f330903b0d24",
    │     "frame": { "x": 5, "y": 5, "w": 25, "h": 25, "rotation": 15 },
    │     "crop": { "zoom": 1.2, "panX": 10, "panY": 5 },
    │     "caption": "Ma photo préférée"
    │   }
    ↓
Backend
    ├─ BookManager::getPage(10)
    ├─ Find photo by id
    ├─ Update photo properties (frame, crop, caption)
    ├─ BookManager::save()
    └─ Return updated page
    ↓
Frontend
    └─ Refresh display
```

---

## 3. Flux Données - Génération PDF Texte

```
USER ACTION: "Générer PDF texte"
    ↓
Frontend
    ├─ POST /api/markdown.php
    │   { "action": "generateTextPdf", "copyToSource": true }
    ↓
Backend - MarkdownPdfManager::generateTextPdf()
    │
    ├─────────────────────────────────────────────────────┐
    │ PASS 1: Extract Metadata                             │
    ├─────────────────────────────────────────────────────┤
    │ 1. writeBuildMarkdown(null, emitSectionMetadata=true)│
    │    ├─ Regex replacements (separators, toc markers)   │
    │    ├─ Inject blockquote class markers                │
    │    ├─ Inject running header metadata                 │
    │    └─ Output: data/markdown/build/livre.build.md     │
    │                                                       │
    │ 2. runPandoc()                                       │
    │    ├─ pandoc livre.build.md --to typst -o raw.typ    │
    │    └─ Output: texte.raw.typ                          │
    │                                                       │
    │ 3. writeTypstFile(emitHeadingMetadata=true)          │
    │    ├─ Prepend prelude (page setup, margins)          │
    │    ├─ Add heading metadata queries                   │
    │    └─ Output: texte.typ                              │
    │                                                       │
    │ 4. typst compile texte.typ texte.pdf                │
    │    └─ Output: texte.pdf (brouillon)                  │
    │                                                       │
    │ 5. queryBuildMetadata()                              │
    │    ├─ typst query texte.typ metadata                │
    │    ├─ Extract headings (title, sourcePage)           │
    │    ├─ Extract sections (id, layout, sourcePage)      │
    │    └─ Output: headings[], sections[]                 │
    │                                                       │
    │ 6. detectBlankPdfPages()                             │
    │    ├─ python pdf_postprocess.py --blank-pages        │
    │    └─ Output: blankPages[]                           │
    ├─────────────────────────────────────────────────────┐
    │ PASS 2: Build Table of Contents                      │
    ├─────────────────────────────────────────────────────┤
    │ 1. buildFinalPageMap(sections, sourcePageCount)      │
    │    ├─ For each sourcePage in PDF:                   │
    │    │   ├─ Determine section layout                  │
    │    │   ├─ Check if blank                            │
    │    │   ├─ Calculate finalPageNumber (with blanks)   │
    │    │   └─ sourceToFinal[sourcePage] = finalPage     │
    │    └─ Output: pageMap {sourceToFinal, blanksBefore} │
    │                                                       │
    │ 2. applyFinalPagesToHeadings()                       │
    │    ├─ For each heading:                             │
    │    │   heading['finalPage'] = pageMap[sourcePage]   │
    │    └─ Update headings with final page numbers        │
    │                                                       │
    │ 3. buildManualTocMarkdown(headings)                  │
    │    └─ Generate Typst ToC block                       │
    │                                                       │
    │ 4. writeBuildMarkdown(manualToc, emitSectionMetadata)│
    │    └─ Inject manual ToC                              │
    │                                                       │
    │ 5. runPandoc() → writeTypstFile() → compile          │
    │    └─ Regenerate PDF with ToC                        │
    ├─────────────────────────────────────────────────────┐
    │ PASS 3: Final Rendering                              │
    ├─────────────────────────────────────────────────────┤
    │ 1. writeBuildMarkdown(manualToc, emitSectionMetadata=false)
    │    └─ Inject ToC (no metadata)                       │
    │                                                       │
    │ 2. writeTypstFile(false, sourceToFinalPageMap)       │
    │    ├─ Generate page map variable for Typst          │
    │    ├─ Update outside-page-number() function         │
    │    │   (uses final-page-map for numbering)          │
    │    └─ Output: texte.typ                              │
    │                                                       │
    │ 3. typst compile texte.typ texte.pdf                │
    │    └─ Output: texte.pdf (final)                      │
    │                                                       │
    │ 4. postProcessPdfWithPageMap(pageMap)               │
    │    ├─ python pdf_postprocess.py texte.pdf           │
    │    ├─ Insert blank pages between text pages          │
    │    └─ Output: texte.processed.pdf                    │
    │                                                       │
    │ 5. if (copyToSource): cp texte.processed.pdf → source.pdf
    │                                                       │
    │ 6. PdfManager::clearCache()                          │
    │    └─ Delete pdf-cache/*.png                         │
    │                                                       │
    │ 7. syncBookPdfPages()                                │
    │    ├─ Update book.json pages based on final PDF     │
    │    └─ Ensure page count matches                      │
    └─────────────────────────────────────────────────────┘
    ↓
Response
    {
      "success": true,
      "data": {
        "pdf": "data/pdf/texte.processed.pdf",
        "finalPageCount": 156,
        "sourcePdfUpdated": true,
        "markdown": "data/markdown/clean/livre.md",
        "toc": "data/markdown/build/toc.json"
      }
    }
    ↓
Frontend
    ├─ Refresh PDF viewer with new source.pdf
    └─ Display updated page count
```

---

## 4. Flux Données - Génération PDF Final (Pages Photo + Texte)

```
USER ACTION: "Cliquer bouton 'Générer PDF Final'"
    ↓
Frontend
    ├─ POST /api/export.php?action=generateFinalPdf
    ├─ Display: "Capture des pages photo (étape 1/2)..."
    │
    ├─ Polling /api/export.php?action=getPdfStatus toutes les 2s
    │   └─ Affiche progression + temps restant estimé
    │
Backend - ExportManager::generateFinalPdf()
    │
    ├─────────────────────────────────────────┐
    │ STEP 1: Capture pages photo             │
    ├─────────────────────────────────────────┤
    │
    │ exec('cd ../capture && node capture-pages.js')
    │
    │ capture-pages.js (Puppeteer):
    │  ├─ Lancé via Node.js
    │  ├─ Puppeteer.launch() → headless Chrome
    │  │
    │  └─ Pour chaque page photo dans book.json:
    │     ├─ setViewport(2834×1889px @ 300 DPI)
    │     ├─ goto('http://localhost:8081/?page=view&num=N')
    │     ├─ Attendre networkidle2
    │     ├─ Attendre images complètement chargées
    │     ├─ screenshot() → data/screenshots/page-NNN.png
    │     └─ Temps: ~4s/page × 182 pages ≈ 12 minutes
    │
    │ Output: 182 fichiers PNG en data/screenshots/
    │
    ├─────────────────────────────────────────┐
    │ STEP 2: Fusionner PDF texte + photos    │
    ├─────────────────────────────────────────┤
    │
    │ exec('cd ../capture && node merge-hq-pdf-v2.js')
    │
    │ merge-hq-pdf-v2.js (pdf-lib):
    │  ├─ Charger book.json (structure pages)
    │  ├─ Charger page-map.json (mapping texte)
    │  ├─ Charger texte.pdf (pages texte pré-générées)
    │  ├─ PDFDocument.create() → nouveau PDF
    │  │
    │  └─ Pour chaque page du livre:
    │     ├─ Si type=photo:
    │     │   ├─ Lire data/screenshots/page-NNN.png
    │     │   ├─ pdfDoc.embedPng()
    │     │   └─ addPage() avec image
    │     │
    │     └─ Si type=text:
    │         ├─ Lookup dans page-map: textPageIndex
    │         ├─ pdfDoc.copyPages(textPdfDoc, [textPageIndex])
    │         └─ addPage() avec contenu copié
    │
    │ Output: livre.print.pdf (complet, alternant texte+photos)
    │ Temps: ~10 secondes
    │
    └─────────────────────────────────────────┘
    ↓
Backend returns:
    {
      "success": true,
      "status": "complete",
      "pdf": "/livre.print.pdf",
      "pageCount": 156,
      "fileSize": "85.2 MB",
      "totalTime": "12:42"
    }
    ↓
Frontend
    ├─ Arrêter polling
    ├─ Afficher ✅ "PDF généré avec succès!"
    └─ Proposer téléchargement ou ouverture livre.print.pdf
```

**Caractéristiques importantes:**
- ✅ Résolution: 300 DPI (2834×1889 px) = qualité impression
- ✅ Ordre pages: Respecte book.json (photo/texte alternance)
- ✅ Marges: Gérées automatiquement par CSS et page-map
- ✅ Gestion erreurs: Continue même si une page échoue (warnings)
- ⏱️ Temps total: 12-15 minutes (long mais sans intervention)

---

## 5. Fichier book.json - Structure Complète

```json
{
  "title": "Une Vie en Mouvement",
  "totalPages": 364,
  "printablePages": 156,
  
  "pageDimensions": {
    "width": 240,
    "height": 160
  },
  
  "properties": {
    "photoPageMargins": {
      "topCm": 1.0,
      "rightCm": 1.0,
      "bottomCm": 1.0,
      "leftCm": 1.0
    },
    "bindingCm": 2.0,
    "textPageMargins": {
      "topCm": 2.0,
      "rightCm": 2.0,
      "bottomCm": 2.0,
      "leftCm": 2.0
    },
    "textBindingCm": 3.0,
    "pageNumberOffset": 0,
    "defaultLayout": "4-grille"
  },
  
  "media": [
    {
      "id": "m_69f330903b0d24",
      "filename": "photo_69f330903b0d24.98733175.jpg",
      "width": 4000,
      "height": 3000,
      "uploadedAt": "2026-04-30T15:30:00Z",
      "defaultCaption": "Coucher de soleil sur la montagne"
    },
    {
      "id": "m_69f330903b8c02",
      "filename": "photo_69f330903b8c02.96467252.jpg",
      "width": 5000,
      "height": 3000,
      "uploadedAt": "2026-05-01T10:15:00Z",
      "defaultCaption": "Paysage automnal"
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
          "mediaId": "m_69f330903b0d24",
          "caption": "Moment magique au coucher",
          "captionAlign": "left",
          "rotation": 0,
          "filter": "none",
          "frame": {
            "x": 5,
            "y": 5,
            "w": 22,
            "h": 22,
            "z": 1,
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
        },
        {
          "id": "photo_69f330903b8c02",
          "filename": "photo_69f330903b8c02.96467252.jpg",
          "mediaId": "m_69f330903b8c02",
          "caption": "Deuxième image",
          "captionAlign": "center",
          "rotation": 15,
          "filter": "sepia",
          "frame": {
            "x": 25,
            "y": 5,
            "w": 20,
            "h": 30,
            "z": 2,
            "shape": "rect",
            "ratio": "original",
            "borderWidth": 2,
            "borderColor": "#333333",
            "backgroundColor": "transparent"
          },
          "crop": {
            "fitMode": "contain",
            "zoom": 1.1,
            "panX": 10,
            "panY": -5
          },
          "width": 5000,
          "height": 3000
        }
      ]
    },
    {
      "pageNumber": 3,
      "side": "right",
      "type": "text",
      "pdfPage": 3
    },
    // ... 360 more pages alternating between type: "photo" (even) and "text" (odd)
  ]
}
```

---

## 5. Flux API - Endpoints & Responses

### GET /api/pages.php?action=getSpread&spread=5

**Request:**
```
GET /api/pages.php?action=getSpread&spread=5
```

**Response:**
```json
{
  "success": true,
  "data": {
    "spreadNumber": 5,
    "leftPage": {
      "pageNumber": 10,
      "side": "left",
      "type": "photo",
      "layout": "4-grille",
      "photos": [...]
    },
    "rightPage": {
      "pageNumber": 11,
      "side": "right",
      "type": "text",
      "pdfPage": 11
    }
  }
}
```

### POST /api/photos.php - Upload

**Request:**
```
POST /api/photos.php
Content-Type: multipart/form-data

action=upload&page=10&file=<binary>
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "photo_69f330903b0d24",
    "filename": "photo_69f330903b0d24.98733175.jpg",
    "caption": "",
    "captionAlign": "left",
    "rotation": 0,
    "filter": "none",
    "frame": {
      "x": 5, "y": 5, "w": 22.5, "h": 22.5, "z": 1,
      "shape": "rect", "ratio": "original",
      "borderWidth": 0, "borderColor": "white", "backgroundColor": "white"
    },
    "crop": {
      "fitMode": "cover", "zoom": 1, "panX": 0, "panY": 0
    },
    "width": 4000,
    "height": 3000
  }
}
```

### POST /api/markdown.php?action=generateTextPdf

**Request:**
```
POST /api/markdown.php
Content-Type: application/json

{
  "action": "generateTextPdf",
  "copyToSource": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "markdown": "data/markdown/clean/livre.md",
    "typst": "data/markdown/build/texte.typ",
    "pdf": "data/pdf/texte.processed.pdf",
    "textPdf": "data/pdf/texte.pdf",
    "toc": "data/markdown/build/toc.json",
    "pageMap": "data/markdown/build/page-map.json",
    "sourcePdfUpdated": true,
    "finalPageCount": 156
  }
}
```

### GET /api/markdown.php?action=getExcerptForPage&page=45

**Request:**
```
GET /api/markdown.php?action=getExcerptForPage&page=45
```

**Response:**
```json
{
  "success": true,
  "data": {
    "page": 45,
    "textPageIndex": 23,
    "textPageCount": 78,
    "start": 15240,
    "end": 23560,
    "excerpt": "...texte du markdown extrait...",
    "isApproximate": true,
    "sourceLength": 234560,
    "windowPages": 3
  }
}
```

---

## 6. Cicle de Vie des Fichiers

```
Creation Timeline:
─────────────────

Time 0: User imports PDF
   └─ source.pdf (manual upload)

Time 1: App initializes
   ├─ BookManager::initBook()
   └─ book.json created (364 pages: text/photo alternating)

Time 2: User edits markdown
   ├─ Updates livre.md
   └─ Saves to data/markdown/clean/livre.md

Time 3: User generates text PDF
   ├─ Markdown → Pandoc → Typst → PDF (3 passes)
   ├─ texte.pdf generated
   ├─ texte.processed.pdf generated (with blanks)
   ├─ page-map.json generated
   ├─ toc.json generated
   └─ source.pdf updated (if copyToSource=true)

Time 4: User uploads photos
   ├─ Each photo in: uploads/photos/photo_*.jpg
   ├─ Metadata in: book.json
   └─ PDF cache generated on demand: pdf-cache/page_*.png

Time 5: User adds photos to spreads
   ├─ Photos added to book.json (pages[].photos[])
   └─ [MISSING] Photo PDF pages generated: pdf/page-*.pdf

Time 6: Generate final PDF
   ├─ [MISSING] Assemble:
   │   ├─ texte.processed.pdf (pages 1-156)
   │   ├─ page-1.pdf (page 2 - first photo spread)
   │   ├─ page-2.pdf (page 3 - first text spread)
   │   └─ ...
   └─ final.pdf (complete book)


File Dependencies:
──────────────────

book.json ←─────────────────┬──────────────────────┐
  ├─ source: master state  │                      │
  ├─ used by: all managers │                      │
  └─ written by: APIs      │                      │
                           │                      │
livre.md                    │                      │
  ├─ source: markdown text │                      │
  ├─ editable by: Text Editor
  └─ read by: MarkdownPdfManager
                           │
                           ├─ pandoc
                           │   ├─ reads: livre.md
                           │   └─ output: texte.raw.typ
                           │
                           ├─ typst
                           │   ├─ reads: texte.typ (+ deco SVG)
                           │   └─ output: texte.pdf
                           │
                           ├─ pdf_postprocess.py
                           │   ├─ reads: texte.pdf, page-map.json
                           │   └─ output: texte.processed.pdf
                           │
                           └─ source.pdf [copy of texte.processed.pdf]
```

---

## 7. Class Diagram (Managers)

```
┌──────────────────────────────┐
│     BookManager              │
├──────────────────────────────┤
│ - BOOK_JSON                  │
├──────────────────────────────┤
│ + load(): array              │
│ + save(data): void           │
│ + initBook(pages): array     │
│ + getPage(num): array        │
│ + getSpread(num): array      │
│ + updatePage(num, data): bool│
│ + insertSpread(after): bool  │
│ + deleteSpread(num): bool    │
│ + getMedia(): array          │
│ + addMedia(data): array      │
│ + updateMediaCaption(): array│
│ + deleteMedia(id): bool      │
│ + getMediaUsage(id): array   │
│ + updateProperties(p): array │
│ + migrateToMedia(book): array│
└──────────────────────────────┘

┌──────────────────────────────┐
│    PhotoManager              │
├──────────────────────────────┤
│ - UPLOAD_DIR                 │
│ - MAX_FILE_SIZE              │
├──────────────────────────────┤
│ + upload(file, page): array  │
│ + getUploadUrl(filename): str│
│ + getUploadPath(filename):str│
│ + validatePhotoOwnership(): b│
│ + delete(photoId, page): bool│
└──────────────────────────────┘

┌──────────────────────────────┐
│   ImageProcessor             │
├──────────────────────────────┤
│ [PHP GD - limited use]       │
├──────────────────────────────┤
│ + process(src, ops): resource│
│ - applyCrop(img, crop): img  │
│ - applyRotation(img): img    │
│ - applyFilter(img, f): img   │
│ - applyFrame(img, f): img    │
└──────────────────────────────┘

┌────────────────────────────────┐
│    MarkdownTextManager         │
├────────────────────────────────┤
│ - MARKDOWN_FILE = clean/livre.md
├────────────────────────────────┤
│ + getExcerptForPage(n): array │
│ + getContext(s, e, dir): array│
│ + getAdjacentExcerpt(s,e): arr│
│ + saveExcerpt(s, e, text): arr│
│ - readMarkdown(): str         │
│ - writeMarkdown(text): void   │
│ - estimateTextPageCount(): int│
│ - moveStartToParagraph(): int │
│ - moveEndToParagraph(): int   │
└────────────────────────────────┘

┌──────────────────────────────────────────┐
│    MarkdownPdfManager                    │
│    [COMPLEX - 1000+ lines]              │
├──────────────────────────────────────────┤
│ + generateTextPdf(copyToSource): array   │
│ + addBlankPagesToTextPdf(): array        │
│ - ensureInputs(): void                   │
│ - writeBuildMarkdown(toc, emit): void    │
│ - runPandoc(pandoc): void                │
│ - writeTypstFile(emit, pageMap): void    │
│ - queryBuildMetadata(typst): array       │
│ - buildFinalPageMap(sections): array     │
│ - applyFinalPagesToHeadings(h, map): arr │
│ - buildManualTocMarkdown(h): str         │
│ - detectBlankPdfPages(path): array       │
│ - postProcessPdfWithPageMap(map): void   │
│ - syncBookPdfPages(): void               │
│ - findTool(name): str                    │
│ - run(args, throw): array                │
│ [+ 20 helper methods for Typst/Pandoc]  │
└──────────────────────────────────────────┘

┌──────────────────────────────────┐
│     PdfManager                   │
├──────────────────────────────────┤
│ - pdf_file = SOURCE_PDF          │
│ - cache_dir = PDF_CACHE_DIR      │
├──────────────────────────────────┤
│ + getPageImage(pageIndex): str   │
│ + countPages(): int              │
│ + getTotalPages(): int           │
│ + clearCache(): void             │
│ - convertPageToPng(idx): bool    │
│ - convertViaPdftoppm(...): bool  │
│ - convertViaGhostscript(...): boo│
│ - findPdftoppm(): str            │
│ - findGhostscript(): str         │
│ - getCacheFilePath(idx): str     │
│ - getCacheUrl(idx): str          │
└──────────────────────────────────┘

┌──────────────────────────────────┐
│    ExportManager                 │
├──────────────────────────────────┤
│ [Uses PHPWord library]           │
├──────────────────────────────────┤
│ + generateWord(): str            │
│ + generateWordSimple(): str      │
└──────────────────────────────────┘
```

---

## 8. Database Schema (book.json pseudo-schema)

```typescript
interface Book {
  title: string;
  totalPages: number;
  printablePages?: number;
  
  pageDimensions: {
    width: number;          // cm
    height: number;         // cm
  };
  
  properties: {
    photoPageMargins: Margins;
    bindingCm: number;
    textPageMargins: Margins;
    textBindingCm: number;
    pageNumberOffset: number;
    defaultLayout: 'pleine-page' | '2-cote' | '2-haut-bas' | 
                   '1g-2d' | '2g-1d' | '1h-2b' | '3-cote' | '4-grille' | 'free';
  };
  
  media: Media[];
  pages: Page[];
}

interface Margins {
  topCm: number;
  rightCm: number;
  bottomCm: number;
  leftCm: number;
}

interface Media {
  id: string;                  // "m_<uniqid>"
  filename: string;
  width: number;               // px
  height: number;              // px
  uploadedAt: string;           // ISO 8601
  defaultCaption: string;
}

interface Page {
  pageNumber: number;          // 1..364
  side: 'left' | 'right';
  type: 'text' | 'photo';
  pdfPage?: number;            // for text pages only
  layout?: string;              // for photo pages
  photos?: Photo[];             // for photo pages
}

interface Photo {
  id: string;                  // "photo_<uniqid>"
  filename: string;
  mediaId: string;             // reference to media.id
  caption: string;
  captionAlign: 'left' | 'center' | 'right';
  rotation: number;            // 0-360 degrees
  filter: 'none' | 'bw' | 'grayscale' | 'sepia' | 'vintage';
  frame: Frame;
  crop: Crop;
  width: number;               // original px
  height: number;              // original px
}

interface Frame {
  x: number;                   // cm
  y: number;                   // cm
  w: number;                   // cm (width)
  h: number;                   // cm (height)
  z: number;                   // z-index
  shape: 'rect' | 'circle' | 'polygon';
  ratio: 'original' | 'fit' | 'fill';
  borderWidth: number;         // cm
  borderColor: string;         // hex or name
  backgroundColor: string;
}

interface Crop {
  fitMode: 'cover' | 'contain';
  zoom: number;                // 1.0 = no zoom
  panX: number;                // % offset
  panY: number;                // % offset
}
```

---

## 9. State Diagram - Photo Lifecycle

```
        ┌─────────────────────────────────────┐
        │   Uploaded to server                │
        │   - File in uploads/photos/         │
        │   - Metadata in memory              │
        └─────────────────────────────────────┘
                      ↓
              [User clicks "Add"]
                      ↓
        ┌─────────────────────────────────────┐
        │   Added to Page                     │
        │   - In book.json pages[n].photos[]  │
        │   - Has ID, caption, default frame  │
        │   - Position: x,y,w,h               │
        └─────────────────────────────────────┘
                      ↓
         [User edits crop/rotation/filter]
                      ↓
        ┌─────────────────────────────────────┐
        │   Modified on Client               │
        │   - Frame updated (CSS transforms)  │
        │   - Crop/pan updated                │
        │   - No server changes yet           │
        └─────────────────────────────────────┘
                      ↓
              [User clicks "Save"]
                      ↓
        ┌─────────────────────────────────────┐
        │   Persisted to book.json            │
        │   - API: POST /api/photos.php       │
        │   - update action                   │
        │   - Saved with all transformations  │
        └─────────────────────────────────────┘
                      ↓
        ┌─────────────────────────────────────┐
        │   [Optional: Generate Preview PDF]  │
        │   - Puppeteer renders page photo    │
        │   - All transformations applied     │
        │   - High-res PDF or PNG output      │
        └─────────────────────────────────────┘
                      ↓
              [User clicks "Delete"]
                      ↓
        ┌─────────────────────────────────────┐
        │   Removed from Page                 │
        │   - Deleted from book.json          │
        │   - File stays in uploads/          │
        │   - Can be re-added from media lib  │
        └─────────────────────────────────────┘
                      ↓
        [Optionally: Clean unused files]
                      ↓
        ┌─────────────────────────────────────┐
        │   File Deleted                      │
        │   - DELETE /api/photos.php          │
        │   - deleteFile=true                 │
        │   - File removed from uploads/      │
        └─────────────────────────────────────┘
```

---

**End of Architecture Document**

