# DPIK Tadbir: Deployment Readiness Checklist & Runbook

This checklist establishes the operational requirements and verification gates standing between "all automated CI gates passed" and "live enterprise operations for executive staff".

Adapted directly from production operational learnings in **`dpik-tugas-laravel`** and governed by [`ADR-016`](adr/ADR-016-ci-cd-quality-hardening-operational-blindspot-remediation.md).

---

## 1. Production Secrets & Configuration (Google Secret Manager)

- [ ] **Generate `APP_KEY`**: Run `php artisan key:generate --show` and store in **Google Secret Manager** as `dpik-tadbir-app-key`. Never commit or bake `.env` files into container images.
- [ ] **Set Environment Variables**:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `APP_URL=https://tadbir.dpik.com.my` (Set to custom mapped domain or active Cloud Run service URL).
  - `ALLOWED_REGISTRATION_EMAILS="abdulrahman@dpik.com.my"` (Pre-approved executive emails for sovereign workspace isolation per [`ADR-013`](adr/ADR-013-whitelisted-registration-and-sovereign-executive-isolation.md)).
  - `SESSION_DRIVER=database` (Ensures session persistence across distributed Cloud Run container instances).
  - `CACHE_STORE=database`
- [ ] **Multi-Provider AI Credentials**:
  - `ANTHROPIC_API_KEY`: Mounted via Secret Manager for primary Claude 3.7 Sonnet turns.
  - `GEMINI_API_KEY`: Mounted via Secret Manager for secondary fallback turns (`gemini-2.5-flash`).

---

## 2. Neon PostgreSQL Dual-Connection Architecture

> [!WARNING]
> **PgBouncer Transaction Pooling Gotcha (`SQLSTATE[25P02]`):**
> Neon's transaction pooler (host ending in `-pooler`) intercepts multi-statement DDL transactions executed by Laravel's migration runner, causing `SQLSTATE[25P02]: current transaction is aborted`.
>
> - **Direct Host (`neondb_owner@ep-xyz.c-3...`)**: Used **exclusively** by the Cloud Run migration job (`dpik-tadbir-migrate`).
> - **Pooled Host (`neondb_owner@ep-xyz-pooler.c-3...`)**: Used by the live Cloud Run web application service for high-concurrency connection multiplexing.

- [x] **Connection Strings Provisioned in CI**: Automated via `neonctl` in `.github/workflows/deploy.yml` with separate outputs for `direct` (migration job) and `pooled` (service runtime).

---

## 3. Company Mail Bridge (IMAP/SMTP) Configuration

- [ ] **`ext-imap` present** in the production image (`Dockerfile`) — required by `MailBridge`.
- [ ] **Company mail host reachable** from Cloud Run on ports 993 (IMAP/SSL) and 465 (SMTP/SSL) — `mail.dpik.com.my` by default (`COMPANY_MAIL_HOST`).
- [ ] **Sovereign Mailbox Credentials**: Each whitelisted executive enters their own email + mailbox password in Executive Settings (`imap_username`/`imap_password`). No app registration or Secret Manager credentials required. Confirm zero raw emails are cached locally per [`ADR-035`](adr/ADR-035-outlook-mcp-removal-imap-smtp-mail-bridge.md).

---

## 4. Cloud Run Deployment & Migration Sequencing

- [x] **Ordered Execution Pipeline (Gate 5)**:
  1. `deploy.yml` triggers upon successful conclusion of `Quality Gate CI` (`workflow_run` on `main`).
  2. Builds and pushes production image to Artifact Registry (`asia-southeast1-docker.pkg.dev/...`).
  3. **Cloud Run Migration Job**: Runs `php artisan migrate --force` against Neon direct host with `--wait`. If migrations fail, the workflow aborts immediately before touching the live service.
  4. **Deploy Service**: Deploys the updated container image to Cloud Run service `dpik-tadbir` pointing to Neon pooled host.
- [ ] **Cold-Start & OPcache Optimization**:
  - Minimum instances: `--min-instances=1` (eliminates cold-start latency).
  - CPU boost enabled during container initialization.
  - OPcache pre-warming active in production FrankenPHP/Caddy runtime.

---

## 5. Post-Deployment Verification & Smoke Tests

After deployment completes:
- [ ] Verify health probe: `GET https://tadbir.dpik.com.my/up` returns HTTP 200.
- [ ] Verify admin portal login: `GET https://tadbir.dpik.com.my/admin/login` renders Filament UI.
- [ ] Test whitelisted registration: Ensure un-whitelisted emails receive HTTP 403 Forbidden.
- [ ] Trigger AI Copilot drawer (`Cmd+J`): Confirm Outlook status badge displays connection state.

---

## 6. Rollback & Disaster Recovery Strategy

- **Instant Traffic Shift**: If an issue arises post-deploy, instantly shift traffic back to the previous healthy revision:
  ```bash
  gcloud run services update-traffic dpik-tadbir \
    --region=asia-southeast1 \
    --to-revisions=PREVIOUS_REVISION_NAME=100
  ```
- **Database Point-in-Time Recovery (PITR)**: Use Neon's instant branch restoration to rollback database state without data loss if a destructive schema change occurs.
