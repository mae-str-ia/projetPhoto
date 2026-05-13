# 📚 Documentation — projetPhoto

Guide complet pour installer, configurer et utiliser projetPhoto.

## 📖 Guides d'Installation et Configuration

Voir le répertoire `guides/` pour :
- **INSTALLATION.md** — Installer projetPhoto localement
- **CONFIGURATION.md** — Configurer config.php et variables d'environnement
- **LOCAL_MODE.md** — Utiliser en mode local (Windows)
- **SERVER_MODE.md** — Déployer sur serveur (Linux/production)
- **SYNC_WORKFLOW.md** — Synchroniser avec le serveur

## 🚀 Quick Start (5 min)

1. **Installation** : `php app/public/api/setup.php`
2. **Démarrer le serveur** : `php -S localhost:8081 -t app/public/`
3. **Ouvrir le navigateur** : http://localhost:8081
4. **Importer des photos** : Onglet Médiathèque

Voir `QUICK_START.md` pour les détails.

## 📚 Référence Complète

### API Endpoints (`api/`)
- **endpoints.md** — Liste tous les endpoints disponibles
- **sync.md** — Synchronisation avec le serveur (FTP)
- **upload-pdf.md** — Upload des PDFs générés
- **versions.md** — Gestion des versions de book.json

### Format et Structure (`reference/`)
- **BOOK_JSON_FORMAT.md** — Structure complète de book.json
- **MARKDOWN_FORMAT.md** — Format markdown supporté
- **STYLES.md** — Styles PDF disponibles (polices, couleurs, etc.)
- **FONTS.md** — Polices PDF disponibles
- **ARCHITECTURE.md** — Architecture technique interne

### Dépannage (`troubleshooting/`)
- **SETUP_ISSUES.md** — Problèmes d'installation
- **PDF_GENERATION.md** — Problèmes de génération de PDF
- **SYNC_ISSUES.md** — Problèmes de synchronisation
- **FONT_ISSUES.md** — Problèmes de polices

## 📂 Structure des Répertoires

```
projetPhoto/
├── app/
│   ├── config/           Configuration
│   ├── src/              Classes PHP (Manager, etc.)
│   ├── public/           Serveur web (index.php, api/)
│   ├── templates/        Templates HTML
│   └── css/              Styles CSS
├── capture/              Capture de pages (Puppeteer)
├── data/
│   ├── LOCAL_ONLY/       Données locales non synchronisées
│   ├── SYNC/             Données synchronisées (FTP)
│   ├── cache/            Cache local (screenshots, PDFs)
│   ├── outputs/          PDFs générés
│   ├── markdown/         Fichier markdown source
│   └── trash/            Anciennes versions
├── tools/                Outils de développement
├── docs/                 Cette documentation
└── capture/              Scripts Node.js de capture
```

## ⚙️ Modes d'Exécution

### Mode Local (Windows)
- Édition du livre en local
- Génération des PDFs localement
- Upload manuel au serveur
- Synchronisation par FTP

### Mode Serveur (Linux/Production)
- Application en ligne accessible
- Édition collaborative
- Synchronisation automatique
- Backup réguliers

Voir `guides/LOCAL_MODE.md` et `guides/SERVER_MODE.md`.

## 🔧 Outils de Développement

Les outils se trouvent dans `tools/` :
- **font-tester/** — Valider les polices PDF
- **typst-tester/** — Tester les styles Typst
- **markdown-tester/** — Valider le markdown

## 📝 Statut du Projet

Voir `STATUS.md` pour :
- Phases d'implémentation
- Fonctionnalités complétées
- Travaux en cours

## 🆘 Support et Issues

Pour un problème :
1. Consultez d'abord la section `troubleshooting/`
2. Vérifiez le fichier `STATUS.md` pour les problèmes connus
3. Vérifiez les logs : `data/logs/`

## 📜 Licence

projetPhoto — 2026

