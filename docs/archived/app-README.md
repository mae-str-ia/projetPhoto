# projetPhoto

Application web PHP pour composer un livre photo de 160 pages.

## Installation

### 1. Dépendances système

- PHP 8.0+ avec extensions : `gd`, `fileinfo`, `mbstring`
- Ghostscript (pour convertir les pages PDF en images)
  - Windows : installer depuis https://www.ghostscript.com/download/gsdnld.html
  - Ou ImageMagick comme alternative

### 2. Installation PHP

```bash
cd c:/Devs/ProjetCR/projetPhoto

# Installer les dépendances Composer
composer install
```

Si composer n'est pas installé, télécharger depuis : https://getcomposer.org

### 3. Configuration

1. Placer le PDF source dans `pdf/source.pdf`
   - Le PDF doit contenir les pages de texte (160 pages)

2. Créer le fichier `data/book.json` initial (automatique au premier accès)

3. Vérifier les permissions sur les répertoires :
   - `data/` (lecture/écriture)
   - `public/uploads/photos/` (lecture/écriture)
   - `data/pdf-cache/` (lecture/écriture)

## Lancement

### Via PHP intégré

```bash
cd c:/Devs/ProjetCR/projetPhoto/public
"C:/Devs/php85/php.exe" -S localhost:8081
```

Puis ouvrir : http://localhost:8081

### Via Apache

Configurer Apache pour que DocumentRoot pointe vers `projetPhoto/public/`

## Structure des pages

- **Pages impaires (1, 3, 5...)** : pages de texte (depuis le PDF)
  - Affichées à droite dans les spreads
  - Read-only

- **Pages paires (2, 4, 6...)** : pages photo (éditables)
  - Affichées à gauche dans les spreads
  - Contiennent les compositions photo

## Navigation

1. **Vue vignettes (défaut)** : `?page=gallery`
   - Grille scrollable de tous les spreads
   - Clic pour zoomer

2. **Vue double-page** : `?page=spread&spread=N`
   - Page gauche : aperçu des photos
   - Page droite : page PDF de texte
   - Clic sur page gauche pour éditer

3. **Éditeur de page** : `?page=editor&page=N`
   - Drag/resize des photos
   - Uploader des photos
   - Ajouter captions
   - Changer layout

## API endpoints

- `POST /api/photos.php`
  - `action: upload` - Télécharger une photo
  - `action: update` - Modifier position/caption/filtre/cadre
  - `action: delete` - Supprimer une photo

- `POST /api/pages.php`
  - `action: get` - Récupérer les données d'une page
  - `action: save` - Sauvegarder le layout d'une page

- `POST /api/pdf.php`
  - `action: get_page` - Obtenir l'URL d'une page PDF convertie en PNG

- `POST /api/export.php`
  - `action: export` - Générer et télécharger le .docx

## Formats supportés

- **Photos** : JPEG, PNG, WebP
- **Taille max par photo** : 50 MB
- **Layouts** : 1, 2, 3, 4 photos ou libre (custom)
- **Filtres** : aucun, B&W, sépia, vintage
- **Cadres** : aucun, fin, épais, ombre

## Données

Toutes les données sont stockées en JSON :
- `data/book.json` : structure du livre (160 pages)
- `public/uploads/photos/` : fichiers photos uploader
- `data/pdf-cache/` : pages PDF converties en PNG

Pas de base de données SQL.

## Dépannage

### PDF ne s'affiche pas
- Vérifier que Ghostscript ou ImageMagick est installé et dans le PATH
- Tester manuellement : `gswin64c -v` (Windows)

### Photos ne s'upload pas
- Vérifier les permissions sur `public/uploads/photos/`
- Vérifier la taille du fichier (max 50 MB)
- Vérifier le type MIME

### Export Word échoue
- Vérifier que Composer dependencies sont installées : `composer install`
- Vérifier que le répertoire `vendor/` existe

## Notes

- Application mono-utilisateur (pas d'authentification)
- Toutes les modifications sont sauvegardées en temps réel en JSON
- Pas de système d'undo/redo (à implémenter si besoin)
- PDF source ne peut pas être changé dynamiquement (édition manuelle du fichier `pdf/source.pdf` requise)
