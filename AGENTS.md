# hardimpactdev/cloudflare-cache

Laravel package for Cloudflare edge caching: response headers middleware, URL/zone purge, optional warming, and model purge hooks.

## Commands

- Full validation: `composer test`
- Unit tests: `composer test:unit`
- Format: `composer lint`
- Static analysis: `composer analyse` (use `--memory-limit=512M` if needed)

## Conventions

- Namespace: `HardImpact\CloudflareCache`
- Never strip `Set-Cookie` headers — apps must use a stateless middleware group for cacheable routes
- Purge defaults to queued; use `async: false` or `--sync` for CLI/tests
- Soft-fail when credentials missing unless `purge.soft_fail` is false
