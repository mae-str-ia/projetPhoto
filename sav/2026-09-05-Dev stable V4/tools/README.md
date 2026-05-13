# Tools — Utilitaires de Développement

Collection d'outils pour tester et valider les différents composants de projetPhoto.

## Sous-répertoires

### font-tester/
Teste différentes polices PDF et génère des samples pour validation.
- `test-fonts.php` — Script de test des polices disponibles
- `test-fonts.html` — Aperçu navigateur des polices
- `samples/` — PDFs générés

### typst-tester/
Teste les différents styles Typst et validation du rendu.
- `test-styles.typ` — Fichier Typst avec différents styles (H1, H2, couleurs, etc.)
- `generate-test.php` — Génère les PDFs de test
- `outputs/` — PDFs générés

### markdown-tester/
Valide le support des différents formats markdown.
- `samples/` — Fichiers markdown d'exemple
  - `test-headings.md` — Titres et hiérarchie
  - `test-formatting.md` — Gras, italique, code, etc.
  - `test-special.md` — Éléments spéciaux, images, etc.
- `preview.php` — Affiche le rendu des samples

## Utilisation

Chaque outil est autonome et documenté avec son propre README.

```bash
# Tester les polices
cd tools/font-tester
php test-fonts.php

# Tester Typst
cd tools/typst-tester
php generate-test.php

# Tester le markdown
cd tools/markdown-tester
php preview.php --file samples/test-headings.md
```
