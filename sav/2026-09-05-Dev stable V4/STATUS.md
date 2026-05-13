# 📊 Status ProjetPhoto - État du Projet

**Date:** 2026-05-06  
**Mode:** En restructuration  

---

## 🎯 Vue d'ensemble

**Objectif:** Déployer l'app photo-book sur `livre.ammae.net` (serveur) tout en gardant une capacité de génération PDF locale (Windows).

**Architecture cible:**
- **Serveur (Linux)**: Éditeur photo + éditeur texte markdown + photos
- **Local (Windows)**: Génération PDF + synchronisation serveur

---

## ✅ Complété (Phase 1-4 de déploiement)

### Phase 1: Linux Compatibility ✅
| Fichier | Change | Status |
|---------|--------|--------|
| `app/src/PdfManager.php` | ✅ OS detection + Linux paths | Complété |
| `app/public/api/markdown.php` | ✅ Guard generateTextPdf (local only) | Complété |
| `app/public/.htaccess` | ✅ Fixed .htpasswd path (Linux) | Complété |
| `app/public/api/pdf.php` | ✅ Guard generateFinalPdf (local only) | Complété |

### Phase 2: Data Sync Endpoint ✅
| Fichier | Change | Status |
|---------|--------|--------|
| `app/public/api/sync.php` | 🆕 Export endpoint (book.json, markdown, photos) | Créé |
| `app/config/config.php` | ✅ Added SYNC_TOKEN | Complété |

### Phase 3: Local Orchestration ✅
| Fichier | Change | Status |
|---------|--------|--------|
| `capture/capture-pages.js` | ✅ VIEW_URL configurable + HTTP Basic Auth | Modifié |
| `capture/generate-pdf.js` | 🆕 Main orchestrator (pull→generate→capture→merge) | Créé |
| `capture/.env.example` | 🆕 Configuration template | Créé |

### Phase 4: CLI Text PDF ✅
| Fichier | Change | Status |
|---------|--------|--------|
| `app/public/api/cli-generate-text.php` | 🆕 CLI script for text PDF generation | Créé |

---

## 🚧 En cours (Phase 5: Restructuration)

### 📖 **VOIR: [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md)**

Ce document contient **toutes les actions détaillées** pour Phase 5:
- 🔍 Exactement quoi créer, modifier, déplacer
- 📋 Code à changé (avant/après)
- ✅ Checklist d'implémentation

### Structure cible:
```
projetPhoto/
├── app/                  ← CODE À DÉPLOYER
├── capture/             ← SCRIPTS LOCAUX
├── tools/               ← 🆕 OUTILS DE DEV (fonts, styles, markdown)
├── docs/                ← 🆕 DOCUMENTATION STRUCTURÉE
├── data/                ← DONNÉES (3 sous-parties)
│   ├── LOCAL_ONLY/      (ne jamais syncer)
│   ├── SYNC/            (FTP sync local ↔ serveur)
│   └── trash/           (anciens fichiers, pour rollback)
├── STATUS.md            ← CE FICHIER (vue globale)
└── IMPLEMENTATION_PLAN.md ← DÉTAILS COMPLETS (lire celui-ci!)
```

---

## 📋 Workflow Final (cible)

```
JOUR 1 - LOCAL (Windows)
├─ Édite projetPhoto/data/LOCAL_ONLY/markdown/livre.md
├─ Génère texte.pdf via UI "Génération PDF"
└─ Upload via UI "Synchronisation" → serveur

JOUR 2 - SERVEUR (Linux, livre.ammae.net)
├─ Ouvre editor → ajoute photos
├─ Place photos sur le texte
└─ Clique "Sauvegarder"
   ├─ book.json mis à jour
   └─ Versioning auto (book.v001.json, book.v002.json, etc.)

JOUR 3 - LOCAL (Windows)
├─ Download via UI "Synchronisation"
│  ├─ book.json (positions des photos)
│  ├─ photos/ (toutes les photos)
│  └─ versions/ (historique)
├─ Génère PDF final via UI "Génération PDF"
│  ├─ Lit data/LOCAL_ONLY/outputs/texte.pdf
│  ├─ Lit data/SYNC/book.json
│  ├─ Lit data/SYNC/photos/
│  └─ Génère livre.print.pdf
└─ ✅ TERMINÉ
```

---

## 🔄 Fichiers Clés

### Code à déployer (app/)
- `config.php` - configuration
- `src/MarkdownPdfManager.php` - génération PDF texte
- `src/BookManager.php` - gestion livre
- `src/PdfManager.php` - conversion PDF → PNG (cache)
- `public/api/sync.php` - export données
- `public/api/upload-pdf.php` - import texte.pdf (NOUVEAU)
- `public/api/versions.php` - historique (NOUVEAU)

### Scripts locaux (capture/)
- `generate-pdf.js` - orchestre tout
- `capture-pages.js` - screenshots Puppeteer
- `merge-hq-pdf-v2.js` - fusionne PDF
- `.env` - configuration locale

### Données (data/)
- `LOCAL_ONLY/markdown/livre.md` - source éditable
- `LOCAL_ONLY/outputs/texte.pdf` - généré localement
- `SYNC/book.json` - synchronisé serveur ↔ local
- `SYNC/photos/` - synchronisé serveur ↔ local
- `SYNC/versions/` - historique book.json
- `trash/` - sauvegardes anciennes structures

### Documentation (docs/)
- `guides/` - guides pratiques
- `api/` - doc des endpoints
- `reference/` - références techniques
- `troubleshooting/` - résolution problèmes

### Outils (tools/)
- `font-tester/` - teste polices
- `typst-tester/` - teste styles
- `markdown-tester/` - teste markdown

---

## 🚀 Prochaines Étapes

**Phase 5 complète:**
1. Créer la nouvelle structure de répertoires
2. Déplacer/nettoyer les fichiers existants
3. Mettre à jour tous les chemins dans le code
4. Ajouter mode local/serveur
5. Créer les outils et documentation

**Estimation:** 4-6 heures pour tout faire correctement

---

## 📝 Notes

- ✅ Le code PHP est **portable** (fonctionne Windows et Linux)
- ✅ Les APIs de sync sont **créées** (`sync.php`)
- ⏳ La restructuration est **planifiée** mais pas encore faite
- ⏳ Le mode local/serveur est **conçu** mais pas codé
- ⏳ Les outils (tools/) sont **prévus** mais vides

**À FAIRE ENSUITE:**
1. Valider le plan de restructuration
2. Implémenter Phase 5 étape par étape
3. Tester en local et sur serveur
4. Déployer sur livre.ammae.net

---

**Dernier update:** 2026-05-06 par Claude Code
