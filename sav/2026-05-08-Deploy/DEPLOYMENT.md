# Déploiement projetPhoto - 2026-05-08

## Structure prête

Tous les fichiers sont dans `projetPhoto/sav/2026-05-08-Deploy/`

## Étapes de déploiement

### 1. Via FTP
Copie tout le contenu vers la racine du serveur web de production.

### 2. APP_MODE déjà configuré

✓ Le `.htaccess` inclut `SetEnv APP_MODE server`

Rien à faire — le serveur utilisera automatiquement le mode production.

## Configuration auto-détectée

Le `config.php` s'adapte automatiquement:
- **APP_MODE=server** → `display_errors=0`, `log_errors=1`
- **Pas défini + Windows** → MODE=local (dev)
- **Pas défini + Linux** → MODE=server (prod)

## Fichiers clés

- `index.php` — Authentification HTTP Basic (roger / livre2026)
- `app/config/config.php` — Configuration unique pour local et prod
- `.htaccess` — Routing Apache (uploads → data/uploads, reste → app/public)
- `data/SYNC/book.json` — Données du livre (251 pages)
- `data/outputs/texte.pdf` — PDF généré (234 pages avec blanks)
- `data/uploads/photos/` — Photos du livre

## Verification

Teste après déploiement:
- Accès au site: `https://domaine.com/` (demande roger/livre2026)
- Gallery affiche les pages correctement
- PDFs se génèrent sans erreur

## Notes

- Aucun fichier de test (`test-*.php`) n'est inclus
- Les configs local et prod sont **identifiées** par APP_MODE
- Les répertoires de données sont préservés
