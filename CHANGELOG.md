# Changelog

All notable changes to `cloudflare-cache` will be documented in this file.

## Unreleased

### Fixed

- Default `stale-while-revalidate` and `stale-if-error` to `0` instead of `86400` — the old defaults let poisoned or broken responses stay servable at the edge for up to ~25 hours after a cache-poisoning incident
- Gate `purge()`, `purgeEverything()`, and `warm()` on the same `enabled` + `environments` check already used for headers, so a local environment with production credentials can no longer purge or warm the live zone
- Gate the Inertia `private, no-store` rewrite in `CacheResponse` behind that same enabled/environment check, so a disabled package is fully inert on Inertia responses too (including non-GET requests)
- Add a request-side guard (`$request->hasSession()`) that skips public cache headers whenever session middleware has already run, as a backstop ahead of the existing response-side `Set-Cookie` check — closes a gap where `CacheResponse` applied as the innermost middleware in a `web` group missed cookies attached after it
- `PurgeUrlsJob` now warms synchronously via `warmNow()` instead of dispatching a second queued job, and purges the whole zone through `CloudflareCacheManager::purgeEverything()` instead of calling the client directly

### Changed

- Simplified the redundant safe-method check in `CloudflareCacheManager::shouldApplyHeaders()`

### Removed

- Dead `InertiaRequest::cacheKeyFragment()` helper (Spatie ResponseCache residue) and its test coverage
- Unreachable `PurgesCloudflareUrls` branch and `method_exists()` duck-typing fallback inside `CloudflareCacheManager::resolveUrls()` — the contract is now the only supported path for models

### Documentation

- Merged the two overlapping Inertia Cache Rule sections in the README into one, replaced the invalid `len()` bypass expression with the correct `any(...)` form, and added the previously-missing Edge TTL guidance
- Documented previously-undocumented environment variables: `CLOUDFLARE_CACHE_STALE_IF_ERROR`, `CLOUDFLARE_CACHE_SOFT_FAIL`, `CLOUDFLARE_CACHE_WARM_ENABLED`, `CLOUDFLARE_CACHE_WARM_TIMEOUT`, `CLOUDFLARE_CACHE_WARM_USER_AGENT`, `CLOUDFLARE_API_BASE_URL`
- Removed customer names from the README description in favor of a generic "brochure/marketing sites" description

## v0.0.5

### Changed

- Cloudflare-only Inertia guidance; dropped the Spatie cache-key fragment note

## v0.0.4

### Changed

- Removed the optional Spatie ResponseCache integration — this package is Cloudflare edge only
- Kept Inertia-safe edge behavior: document visits cacheable; `X-Inertia` partials get `private, no-store`
