## Bakery ERP API (WS_Bakery)

Laravel API for the BK Portal bakery ERP.

### Production checklist

- Copy `.env.example` → `.env`, generate `APP_KEY`, set `APP_ENV=production` and **`APP_DEBUG=false`**.
- Configure `DB_*` (MySQL recommended for production; SQLite is fine for local/tests).
- Prefer Redis for `CACHE_STORE`, `QUEUE_CONNECTION`, and rate limiting when running multiple app instances.
- Set `SANCTUM_TOKEN_EXPIRATION` (minutes; default 480). Tokens expire and password changes revoke all sessions.
- Set SPA origin(s) for cookie auth:
  - `CORS_ALLOWED_ORIGINS` — full frontend origin(s), e.g. `https://portal.yourbakery.com`
  - `SANCTUM_STATEFUL_DOMAINS` — frontend host(s), e.g. `portal.yourbakery.com`
  - With HTTPS: `SESSION_SECURE_COOKIE=true` and `APP_URL` on HTTPS (terminate TLS at reverse proxy).
- Run **`./setup.sh --live-update`** on existing production (lists pending migrations, `php artisan migrate --force`, field sync, staff profiles, image storage, transfer access). Do **not** run a full `./setup.sh` on a live DB — that path is for new installs.
- Manual equivalent: `php artisan migrate --force` and start a queue worker if using the database/redis queue:
  `php artisan queue:work` (keep alive with Supervisor). Most POS/billing work is synchronous; bulk profile repair is queued (`RepairProfilesJob`).
- Production env: `ALLOW_PUBLIC_REGISTRATION=false` (default when `APP_ENV=production`). Optional `BILLING_STAFF_MAX_DISCOUNT_PCT=0.10`. `CACHE_STORE=database` (or Redis) for Idempotency-Key locks.
- API security: org isolation, branch scoping, module permissions, billing catalog prices, staff discount cap, admin-only paid-bill void, security headers, and tiered throttles are enforced in code.
- Do not expose Playwright/demo seed users in production.
- Run dependency audits before release: `composer audit` and frontend `npm audit`.

**Hosting / DigitalOcean sizing, domains, and queue worker explanation:** see frontend [`agent/DEPLOYMENT.md`](../../bk-frontend/agent/DEPLOYMENT.md).

### Tests

```bash
php artisan test --filter='StockIntegrityTest|AuthorizationHardeningTest|SecurityHardeningTest|OnSiteDemoFlowTest'
```

### On-site client demo (laptop, no server)

See [`docs/ON_SITE_DEMO.md`](../../docs/ON_SITE_DEMO.md) and run from repo root:

```bash
./scripts/demo-preflight.sh   # at home
./scripts/demo-start.sh       # at client site
```

### Recent changes (2026-09-04)

- **Live installer** — `./setup.sh --live-update` applies all pending migrations (including Returns batches, product image folders, shelf-life columns, product source, ingredient category, product stock ledger, mandatory product number, SalesReturn CRM field fix). Does not recreate the DB or reset superadmin.
- **Idempotency** — Shared `App\Support\Idempotency` on Material Withdrawal and Returns creates (Billing/Transfer already required the header).
- **Billing** — Staff discount cap (`BILLING_STAFF_MAX_DISCOUNT_PCT`); only admins can cancel/re-hold a paid bill.
- **Signup** — `ALLOW_PUBLIC_REGISTRATION` off in production by default.
- **Returns** — Loss value uses catalog price + POS weight formula. Migrations `2026_09_04_224000_fix_sales_return_crm_fields_for_batches` and `2026_09_04_230000_hide_created_at_on_sales_return_list`.
- **Agent docs** — See [`.agents/AGENTS.md`](./.agents/AGENTS.md) (workspace import: [`../.agents/AGENTS.md`](../.agents/AGENTS.md)).

After deploy: `./setup.sh --live-update` then confirm `php artisan migrate:status` has no Pending rows.

### Recent changes (2026-08-30)

- **Material Withdrawal labels** — User-facing rename from Material Issue (module key/API remain `MaterialIssue`). Migration: `2026_08_30_200000_update_material_withdrawal_labels.php`. Updated `PortalModuleSeed`, `ModuleFieldConfig`, and `MaterialIssueController` messages/ledger notes.
- **Billing list** — `withCount('items')` on list endpoint; `itemCount` exposed on `BillingResource` and in `ModuleFieldConfig` for the Bills history list.
- **Product number validation** — Frontend must send `productNumber` as a string (supports leading zeros); backend validates as string per org-scoped uniqueness.
- **Agent docs** — See [`.agents/AGENTS.md`](./.agents/AGENTS.md) §13 for full changelog.

After deploy: `php artisan migrate --force` (includes Material Withdrawal label migration).

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
