# Note de conception - cadre, zoom et cadrage photo

## Objectif

Le fonctionnement du zoom doit etre repense autour d'une distinction claire entre deux objets :

- le cadre photo, qui definit la place occupee sur la page ;
- l'image, qui est inseree dans ce cadre, puis zoomee et deplacee a l'interieur.

Aujourd'hui, ces notions sont melangees. La photo porte a la fois une `position`, un `fit`, un `zoom`, un `panX`, un `panY`, une bordure et parfois une logique de slot. Cela rend difficile de comprendre ce qui bouge : est-ce la photo sur la page, ou l'image a l'interieur de son cadre ?

Le modele souhaite est donc :

1. On pose un cadre sur la page.
2. Le cadre a au depart les proportions et la taille de la photo.
3. Le cadre definit les bords exterieurs visibles de la photo.
4. L'image est placee dans ce cadre.
5. On peut zoomer/deplacer l'image dans le cadre sans modifier le cadre.
6. On peut ensuite modifier la forme et les proportions du cadre.

## Vocabulaire

### Cadre

Le cadre est l'objet manipule sur la page. Il a :

- une position dans la page ;
- une largeur et une hauteur ;
- un ordre d'empilement ;
- une forme ;
- une bordure visible ou invisible ;
- un fond, souvent blanc, visible si l'image ne remplit pas tout le cadre.

Le cadre est le conteneur. Il ne depend pas du zoom.

### Image

L'image est le fichier photo original. Elle est rendue a l'interieur du cadre. Elle a :

- un fichier source ;
- une largeur et une hauteur naturelles ;
- un facteur de zoom ;
- un deplacement horizontal et vertical dans le cadre ;
- des filtres et reglages visuels.

L'image ne definit pas directement la place occupee sur la page. Elle ne fait que remplir ou depasser le cadre.

### Cadrage

Le cadrage est l'ensemble des valeurs qui disent comment l'image est placee dans le cadre :

- `zoom` : facteur d'agrandissement ;
- `panX` : deplacement horizontal ;
- `panY` : deplacement vertical ;
- `fitMode` : mode initial d'insertion, par exemple `cover` ou `contain`.

Le cadrage ne modifie jamais `x`, `y`, `w`, `h` du cadre.

## Probleme actuel

Dans le code actuel, une photo contient deja des champs proches de ce modele :

- `position.x`, `position.y`, `position.w`, `position.h`, `position.z` pour le placement en mode libre ;
- `zoom`, `panX`, `panY` pour le cadrage interne ;
- `fit` pour `cover` ou `contain` ;
- `borderWidth`, `borderColor` pour l'apparence du cadre.

Mais l'implementation ne va pas jusqu'au bout :

- la notion de cadre n'est pas formalisee dans les donnees ;
- le mode slot et le mode libre n'utilisent pas exactement le meme modele mental ;
- `_applyImgTransform()` remet actuellement `transform` a vide dans l'editeur principal ;
- la modal applique un zoom/pan, mais cette logique n'est pas centralisee ;
- la taille du cadre et le zoom de l'image peuvent etre confondus dans l'interface.

Le resultat est que le zoom semble instable ou incomplet : il existe dans les donnees et dans certains controles, mais il ne correspond pas encore a un vrai cadrage permanent dans toutes les vues.

## Modele de donnees cible

Chaque photo posee sur une page devrait etre representee comme une instance de cadre contenant une image.

Exemple de structure cible :

```json
{
  "id": "photo_xxx",
  "filename": "photo_xxx.jpg",
  "naturalWidth": 4032,
  "naturalHeight": 3024,
  "frame": {
    "x": 12,
    "y": 8,
    "w": 38,
    "h": 42,
    "z": 1,
    "shape": "rect",
    "borderWidth": 0,
    "borderColor": "white",
    "backgroundColor": "white"
  },
  "crop": {
    "fitMode": "cover",
    "zoom": 1,
    "panX": 0,
    "panY": 0
  },
  "appearance": {
    "brightness": 100,
    "contrast": 100,
    "filter": "none"
  },
  "caption": {
    "text": "",
    "color": "white",
    "size": 11
  }
}
```

Cette structure peut etre introduite progressivement sans casser le JSON existant. Dans un premier temps, on peut garder les champs actuels et les interpreter comme suit :

- `position` devient le futur `frame` ;
- `zoom`, `panX`, `panY`, `fit` deviennent le futur `crop` ;
- `borderWidth`, `borderColor` deviennent des proprietes du futur `frame` ;
- `caption`, `captionColor`, `captionSize` pourront ensuite etre regroupes.

