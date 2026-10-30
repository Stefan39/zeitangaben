# Checkliste neues öffentliches Repo (JAC-686)

Gilt für jedes Repo unter Stefan39. Reihenfolge einhalten.

## Vor der Veröffentlichung
- [ ] Alle `{{…}}` ersetzt, ungenutzte Abschnitte gelöscht (`grep -rn '{{' .` liefert nichts)
- [ ] `composer.scripts.json` in `composer.json` übernommen und gelöscht
- [ ] Secret-Scan: `gitleaks detect --no-git -v` ohne Treffer
- [ ] Keine internen Namen, IDs, Hostnamen, Agenten-Prompts, Kundendaten (auch nicht in Tests/Fixtures/GIF)
- [ ] Frische Git-Historie, wenn Code aus einem privaten Repo stammt (kein Kopieren der History)
- [ ] CI grün, PHPStan Level 8, Tests vorhanden
- [ ] GIF zeigt den Nutzen in 10–20 s, alle Frames geprüft
- [ ] Markenhinweis gesetzt, wenn ein Fremdprodukt im Namen oder README vorkommt
- [ ] Aussage „läuft produktiv“ nur mit Beleg

## Repo-Einstellungen (GitHub)
- [ ] Beschreibung (ein Satz, englisch) + Website https://www.jacomeit.de
- [ ] Topics: Sprache, Framework, Thema (z. B. `php`, `symfony`, `mcp`, `ai`, `llm`)
- [ ] Social-Preview-Bild hochgeladen
- [ ] Discussions an, Wiki aus, Projects aus
- [ ] Private vulnerability reporting an (Settings → Code security)
- [ ] Branch-Schutz `main`: PR + grüne CI Pflicht
- [ ] `jacomeit-tim` als Collaborator (write), Stefan bleibt alleiniger Admin
- [ ] Repo-Wache (JAC-698) auf das Repo erweitert

## Nach der Veröffentlichung
- [ ] Release mit Tag + Notes, Packagist eingetragen (Auto-Update-Hook)
- [ ] Im Profil gepinnt, Profil-README ergänzt
- [ ] Erklärseite auf jacomeit.de (JAC-693) verlinkt
- [ ] Launch nach Kanalplan (JAC-691), nur nach Stefans Freigabe
