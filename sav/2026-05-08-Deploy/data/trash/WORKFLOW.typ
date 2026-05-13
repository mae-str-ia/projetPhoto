#set page(
  paper: "a4",
  margin: (top: 2cm, bottom: 2cm, left: 1.5cm, right: 1.5cm),
  numbering: "1 / 1",
  header: [
    #set text(size: 10pt, fill: gray)
    ProjetPhoto - Documentation Complète
  ],
  footer: [
    #set text(size: 9pt, fill: gray)
    #align(right)[
      #context "Page " + str(counter(page).get().first())
    ]
  ]
)

#set text(font: "New Computer Modern", size: 11pt, lang: "fr")
#set par(justify: true, leading: 1.5em)
#show heading: it => {
  if it.level == 1 {
    pagebreak()
  }
  it
}

// Title
#align(center)[
  #text(size: 28pt, weight: "bold")[ProjetPhoto]
  #text(size: 16pt, fill: blue)[Chaîne Complète de Génération du Livre PDF]
  #v(1em)
  #text(size: 11pt, style: "italic")[Documentation Complète - 2026-05-03]
]

#v(2em)

= Vue d'ensemble

ProjetPhoto est une application web permettant de créer et générer un *livre photo professionnel imprimable en 300 DPI* au format *24cm × 16cm* (A5 paysage). La génération implique la capture de pages web via Puppeteer, la fusion avec un PDF texte généré via Typst, et l'assemblage final en un PDF à haute résolution prêt pour l'imprimerie.

*Résultat final:* `livre.print.pdf` - 233 pages, 36.9 MB, 24×16cm à 300 DPI

= Architecture Générale

Les composants principaux de la solution:

- *Application Web (PHP)* - Visualisation des pages et paramétrage via book.json
- *Puppeteer* - Capture des pages en PNG haute résolution (2835×1890px)
- *Typst* - Génération du PDF texte vectoriel (120 pages)
- *pdf-lib* - Fusion des images PNG avec les pages PDF texte

= Technologies Utilisées

== Backend
- *PHP 8.5* - Serveur web, logique métier, rendu pages HTML
- *JSON* - Stockage données (book.json pour structure, page-map.json pour positions)
- *Puppeteer (Node.js)* - Capture de pages web en haute résolution PNG

== Frontend
- *HTML/CSS/JavaScript vanilla* - Interface d'édition
- *CSS Aspect-ratio* - Responsive design à proportions constantes

== Génération PDF
- *Typst* - Générateur PDF vectoriel pour les pages texte
- *pdf-lib (Node.js)* - Fusion des photos PNG et pages PDF texte

== Dimensions & Résolution
- *24cm × 16cm* page book (A5 paysage)
- *300 DPI* à l'impression
- *2835 × 1890 pixels* par page photo
- *Vectoriel pour le texte* (pas de rastérisation)

= Structure des Données

== book.json
*Localisation:* `data/book.json` \
*Rôle:* Source de vérité pour la structure du livre

Contient la structure complète du livre (366 pages = 183 photos + 183 texte).

Chaque page contient:
- `pageNumber`: Numéro de page final
- `type`: `"photo"` ou `"text"`
- `side`: `"left"` ou `"right"`
- `layout`: Type de mise en page
- `photos`: Array des IDs photos à afficher
- `photoLayout`: Configuration des positions/tailles

*Important:* book.json déclare 183 pages texte, mais le PDF final n'utilise que jusqu'à la page 233 (déterminé par texte.pdf réel qui contient 120 pages).

== page-map.json
*Localisation:* `data/markdown/build/page-map.json` \
*Généré par:* Process de génération Typst/Markdown

Contient:
- `sourceToFinal`: Mapping {textPageIndex → finalPageNumber}
- `sectionByPage`: Info de section pour chaque page
- `blanksBefore`: Pages blanches à insérer

Rôle: Source de vérité pour les positions des pages texte dans le PDF final

== texte.pdf
*Localisation:* `data/pdf/texte.pdf` \
*Généré par:* Typst (markdown → PDF vectoriel)

- Dimensions: 24cm × 16cm (680.31 × 453.54 points)
- Pages: 120 pages
- Contenu: Pages de remerciements, table des matières, corps du texte
- Qualité: Vectoriel, texte sélectionnable

= Chaîne Complète (Workflow)

== Phase 1: Interface Web & Configuration (PHP)
1. Accès: `http://localhost:port/app/`
2. Données source: `data/book.json`
3. Visualisation structure du livre page par page
4. Édition des paramètres des photos
5. Stockage: Modifications sauvegardées dans `data/book.json`
6. Endpoint de rendu: `GET /app/view.php?page=N`