## Regle de rendu

Le DOM devrait toujours suivre la meme structure :

```html
<div class="photo-frame">
  <div class="photo-image-clip">
    <img class="photo-image">
  </div>
  <div class="photo-border"></div>
  <div class="photo-caption"></div>
</div>
```

### `photo-frame`

Le cadre est positionne sur la page :

```css
.photo-frame {
  position: absolute;
  left: var(--frame-x);
  top: var(--frame-y);
  width: var(--frame-w);
  height: var(--frame-h);
  overflow: hidden;
  background: white;
}
```

### `photo-image-clip`

Le clip definit la zone visible de l'image. Pour un rectangle simple, il remplit tout le cadre.

```css
.photo-image-clip {
  position: absolute;
  inset: 0;
  overflow: hidden;
}
```

Pour les formes futures, c'est probablement ce niveau qui portera `border-radius`, `clip-path` ou un masque.

### `photo-image`

L'image est placee dans le clip, puis transformee :

```css
.photo-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transform-origin: center center;
}
```

Le zoom et le deplacement s'appliquent ici :

```css
transform: translate(var(--pan-x), var(--pan-y)) scale(var(--zoom));
```

## Comportement attendu

### Ajout d'une photo

Quand une photo est ajoutee en mode libre :

1. Lire ses dimensions naturelles.
2. Creer un cadre avec le meme ratio que la photo.
3. Donner au cadre une taille initiale raisonnable sur la page.
4. Placer `zoom = 1`, `panX = 0`, `panY = 0`.
5. Utiliser `fitMode = cover` ou `contain` selon le choix UX retenu.

Le point important : la photo n'est pas "posee" directement sur la page. C'est son cadre qui est pose.

### Deplacement sur la page

Quand l'utilisateur deplace une photo dans la planche, il deplace le cadre :

- `frame.x` change ;
- `frame.y` change ;
- le cadrage interne ne change pas.

### Redimensionnement

Quand l'utilisateur tire les poignees exterieures, il modifie le cadre :

- `frame.w` change ;
- `frame.h` change ;
- le zoom et le pan internes restent stables autant que possible.

Il faudra choisir un comportement pour le cadrage lors d'un changement de ratio :

- conserver le centre visuel de l'image ;
- conserver les valeurs `zoom/pan` en pourcentage du cadre ;
- ou recentrer automatiquement si le nouveau cadre laisse apparaitre des bords vides non voulus.

La solution la plus previsible est de conserver `zoom/pan` en pourcentage, puis de contraindre `panX/panY` pour eviter les zones vides en mode `cover`.

### Zoom

Quand l'utilisateur zoome :

- le cadre ne bouge pas ;
- `frame.x/y/w/h` ne changent pas ;
- seul `crop.zoom` change ;
- l'image est agrandie dans le cadre ;
- le deplacement possible augmente avec le zoom.

Le zoom minimum depend du mode :

- en `cover`, le minimum pratique est `1`, car l'image remplit deja le cadre ;
- en `contain`, le minimum peut etre `1`, mais des bandes peuvent etre visibles ;
- si on veut eviter toute zone vide, le systeme doit calculer un zoom minimum dynamique selon le ratio image/cadre.

### Deplacement interne de l'image

Quand l'utilisateur glisse l'image avec un zoom actif :

- le cadre ne bouge pas ;
- `crop.panX` et `crop.panY` changent ;
- le deplacement est contraint pour ne pas sortir l'image du cadre en mode `cover`.

Le deplacement doit etre exprime dans une unite stable. Deux options :

- pourcentage du cadre ;
- pourcentage de la taille image rendue.

Pour cette application, le pourcentage du cadre est plus simple a maintenir avec les donnees actuelles.

## Formes de cadre

La possibilite de modifier la forme du cadre doit rester separee du zoom.

Exemples de formes :

- rectangle ;
- rectangle arrondi ;
- carre ;
- cercle ;
- ovale ;
- ratio impose, par exemple 4:3, 3:2, 1:1, 16:9 ;
- forme libre plus tard, via `clip-path`.

Le champ `frame.shape` peut commencer simplement :

```json
{
  "shape": "rect",
  "radius": 0,
  "ratio": null
}
```

Puis evoluer :

```json
{
  "shape": "rounded",
  "radius": 12,
  "ratio": "4:3"
}
```

La forme agit sur le clip et sur la bordure, pas sur l'image source.

## Interface utilisateur proposee

L'interface devrait separer deux modes d'edition.

### Mode cadre

Ce mode sert a placer la photo dans la page :

