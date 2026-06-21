---
title: "Installation"
topic: install.wizard
version: 1
audience: [admin]
related:
    - admin.tenants
    - auth.login
---

The installation wizard guides you step by step through the initial
setup of WorkDiary. Each step saves its values immediately, so an
interruption can be safely repeated at any time. Once installation is
complete, the wizard is locked and no longer accessible.

The steps at a glance:

- **Requirements**: Checks the server and PHP requirements for the
  selected database driver.
- **Application**: Name, URL, environment, language and time zone. The
  application key is ensured in this step.
- **Database**: Driver and credentials. The connection is tested, then
  migrations are run and roles and permissions are created.
- **Administrator**: Creates the first organization and the
  administrator account.
- **Mail**: Delivery channel and sender address for emails (log or
  SMTP).
- **Integrations**: Optional credentials such as the Lexoffice API key
  and VAPID keys for web push.
- **Finish**: Sets the lock file, clears the caches and leads to
  sign-in. The administrator then logs in again normally.
