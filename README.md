# Toolkit for Polylang

Essential extras for **free** Polylang: translated slugs, content duplication, field sync and better media handling. Free, GPL-2.0-or-later.

> Not affiliated with or endorsed by WP SYNTEX, the makers of Polylang. For official premium support and advanced features see [Polylang Pro](https://polylang.pro/).

Requires WordPress 6.8+, PHP 8.1+ and free Polylang 3.9+. Status: **0.1.0-dev, foundation only** (no user-facing modules yet). See [docs/ROADMAP.md](docs/ROADMAP.md).

## Contributing
Agents and humans: read [AGENTS.md](AGENTS.md) first. Work is spec-first, test-second, code-third, and `bin/benchmark` is the definition of done.

```bash
composer install && npm ci
npm run env:start:coverage   # needs Docker
npm run benchmark
```
