# SiteCharter Connector

The official WordPress connector for [SiteCharter](https://sitecharter.com).

SiteCharter lets the people closest to a website's facts request bounded edits
without handing them theme access, FTP credentials, or a broad WordPress admin
session. This plugin supplies the WordPress-specific connection layer:

- installs SiteCharter's public verification marker without editing a theme;
- creates a constrained integration account and dedicated application password;
- flushes supported WordPress caches after a verified change;
- provides a gzipped database export for SiteCharter's backup layer;
- exposes a handshake describing the connection's available capabilities.

The connector does not add an AI editor to wp-admin. Editing happens through a
SiteCharter workspace; WordPress remains the content system underneath it.

## Install

1. Download the release zip from GitHub.
2. In WordPress, open **Plugins → Add New → Upload Plugin**.
3. Activate **SiteCharter Connector**.
4. Open **Settings → SiteCharter** and paste the connection key from your
   SiteCharter workspace.
5. Create the dedicated application password and copy it into SiteCharter.

WordPress 6.4+ and PHP 8.0+ are required.

## Security model

The connection key appears in public page markup so SiteCharter can verify that
the website owner installed the connector. It is a public identifier, not a
password.

Protected REST endpoints require WordPress to authenticate the dedicated
SiteCharter integration account through an application password. That account
can edit posts and pages, upload media, flush supported caches, and create a
database backup. It cannot manage users, plugins, themes, or general WordPress
settings. Treat its application password as a sensitive credential and revoke
it immediately if the connection is no longer used or may have been exposed.

See [SECURITY.md](SECURITY.md) for reporting and operational guidance.

## Endpoints

All routes live under `/wp-json/sitecharter/v1`:

- `POST /handshake`
- `POST /cache-flush`
- `POST /db-export`

Requests must carry the matching `X-SiteCharter-Key` header and valid WordPress
application-password authentication for the dedicated integration account.

## Development

```bash
php -l sitecharter-connector.php
```

The first release is deliberately one file so it can be inspected without a
build step. Test changes against a disposable WordPress site before using them
on a production installation.

GPL-2.0-or-later. Contributions are welcome.
