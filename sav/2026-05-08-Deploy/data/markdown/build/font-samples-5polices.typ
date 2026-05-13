#let fonts = (
  "Arial",
  "Calibri",
  "Garamond",
  "Optima",
  "Times New Roman",
)

#let sample-title(index, font-name) = {
  let number = index + 1
  let n = if number < 10 { "0" + str(number) } else { str(number) }
  [#n -- #font-name]
}

#let toc-entry(i) = {
  let font-name = fonts.at(i)
  let first-page = 2 + i * 2
  [
    #block(width: 100%)[
      #text(weight: "bold")[#sample-title(i, font-name)]
      #box(width: 1fr, line(length: 100%, stroke: (paint: rgb("#bbbbbb"), dash: "dotted")))
      #text[#first-page - #(first-page + 1)]
    ]
    #v(0.08cm)
  ]
}

#let toc() = [
  #set text(font: "Arial", size: 8.6pt, lang: "fr")
  #set par(leading: 0.35em)
  #align(center)[#text(size: 15pt, weight: "bold")[Table des matieres]]
  #v(0.25cm)
  #grid(columns: (1fr), column-gutter: 1cm)[
    #for i in range(0, fonts.len()) {
      toc-entry(i)
    }
  ]
]

#let page-one(index, font-name) = [
  #set text(font: font-name, size: 11pt, lang: "fr")
  #set par(justify: true, leading: 0.62em)
  #align(center)[#text(size: 17pt, weight: "bold")[#sample-title(index, font-name)]]
  #v(0.55cm)
  #text(size: 13pt, weight: "bold")[Une Vie en Mouvement]
  #v(0.35cm)
  #text(size: 12pt, weight: "bold")[Partie 1 : Une enfance bousculée]
  #v(0.2cm)
  #text(size: 11.5pt, weight: "bold")[Chapitre 1 : Le Caire, Égypte (1951).]
  #v(0.5cm)

  J'ai neuf ans, je boucle les sangles de ma valise. Ça y est, les vacances sont terminées. Quand je ferme les yeux, je nous revois, Maman, accompagnée de ses soeurs, toute ma fratrie et moi. Papa n'était pas venu, il ne prenait de congés qu'une année sur deux. Nous avions loué des bungalows à Asmara. Une semaine entière en famille, pleine de soleil. Je souris en repensant à Tante Lucy. Elle avait poussé un cri perçant en découvrant, dans son placard, une araignée énorme, grosse comme une mygale.

  "Roger ! Le chauffeur pour l'aéroport est là !" La voix de maman m'arrache à mes rêveries. Je traîne la patte dans l'escalier, embrasse mes parents et mes frères et rejoins le taxi dans la rue. Heureusement, je ne suis pas tout seul. Liliane, ma soeur de deux ans ma cadette, fait également partie du voyage. Nous sommes les deux plus grands, c'est sans doute ce qui a poussé le choix de nos parents à nous envoyer vivre chez Granny et grand-père, au Caire.

]

#let page-two(index, font-name) = [
  #set text(font: font-name, size: 11pt, lang: "fr")
  #set par(justify: true, leading: 0.62em)
  #align(center)[#text(size: 17pt, weight: "bold")[#sample-title(index, font-name)]]
  #v(0.55cm)

  Un long silence occupe la voiture. Nous échangeons peu avec Liliane, nous observons simplement les paysages qui défilent par la fenêtre comme pour taire la souffrance de cette nouvelle séparation. Quand l'avion décolle, ma gorge se noue. C'est difficile de voir s'éloigner cette terre que j'aime tant : Khartoum, le Soudan. Là où je suis né, et où je rêve déjà de construire ma vie.

  J'aime la petite maison de mes parents, située dans le groupement d'habitations occupé par toute ma famille paternelle. Depuis que je suis petit, je m'endors dans la véranda aux côtés de mes frères et de ma soeur. Il n'y a pas de toit, juste un plafond d'étoiles et une douce brise qui caresse nos cheveux. Les soirs d'orage sont mes préférés, les éclairs zébrant le ciel comme des cartes de géographie lumineuses.
]

#set page(width: 24cm, height: 16cm, margin: (left: 2cm, right: 2cm, top: 2cm, bottom: 2cm), footer: context align(right)[#counter(page).display()])

#toc()
#pagebreak()

#set page(width: 24cm, height: 16cm, margin: (left: 5cm, right: 2cm, top: 2cm, bottom: 2cm), footer: context align(right)[#counter(page).display()])

#for (i, font-name) in fonts.enumerate() {
  page-one(i, font-name)
  pagebreak()
  page-two(i, font-name)
  if i < fonts.len() - 1 {
    pagebreak()
  }
}
