#let page-number() = context align(if calc.odd(counter(page).get().first()) { right } else { left })[#counter(page).display("1")]
#set page(width: 24cm, height: 16cm, footer: none)
#box(width: 0pt, height: 0pt)
#pagebreak()
#set page(footer: page-number())
Remerciements
#pagebreak()
#set page(footer: none)
#box(width: 0pt, height: 0pt)
#pagebreak()
#set page(footer: page-number())
Sommaire