== Phase 2: Génération Markdown & Typst
1. Entrée: book.json + structure markdown
2. Génération fichier `.md` pour chaque section
3. Injection métadonnées de section
4. Compilation Typst: markdown → PDF vectoriel
5. Output: `texte.pdf` (120 pages)
6. Side effect: Génère `page-map.json`

== Phase 3: Capture des Pages Photos (Puppeteer)
*Script:* `capture/capture-pages.js`

```
node capture-pages.js
```

Process:
1. Lance navigateur headless (Chromium)
2. Pour chaque page photo, navigue vers `http://localhost:port/app/view.php?page=N`
3. Attend que toutes les images soient chargées (3000ms timeout)
4. Viewport: *2835 × 1890 pixels* (300 DPI @ 24×16cm)
5. Prend screenshot PNG
6. Sauvegarde: `data/screenshots/page-XXX.png`

Résultat: 183 fichiers PNG de 2835×1890 pixels

== Phase 4: Fusion PDF (pdf-lib)
*Script principal:* `capture/merge-hq-pdf-v2.js`

```
node merge-hq-pdf-v2.js
```

Process:
1. Charge les données (book.json, page-map.json, texte.pdf, screenshots)
2. Crée document PDF avec dimensions 2835 × 1890 points (300 DPI)
3. Pour chaque page finale (1 à 233):
   - Si page texte: copie depuis texte.pdf via page-map
   - Si page photo: embed PNG
   - Skip pages au-delà de 233
4. Sauvegarde: `livre.print.pdf`

== Phase 5: Correction des Dimensions
*Script:* `capture/fix-pdf-size.js`

```
node fix-pdf-size.js
```

Problème: PDF généré affiche 100×66cm au lieu de 24×16cm

Solution: Scale toutes les pages par 0.24 (= 680.31 / 2835)

Résultat final: `livre.print.pdf` - 24cm × 16cm @ 300 DPI

= Éléments Importants à Retenir

== Mismatch book.json vs texte.pdf
- book.json déclare 183 pages texte
- texte.pdf n'en contient que 120
- Solution: Utiliser `page-map.json` comme source de vérité
- Générer seulement jusqu'à final page 233
- Warning pour les 67 photos non utilisées

== Résolution 300 DPI
- À la capture: 2835 pixels × 24cm = 300 DPI
- À l'affichage écran: 72 DPI (standard PDF)
- À l'impression: 300 DPI réel

== Chaîne de facteurs d'échelle
- PHP template: × 3.15 pour captions/borders
- Merge initial: Pages @ 2835×1890 points
- Fix final: × 0.24 pour affichage standard
- Résultat: Pages affichent 24×16cm, impriment 300 DPI

== Vectoriel vs Rasterisé
- Texte PDF (Typst): Vectoriel ✓
- Images photos (Puppeteer): PNG rasterisé
- Texte reste texte lors du scaling PDF

== Ordre des pages texte
- Ne pas supposer ordre séquentiel
- Utiliser `sourceToFinal` de page-map.json
- Text page N peut aller à final page N+X

= Lancer la Génération Complète

```
// 1. Capturer toutes les pages photos
cd projetPhoto/capture
node capture-pages.js

// 2. Générer texte PDF via Typst
// (génère texte.pdf + page-map.json)

// 3. Fusionner photos + texte
node merge-hq-pdf-v2.js

// 4. Corriger dimensions d'affichage
node fix-pdf-size.js
```

= Débogage Courant

#table(
  columns: 3,
  [*Problème*], [*Cause*], [*Solution*],
  [PDF 100×66cm], [Pas de fix-pdf-size.js], [Lancer `node fix-pdf-size.js`],
  [Photos floues], [Viewport ≠ 2835×1890], [Vérifier capture-pages.js],
  [Pages différentes tailles], [Texte pas rescalé], [Vérifier scaleRatio],
  [Texte rasterisé], [Embedding PNG au lieu de PDF], [Utiliser `copyPages()`],
  [Images non chargées], [Timeout trop court], [Augmenter timeout],
  [Pages 121-183 manquantes], [texte.pdf incomplet], [Régénérer avec Typst],
)

= Notes de Maintenance

- *book.json* peut devenir obsolète vs texte.pdf → vérifier page-map.json
- *Capture temps:* ~12 minutes pour 183 pages
- *Merge temps:* ~30 secondes
- *Fix temps:* ~20 secondes
- *Espace disque:* ~200 MB PNGs + 5 MB PDFs

---

#align(center)[
  #text(size: 9pt, fill: gray)[Généré: 2026-05-03 | ProjetPhoto v1.0]
]
