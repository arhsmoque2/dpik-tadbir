# DPIK Tadbir: Environment & Infrastructure Variable Contract

This document provides the authoritative, zero-leak reference of all environment variables, cloud secrets, and infrastructure parameters across DPIK Tadbir. It ensures that human operators, CI/CD runners, and autonomous coding agents can immediately discover and verify configuration requirements without searching through code or workflow files.

---

## 1. Quick-Scan Infrastructure Variable Matrix

### 1.1 Google Cloud Platform & Cloud Run (`arh-gcloud-vm`)

| Variable / Secret | Tier / Location | Classification | Required In | Canonical Value / Pattern | Description |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `GCP_PROJECT_ID` | GitHub Var (`vars.`) | Public Config | Deploy Gate | `arh-gcloud-vm` | Google Cloud project hosting Cloud Run and Artifact Registry. |
| `GCP_REGION` | GitHub Var (`vars.`) | Public Config | Deploy Gate | `asia-southeast1` | Regional compute and container location (Singapore). |
| `GCP_ARTIFACT_REPOSITORY` | GitHub Var (`vars.`) | Public Config | Deploy Gate | `dpik-tadbir` | Docker repository name within Artifact Registry. |
| `CLOUD_RUN_SERVICE` | GitHub Var (`vars.`) | Public Config | Deploy Gate | `dpik-tadbir` | Cloud Run fully managed service identity. |
| `GCP_WORKLOAD_IDENTITY_PROVIDER` | GitHub Var (`vars.`) | Public Config | Deploy Gate | `projects/102469945521/locations/global/workloadIdentityPools/urus-github-pool/providers/urus-github-provider` | Keyless OIDC federation pool provider for GitHub Actions. |
| `GCP_DEPLOY_SERVICE_ACCOUNT` | GitHub Var (`vars.`) | Public Config | Deploy Gate | `dpik-tugas-deployer@arh-gcloud-vm.iam.gserviceaccount.com` | Service account authorized for image pushing and service deployment. |

### 1.2 Serverless PostgreSQL (Neon: `floral-haze-01285681`)

| Variable / Secret | Tier / Location | Classification | Required In | Source / Fallback | Description |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `NEON_PROJECT_ID` | GitHub Var (`vars.`) | Public Config | Deploy Gate | `floral-haze-01285681` | Neon project identifier (`DPIK-Tadbir` in `aws-ap-southeast-1`). |
| `NEON_API_KEY` | GitHub Secret (`secrets.`) | Sensitive Secret | Deploy Gate | SOPS: `sops/neon.enc.yaml` | Administrative API token for `neonctl` connection string resolution. |
| `DATABASE_URL` (Direct) | Ephemeral Job Env | Sensitive Secret | Migration Job | Fetched via `neonctl` | Direct endpoint (`ep-falling-mountain-b3qev4e1`) to bypass PgBouncer transaction limits. |
| `DATABASE_URL` (Pooled) | Cloud Run Env | Sensitive Secret | Web Runtime | Fetched via `neonctl` | Pooled endpoint (`-pooler`) for resilient serverless connection multiplexing. |
| `DB_CONNECTION` | `.env` / Cloud Run Env | Public Config | All Runtimes | `sqlite` (local/test) / `pgsql` (cloud) | Laravel database driver selector. |

### 1.3 Multi-Provider AI Subsystem (Claude 3.7 & Gemini 2.5)

| Variable / Secret | Tier / Location | Classification | Required In | Resolution Hierarchy | Description |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `ANTHROPIC_API_KEY` | Encrypted DB / GHA Secret | Sensitive Secret | Agent Turns | 1. User `ExecutiveSettings`<br>2. SOPS fallback (`ai_providers.enc.yaml`) | Primary LLM key for Claude 3.7 Sonnet reasoning turns. |
| `GEMINI_API_KEY` | Encrypted DB / GHA Secret | Sensitive Secret | Fallback Turns | 1. User `ExecutiveSettings`<br>2. SOPS fallback (`ai_providers.enc.yaml`) | Secondary LLM key for Gemini 2.5 Flash automatic failover. |
| `AI_DEFAULT_PROVIDER` | `.env` / Config | Public Config | Local / Cloud | Default: `anthropic` | Primary gateway provider selection. |
| `AI_DEFAULT_MODEL` | `.env` / Config | Public Config | Local / Cloud | Default: `claude-3-7-sonnet-20250219` | Primary model identifier. |
| `AI_FALLBACK_PROVIDER` | `.env` / Config | Public Config | Local / Cloud | Default: `gemini` | Fallback provider invoked on 429/500 errors. |
| `AI_FALLBACK_MODEL` | `.env` / Config | Public Config | Local / Cloud | Default: `gemini-2.5-flash` | Fallback model identifier. |

### 1.4 Microsoft Graph & Outlook MCP Bridge

| Variable / Secret | Tier / Location | Classification | Required In | Canonical Value | Description |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `MICROSOFT_CLIENT_ID` | GitHub Secret (`secrets.`) | Sensitive Secret | Live MCP Bridge | Azure Entra ID App ID | Application registration ID for Microsoft Graph access. |
| `MICROSOFT_CLIENT_SECRET` | GitHub Secret (`secrets.`) | Sensitive Secret | Live MCP Bridge | Azure Entra ID Secret | OAuth client secret for mailbox query authorization. |
| `MICROSOFT_TENANT_ID` | GitHub Secret (`secrets.`) | Sensitive Secret | Live MCP Bridge | Azure 365 Tenant ID | Organization directory tenant identifier. |
| `OUTLOOK_MCP_COMMAND` | `.env` / Config | Public Config | Local / Cloud | `uv` | Executable used to spawn the Outlook MCP background server. |
| `OUTLOOK_MCP_ARGS` | `.env` / Config | Public Config | Local / Cloud | `run python -m outlook_mcp.server` | Arguments supplied to spawn the MCP bridge. |
| `OUTLOOK_MCP_TIMEOUT` | `.env` / Config | Public Config | Local / Cloud | `30` | Subprocess execution timeout in seconds. |

