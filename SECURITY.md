# Security Policy

Ultimate Xtreme is a self-hosted console that stores provider credentials and
proxies stream requests. Treat every deployment as sensitive infrastructure.

## Supported versions

Security fixes are applied to the current **4.x** line. Older copies and the
legacy standalone scripts are not supported.

## Reporting a vulnerability

**Please do not open a public issue for a vulnerability.**

Report it privately to **johirxofficial@gmail.com** with:

- A short description of the issue and its impact
- Steps to reproduce, including the affected route or file
- The version and PHP/Apache environment
- Any proof of concept, logs or screenshots that help confirm the issue
- Whether you would like to be credited in the fix

You can expect an acknowledgement within 72 hours. Please allow time for a fix
and a coordinated disclosure before publishing details. Do not access other
users' data, disrupt a live installation or use real provider accounts when
testing a report.

## In scope

Examples of issues we want to hear about:

- Authentication or role-check bypass
- CSRF, XSS or SQL injection
- Path traversal, arbitrary file read/write or remote code execution
- SSRF or open-relay behaviour in the stream, playlist or connection-test code
- Exposure of the SQLite database, cache, backups or application secret
- Signature reuse or tampering in the public `/live`, `/pl` and `/stream` routes
- Credential leakage in logs, exports or error responses

## Deployment hardening

- Serve the console over HTTPS and keep PHP/Apache up to date.
- Protect `data/` from web access. The included `.htaccess` does this on
  Apache; replicate the rule on other web servers.
- Keep the project out of a public document root when possible, or block
  `.git`, `includes/`, `admin/partials/` and database files explicitly.
- Use a unique, strong administrator password and separate accounts for
  managers.
- Treat JSON backups as secrets: they contain provider credentials in plain
  text. Store and delete them securely.
- Do not commit the SQLite database, cache, logs or local configuration.
- Review provider hosts before adding them. Connection tests and channel
  requests are made by the server.

## Repository hygiene

The public repository intentionally excludes:

- `data/*.sqlite*` and SQLite sidecar files
- `data/cache/` and backups
- Provider credentials and seeded provider accounts
- Legacy standalone source copies

If you believe a secret has been committed, notify the maintainer immediately
so the affected credential can be rotated and the history can be cleaned up.
