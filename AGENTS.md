# AGENTS.md

Single-repo: `INLISLIteV33/` (CodeIgniter 4.7, PHP 8.2) + `slims9_bulian/` (native SLiMS, PHP 8.2),
plus `installer/` deploy tooling. Sources flattened (see `SOURCES.md`); do not re-nest git.

## Branches

- `main` = canonical, deployable. Feature work on `feature/*`.
- Existing: `feature/captcha-settings` (login captcha, merged scope), `feature/label-mixcode`,
  `feature/cloudflare-turnstile` (empty shell), `pr/upstream-3-sanitize-image-url`,
  `chore/sync-upstream-2026-09-20` (already merged).
- Upstream truth for INLISLite: `INLISLite-Perpusnas/INLISLIteV33`. SLiMS `slims9_bulian` = v9.8.0, no sync needed.

## Local dev (verified working)

- DB: MariaDB data dir `tmp/mysql-data`, port `3307`, `--skip-grant-tables`, DB `inlislite_v33`.
- PHP: `php-legacy` 8.3 (has gd/mysqli/intl). Web: `php-legacy -S 127.0.0.1:8080 -t INLISLIteV33/public/`.
- Login: `admin` / `admin123`. Myth/Auth hash = `password_hash(base64(sha384(pass)), BCRYPT)` — plain `password_hash` can NOT log in (see `installer/install.sh`).
- Session permission matrix is built at login: after adding permissions/menus, logout + login again.
- Scratch output goes in project `tmp/`, never `/tmp`.

## INLISLite module system (CodeIgniter auto-discovery)

- Modules live in `INLISLIteV33/app/Modules/` (+ `SubModule/`). `Config/Modules.php` has auto-discovery ON — new module dirs with `Config/Routes.php` register automatically, no central wiring.
- Own module = folder with `Controllers/` + `Views/` (+ optional `Config/Routes.php`, `Models/`). Look at an existing sibling module before creating one.
- Settings pattern: key-value rows in `settingparameters` (`Name`/`Value`), edited via `Pengaturan*` admin modules. Env file is fallback only.

## Installer (`installer/`)

- `build.sh` → regenerates `installer/dist/` (zips + sql). Run only when code changed; fresh clones already contain `dist/`. Includes no-secrets grep gate (`inlis123|slims123|admin123`) — keep credentials out of staged config.
- `installer.php` = web flow (extract → chunked import with resume → config → admin). `install.sh` = SSH equivalent with `--mode both|inlis|slims`.
- `mycmd()` in `install.sh` does NOT forward extra args — `-e` upserts would silently no-op; the fix (forward `"$@"`) lives on `feature/captcha-settings`. Do not regress this when porting.
- Test web flow with staging dir + scratch DB (e.g. `/tmp/inst-test`, port 8099); test `install.sh` with `--mode inlis` + scratch DB. Clean up DBs/staging afterwards.

## Production

- SSH `vektraco@vektracode.web.id` (flaky — `connection reset` = rate-limit, wait and retry). Docroot `~/public_html/inlis/public`. Backups `~/backup-inlis-*.tar.gz` + `~/backup-inlisdb-*.sql.gz`.
- Deploy = upload `installer/installer.php` + `dist/` contents, run in browser; or manual File Manager + phpMyAdmin (see `installer/MANUAL-UPLOAD.md`, `DEPLOY-SATU-HOSTING.md`).
- Never `composer install` on hosting (OOM + dompdf advisory block) — `vendor/` is tracked in git.

## Gotchas

- `INLISLIteV33/.env`, SLiMS `config/*.php`, `installer/dist/`, `installer/customers.tsv`, `insilite/` are gitignored — but `vendor/` and `public/uploads/` ARE tracked. Fresh clone is deploy-ready.
- CI4 views: undefined variables surface as LSP/serena diagnostics — most are false positives (`$this->data` pattern). Don't "fix" them blindly.
- `rtk` output filters branch names and truncates; use raw `git` when you need exact branch/log/status.
