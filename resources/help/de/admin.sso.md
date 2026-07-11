---
title: "SSO & Verzeichnisdienste"
topic: admin.sso
version: 1
audience:
    - admin
related:
    - admin.integrations
---

Auf dieser Seite verwaltest du die Anbindung an den Identitätsanbieter
deiner Organisation: SCIM-Provisionierung, OIDC-Single-Sign-on und
SAML 2.0. Das Modul ist Teil des Enterprise-Plans.

**SCIM-Provisionierung:** Dein Identitätsanbieter (Entra ID, Keycloak,
Okta …) legt Konten an, aktualisiert und deaktiviert sie über den
SCIM-Endpunkt. Dafür stellst du ein Bearer-Token aus — der Klartext
wird genau einmal angezeigt. Ein im Verzeichnis deaktiviertes Konto
verliert sofort Anmeldung, Sitzungen und API-Tokens. SCIM vergibt nie
Rollen; SCIM-Gruppen kannst du bewusst einem Team zuordnen.

**OIDC-Single-Sign-on:** Hinterlege Issuer, Client-ID und
Client-Secret aus der App-Registrierung deines Identitätsanbieters
und registriere dort die angezeigte Callback-URL. Mitarbeitende
starten die Anmeldung über die SSO-Start-URL oder den Link
„Mit Single-Sign-on anmelden" auf der Anmeldeseite (dort wird die
Organisations-Kennung abgefragt).

**SAML 2.0:** Hinterlege Entity-ID, SSO-URL und Signaturzertifikat des
Identitätsanbieters; die SP-Metadata-URL stellst du dem IdP bereit.
Antworten müssen signierte Assertions tragen; vom IdP initiierte
(unaufgeforderte) Anmeldungen werden abgelehnt. Für die
Zertifikatsrotation kannst du ein Folgezertifikat parallel hinterlegen.

**Kontoverknüpfung:** Eine IdP-Identität wird ausschließlich über
Issuer + Subject mit einem WorkDiary-Konto verknüpft. SSO legt nie
Konten an und vergibt nie Rollen — Konten kommen über SCIM oder werden
manuell angelegt. Optional kann die E-Mail-Erstverknüpfung ein
vorhandenes Konto beim ersten Login über die E-Mail-Adresse verbinden
(nur bei genau einem Treffer).

**SSO-Pflicht und Break-Glass:** Mit der SSO-Pflicht wird der
Passwort-Login aller Konten der Organisation serverseitig gesperrt.
Lege vorher mindestens ein Break-Glass-Konto fest: ein nicht
föderiertes Notfallkonto, das sich weiterhin lokal anmelden darf —
sonst sperrt ein Ausfall des Identitätsanbieters die gesamte
Organisation aus. Jede Break-Glass-Anmeldung wird auditiert.

**Mehr-Faktor:** Nach einem SSO-Login übernimmt der Identitätsanbieter
die Mehr-Faktor-Prüfung. Die lokale Anmeldung inklusive
Zwei-Faktor-Verfahren bleibt für Konten ohne SSO unverändert.