### 1.5 Application Security & Whitelist Gating

| Variable / Secret | Tier / Location | Classification | Required In | Canonical Value | Description |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `APP_KEY` | GitHub Secret / `.env` | Sensitive Secret | All Runtimes | Base64 32-byte key | Laravel AES-256-CBC cipher encryption key. |
| `APP_ENV` | `.env` / Cloud Run Env | Public Config | All Runtimes | `local` / `testing` / `production` | Application environment state. |
| `APP_URL` | GitHub Var (`vars.`) | Public Config | Web Runtime | `https://dpik-tadbir-102469945521.asia-southeast1.run.app` | Root URL for asset routing and absolute redirects. |
| `ALLOWED_REGISTRATION_EMAILS` | GitHub Var / `.env` | Public Config | Auth Gate | `rahman@dpik...,smoque@...,arh.homelab@...,hilmio@...,hamid@...` | Whitelist seed list for initial user access gate. |
| `FILAMENT_PATH` | `.env` / Config | Public Config | Local / Cloud | `admin` | Route prefix for the Filament administration panel. |

---

## 2. Storage Tiers & Canonical Sources of Truth

DPIK Tadbir follows a zero-plaintext-in-git architecture. Variables and secrets are stored in strictly governed tiers:

```text
+----------------------------------------------------------------------------------+
|                         CONFIGURATION STORAGE TIERS                              |
+----------------------------------------------------------------------------------+
|                                                                                  |
|  1. ARH SOPS Vault (Permanent Sovereign Store)                                   |
|     Path: D:/_ARH-AGENT-OS/ARH-OS-Central/arh-secrets-vault/sops/                |
|     Files: neon.enc.yaml, ai_providers.enc.yaml, cloudflare.enc.yaml             |
|     Access: Age key via id_ed25519_arhsmoque2                                    |
|                                                                                  |
|  2. GitHub Repository Variables & Secrets (CI/CD Deploy Engine)                  |
|     Repository: arhsmoque2/dpik-tadbir                                           |
|     Variables (Public): GCP_PROJECT_ID, NEON_PROJECT_ID, APP_URL, etc.           |
|     Secrets (Masked): NEON_API_KEY, ANTHROPIC_API_KEY, APP_KEY, etc.             |
|                                                                                  |
|  3. Sovereign Executive User Database (User-Scoped At-Rest Encryption)           |
|     Table: users (columns: anthropic_api_key, gemini_api_key)                    |
|     Cipher: Laravel encrypted cast (AES-256-CBC via APP_KEY)                     |
|     Surface: Managed directly by logged-in executive in ExecutiveSettings        |
|                                                                                  |
|  4. Local Development Environment (Non-Committed)                                |
|     File: .env (gitignored)                                                      |
|     Template: .env.example                                                       |
|                                                                                  |
+----------------------------------------------------------------------------------+
```

---

## 3. Dual Connection String Architecture (Neon Serverless)

A common pitfall with serverless PostgreSQL poolers (PgBouncer) is transaction-level prepared statement locks during DDL migrations (`SQLSTATE[25P02]`). Tadbir isolates these two concerns:

1. **Migration Job (`dpik-tadbir-migrate`)**:
   - Executes before container traffic flips.
   - Connects to Neon's **direct, unpooled host**:
     `ep-falling-mountain-b3qev4e1.c-4.ap-southeast-1.aws.neon.tech:5432/neondb`
   - Bypasses pooling to execute schema changes safely.
2. **Serving Web Service (`dpik-tadbir`)**:
   - Serves HTTP requests via FrankenPHP.
   - Connects to Neon's **pooled host**:
     `ep-falling-mountain-b3qev4e1-pooler.c-4.ap-southeast-1.aws.neon.tech:5432/neondb?sslmode=require`
   - Handles auto-scaling spikes without exhausting Postgres backend connections.

---

## 4. AI Provider Key Resolution Hierarchy

AI keys follow a deterministic precedence order:

```text
User Turn Triggered
       │
       ▼
Does authenticated User have personal API key set in ExecutiveSettings?
      ├── YES ──► Decrypt and use user-scoped key (Sovereign billing)
      │
      └── NO  ──► Fall back to system environment / SOPS vault key
```

For hermetic testing and CI runs, Mock and Fake gateways are used with zero external network requests.

---

## 5. Agent Verification & Health Preflight Commands

Incoming agents can verify their local and cloud environment configuration using the following commands:

```bash
# 1. Verify GitHub Actions repository variables
gh variable list --repo arhsmoque2/dpik-tadbir

# 2. Verify GitHub Actions repository secrets presence
gh secret list --repo arhsmoque2/dpik-tadbir

# 3. Test Neon database endpoint latency and connectivity
node D:/_ARH-AGENT-OS/_AGENT-CAPABILITIES/arh-infra-devkit/neon/neon-preflight.mjs

# 4. Verify local Laravel environment and configuration
php artisan config:show services.registration
php artisan config:show services.ai

# 5. Run full test suite with hermetic in-memory fixtures
php vendor/bin/pest
```
