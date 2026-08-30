=== SiteCharter Connector ===
Contributors: sitecharter
Tags: editing, safety, backup, cache, verification
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.1.1
License: GPL-2.0-orlater

Connect your WordPress site to SiteCharter — safe, bounded editing with public verification and a credible way back.

== Description ==

SiteCharter lets the people closest to the facts update the existing site without giving away broad access. This connector:

* Registers the site with your SiteCharter workspace using a public connection key.
* Prints the one line of code on every page, so no theme edit is needed.
* Creates a constrained integration account and application password (shown once).
* Exposes cache-flush across common cache plugins, reported honestly.
* Provides a content-complete gzipped database export, which is what makes SiteCharter's backup promise honest on REST connections.

The connection key is deliberately visible in page markup so SiteCharter can verify the public site. It is an identifier, not a secret. Protected endpoints require the dedicated integration account's WordPress application password and a matching connection key. The account cannot manage users, plugins, themes, or general settings.

== Installation ==

1. Install and activate the plugin.
2. Settings → SiteCharter: paste the site key from your SiteCharter workspace.
3. Click "Create application password" and paste it into SiteCharter when prompted.

== Changelog ==

= 0.1.1 =
* Security hardening: use a constrained integration account instead of an administrator account; change database export to POST with no-store response headers.

= 0.1.0 =
* Initial release: registration, loader line, application-password minting, cache flush, database export.
