# Contributing to Ultimate Xtreme

Thanks for taking the time to improve Ultimate Xtreme. This project is a
small, dependency-free PHP application, so contributions should stay focused,
readable and easy to review.

By participating, you agree to follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## Before you start

- Search existing issues and pull requests before opening a new one.
- For larger changes, open an issue first so the approach can be discussed.
- Report security vulnerabilities privately as described in
  [SECURITY.md](SECURITY.md). Do not open a public issue for a vulnerability.
- Never commit provider credentials, the SQLite database, channel cache,
  backups, logs or local configuration.

## Development setup

Requirements:

- PHP 8.0+ with `curl`, `json`, `mbstring`, `openssl` and `pdo_sqlite`
- Apache with `mod_rewrite` for friendly URLs, or PHP's built-in server for
  direct `.php` paths
- Write access to `data/`

Run the app locally:

```bash
php -S 127.0.0.1:8899 -t .
```

Then open `http://127.0.0.1:8899/admin/login.php`. The first visit creates a
fresh database and asks you to create the first administrator account.

If you use Apache/XAMPP, point a virtual host or folder at the project root and
make sure `AllowOverride All` is enabled so `.htaccess` can route requests.

## Making a change

1. Fork the repository and create a topic branch from `main`:
   `git switch -c fix/channel-cache`.
2. Keep the change focused. Avoid unrelated formatting or refactors in the same
   pull request.
3. Follow the coding guidelines below.
4. Test the affected flow locally.
5. Update documentation when behaviour, configuration, routes or setup changes.
6. Open a pull request with a clear title and a short description of the
   problem, the approach and the testing performed.

For UI changes, include a screenshot or a short recording in the pull request.
For security-sensitive changes, describe the threat model and the failure cases
you considered.

## Coding guidelines

- Target **PHP 8.0+** and keep the project compatible with SQLite.
- Match the existing style: four-space indentation, descriptive names, early
  returns and small functions.
- Use strict comparisons (`===`, `!==`) and avoid loose type juggling.
- Use prepared statements for every query. Never interpolate user input into
  SQL.
- Escape output with the existing `e()` helper. Do not build HTML from raw
  request data.
- Keep CSRF protection on every state-changing form.
- Keep role checks on every authenticated page and action. At least one active
  administrator must remain.
- Prefer the existing helpers and layout partials over introducing a new
  framework or dependency.
- Keep the UI light-only and responsive. Reuse the existing design tokens and
  components in `assets/app.css`.
- Do not add a build step, Composer package or JavaScript framework without
  discussing it in an issue first.
- Keep provider credentials out of source files, fixtures, screenshots and
  documentation. Use placeholder hosts such as `panel.example.com`.
- Do not weaken HTTP error behaviour or add silent stream fallbacks.

## Testing checklist

Run these checks before opening a pull request:

```powershell
# PHP syntax
Get-ChildItem -Recurse -Filter *.php |
  ForEach-Object { php -l $_.FullName }

# JavaScript syntax
node --check assets/app.js
```

Then exercise the relevant flow manually:

- First-run setup and sign-in
- Provider create, edit, duplicate, pause and delete
- Connection test and channel loading
- Relay and Direct delivery
- Generated M3U playlist and public stream endpoints
- Administrator and Manager permissions
- Account password change and Team management
- Activity log, backup export/import and cache controls
- Real HTTP errors for a paused server, unknown channel or upstream failure

If you cannot run a check, say so in the pull request.

## Commit messages

Use the imperative mood and keep the first line under about 72 characters.
Examples:

```text
Fix cache invalidation after provider edit
Document the first-run setup flow
Harden playlist payload validation
```

Add a body when the reason for the change is not obvious.

## Documentation

Update `README.md` for user-facing changes and the changelog when a release
adds or removes a feature. Keep instructions copy-pasteable on Windows and
Linux where possible. Never include real provider details in examples.

## License

By contributing, you agree that your contribution is licensed under the
[MIT License](LICENSE).
