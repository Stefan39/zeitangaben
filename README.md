# zeitangaben

> Resolves colloquial German time expressions such as "in den Herbstferien", "über Ostern" or "morgen" to a concrete period, or to a clarifying question with the reason why it could not decide.

[![CI](https://github.com/Stefan39/zeitangaben/actions/workflows/ci.yml/badge.svg)](https://github.com/Stefan39/zeitangaben/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](phpstan.neon)
[![Latest version](https://img.shields.io/packagist/v/stejac/zeitangaben)](https://packagist.org/packages/stejac/zeitangaben)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

[Deutsche Version](README.de.md)

<p align="center">
  <img src="docs/demo/demo.gif" alt="Five German time expressions on the command line: two resolve to a date range, three produce a question with candidate periods" width="720">
</p>

## Why this one?

"In den Herbstferien" has no single answer: the dates depend on the German state. "Über Ostern" can mean the public holidays or the school break. Most date parsers either guess or return nothing. To our knowledge, none of the common ones knows school holidays by state: Duckling knows public holidays only, and the documentation of dateparser and chrono does not mention holidays.

This library resolves what is certain and **asks when it is not**. Every answer is one of three results, so a chatbot, an agent or a form can handle it without guessing:

- `Resolved`: one period, start and end inclusive.
- `Clarification`: a reason, a ready-to-ask German `question` and, where possible, `candidates` the user can pick from.
- `NoExpression`: no expression was recognised in the text (see [what `none` means](#what-none-means)).

Further properties, all covered by tests:

- The library never reads the system clock. Relative terms need an explicit reference date, so the same message gives the same answer whenever it is processed.
- Public holidays come from [Yasumi](https://github.com/azuyalabs/yasumi), school holidays from a bundled data file (see [Data](#data)). No network access at runtime.
- Pure PHP, PHP 8.3 or newer.

## Quickstart

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

Output:

```text
12.10.2026 bis 24.10.2026
```

The reference date is required for relative terms ("morgen", "nächstes Wochenende", "im Herbst") and for deciding which year is meant. It is a parameter on purpose: a message that is read a day later must still resolve to the same date. If it is missing, the result is a `Clarification` with the reason `missing_reference_date`. A state named in the text ("Herbstferien in Bayern") takes precedence over the state in the context.

### Handling a clarification

```php
<?php
require 'vendor/autoload.php';

use Stejac\Zeitangaben\{Clarification, Context, Resolver, State};

$context = new Context(new DateTimeImmutable('2026-09-16'), State::NI);
$result = Resolver::create()->resolve('über Ostern', $context);

if ($result instanceof Clarification) {
    echo $result->reason->value, ': ', $result->question, "\n";
    foreach ($result->candidates as $i => $candidate) {
        echo $i + 1, ') ', $candidate->label, ', ', $candidate->period->start->format('Y-m-d'), ' to ', $candidate->period->end->format('Y-m-d'), "\n";
    }
}
```

Output:

```text
ambiguous: Meinen Sie bei „über Ostern“ die Feiertage (26.03. bis 29.03.2027) oder die Schulferien (22.03. bis 03.04.2027)?
1) Karfreitag bis Ostermontag, 2027-03-26 to 2027-03-29
2) Osterferien, 2027-03-22 to 2027-04-03
```

`question` is written for the end user and can be shown as it is, by a form or by an LLM that relays it. `candidates` are the valid answers: render them as buttons or a select, or hand them to the model as the allowed choices, then continue with the chosen period. For `missing_state` there are no candidates; ask the question, then call `resolve()` again with a `Context` that has the state. Every result is JSON-serialisable (`json_encode($result)`):

```json
{
    "type": "clarification",
    "reason": "ambiguous",
    "question": "Meinen Sie bei „über Ostern“ die Feiertage (26.03. bis 29.03.2027) oder die Schulferien (22.03. bis 03.04.2027)?",
    "expression": "über Ostern",
    "candidates": [
        {"start": "2027-03-26", "end": "2027-03-29", "label": "Karfreitag bis Ostermontag"},
        {"start": "2027-03-22", "end": "2027-04-03", "label": "Osterferien"}
    ]
}
```

### Command line

```bash
vendor/bin/zeitangaben "in den Herbstferien" --state=NI --date=2026-09-16
vendor/bin/zeitangaben "Sommerferien" --date=2026-09-16 --json
```

Options: `--state=<code>` (two-letter state code such as `NI`, `BY`, `NW`), `--date=YYYY-MM-DD` (reference date), `--json`. Exit code 0 for every result including a clarification, 2 for invalid arguments. The human-readable output is German.

## What a result can be

### Reasons for a clarification

| Reason | Meaning |
| --- | --- |
| `missing_state` | The expression depends on the state ("Sommerferien") and none was given. |
| `missing_reference_date` | A relative expression ("morgen") and no reference date was given. |
| `ambiguous` | Two or more readings are plausible ("über Ostern": holidays or school break). `candidates` lists them. |
| `vague` | The expression names no exact period ("Anfang Oktober", "Frühling"). `candidates` offers a likely reading. |
| `not_a_calendar_term` | The term is jargon, not a calendar term ("Nebensaison"). |
| `out_of_data` | School holidays are requested for a year the bundled data does not cover. |
| `not_in_state` | The state has no such holidays, for example Pfingstferien in Hessen. |
| `invalid_date` | The date does not exist ("31.2.", a calendar week 60). |
| `multiple_expressions` | The text contains several time expressions; the question names them. |

### What `none` means

`NoExpression` (`{"type": "none"}`) means the text was **not recognised**, not that it contains no date. Recognition is rule-based and has gaps. Not recognised for now, for example: „erste Juliwoche“, „Ende des Monats“, „in 3 Monaten“. Treat `none` as "ask the user or fall back", never as proof that no time was meant.

## Features

| Feature | Status |
| --- | --- |
| Explicit dates and calendar weeks ("am 3. Oktober", "KW 42") | stable |
| Relative days, weeks and weekends ("morgen", "nächste Woche") | stable |
| Months and parts of months, seasons | stable |
| Feasts and long weekends (Ostern, Pfingsten, Weihnachten, Brückentage) | stable |
| School holidays per state, 2026 to 2029 | stable |
| Clarifications with reason, question and candidates | stable |
| MCP tool for use from LLM clients | planned |
| Stay length ("eine Woche im Herbst") | out of scope |

The rules are covered by unit tests and by a corpus test with 132 expressions (`tests/Corpus/`).

## Data

School holidays come from the [OpenHolidays API](https://www.openholidaysapi.org) and are bundled in `data/school-holidays-de.json`. Public holidays are computed by [Yasumi](https://github.com/azuyalabs/yasumi).

- Complete for 2026 through 2029, partial for 2030. Later years give `out_of_data`.
- Mecklenburg-Vorpommern: dates of general schools. Schleswig-Holstein: dates of the mainland (not Sylt, Föhr, Amrum, Helgoland and the Halligen).
- Hamburg has Frühjahrsferien instead of Osterferien; they are kept apart from Easter.

Details, name mapping and known gaps are in [data/README.md](data/README.md). The data is licensed under the [ODbL 1.0](data/LICENSE) (attribution: OpenHolidays API); the code is MIT. If you redistribute the data or a derived database, the ODbL applies to it.

## Security

Read [SECURITY.md](SECURITY.md) before reporting a vulnerability. The library processes text only and performs no I/O besides reading its bundled data file.

## Contributing

Issues and pull requests are welcome, see [CONTRIBUTING.md](CONTRIBUTING.md). A wrongly resolved or unrecognised expression is a useful bug report: include the text, the state and the reference date.

## About

Built and maintained by [Stefan Jacomeit](https://www.jacomeit.de), freelance PHP/Symfony developer with 25+ years of experience, building AI integrations for businesses.

## Credits

[OpenHolidays API](https://www.openholidaysapi.org) for the school holiday data and [Yasumi](https://github.com/azuyalabs/yasumi) for public holidays.

## License

Code: [MIT](LICENSE). Data in `data/`: [ODbL 1.0](data/LICENSE).
