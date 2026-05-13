# 🎨 Styles PDF Texte - Où Sont-Ils Stockés?

## 📍 Localisation Principale

**Fichier:** `app/src/MarkdownPdfManager.php`  
**Méthode:** `writeTypstFile()`  
**Lignes:** 499-550

Tous les styles Typst sont **générés dynamiquement en PHP** et injectés dans le fichier Typst avant compilation.

---

## 🏗️ Structure des Styles

### 1. **Configuration de Page** (Ligne 526)

```typst
#set page(
  width: 24cm,
  height: 16cm,
  margin: (
    left: {$left}cm,       # Calculé desde book.json properties
    right: {$right}cm,
    top: {$top}cm,
    bottom: {$bottom}cm
  ),
  footer: outside-page-number(),
  foreground: running-page-header()
)
```

**Source des marges:** `app/src/MarkdownPdfManager.php` ligne 462-470
```php
$margins = $props['textPageMargins'] ?? [
    'topCm' => 2,
    'rightCm' => 2,
    'bottomCm' => 2,
    'leftCm' => 2,
];
$binding = (float)($props['textBindingCm'] ?? 3);
```

### 2. **Configuration de Texte** (Ligne 527-528)

```typst
#set text(font: "Arial", size: 11pt, lang: "fr")
#set par(justify: true, leading: 0.62em)
```

**Paramètres fixes:**
- Police: Arial
- Taille: 11pt
- Langue: Français
- Justification: Oui
- Interligne: 0.62em

### 3. **Numérotation des Pages** (Lignes 502-509)

```typst
#let outside-page-number() = context {
  let source-page = counter(page).get().first()
  let n = if source-page <= final-page-map.len() { 
    final-page-map.at(source-page - 1) 
  } else { 
    source-page 
  }
  if calc.odd(n) {
    align(right)[#n]  # Pages impaires (droite)
  } else {
    none  # Pages paires (pas de numéro visible)
  }
}
```

### 4. **En-têtes Courants (Running Headers)** (Lignes 511-524)

```typst
#let running-page-header() = context {
  // Affiche uniquement sur pages droites (impaires)
  if calc.odd(n) and n >= 9 {
    place(top + left, dx: 5cm, dy: 0.7cm)[
      #text(size: 8pt, style: "italic", fill: rgb("#666666"))
      [Une Vie en Mouvement]
    ]
    place(top + right, dx: -2cm, dy: 0.7cm)[
      #text(size: 8pt, style: "italic", fill: rgb("#666666"))
      [#h.part — #h.chapter]
    ]
  }
}
```

**Styles:**
- Taille: 8pt
- Style: Italique
- Couleur: #666666 (gris)
- Position: Haut page, 5cm gauche et 2cm droite

### 5. **Titres/Headings** (Lignes 534-542)

```typst
#show heading: it => {
  if it.level == 1 {
    block(above: 2.4em, below: 1.8em)[
      #text(size: 20pt, weight: "bold")[#it.body]
    ]
  } else if it.level == 2 {
    block(above: 1.8em, below: 1.3em)[
      #text(size: 16pt, weight: "bold")[#it.body]
    ]
  } else {
    it
  }
}
```

