# Contributing

Keep the connector small, inspectable, and compatible with the WordPress APIs
it extends. Explain the user-visible reason for each change, avoid new runtime
dependencies unless they are essential, and test against a disposable
WordPress installation.

Before opening a pull request:

```bash
php -l sitecharter-connector.php
```

Never include real application passwords, database exports, private site URLs,
or customer data in issues, fixtures, screenshots, or commits.

