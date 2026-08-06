# Changelog

All notable changes to `cloudflare-cache` will be documented in this file.

## Unreleased

### Added

- Inertia-aware edge headers: document visits stay public/CDN-cacheable; `X-Inertia` partials get `private, no-store`
- Optional Spatie ResponseCache profile + hasher (`InertiaCacheProfile`, `InertiaCacheHasher`) for origin SSR caching


### Added

- Initial release: cache headers middleware, Cloudflare purge client, optional warming, model trait, and Artisan commands.
