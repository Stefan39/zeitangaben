# zeitangaben

> Wandelt umgangssprachliche deutsche Zeitangaben wie „in den Herbstferien“, „über Ostern“ oder „morgen“ in einen konkreten Zeitraum um oder liefert eine Rückfrage samt Begründung, warum sich die Angabe nicht eindeutig auflösen lässt.

[English version](README.md)

<p align="center">
  <img src="docs/demo/demo.gif" alt="Fünf deutsche Zeitangaben auf der Kommandozeile: zwei werden zu einem Zeitraum aufgelöst, drei führen zu einer Rückfrage mit Vorschlägen" width="720">
</p>

## Wofür?

Wer Anfragen per Chat, Formular oder Mail entgegennimmt, liest Sätze wie „Wir möchten in den Herbstferien kommen“. Welche Tage das sind, hängt vom Bundesland ab, und „über Ostern“ kann die Feiertage oder die Schulferien meinen. Die Bibliothek löst auf, was eindeutig ist, und fragt nach, wenn nicht. Eine Rückfrage enthält einen fertigen deutschen Fragesatz und, wo möglich, Antwortvorschläge, die ein Formular oder ein KI-Assistent direkt anzeigen kann.

Jedes Ergebnis ist eines von dreien:

- `Resolved`: ein Zeitraum, Anfang und Ende jeweils einschließlich.
- `Clarification`: ein Grund, eine Rückfrage (`question`) und, falls möglich, Vorschläge (`candidates`).
- `NoExpression`: im Text wurde keine Zeitangabe erkannt (siehe [Bedeutung von `none`](#bedeutung-von-none)).

## Schnellstart

```bash
composer require stejac/zeitangaben
```

```php
<?php
require 'vendor/autoload.php';

use Stejac\Zeitangaben\{Clarification, Context, Resolved, Resolver, State};

$context = new Context(new DateTimeImmutable('2026-09-16'), State::NI);
$result = Resolver::create()->resolve('in den Herbstferien', $context);

if ($result instanceof Resolved) {
    echo $result->period->start->format('d.m.Y'), ' bis ', $result->period->end->format('d.m.Y'), "\n";
} elseif ($result instanceof Clarification) {
    echo $result->question, "\n";
}
```

Ausgabe:

```text
12.10.2026 bis 24.10.2026
```

Das Bezugsdatum ist für relative Angaben („morgen“, „nächstes Wochenende“, „im Herbst“) und für die Wahl des Jahres erforderlich. Es wird bewusst übergeben und nicht aus der Systemuhr gelesen: Eine Nachricht, die einen Tag später verarbeitet wird, muss dasselbe Datum ergeben. Fehlt es, ist das Ergebnis eine `Clarification` mit dem Grund `missing_reference_date`. Ein im Text nach einem Wort wie „in“, „aus“ oder „nach“ genanntes Bundesland („Herbstferien in Bayern“) hat Vorrang vor dem Bundesland im Kontext. Ein bloßer Name („Frau Berlin“, „FC Bayern“) gilt nicht als Bundesland: Hängt die Antwort vom Bundesland ab, kommt eine Rückfrage, ebenso bei zwei verschiedenen Bundesländern.

### Sätze oder Ausdrücke?

Am besten übergeben Sie nur die Zeitangabe selbst, zum Beispiel von einem Sprachmodell herausgelöst. Das ergibt die meisten Antworten, denn `Resolved` heißt: sicher. Es bleibt nur stehen, wenn alles andere in der Eingabe als harmlos bekannt ist. Erlaubt sind Grußformeln, die Nennung eines Bundeslands und eine geschlossene Liste aus Funktionswörtern und häufigen Verben („Wir möchten gerne im Juli anreisen“). Jedes andere Wort und jede andere Zahl führt zu einer Rückfrage mit dem Grund `unclear_context` und dem gefundenen Zeitraum als einzigem Vorschlag, weil die Bibliothek nicht wissen kann, was das Wort an der Bedeutung ändert. Mit dem Bezugsdatum 30.10.2026:

| Eingabe | Ergebnis |
| --- | --- |
| `im Juli` | `Resolved` |
| `Wir möchten gerne im Juli anreisen` | `Resolved` |
| `Keine Termine im Juli` | `unclear_context`: „Meinen Sie „im Juli“, also 01.07.2027 bis 31.07.2027?“ |
| `Silvester 2000 Euro` | `unclear_context`: „Meinen Sie „Silvester“, also 31.12.2026?“ |

Die Liste enthält keine Verneinungen, keine Zahlen und keine Hauptwörter, die etwas anderes benennen könnten. Ein Jahr neben der Angabe gehört zur Angabe. Zwei Ziffern gelten nur von 00 bis 69 als Jahr und nur direkt nach einem Datum in Ziffern, hinter einem Apostroph („im Juli '27“) oder ganz am Ende der Eingabe.

Eine Rückfrage lässt sich so weiterverarbeiten: `question` kann unverändert angezeigt werden, `candidates` sind die zulässigen Antworten und eignen sich als Auswahl oder als erlaubte Optionen für ein Sprachmodell. Bei `missing_state` gibt es keine Vorschläge; nach der Antwort wird `resolve()` mit einem `Context` samt Bundesland erneut aufgerufen. Alle Ergebnisse sind mit `json_encode($result)` serialisierbar; aufgelöste Schulferien nennen dabei unter `state` das verwendete Bundesland. Ein vollständiges Beispiel mit Ausgabe steht in der [englischen README](README.md#handling-a-clarification).

Kommandozeile:

```bash
vendor/bin/zeitangaben "in den Herbstferien" --state=NI --date=2026-09-16
vendor/bin/zeitangaben "Sommerferien" --date=2026-09-16 --json
```

Optionen: `--state`, `--date`, `--json`, `--help`. Exit-Code 0 bei jedem Ergebnis (auch bei einer Rückfrage), 2 bei ungültigen Argumenten.

## Gründe für eine Rückfrage

| Grund | Bedeutung |
| --- | --- |
| `missing_state` | Die Angabe hängt vom Bundesland ab („Sommerferien“), es wurde keines genannt. |
| `missing_reference_date` | Relative Angabe („morgen“) ohne Bezugsdatum. |
| `ambiguous` | Mehrere Lesarten sind plausibel („über Ostern“: Feiertage oder Schulferien). |
| `vague` | Die Angabe nennt keinen genauen Zeitraum („Anfang Oktober“, „Frühling“). |
| `not_a_calendar_term` | Fachjargon statt Kalenderbegriff („Nebensaison“). |
| `out_of_data` | Schulferien für ein Jahr, das der Datenbestand nicht abdeckt. |
| `not_in_state` | Das Bundesland kennt diese Ferien nicht, zum Beispiel Pfingstferien in Hessen. |
| `invalid_date` | Das Datum existiert nicht („31.2.“, Kalenderwoche 60), der Wochentag passt nicht dazu („Mo, 12.06.2027“) oder ein Zeitraum läuft rückwärts („vom 19. bis 12. Oktober“). |
| `multiple_expressions` | Der Text enthält mehrere Zeitangaben; die Rückfrage nennt sie. |
| `unclear_context` | Eine Angabe wurde gefunden, aber die Eingabe enthält ein Wort oder eine Zahl, die nicht als harmlos bekannt ist („Keine Termine im Juli“). Einziger Vorschlag ist der gefundene Zeitraum; siehe [Sätze oder Ausdrücke?](#sätze-oder-ausdrücke) |

### Bedeutung von `none`

`NoExpression` (`{"type": "none"}`) heißt: Der Text wurde **nicht erkannt**, nicht „im Text steht kein Datum“. Die Erkennung ist regelbasiert und hat Lücken. Vorerst nicht erkannt werden zum Beispiel „erste Juliwoche“, „Ende des Monats“ und „in 3 Monaten“. `none` sollte daher als „nachfragen oder auf andere Weise behandeln“ gelesen werden, nie als Beleg, dass keine Zeit gemeint war.

Die Vergangenheit wird nicht ausgewertet, „Ostern war schön“ führt also weiterhin zu einer Rückfrage zum nächsten Osterfest.

## Öffentliche API

Zur öffentlichen API gehören `Resolver`, `Context`, `State` (die Fälle, `germanName()` und `locative()`; Methoden mit `@internal` gehören nicht dazu), `Result` mit `Resolved`, `Clarification` und `NoExpression`, `ClarificationReason`, `Candidate`, `Period`, `InvalidPeriodException` sowie die Schnittstellen `School\SchoolHolidayProvider` und `Holiday\PublicHolidayProvider` mit ihren Werttypen. Alles mit `@internal` (`Rule\*`, `Calendar\*`, `Text\*`, `Cli\*`) kann sich mit jeder Version ändern.

## Unterschied zu anderen Lösungen

Soweit bekannt, kennt keiner der gängigen Parser Schulferien nach Bundesland: Duckling kennt nur gesetzliche Feiertage, und in der Dokumentation von dateparser und chrono kommen Feiertage nicht vor. Diese Bibliothek kombiniert Feiertage, Schulferien und Alltagsausdrücke und liefert bei Unsicherheit eine begründete Rückfrage statt einer geratenen Antwort. Sie liest keine Systemuhr und braucht zur Laufzeit keinen Netzzugriff.

## Status

| Funktion | Status |
| --- | --- |
| Konkrete Daten und Kalenderwochen | beta |
| Relative Tage, Wochen und Wochenenden | beta |
| Monate, Monatsabschnitte, Jahreszeiten | beta |
| Feste und lange Wochenenden (Ostern, Pfingsten, Weihnachten, Brückentage) | beta |
| Schulferien je Bundesland, 2026 bis 2029 | beta |
| Rückfragen mit Grund, Fragesatz und Vorschlägen | beta |
| MCP-Tool für KI-Clients | geplant |
| Aufenthaltsdauer („eine Woche im Herbst“) | nicht vorgesehen |

## Daten

Die Schulferien stammen aus der [OpenHolidays API](https://www.openholidaysapi.org) und liegen in `data/school-holidays-de.json`. Gesetzliche Feiertage berechnet [Yasumi](https://github.com/azuyalabs/yasumi).

- Vollständig bis 2029 für Oster- (außer Hamburg), Sommer-, Herbst- und Weihnachtsferien. Pfingst- und Winterferien gibt es nur dort, wo das Bundesland sie hat, Lücken sind möglich. 2030 ist unvollständig; eine Lücke oder ein späteres Jahr ergibt `out_of_data`.
- Mecklenburg-Vorpommern: Termine der allgemeinbildenden Schulen. Schleswig-Holstein: Termine des Festlands (ohne Sylt, Föhr, Amrum, Helgoland und die Halligen).
- Hamburg hat Frühjahrsferien statt Osterferien.

Einzelheiten stehen in [data/README.md](data/README.md) (englisch). Die Daten stehen unter der [ODbL 1.0](data/LICENSE) (Quellenangabe: OpenHolidays API), der Code unter MIT.

## Über

Entwickelt und gepflegt von [Stefan Jacomeit](https://www.jacomeit.de) aus Bremerhaven, freiberuflicher PHP/Symfony-Entwickler, KI-Integrationen für Unternehmen.

## Lizenz

Code: [MIT](LICENSE). Daten in `data/`: [ODbL 1.0](data/LICENSE), siehe [NOTICE](NOTICE). Die englische README ist maßgeblich.
