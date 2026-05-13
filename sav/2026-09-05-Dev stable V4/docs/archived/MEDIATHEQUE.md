# Médiathèque — Document de conception

## Objectif

Centraliser toutes les photos dans une médiathèque globale. Les pages du livre
référencent les photos de cette médiathèque au lieu de les posséder directement.
Une même photo peut être utilisée sur plusieurs pages avec des réglages différents.

---

## Modèle de données

### `book.json` — nouveau tableau `media[]`

```json
{
  "media": [
    {
      "id": "m_abc123",
      "filename": "photo_xxx.jpg",
      "width": 3000,
      "height": 2000,
      "uploadedAt": "2026-04-30T10:00:00",
      "defaultCaption": "Vue du jardin"
    }
  ]
}
```

### `page.photos[]` — ajout d'un champ `mediaId`

Le champ `filename` est conservé en doublon pour ne pas casser le code existant.
Tous les réglages (crop, frame, filter, caption…) restent par-page.

```json
{
  "id": "p_xyz",
  "mediaId": "m_abc123",
  "filename": "photo_xxx.jpg",
  "caption": "Légende spécifique à cette page",
  "crop": { "zoom": 1.2, "panX": 5, "panY": 0, "fitMode": "cover" },
  "frame": { "shape": "rect", "borderWidth": 0 },
  "filter": "",
  "rotation": 0
}
```

### Migration automatique

Au premier chargement, les photos existantes dans les pages qui n'ont pas de
`mediaId` sont enregistrées dans `media[]`. Déclenchée dans `BookManager::load()`.

---

## Architecture

### Nouveaux fichiers

| Fichier | Rôle |
|---|---|
| `app/public/api/media.php` | API REST : list, upload, delete, updateCaption |
| `app/src/MediaManager.php` | Logique métier médiathèque |
| `app/templates/media.php` | Page médiathèque |
| `app/public/js/media.js` | JS de la page médiathèque |

### Fichiers modifiés

| Fichier | Modification |
|---|---|
| `app/src/BookManager.php` | Ajout `media[]`, migration auto, méthodes CRUD |
| `app/public/api/photos.php` | Upload → crée entrée media + photo de page |
| `app/public/index.php` | Route `?page=media` |
| `app/templates/layout.php` | Lien "Médiathèque" dans le nav |
| `app/templates/editor.php` | Bouton "Ajouter depuis médiathèque" dans la sidebar |
| `app/public/js/page-editor.js` | Modal picker médiathèque, upload vers media |

---

## Pages et UI

### Page Médiathèque (`?page=media`)

- Grille de toutes les photos
- Pour chaque photo :
  - Vignette
  - Légende par défaut (éditable inline)
  - Badge "utilisée sur N pages" avec tooltip listant les pages
  - Bouton supprimer (désactivé si utilisée)
- Bouton upload en haut
- Pas de drag & drop (pas nécessaire ici)

### Éditeur — sidebar

- Inchangée : montre les photos assignées à la page courante
- Nouveau bouton **"+ Médiathèque"** en haut de la sidebar (à côté de l'upload)
- Clic → ouvre un modal picker

### Modal picker médiathèque (dans l'éditeur)

- Grille des photos de la médiathèque
- Filtre : toutes / non utilisées sur cette page
- Clic sur une photo → l'ajoute à la page (crée une entrée dans `page.photos[]`)
- La légende par défaut de la media est copiée dans la photo de page

### Upload (depuis l'éditeur)

Flux actuel : upload → `page.photos[]`
Nouveau flux : upload → `media[]` + `page.photos[]` (en une seule requête)

---

## API `media.php`

| Action | Paramètres | Résultat |
|---|---|---|
| `list` | — | `{ media: [...] }` avec `usedBy` par photo |
| `upload` | fichier multipart, `page?` | crée dans media[], optionnellement dans page |
| `delete` | `mediaId` | erreur si utilisée sur une page |
| `updateCaption` | `mediaId`, `caption` | met à jour `defaultCaption` |

---

## Ordre d'implémentation

1. `BookManager` — structure `media[]` + migration + CRUD
2. `MediaManager` — wrapper logique
3. `media.php` API
4. `photos.php` — upload crée aussi l'entrée media
5. Navigation + route `?page=media`
6. Page médiathèque (template + JS)
7. Éditeur — modal picker + bouton
8. Tests et vérification migration

---

## Ce qui ne change pas

- Le code de rendu des photos (galerie, éditeur, export) lit `filename` → inchangé
- Les réglages (crop, frame, filter) restent par-page
- Le drag & drop vers les slots reste identique
- `PhotoManager::delete()` supprime de `page.photos[]` uniquement (pas de media)
