# 📊 Statut du Projet — projetPhoto

Dernier update: 2026-05-08

## ✅ Phases Complétées

### Phase 5 — Restructuration Complète ✓
- ✓ Réorganisation hiérarchique (LOCAL_ONLY, SYNC, cache, outputs)
- ✓ Séparation des données locales vs synchronisées
- ✓ Tous les chemins mis à jour (config, managers, scripts)
- ✓ .gitignore complet mis en place
- ✓ Documentation créée (README guides)
- ✓ Nettoyage des fichiers éparpillés

### Zoom Control ✓
- ✓ Déplacé dans le bandeau sticky du header
- ✓ CSS styling ajouté
- ✓ Ancien toolbar supprimé de gallery.php
- ✓ Références JavaScript mises à jour
- ✓ Tests validés

### API Endpoints ✓
- ✓ `upload-pdf.php` — Upload texte.pdf avec versioning
- ✓ `versions.php` — Lister et récupérer versions de book.json
- ✓ `texte-pdf.php` — Servir le PDF texte (migration vers API)

---

## 🚧 Travaux en Cours

### Menu "Mode Local" — Synchronisation FTP
État: Partiellement implémenté (HTTP au lieu de FTP)

**Fonctionnalités à implémenter:**

#### Génération PDF (côté local)
- [ ] **Générer texte.pdf** — Appel endpoint markdown → PDF via Pandoc
- [ ] **Capturer pages photo** — Puppeteer capture 300 DPI
- [ ] **Générer PDF final** — Merge texte.pdf + screenshots via pdf-lib

#### Synchronisation FTP
- [ ] **Upload texte.pdf** — Envoyer vers serveur (FTP/SFTP)
- [ ] **Download du serveur** — Récupérer images + book.json (FTP/SFTP)
- [ ] **Voir versions** — Liste des versions serveur (FTP/SFTP)

**Implémentation actuelle:**
- `localUploadTextPdf()` — HTTP POST (fonctionnel mais pas FTP)
- `localDownloadFromServer()` — Non implémenté
- `localGenerateTextPdf()` — Placeholder alert
- `localCapturePagesDialog()` — Placeholder alert
- `localGenerateFinalPdf()` — Placeholder alert

---

## 📋 Tâches Prioritaires

### 1. Finaliser Menu Local (FTP Sync)
- [ ] Implémenter FTP upload/download vs HTTP
- [ ] Tester sync bidirectionnelle
- [ ] Ajouter barre de progression pour uploads
- [ ] Gestion des erreurs FTP robuste

### 2. Compléter Génération PDF
- [ ] Intégrer appels Pandoc pour texte.pdf
- [ ] Configurer Puppeteer pour captures photos
- [ ] Tester merge PDF final

### 3. Screenshots Vides
- [ ] Régénérer screenshots via `node capture-pages.js`
- [ ] Fusionner PDF final via `node merge-hq-pdf-v2.js`
- Temps estimé: 15-20 minutes pour 100+ pages

### 4. Port 8081
- [ ] Résoudre conflit Docker (actuellement bloqué)
- [ ] Server actuellement sur port 8090 pour tests
- Dépend de: Configuration Docker/WSL

---

## 🐛 Problèmes Connus

### Port 8081 Indisponible
- **Cause**: Docker Desktop occupe le port via com.docker.backend
- **Impact**: Serveur PHP lancé sur 8090 pour tests
- **Solution**: À définir (arrêter Docker, reconfigurer, utiliser WSL, etc.)

### Screenshots Directory Vide
- **Cause**: Suppression accidentelle pendant restructuration
- **Impact**: Galerie charge mais pas de prévisualisations photos
- **Solution**: Régénérer via `node capture-pages.js`
- **Temps**: ~15-20 min

### Fonctionnalités Placeholder
- `localGenerateTextPdf()` — Alert dev uniquement
- `localCapturePagesDialog()` — Alert dev uniquement
- `localGenerateFinalPdf()` — Alert dev uniquement
- `localDownloadFromServer()` — Alert dev uniquement

---

## 📈 Architecture Actuelle

```
Mode Local (Développement)
├── Édition livre (gallery, editor)
├── Menu "Mode Local" (bas à droite)
│   ├── Génération PDF (placeholder)
│   └── Sync FTP (partiellement implémenté)
└── Synchronisation Serveur
    ├── Upload via HTTP (fonctionnel)
    ├── Download via FTP (à faire)
    └── Versioning (fonctionnel)
```

---

## 🔗 Fichiers Concernés

**Frontend:**
- `app/templates/layout.php` — Menu local + zoom header
- `app/templates/gallery.php` — Galerie + zoom control
- `app/public/js/app.js` — Event handlers

**Backend:**
- `app/public/api/upload-pdf.php` — Upload endpoint
- `app/public/api/versions.php` — Versions endpoint
- `app/src/MarkdownPdfManager.php` — Génération PDF

**Scripts:**
- `capture/capture-pages.js` — Capture screenshots
- `capture/merge-hq-pdf-v2.js` — Merge PDFs
- `capture/generate-pdf.js` — Pipeline complet

---

## 📅 Prochaines Étapes Suggérées

1. **Valider** que zoom header fonctionne correctement
2. **Résoudre** port 8081 si prioritaire
3. **Implémenter** FTP sync pour menu local
4. **Régénérer** screenshots si galerie nécessaire
5. **Tester** pipeline complet (capture → merge → upload)

