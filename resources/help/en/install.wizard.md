---
title: "Installation"
topic: install.wizard
version: 3
keywords:
    - setup wizard
    - initial setup
    - first-time setup
    - installer
    - system requirements
    - database setup
    - create administrator
    - SMTP settings
    - mail server
    - web push
    - VAPID
    - application key
audience: [admin]
related:
    - admin.tenants
    - auth.login
---

The installation wizard guides you step by step through the initial
setup of WorkDiary. Each step saves its values immediately, so an
interruption can be safely repeated at any time. **Next** takes you to
the next step, **Back** to the previous one. Once installation is
complete, the wizard is locked and can no longer be opened.

The steps at a glance:

- **Requirements**: checks whether the server meets all requirements for
  the selected **Database driver**. After fixing the marked items, check
  again with **Update**.
- **Application**: **Application name**, **Application URL**,
  **Environment**, **Language** and **Timezone**. If no application key
  exists yet, one is generated automatically; an existing key remains
  unchanged.
- **Database**: **Driver** and connection details. **Connect & migrate**
  tests the connection, sets up the database and creates roles and
  permissions. Enable the option to empty the database before migration
  only if the database is meant to be empty or an earlier attempt was
  aborted.
- **Administrator**: creates the first organization (**Organisation
  name**) and the administrator account with **Create administrator**.
- **Email**: delivery channel (**Mailer**) and sender for emails. With
  “log”, emails are only logged and not sent; with “smtp” you enter the
  **SMTP host**, port, credentials and **Encryption**, plus the **Sender
  address** and **Sender name**.
- **Integrations**: optional credentials such as the **Lexoffice API
  key** and the key pair for **Web push (VAPID)**, which **Generate key**
  creates automatically. Everything can be added later.
- **Completion**: **Complete installation** locks the wizard, discards
  cached settings so that the new values take effect immediately, and
  leads to sign-in. The administrator then signs in again normally.
