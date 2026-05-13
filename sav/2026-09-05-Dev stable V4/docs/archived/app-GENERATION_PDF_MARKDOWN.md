# Generation du PDF depuis Markdown

## Objectif

Le texte du livre doit avoir une source unique au format Markdown. Ce fichier Markdown sert a generer les pages texte du livre, tandis que les planches photo deja editees dans l'application servent a generer les pages image.

Le PDF final ne doit donc plus etre un fichier PDF importe et remplace manuellement. Il doit devenir une sortie generee a partir de deux sources :

- le Markdown continu pour le texte ;
- `book.json` et les images importees pour les planches photo.

## Principe retenu

Le fichier Markdown reste un texte continu. Il ne doit pas etre decoupe en fichiers par page et il ne doit pas contenir de marqueurs de pagination imposes par l'application.

La pagination du texte est faite au moment de la generation PDF. Le texte remplit automatiquement les pages texte, dans l'ordre.

Le PDF final est ensuite construit en alternant :

- une page texte generee depuis le Markdown ;
- une page photo generee depuis une planche de l'application ;
- puis la page texte suivante ;
- puis la planche photo suivante.

La convention exacte gauche/droite doit rester coherente avec le livre courant. Dans l'etat vise, les pages texte correspondent aux pages de droite et les planches photo aux pages de gauche.

## Fichiers

Organisation proposee :

```text
projetPhoto/data/markdown/source/
```

Contient les fichiers Markdown originaux importes, conserves comme reference.

```text
projetPhoto/data/markdown/clean/livre.md
```

Source principale editable. C'est le seul fichier Markdown qui fait foi pour le texte du livre.

```text
projetPhoto/data/markdown/build/
```

Contient les fichiers generes automatiquement pendant la construction : HTML, Typst, carte de pagination, logs, etc.

```text
projetPhoto/data/pdf/source.pdf
```

PDF texte ou PDF de reference utilise pour l'aperçu dans l'application.

```text
projetPhoto/data/pdf/final.pdf
```

PDF final du livre, contenant texte et planches photo.

## Nettoyage du Markdown

Le fichier Markdown importe depuis Word ou Pandoc doit etre nettoye avant de devenir `clean/livre.md`.

Le controle deja fait sur le fichier Markdown indique que le probleme principal n'est pas l'encodage UTF-8 : les accents semblent bien presents. Le nettoyage concerne plutot :

- les artefacts Pandoc ou Word ;
- les ancres inutiles ;
- les chemins d'images ;
- les attributs techniques d'images ;
- les espaces et retours de ligne parasites ;
- les styles qui devraient etre reportes dans le template PDF plutot que dans le texte.

Le fichier nettoye doit rester lisible et modifiable a la main.

## Edition du texte

Le bouton "Editer le texte" sur une page texte doit ouvrir un editeur Markdown.

Comme le Markdown reste continu et sans marqueurs de page, l'application ne peut pas savoir de maniere absolue quelle portion exacte du Markdown correspond a une page PDF. La pagination depend du moteur de rendu, des polices, des marges, des titres et des sauts de page.

L'approche retenue est donc :

- conserver un seul fichier `livre.md` ;
- generer une carte de pagination separee dans `build/page-map.json` ;
- utiliser cette carte pour ouvrir un extrait autour de la page courante ;
- afficher dans l'editeur le texte correspondant approximativement a la page precedente, la page courante et la page suivante ;
- a la sauvegarde, remplacer l'extrait dans `livre.md` ;
- regenerer ensuite le PDF texte et la carte de pagination.

Cette carte de pagination est un fichier technique genere. Elle ne doit pas polluer le Markdown source.

## Moteur de rendu propose

Le moteur propose est :

```text
Pandoc + Typst
```

Pandoc lit le Markdown et le convertit vers un format intermediaire propre, typiquement Typst.

Typst genere le PDF avec une mise en page precise :

- taille de page exacte : 24 cm x 16 cm ;
- marges parametrees ;
- polices integrees ;
- texte vectoriel ;
- images conservees en haute resolution ;
- sortie PDF reproductible ;
- option PDF/A possible si necessaire.

Cette chaine est plus simple a maintenir qu'une chaine LaTeX complete, tout en permettant un PDF de bonne qualite pour l'impression.

## Marges texte

Les marges des pages texte doivent etre parametrees separement des marges des planches photo.

Valeurs de base :

- haut : 2 cm ;
- bas : 2 cm ;
- gauche : 2 cm ;
- droite : 2 cm ;
- reliure texte : 3 cm en plus du cote interieur.

Dans le livre courant, les pages texte sont des pages de droite. Le cote interieur est donc le cote gauche de la page texte, ce qui donne par defaut :

```text
haut: 2 cm
bas: 2 cm
gauche: 5 cm
droite: 2 cm
```

Ces valeurs sont stockees dans les proprietes du livre, separement des marges photo.

