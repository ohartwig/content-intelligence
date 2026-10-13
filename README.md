# Content Intelligence — TYPO3 Extension

[![TYPO3 14](https://img.shields.io/badge/TYPO3-14.x-orange.svg)](https://get.typo3.org/)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net/)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Content quality analysis extension for TYPO3 that provides AI-Readiness scoring, brand voice consistency checks, freshness analysis, and a compliance dashboard for AI-generated content.

## Features

- **AI-Readiness Scoring** — Evaluate how well content is structured for AI consumption
- **Brand Voice Analysis** — Check content consistency against defined brand voice guidelines
- **Freshness Analysis** — Track content age and flag stale or outdated content
- **Audit Trail** — Full history of AI-generated content changes for compliance
- **Compliance Dashboard** — Backend module for content quality oversight
- **Analyzer Pipeline** — Extensible analysis chain for custom quality checks
- **DDD 4-Layer Architecture** — Strict layer separation enforced by deptrac

## Installation

```bash
composer require koh/content-intelligence
```

### Requirements

- PHP 8.2+
- TYPO3 14.0
- `koh/ai-workflows` (required — workflow engine)

## Architecture

```
Classes/
├── Domain/          # Analysis models, scoring value objects, contracts
├── Application/     # Analysis orchestration services
├── Infrastructure/  # Analyzers, persistence, HTTP clients
└── Presentation/    # Dashboard controllers, event listeners
```

### Database Tables

| Table | Purpose |
|-------|---------|
| `tx_contentintelligence_analysis_result` | Analysis results and scores |
| `tx_contentintelligence_audit_entry` | Audit trail entries |
| `pages` (extended) | Additional fields for content intelligence metadata |
| `tt_content` (extended) | Additional fields for content intelligence metadata |

## Configuration

Configure analysis rules and thresholds through the TYPO3 backend. Brand voice definitions set the baseline for consistency checks. Scoring weights can be adjusted per content type.

## Development

```bash
composer install
composer test                    # Unit tests
composer phpstan                 # Static analysis (level 8)
composer deptrac                 # DDD layer enforcement
vendor/bin/php-cs-fixer fix      # Code style (PER-CS3x0)
```

## Dependencies

| Package | Type | Purpose |
|---------|------|---------|
| `koh/ai-workflows` | Required | Workflow orchestration engine |
| `koh/dev` | Dev | Shared QA tooling |

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) for details.
