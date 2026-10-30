# Terminal demo recorded with charmbracelet/vhs: `vhs docs/demo/demo.tape` (run from the repository root).
# Five inputs, fixed reference date, 10-20 s in total. vhs cannot type umlauts, so the
# inputs use the ASCII spelling (ue, ae), which the resolver treats the same way.
Output docs/demo/demo.gif
Set Shell bash
Set FontSize 20
Set Width 1162
Set Height 300
Set Padding 24
Set Theme "Catppuccin Mocha"
Set TypingSpeed 15ms

Hide
Type "export PS1='$ ' PATH=$PWD/bin:$PATH"
Enter
Type "clear"
Enter
Sleep 300ms
Show

Type `zeitangaben "in den Herbstferien" --state=NI --date=2026-09-16`
Sleep 300ms
Enter
Sleep 2300ms
Ctrl+L

Type `zeitangaben "Sommerferien" --date=2026-09-16`
Sleep 300ms
Enter
Sleep 2300ms
Ctrl+L

Type `zeitangaben "morgen" --date=2026-09-16`
Sleep 300ms
Enter
Sleep 1800ms
Ctrl+L

Type `zeitangaben "ueber Ostern" --state=NI --date=2026-09-16`
Sleep 300ms
Enter
Sleep 3000ms
Ctrl+L

Type `zeitangaben "naechstes Wochenende" --date=2026-09-16`
Sleep 300ms
Enter
Sleep 3200ms
