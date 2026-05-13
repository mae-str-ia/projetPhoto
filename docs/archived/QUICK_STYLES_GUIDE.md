# ⚡ Guide Rapide - Styles PDF Texte

## 🎯 Réponse Directe

**Les styles pour la génération PDF texte sont stockés à:**

```
📍 app/src/MarkdownPdfManager.php
   └─ Méthode: writeTypstFile()
   └─ Lignes: 499-550
```

**Aucun fichier CSS ou SCSS séparé.**  
Tout est **généré dynamiquement en PHP** puis injecté dans Typst.

---

## 🗂️ Où Sont Quoi

| Style | Localisation |
|-------|--------------|
| **Marges page** | `data/book.json` → `properties.textPageMargins` |
| **Police/Taille** | `app/src/MarkdownPdfManager.php:527` |
| **Titres (H1/H2)** | `app/src/MarkdownPdfManager.php:534-542` |
| **Numéros page** | `app/src/MarkdownPdfManager.php:502-509` |
| **En-têtes courants** | `app/src/MarkdownPdfManager.php:511-524` |
| **Table des matières** | `app/src/MarkdownPdfManager.php:544-548` |
| **Dialogue/Courrier** | `app/src/MarkdownPdfManager.php:441, 453` |

---

## 📝 Modifier un Style - Exemples

### Exemple 1: Changer la taille de police

**Fichier:** `app/src/MarkdownPdfManager.php` ligne 527

```php
// AVANT
#set text(font: "Arial", size: 11pt, lang: "fr")

// APRÈS (pour 12pt)
#set text(font: "Arial", size: 12pt, lang: "fr")
```

### Exemple 2: Changer les marges

**Fichier:** `app/src/MarkdownPdfManager.php` ligne 464-467

```php
// AVANT
'topCm' => 2,
'rightCm' => 2,

// APRÈS
'topCm' => 2.5,
'rightCm' => 2.5,
```

**OU via API:**
```bash
POST /api/book.php
{
  "action": "updateProperties",
  "textPageMargins": { "topCm": 2.5, ... }
}
```

### Exemple 3: Changer couleur en-têtes

**Fichier:** `app/src/MarkdownPdfManager.php` ligne 518

```php
// AVANT (gris)
fill: rgb("#666666")

// APRÈS (bleu foncé)
fill: rgb("#003366")
```

### Exemple 4: Taille titre H1

**Fichier:** `app/src/MarkdownPdfManager.php` ligne 537

```typst
// AVANT
#text(size: 20pt, weight: "bold")

// APRÈS (plus grand)
#text(size: 24pt, weight: "bold")
```

---

## 🔄 Après Modification

**Régénérer le PDF:**

```bash
POST /api/markdown.php?action=generateTextPdf
```

Le PDF est recompilé automatiquement avec les nouveaux styles.

---

## 📌 3 Niveaux de Styles

### 1. Styles Marges (Modifiables via UI/API)
- Lieu: `data/book.json`
- Via API: `/api/book.php` action `updateProperties`
- Appliquées dynamiquement

### 2. Styles Texte (Modifiables via code PHP)
- Lieu: `MarkdownPdfManager.php:527-542`
- Requiert modification code source
- Regex chercher ligne exacte

### 3. Styles Typst Générés (Générés à la compilation)
- Lieu: `data/markdown/build/texte.typ` (généré)
- Ne pas modifier directement
- Régénérer via API

---

## ✨ Tableau Récapitulatif

```
┌─────────────────────┬──────────────────┬─────────────────┐
│ Style               │ Type             │ Localisation    │
├─────────────────────┼──────────────────┼─────────────────┤
│ Marges              │ Config DB        │ book.json       │
│ Police              │ Hardcoded        │ MarkdownPdf:527 │
│ Taille              │ Hardcoded        │ MarkdownPdf:527 │
│ Titres              │ Hardcoded        │ MarkdownPdf:534 │
│ En-têtes            │ Hardcoded        │ MarkdownPdf:511 │
│ Numéros page        │ Hardcoded        │ MarkdownPdf:502 │
│ Couleurs            │ Hardcoded        │ MarkdownPdf:518 │
└─────────────────────┴──────────────────┴─────────────────┘
```

---

## 🚀 Tâches Courantes

**Changer la taille de police générale?**
→ `MarkdownPdfManager.php:527`

**Changer les marges?**
→ `data/book.json` ou API `/api/book.php`

**Changer l'apparence des titres?**
→ `MarkdownPdfManager.php:534-542`

**Changer la couleur des en-têtes?**
→ `MarkdownPdfManager.php:518-519`

**Changer l'interligne?**
→ `MarkdownPdfManager.php:528` (leading)

---

📖 Pour plus de détails: Voir `STYLES_PDF_TEXTE.md`

