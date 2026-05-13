# Capture — Scripts Node.js pour PDF

Scripts pour capturer et fusionner les pages photos et texte en un PDF final.

## Scripts Actifs

### capture-pages.js
Capture les pages photo du livre en haute résolution (300 DPI) via Puppeteer.

```bash
node capture-pages.js
```

**Entrée :** `../data/book.json`  
**Sortie :** `../data/cache/screenshots/`

### merge-hq-pdf-v2.js
Fusionne le PDF texte avec les screenshots photos en un seul PDF final.

```bash
node merge-hq-pdf-v2.js
```

**Entrée :**
- `../data/outputs/texte.pdf` (PDF texte généré)
- `../data/cache/screenshots/` (pages photos)
- `../data/markdown/build/page-map.json` (mapping des pages)

**Sortie :** `./livre.print.pdf`

### generate-pdf.js
Script de génération complète (capture + merge en une seule commande).

```bash
node generate-pdf.js
```

## Configuration

Créer `.env` à partir de `.env.example` :

```bash
cp .env.example .env
```

Variables possibles :
- `VIEW_URL` — URL du serveur web pour capturer les pages
- `SITE_PASSWORD` — Mot de passe si le site est protégé
- `DPI` — Résolution (défaut: 300)

## Dépendances

```bash
npm install
```

Bibliothèques principales :
- `puppeteer` — Capture de pages navigateur
- `pdf-lib` — Manipulation de PDFs
- Autres dépendances dans `package.json`

## Fichiers Archivés

Ancien code et scripts de test dans `archived/` :
- Anciennes versions de merge (merge-pdf.js, merge-hq-pdf.js)
- Scripts de test (test-*.js)
- Logs de génération

## Workflow Complet de Génération

### Regénération des Screenshots (si manquants)

Les screenshots des pages photos peuvent avoir été supprimés accidentellement. Pour les regénérer :

```bash
# 1. Aller au répertoire capture
cd projetPhoto/capture

# 2. Installer/mettre à jour les dépendances
npm install

# 3. Capturer les pages à 300 DPI
node capture-pages.js
# Entrée: ../data/book.json
# Sortie: ../data/cache/screenshots/

# 4. Fusionner avec le PDF texte
node merge-hq-pdf-v2.js
# Entrée: ../data/outputs/texte.pdf + ../data/cache/screenshots/
# Sortie: ./livre.print.pdf (à la racine de capture/)
```

**Temps estimé:** 15-20 minutes pour 100+ pages

### Regénération Complète (PDF texte + photos)

```bash
# Depuis capture/
node generate-pdf.js
```

Cela combine capture et merge en une seule commande.

## Notes

- Tous les chemins sont relatifs au répertoire `capture/`
- Le PDF final `livre.print.pdf` est généré à la racine de `capture/`
- Les screenshots utilisent 300 DPI pour haute qualité
- La fusion page-par-page permet une gestion fine du layout
- Si capture-pages.js échoue : vérifier que le serveur web fonctionne (http://localhost:8081)