Les pages texte doivent aussi contenir une sequence d'ouverture :

```text
page 1 : page blanche, sans numero ;
page 2 : page gauche blanche ;
page 3 : page droite de remerciements ;
page 4 : sommaire, page gauche ;
page 5 : sommaire, page droite ;
page 6 : page gauche blanche ;
page 7 : debut du livre, page droite.
```

Les pages de droite doivent etre les pages impaires du PDF final. Les pages gauches blanches comptent dans la pagination du livre. Les numeros visibles sur les pages texte et les numeros affiches dans le sommaire doivent donc etre les numeros physiques du PDF final, pas les numeros des seules pages texte.

## Qualite d'impression

Pour obtenir un PDF adapte a l'impression, il faudra verifier :

- format final exact du livre ;
- marges et eventuel fond perdu selon l'imprimeur ;
- resolution des planches photo, idealement autour de 300 dpi ;
- absence de recompression inutile des images ;
- integration correcte des polices ;
- rendu noir et blanc/couleur conforme ;
- profil couleur si l'imprimeur l'exige.

Le texte genere par Typst sera vectoriel. Le point le plus sensible sera donc surtout le rendu des planches photo.

## Generation des planches photo

Les pages photo doivent etre generees depuis les donnees deja presentes dans l'application :

- dimensions de page ;
- marges ;
- layout ;
- cadres ;
- zoom et deplacement dans le cadre ;
- legendes ;
- alignements ;
- filtres ;
- images originales.

Chaque planche doit etre rendue a la taille physique finale du livre. Pour l'impression, le rendu image doit viser une resolution suffisante.

Pour une page de 24 cm x 16 cm a 300 dpi, la taille raster cible est environ :

```text
2835 x 1890 px
```

Si les planches peuvent etre generees directement en PDF vectoriel, c'est preferable pour les textes et cadres. Sinon, un rendu PNG haute resolution peut convenir, a condition de ne pas degrader les images.

## Assemblage du PDF final

La generation finale devra produire :

```text
texte.pdf
photos.pdf ou pages_photo/*.pdf
final.pdf
```

Puis assembler les pages dans l'ordre du livre.

Outils possibles :

- `qpdf` pour assembler et manipuler les pages PDF ;
- une librairie PHP specialisee ;
- une etape Typst unique si les pages photo peuvent etre injectees proprement.

Le choix exact dependra du niveau de controle necessaire et des outils disponibles sur le poste.

## Dependances locales

Dependances minimales proposees :

```powershell
pandoc --version
typst --version
```

Dependance probable pour l'assemblage final :

```powershell
qpdf --version
```

Le projet utilise deja Calibre/Poppler pour convertir des pages PDF en images d'aperçu :

```text
C:\Program Files\Calibre2\app\bin\pdfinfo.exe
C:\Program Files\Calibre2\app\bin\pdftoppm.exe
```

## Etapes de mise en oeuvre

1. Creer ou choisir `data/markdown/clean/livre.md` comme source texte unique.
2. Ajouter un gestionnaire Markdown dans l'application.
3. Ajouter une page "Editer le texte" qui edite un extrait de `livre.md`.
4. Sauvegarder les modifications dans le fichier Markdown complet.
5. Mettre en place la generation `livre.md` -> `texte.pdf`.
6. Generer une carte de pagination technique dans `build/page-map.json`.
7. Utiliser cette carte pour mieux positionner l'editeur sur la page courante.
8. Generer les pages photo depuis les planches.
9. Assembler texte et photos dans `final.pdf`.
10. Remplacer l'ancien flux base sur un PDF manuel par ce flux genere.

## Etat d'integration

Le gestionnaire `MarkdownPdfManager` genere actuellement :

```text
data/markdown/build/livre.build.md
data/markdown/build/texte.raw.typ
data/markdown/build/texte.typ
data/pdf/texte.pdf
data/pdf/source.pdf
```

L'API `public/api/markdown.php` expose l'action `generateTextPdf`. Le menu Options de l'interface contient une action pour regenerer le PDF texte depuis `clean/livre.md`.

Les pages d'ouverture sont maintenant pilotees depuis le Markdown avec des marqueurs :

```markdown
<!-- blank-page -->
<!-- right-page -->
<!-- toc -->
```

Ces marqueurs sont convertis en instructions Typst pendant la generation. Le PDF texte n'est donc plus compose par assemblage de blocs separes pour les pages d'ouverture : il vient d'un document Markdown unique.

## Point important

Sans marqueurs dans le Markdown, la correspondance entre une page PDF et un extrait Markdown restera une donnee calculee. Elle peut changer apres modification du texte, car une phrase ajoutee ou retiree peut decaler les pages suivantes.

C'est acceptable si l'editeur sert a ouvrir une zone de travail autour de la page courante, puis si le PDF est regenere apres chaque sauvegarde.
