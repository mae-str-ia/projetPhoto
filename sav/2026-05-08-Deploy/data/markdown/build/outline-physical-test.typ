#set page(width: 24cm, height: 16cm)
#show outline.entry: it => {
  let physical = context counter(page).at(it.element.location()).first() * 2
  block(width: 100%)[#it.element.body #box(width: 1fr, it.fill) #physical]
}
#outline(title: [Sommaire])
#pagebreak()
= Premier
Texte
#pagebreak()
= Second
Texte