**H1 (##):**
- Taille: 20pt
- Poids: Bold
- Espace avant: 2.4em
- Espace après: 1.8em

**H2 (###):**
- Taille: 16pt
- Poids: Bold
- Espace avant: 1.8em
- Espace après: 1.3em

**H3+ (####):** Non stylisés (utilise défaut)

### 6. **Table des Matières** (Lignes 544-548)

```typst
#show outline.entry: it => {
  let physical-page = context counter(page).at(...)
  block(width: 100%)[
    #h((it.level - 1) * 1em)  # Indentation par niveau
    #it.element.body
    #box(width: 1fr, it.fill)  # Pointillés
    #physical-page             # Numéro de page
  ]
  v(0.55em)  # Espacement vertical
}
```

### 7. **Blocs Spécialisés** (Lignes 453-456)

```typst
// Dialogue
#block(width: 100%, inset: (left: 0.5em, right: 2em), 
       stroke: (left: 0.75pt + black))[...]

// Courrier (lettre)
#block(width: 100%, inset: (left: 1em, right: 1em))[
  #set text(font: "Segoe Print", size: 10pt)[...]
]
```

---

## 🔧 Comment Modifier les Styles

### 1. Modifier les Marges

**Fichier:** `app/src/MarkdownPdfManager.php` ligne 462-470

```php
$margins = $props['textPageMargins'] ?? [
    'topCm' => 2,        // ← MODIFIER ICI
    'rightCm' => 2,      // ← MODIFIER ICI
    'bottomCm' => 2,     // ← MODIFIER ICI
    'leftCm' => 2,       // ← MODIFIER ICI
];
```

**Ou via API:** 
```bash
POST /api/book.php
{
  "action": "updateProperties",
  "textPageMargins": {
    "topCm": 2.5,
    "rightCm": 2.5,
    "bottomCm": 2.5,
    "leftCm": 2.5
  }
}
```

Sauvegardé dans: `data/book.json` → `properties.textPageMargins`

### 2. Modifier la Police ou Taille

**Fichier:** `app/src/MarkdownPdfManager.php` ligne 527

```php
// Chercher cette ligne dans writeTypstFile():
$prelude = <<<TYPST
#set text(font: "Arial", size: 11pt, lang: "fr")
```

**Changer:**
- Font: "Arial" → "Times New Roman", "Garamond", etc.
- Taille: 11pt → 12pt, 10pt, etc.

### 3. Modifier les Couleurs d'En-tête

**Fichier:** `app/src/MarkdownPdfManager.php` ligne 518-519

```php
fill: rgb("#666666")  // ← Changer la couleur hex
```

### 4. Modifier les Espacements Titres

**Fichier:** `app/src/MarkdownPdfManager.php` ligne 537-539

```typst
block(above: 2.4em, below: 1.8em)[  // ← Ajuster espaces
  #text(size: 20pt, ...)[...]
]
```

### 5. Modifier la Police Dialogue/Courrier

**Fichier:** `app/src/MarkdownPdfManager.php` ligne 452-456

```php
'courrier' => '#block(...)[#set text(font: "Segoe Print", size: 10pt)...'
```

---

## 📊 Tableau des Styles

| Élément | Police | Taille | Couleur | Localisation |
|---------|--------|--------|---------|--------------|
| Corps texte | Arial | 11pt | Noir | L.527 |
| H1 | Arial | 20pt | Noir | L.537 |
| H2 | Arial | 16pt | Noir | L.539 |
| En-têtes | Arial | 8pt | #666666 | L.518-519 |
| Dialogue | Arial | 11pt | Noir | L.441 |
| Courrier | Segoe Print | 10pt | Noir | L.453 |
| Numéros page | Arial | 11pt | Noir | L.506 |

---

## 🔄 Flux de Génération

```
1. Charger book.json
   ↓
2. BookManager::load() 
   → Récupère properties.textPageMargins
   ↓
3. MarkdownPdfManager::writeTypstFile()
   → Injecte les marges dans $left, $right, $top, $bottom
   → Génère $prelude avec tous les styles
   ↓
4. Écrire texte.typ avec styles
   → Fichier: data/markdown/build/texte.typ
   ↓
5. Typst compile texte.typ
   → Utilise tous les styles définis
   → Génère texte.pdf
```

---

## 📁 Fichiers Concernés

| Fichier | Rôle |
|---------|------|
| `app/src/MarkdownPdfManager.php` | 🎨 Génère tous les styles Typst |
| `data/book.json` | 📊 Stocke les valeurs de marges |
| `data/markdown/build/texte.typ` | 📄 Fichier Typst compilé (généré) |
| `data/pdf/texte.pdf` | 📑 PDF résultant |

---

## ✏️ Exemple: Modifier la Taille de Police

**Avant:**
```php
#set text(font: "Arial", size: 11pt, lang: "fr")
```

**Après (pour 12pt):**
```php
#set text(font: "Arial", size: 12pt, lang: "fr")
```

**Puis régénérer:**
```bash
POST /api/markdown.php?action=generateTextPdf
```

---

## 🎯 Points Clés

✅ **Tous les styles sont générés en PHP** (pas de fichier CSS séparé)  
✅ **Les marges sont stockées dans book.json** (modifiables via API)  
✅ **Les autres styles sont hardcodés** dans MarkdownPdfManager.php  
✅ **Typst interprète les styles** lors de la compilation  
✅ **Pas de feuille de style externe** (tout en ligne dans le Typst)

---

**Source:** `app/src/MarkdownPdfManager.php` méthodes `writeTypstFile()` (L.461-555)

