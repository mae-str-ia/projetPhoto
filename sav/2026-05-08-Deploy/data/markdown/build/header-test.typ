#let rh = <running-header>
#set page(width: 10cm, height: 10cm, foreground: context {
  let hs = query(selector(rh).before(here()))
  if hs.len() > 0 { let h = hs.last().value; place(top + right, dx: -1cm, dy: 0.5cm)[#h.part -- #h.chapter] }
})
#metadata((part: "Part", chapter: "Chap")) <running-header>
Hello
#pagebreak()
World
