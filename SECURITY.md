# Security Policy

## Supported Versions

This package is currently under active pre-release development. Security fixes are applied to the current main branch until the first stable release policy is published.

## Reporting a Vulnerability

Please do not open a public issue for suspected vulnerabilities.

Email security reports to `security@example.com` with:

- affected package version or commit hash
- a short reproduction
- expected and actual behavior
- any logs, payloads, or proof-of-concept details that help verify the issue

We will acknowledge receipt, investigate privately, and coordinate disclosure once a fix is available. This address is a placeholder for package maintainers to replace before public distribution.

## Security Expectations

- Spreadsheet endpoints must use registered editor tokens and must never accept model class names from request input.
- Host applications should keep the default `web` and `auth` route middleware so session authentication and CSRF protection remain active.
- Every editor that exposes real data should define an authorization callback.
- Tenant-aware applications should define `tenantQuery()` so read, write, export, and import operations stay scoped to the active Filament tenant.
- Keep `sensitive_fields` current for your domain and leave audit redaction enabled unless your compliance process explicitly requires raw values.