- deplacer le cadre ;
- redimensionner le cadre ;
- choisir une proportion ;
- choisir une forme ;
- regler la bordure ;
- changer l'ordre avant/arriere.

Les poignees visibles concernent le cadre exterieur.

### Mode image

Ce mode sert a cadrer le contenu a l'interieur :

- zoomer ;
- deplacer l'image dans le cadre ;
- recentrer ;
- remplir le cadre ;
- afficher toute l'image ;
- appliquer les filtres.

Le cadre doit rester visible pendant ce mode, mais ses poignees de taille ne doivent pas etre le controle principal.

## Migration progressive

Il n'est pas necessaire de tout refactorer en une seule fois.

### Etape 1 - Clarifier le rendu

Unifier le DOM de rendu dans l'editeur, la galerie et la vue double-page :

- un conteneur cadre ;
- un clip image ;
- une image transformee ;
- une bordure ;
- une legende.

Dans cette etape, on peut continuer a utiliser les champs actuels.

### Etape 2 - Corriger l'application du zoom

Centraliser une fonction de rendu du cadrage :

```js
applyImageCrop(imgEl, photo) {
  const zoom = photo.zoom || 1;
  const panX = photo.panX || 0;
  const panY = photo.panY || 0;

  imgEl.style.objectFit = photo.fit || 'cover';
  imgEl.style.transformOrigin = 'center center';
  imgEl.style.transform = `translate(${panX}%, ${panY}%) scale(${zoom})`;
}
```

Cette fonction doit etre utilisee partout :

- slots de layout ;
- mode libre ;
- modal d'edition ;
- galerie ;
- vue double-page.

### Etape 3 - Introduire le vocabulaire `frame/crop`

Sans changer immediatement le JSON, ajouter dans le code des helpers :

```js
getFrame(photo) {
  return photo.frame || photo.position || { x: 5, y: 5, w: 40, h: 40, z: 1 };
}

getCrop(photo) {
  return photo.crop || {
    fitMode: photo.fit || 'cover',
    zoom: photo.zoom || 1,
    panX: photo.panX || 0,
    panY: photo.panY || 0
  };
}
```

Cela permet d'ecrire le nouveau code avec le bon modele mental avant de migrer les donnees.

### Etape 4 - Ajouter les proportions de cadre

Ajouter des commandes de ratio :

- libre ;
- ratio photo originale ;
- 1:1 ;
- 4:3 ;
- 3:2 ;
- 16:9.

Le changement de ratio modifie `frame.w/h`, pas `crop.zoom`.

### Etape 5 - Migrer le stockage

Quand le rendu est stabilise, migrer progressivement :

- `position` vers `frame` ;
- `fit/zoom/panX/panY` vers `crop` ;
- `borderWidth/borderColor` vers `frame` ;
- `caption/captionColor/captionSize` vers `caption`.

Pour compatibilite, les API doivent accepter l'ancien et le nouveau format pendant une phase transitoire.

## Points de vigilance

### Contraintes de pan

Le pan doit etre borne. Sinon l'utilisateur peut faire sortir l'image du cadre et creer des zones vides involontaires.

Le calcul depend du ratio image/cadre, de `object-fit` et du zoom. Il faut probablement une fonction dediee :

```js
clampCropPan(photo, frame) {
  // Calcule les limites panX/panY selon le ratio image/cadre et le zoom.
}
```

### Unites

Les dimensions de cadre sont actuellement en pourcentage de page. C'est correct pour le stockage.

Le pan devrait rester en pourcentage pour etre portable entre tailles d'ecran, galerie et export. Eviter de stocker des pixels d'ecran.

### Export

La meme logique devra etre reproduite dans l'export Word/PDF si l'export doit refleter exactement l'editeur. Il faudra donc eviter une logique uniquement CSS si elle n'est pas transposable.

### Anciennes planches

Les pages existantes doivent continuer a s'afficher. Les valeurs manquantes doivent avoir des defaults :

- cadre : position existante ou slot courant ;
- zoom : `1` ;
- pan : `0` ;
- fit : `cover` ;
- forme : rectangle.

## Decision recommandee

La meilleure direction est de considerer que l'objet principal edite sur la page est le cadre, pas l'image.

L'image est un contenu interne du cadre. Le zoom et le deplacement ne changent jamais l'objet place sur la page ; ils changent seulement la maniere dont l'image est vue a travers ce cadre.

Cette separation donnera une base plus solide pour :

- un zoom comprehensible ;
- un deplacement interne fiable ;
- des cadres redimensionnables ;
- des proportions modifiables ;
- des formes de cadre ;
- un rendu coherent entre editeur, livre et export.
