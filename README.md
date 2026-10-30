<!-- Template: replace every {{…}} placeholder. Delete sections that do not apply — never ship a placeholder. -->
# {{project-name}}

> {{One sentence: what it does for whom. No buzzwords. Example: "Ask Claude about your open invoices — a read-only MCP server for Lexware Office, written in PHP."}}

[![CI](https://github.com/Stefan39/{{repo}}/actions/workflows/ci.yml/badge.svg)](https://github.com/Stefan39/{{repo}}/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](phpstan.neon)
[![Latest version](https://img.shields.io/packagist/v/{{vendor}}/{{package}})](https://packagist.org/packages/{{vendor}}/{{package}})
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

[Deutsche Version](README.de.md)

<p align="center">
  <img src="docs/demo/demo.gif" alt="{{What the GIF shows, in one sentence}}" width="720">
</p>

## Why this one?

{{2–4 sentences. Name the alternatives honestly and say what is different. Only claims you can back up.}}

- {{Differentiator 1, e.g. "Native PHP on the official MCP SDK"}}
- {{Differentiator 2}}
- {{Differentiator 3}}

## Quickstart

```bash
composer require {{vendor}}/{{package}}
```

{{Minimal working example — copy, paste, run. Under 15 lines.}}

## Features

| Feature | Status |
| --- | --- |
| {{feature}} | stable |
| {{feature}} | experimental |
| {{feature}} | planned |

## Configuration

{{Env vars / config keys as a table: name, default, description. Secrets only via environment.}}

## Security

Read [SECURITY.md](SECURITY.md) before reporting a vulnerability. {{Project-specific notes, e.g. "Read-only by default. Write tools must be enabled explicitly."}}

## Used in production

{{Only if true, with a link. Otherwise delete this section.}}

## Contributing

Issues and pull requests are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md).

## About

Built and maintained by [Stefan Jacomeit](https://www.jacomeit.de) — freelance PHP/Symfony developer with 25+ years of experience, building AI integrations for businesses.

{{Trademark note if a third-party product is named, e.g.: "Unofficial. Not affiliated with or endorsed by Haufe Group. Lexware and lexoffice are trademarks of Haufe Group."}}

## License

[MIT](LICENSE)
