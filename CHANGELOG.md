# Changelog

All notable changes to this project are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-11-01

### Added
- `Resolver` that turns colloquial German time expressions into a `Resolved` period, a `Clarification` or `NoExpression`.
- Explicit dates, calendar weeks, relative days, weeks and weekends, months, parts of months and seasons.
- Feasts and long weekends (Easter, Pentecost, Christmas, bridge days) with public holidays from Yasumi.
- School holidays per state for 2026 through 2029 (partial for 2030) from the OpenHolidays API, bundled under the ODbL 1.0.
- Nine clarification reasons with a German question and candidate periods: `missing_state`, `missing_reference_date`, `ambiguous`, `vague`, `not_a_calendar_term`, `out_of_data`, `not_in_state`, `invalid_date`, `multiple_expressions`.
- Command line interface `bin/zeitangaben` with `--state`, `--date` and `--json`.
