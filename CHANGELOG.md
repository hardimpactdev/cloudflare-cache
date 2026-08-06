# Changelog

All notable changes to `cloudflare-cache` will be documented in this file.

## Unreleased

### Changed

- Remove optional Spatie ResponseCache integration — this package is Cloudflare edge only
- Keep Inertia-safe edge behavior: document visits cacheable; `X-Inertia` partials get `private, no-store`

### Added (prior)

- Inertia-aware edge headers and CF cache-rule guidance for document vs XHR

