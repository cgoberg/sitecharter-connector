# Security

Please report vulnerabilities privately through GitHub's security-advisory
flow for this repository. Do not open a public issue containing a working
exploit, application password, database export, or identifiable site data.

## Credential boundary

The SiteCharter connection key is public by design. The WordPress application
password is the credential protecting the connector's privileged routes.
Create a dedicated password for SiteCharter, transmit it only to the intended
SiteCharter workspace, and revoke it when the connection is removed.

The database export is content-complete and can contain users, password hashes,
configuration, plugin data, and personal information. Store it with the same
care as a production database backup.

## Supported versions

Security fixes are applied to the latest release. The current minimums are
WordPress 6.4 and PHP 8.0.

