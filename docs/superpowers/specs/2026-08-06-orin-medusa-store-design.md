# Orin Medusa Store — Design Spec

Date: 2026-08-06

## Goal

Stand up a fresh Medusa v2 e-commerce store on the existing VPS (82.29.181.61), fully replacing the previous incomplete "kronvard" attempt, and connect it to the `orin.se` domain family. Roll out through a staging environment first; the public `orin.se` root domain does not go live until explicitly approved.

## Scope

- **In scope:** new Medusa v2 backend + bundled Admin dashboard, Next.js storefront, CloudPanel site/vhost + SSL provisioning for 4 subdomains, PM2 process management, product import from the old store's export, staging rollout.
- **Out of scope:** payment provider integration (deferred — no provider chosen yet), the `alamin` accounting app (must not be touched), the `urme.se` site (separate, untouched), MySQL exposure finding (flagged separately, no action).

## Current State (discovered during investigation)

- Linux user `orin` already exists on the VPS with a stopped/incomplete Medusa monorepo at `/home/orin/htdocs/www.orin.se` (git remote: `github.com/mohamedal2min-u/orin-store`, PM2 processes not running).
- Postgres DB `orin_medusa` (owner `orin_medusa_user`) and Redis exist, both correctly bound to `127.0.0.1` only — not internet-exposed.
- DNS on `orin.se` already has `@`, `www` (CNAME→orin.se), `admin`, `api` A records pointing at the VPS; `staging` A record was added 2026-08-06 with explicit user approval.
- No working nginx vhost currently answers for `orin.se` (connection resets) — CloudPanel site/vhost was never finished for this domain.
- Root SSH access confirmed working; `clpctl` (CloudPanel CLI) available with commands `site:add:nodejs`, `site:add:reverse-proxy`, `lets-encrypt:install:certificate --domainName=`, `site:delete`.
- Old product export (`/home/orin/exported_products.json`) contains 40 real products (watches, Swedish copy) in standard Medusa export format — to be re-imported into the new store. Note: image URLs in that file point to `www.urme.se`, an unrelated domain — those thumbnails may break independently of this project.

## Hard Constraints (user-specified, standing rule)

1. No DNS changes without explicit per-change approval (the `staging` A record was pre-approved individually).
2. No firewall changes without explicit approval.
3. No data deletion without explicit approval, even if discussed previously in principle — always get a final go-ahead immediately before a destructive step.
4. Postgres and Redis must never be bound to a public interface — must stay on `127.0.0.1`.
5. **No content goes live on `orin.se` (apex/root, the public production domain) without explicit approval.** Everything is built and verified on `staging.orin.se` first.

## Domain Architecture

| Domain | Purpose | Notes |
|---|---|---|
| `orin.se` | Production storefront (Next.js) | Vhost/SSL may be provisioned ahead of time; **no app deployed/pointed live until approved** |
| `www.orin.se` | Permanent redirect → `orin.se` | |
| `api.orin.se` | Medusa backend API | Needed regardless of storefront going live (admin needs it to manage products before launch) |
| `admin.orin.se` | Medusa Admin dashboard (bundled in backend) | Reverse-proxied to the same backend process as `api.orin.se` |
| `staging.orin.se` | Full preview of the storefront | Used for review/testing before `orin.se` cutover |

All 5 (4 CloudPanel sites) get Let's Encrypt SSL via `clpctl lets-encrypt:install:certificate`.

## Application Architecture

- Official Medusa v2 scaffold, Turborepo monorepo layout (`apps/backend`, `apps/storefront`) — matches upstream docs and the prior (abandoned) attempt's proven-compatible layout, easiest to keep updated.
- Backend: Node.js via CloudPanel's `site:add:nodejs` site type, process managed by PM2 as `orin-backend`, app port e.g. `9000` (bound to `127.0.0.1`, reverse-proxied by nginx).
- Storefront: Next.js, PM2 process `orin-storefront`, separate port e.g. `4000`.
- New Postgres DB (fresh `orin_medusa`, recreated after wiping the old one) + Redis with a distinct DB index to avoid clashing with any other app on the shared Redis instance.
- Deploy tooling modeled on the existing `alamin` `deploy.mjs` pattern (git fetch + hard reset, install, build, `pm2 restart`) but kept inside the `orin-store` repo itself — not mixed into the unrelated `alamin` accounting repo.

## Cleanup (destructive — requires final explicit go-ahead immediately before running)

- Delete `/home/orin/htdocs/www.orin.se` (old kronvard code, node_modules, git history) in full.
- Drop and recreate Postgres DB `orin_medusa`.
- Keep `/home/orin/exported_products.json` for re-import.
- Do not touch `alamin-api`, `maa`, or any files outside the `orin` user's home directory.

## Data Migration

- After the new backend is running, import the 40 products from `exported_products.json` via the Medusa Admin API/import tooling.

## Payment

- No provider configured initially. Store functions without a live payment method until a provider (Stripe/Klarna/other) is chosen later.

## Rollout Order

1. Provision CloudPanel sites + SSL for all 4 subdomains (explicitly authorized — user provided CloudPanel/root credentials for this purpose).
2. Wipe old project + DB (after final explicit confirmation).
3. Scaffold new Medusa v2 monorepo, configure `.env` (DB/Redis on localhost only), deploy backend to `api.orin.se` / `admin.orin.se`.
4. Import the 40 products.
5. Deploy storefront to `staging.orin.se` for review.
6. **Stop and wait for explicit approval** before pointing the storefront at `orin.se` (production cutover).
