---
title: "Access & Security"
topic: customer-portal.access
version: 3
keywords:
    - login
    - sign in
    - password
    - 2FA
    - two-factor authentication
    - authenticator app
    - passkey
    - security key
    - recovery codes
    - change email
    - stay signed in
    - profile
audience: []
related:
    - customer-portal.overview
---

Your access to the customer portal is a personal account that your contractor creates for you. This topic explains how to activate the access, sign in, change your login email and protect the access with a second factor.

## Activating your access

Your contractor invites you by email; the subject reads “Your access to the … customer portal”. The email contains the button **Set password**. The link can be used once and is valid for seven days; the expiry date is stated in the email.

1. Click **Set password** in the email.
2. Enter a password under **New password** and repeat it under **Repeat password**.
3. Click **Save password**.

The password must be at least 12 characters long and contain upper- and lower-case letters, digits and special characters. Passwords known from data breaches are rejected. Afterwards the sign-in page opens with the notice **Your password has been set. You can sign in now.**

If the link has expired, the page can no longer be opened. In that case ask your contractor to send you the invitation again.

## Signing in

On the **Sign in** page enter your **Email** and your **Password** and click **Sign in**. With **Stay signed in** you do not have to sign in again on this device every time you visit – use this option only on your own device.

- If email or password is wrong, **These credentials do not match our records.** appears. For security reasons the portal does not say which entry was wrong.
- The number of sign-in attempts is limited; after too many failed attempts you have to wait a while.
- If your contractor has deactivated your access, signing in is no longer possible.
- There is no function in the portal for changing or resetting the password. If you have forgotten your password, please contact your contractor.

### Second factor when signing in

If you have set up a second factor, the page **Two-factor confirmation** follows after the password. Depending on the method you set up:

- enter the 6-digit code from your authenticator app under **Code** and click **Confirm**,
- click **Send code by email** and enter the code from the email; then **Confirm with email code**,
- sign in with **With passkey / security key**,
- or enter one of your recovery codes via **Use recovery code instead**.

After several wrong codes, input is blocked for a short time; after too many failed attempts the sign-in starts over. **Cancel** takes you back to the sign-in page.

## Profile and login email

Under **Profile**, in the section **Your access**, you see the entries **Name**, **Login email** and **Customer** – the company your access is assigned to. Name and company can only be changed by your contractor.

You change your login email yourself:

1. Under **Change email address** enter the **New email address**.
2. Click **Send confirmation link**. If you have not signed in recently, the
   portal first asks for your password under **Confirm password**.
3. Within 24 hours, open the link in the email sent to the new address.

Only after you click the link does the new address become your login email; the previous address receives a notice about the change. Until then you sign in with the previous address, and the profile shows for which address a confirmation is pending and until when. A new request replaces the pending one. If the new address is already in use, no email arrives; the message in the portal is the same in every case so that nobody can draw conclusions about other accounts.

## Setting up two-factor authentication

**Security** opens the page **Two-factor authentication**. At the top right you see the state: **Active**, **Setup pending** or **Inactive**. The section **Add method** offers three methods; you can use several at the same time.

### Authenticator app

1. Click **Show QR code**.
2. Scan the QR code with your app (for example Google Authenticator, Aegis or 1Password) or type in the displayed **Key**.
3. Enter the 6-digit code from the app and click **Confirm**.

### Email code

1. Click **Enable email code**. The portal sends a code to your login email.
2. Enter the code and click **Confirm**. If no email has arrived, request a new code with **Resend code**.

### Security key / passkey (FIDO2)

Click **Add passkey** and follow the instructions of your browser or device – with a passkey, your smartphone or a hardware key. If your sign-in was some time ago, the portal first asks for your password on the page **Confirm password**.

### Recovery codes

When you set up the first method, the portal shows your **Recovery codes** once. Each code works exactly once and replaces the second factor when signing in. Keep the codes in a safe place; the portal does not show them again. If the authenticator app is set up, you can create a new set: under **Regenerate recovery codes** enter the code from the app in the field **Current app code** and click **Generate new**. The previous codes are then no longer valid.

## Removing factors or disabling everything

The section **Active factors** lists the methods you have set up. You remove the email code and passkeys individually with **Remove** (bin icon). The authenticator app can only be switched off together with everything else.

Under **Disable all** you switch off two-factor authentication completely: enter an **App or recovery code** and click **Deactivate**. All factors and recovery codes are deleted in the process.

## When two-factor authentication is mandatory

Your contractor can require a second factor for all accesses. In that case:

- If you do not have a second factor yet, the portal takes you after signing in to the page **Two-factor authentication** with the notice **Your organization requires two-factor authentication. Please set it up now.** Other pages only open once a method has been set up.
- The last remaining factor cannot be removed.
- **Disable all** is missing; instead there is a notice that deactivation is not possible.
