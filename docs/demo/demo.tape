# Terminal demo recorded with charmbracelet/vhs: `vhs docs/demo/demo.tape` (run from the repository root).
# Five inputs, fixed reference date, 10-20 s in total. Umlauts need zsh and the UTF-8 locale below.
Output docs/demo/demo.gif
Set Shell zsh
Set FontSize 20
Set Width 1162
Set Height 300
Set Padding 24
Set Theme "Catppuccin Mocha"
Set TypingSpeed 15ms
Env LC_ALL "de_DE.UTF-8"

Hide
Type "export PROMPT='$ ' PATH=$PWD/bin:$PATH"
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

Type `zeitangaben "über Ostern" --state=NI --date=2026-09-16`
Sleep 300ms
Enter
Sleep 3000ms
Ctrl+L

Type `zeitangaben "nächstes Wochenende" --date=2026-09-16`
Sleep 300ms
Enter
Sleep 3200ms
