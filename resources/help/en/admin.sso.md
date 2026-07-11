---
title: "SSO & directory services"
topic: admin.sso
version: 1
audience:
    - admin
related:
    - admin.integrations
---

On this page you manage the connection to your organization's identity
provider: SCIM provisioning, OIDC single sign-on and SAML 2.0. The
module is part of the Enterprise plan.

**SCIM provisioning:** Your identity provider (Entra ID, Keycloak,
Okta …) creates, updates and deactivates accounts via the SCIM
endpoint. You issue a bearer token for this — the plaintext is shown
exactly once. An account deactivated in the directory immediately
loses sign-in, sessions and API tokens. SCIM never grants roles; you
can deliberately map SCIM groups to a team.

**OIDC single sign-on:** Store the issuer, client ID and client secret
from your identity provider's app registration and register the shown
callback URL there. Employees start the sign-in via the SSO start URL
or the "Sign in with single sign-on" link on the sign-in page (which
asks for the organization identifier).

**SAML 2.0:** Store the identity provider's entity ID, SSO URL and
signing certificate; provide the SP metadata URL to the IdP. Responses
must carry signed assertions; IdP-initiated (unsolicited) sign-ins are
rejected. For certificate rotation you can store a successor
certificate in parallel.

**Account linking:** An IdP identity is linked to a WorkDiary account
exclusively via issuer + subject. SSO never creates accounts and never
grants roles — accounts come via SCIM or are created manually.
Optionally, first-login e-mail linking can connect an existing account
via its e-mail address on the first sign-in (only on exactly one
match).

**Enforced SSO and break-glass:** Enforcing SSO blocks password
sign-in for all accounts of the organization server-side. Define at
least one break-glass account first: a non-federated emergency account
that may keep signing in locally — otherwise an identity provider
outage locks out the entire organization. Every break-glass sign-in is
audited.

**Multi-factor:** After an SSO login the identity provider is
responsible for multi-factor checks. Local sign-in including
two-factor methods remains unchanged for accounts without SSO.
