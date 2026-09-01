# ADR-024: Google OAuth Superadmin Auto-Provisioning & Domain Whitelist Identity Boundary

**Status**: Accepted  
**Date**: 2026-09-01  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
DPIK Tadbir operates under strict sovereignty and sovereign executive isolation (ADR-013). However, managing local passwords across corporate leadership introduces operational friction. The executive leadership operates primarily across designated personal Google accounts (`smoque@gmail.com`, `arh.homelab@gmail.com`) and DPIK corporate email (`rahman@dpik.com.my`). The system requires a frictionless, 1-click authentication path while strictly guaranteeing that unauthorized external Google accounts are rejected at the edge.

## Decision
1. **Laravel Socialite Google OAuth Integration**:
   - Implement `/auth/google` redirect and `/auth/google/callback` handler routes using `laravel/socialite`.
   - Credentials (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`) are managed via SOPS encryption and injected into Cloud Run.
2. **Whitelist-Gated Account Resolution & Auto-Provisioning**:
   - Every incoming Google OAuth payload is intercepted and validated against `RegistrationWhitelistService`.
   - Non-whitelisted Google accounts are immediately rejected with a flash error (`Unauthorized email domain or account`).
   - Whitelisted superadmin emails (`smoque@gmail.com`, `arh.homelab@gmail.com`, `rahman@dpik.com.my`) are automatically assigned the `super_admin` role.
   - Whitelisted corporate `@dpik.com.my` accounts are automatically assigned the `executive` role.
3. **Database Schema & User Linking**:
   - Added indexed `google_id`, `avatar_url`, and `email_verified_at` to the `users` table via migration `2026_09_01_000006_add_google_oauth_columns_to_users_table.php`.
   - Existing users authenticating with matching emails are automatically linked to their `google_id` without duplicating user records.
4. **UI Render Hooks**:
   - Added responsive, branded Google Sign-In button component registered on Filament's `PanelsRenderHook::AUTH_LOGIN_FORM_AFTER` and `AUTH_REGISTER_FORM_AFTER`.

## Consequences
- **Positive**: Instant 1-click authentication for superadmins and company executives.
- **Positive**: Strict security boundary — zero unverified external accounts can access the system.
- **Positive**: Complete backwards compatibility with standard email/password authentication.
- **Trade-off**: Requires Google Cloud OAuth credentials to be configured in Cloud Run deployment environments.
