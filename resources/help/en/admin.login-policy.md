---
title: "Sign-in policy: hours, office networks, passkey and emergency lockdown"
topic: admin.login-policy
version: 2
keywords:
    - sign-in policy
    - sign-in hours
    - time window
    - office network
    - IP range
    - CIDR
    - remote work
    - approve device
    - passkey
    - passkey requirement
    - emergency lockdown
    - sign everyone out
audience:
    - admin
related:
    - admin.security
    - account.two-factor
---

The sign-in policy defines **when** and **from where** the members of
your organisation may sign in. Organisation admins are always exempt from
hours and networks so the organisation cannot lock itself out; platform
operations and customer portal accounts are never affected.

**Sign-in hours**: One time window per weekday in the organisation's time
zone, optionally only for selected roles. Outside the window no new
sign-in succeeds – the message names the next opening – and running
sessions end when the window closes. A window may run past midnight
(22:00–06:00). Personal API tokens follow the hours; time clocks and
integrations do not.

**Office networks**: A list of allowed networks (IPv4/IPv6, also as
CIDR) and DNS names of dynamic connections; the server resolves the names
every five minutes. Anyone without the right “Sign in from outside the
office networks” signs in from these networks only. With the right,
signing in from outside works too, but only from an **approved device**:
a new device files an approval request when signing in, which
organisation admins approve or reject here. The device is recognised by a
signed cookie, not by its IP address. Your own device is approved by
someone else.

**“Log only” effect**: Hours and networks can be logged first. The bottom
of the page then shows for a week who the rule would have locked out.
Only switch to “Enforce” once the list is right – wrongly entered networks
would otherwise lock out whole offices.

**Passkey requirement**: For selected roles the organisation requires
sign-in with a passkey. Anyone without one sets it up after the next
sign-in under “Two-factor authentication”. A recovery code remains the
emergency route if the device is missing.

**Emergency lockdown**: In an emergency, owners or organisation admins end
all sessions and personal API tokens of the organisation except their own
session, giving a reason and re-entering their password. Afterwards only
organisation admins and people with the “Emergency lockdown” right can sign
in, until someone with this right or platform operations lifts the
lockdown. All organisation admins are notified; time clocks, location
devices and integrations keep running, other organisations are unaffected.

**Personal access**: API tokens and AI connections (MCP) carry no device
cookie. Outside the office networks they only work with the separate right
“Use personal access outside the office networks too”; hours and the
emergency lockdown always apply to them.

**Devices and DNS names in detail**: A rejected or revoked approval stays in
place – the same device does not ask again until you “forget” the rejection.
At most three requests per person are open. Each organisation may enter at
most ten DNS names; they are resolved in the background with a time limit, a
successful resolution stays valid for at most 24 hours, and internal
addresses from DNS answers only count if the operator allows it. “Enforce”
requires at least one time window or network.

**Passkey requirement and SSO**: If someone signs in with a recovery code
despite the passkey requirement, the person and all organisation admins are
notified; the last passkey can only be removed once a new one is set up.
Sessions signed in via SSO are not subject to the passkey requirement – the
identity provider decides the factor there, the lever is SSO enforcement.
