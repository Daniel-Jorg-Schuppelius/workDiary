---
title: "Anmelderichtlinie: Zeiten, Büronetze, Passkey und Notaus"
topic: admin.login-policy
version: 2
keywords:
    - Anmelderichtlinie
    - Anmeldezeiten
    - Zeitfenster
    - Büronetz
    - IP-Bereich
    - CIDR
    - Homeoffice
    - Gerät freigeben
    - Passkey
    - Passkey-Pflicht
    - Notaus
    - alle abmelden
audience:
    - admin
related:
    - admin.security
    - account.two-factor
---

Die Anmelderichtlinie legt fest, **wann** und **von wo** sich die
Mitglieder Ihrer Organisation anmelden dürfen. Org-Admins sind von
Zeiten und Netzen immer ausgenommen, damit sich die Organisation nicht
selbst aussperrt; der Plattformbetrieb und Kundenportal-Konten sind nie
betroffen.

**Anmeldezeiten**: Je Wochentag ein Zeitfenster in der Zeitzone der
Organisation, auf Wunsch nur für ausgewählte Rollen. Außerhalb des
Fensters gelingt keine neue Anmeldung – die Meldung nennt die nächste
Öffnung –, und laufende Sitzungen enden mit dem Fensterende. Ein Fenster
darf über Mitternacht reichen (22:00–06:00). Persönliche API-Tokens
folgen den Zeiten; Stempelterminals und Integrationen nicht.

**Büronetze**: Eine Liste erlaubter Netze (IPv4/IPv6, auch als CIDR) und
DNS-Namen dynamischer Anschlüsse; die Namen löst der Server alle fünf
Minuten auf. Wer das Recht „Von außerhalb der Büronetze anmelden“ nicht
hat, meldet sich nur aus diesen Netzen an. Mit dem Recht geht es auch
von außen, aber nur von einem **freigegebenen Gerät**: Ein neues Gerät
stellt bei der Anmeldung eine Freigabeanfrage, die Org-Admins hier
freigeben oder ablehnen. Erkannt wird das Gerät an einem signierten
Cookie, nicht an der IP-Adresse. Das eigene Gerät gibt jemand anderes
frei.

**Wirkung „Nur protokollieren“**: Zeiten und Netze lassen sich zuerst
nur protokollieren. Unten auf der Seite steht dann eine Woche lang, wen
die Regel ausgesperrt hätte. Erst wenn die Liste stimmt, auf
„Durchsetzen“ umstellen – falsch eingetragene Netze sperren sonst ganze
Büros aus.

**Passkey-Pflicht**: Für ausgewählte Rollen verlangt die Organisation die
Anmeldung mit Passkey. Wer noch keinen hat, richtet ihn nach der
nächsten Anmeldung unter „Zwei-Faktor-Authentifizierung“ ein. Ein
Wiederherstellungscode bleibt der Notweg, wenn das Gerät fehlt.

**Notaus**: Im Ernstfall beenden Inhaber oder Org-Admins mit einem Grund
und erneuter Passworteingabe alle Sitzungen und persönlichen API-Tokens
der Organisation außer der eigenen Sitzung. Danach können sich nur noch
Org-Admins und Personen mit dem Recht „Notaus“ anmelden, bis jemand mit
diesem Recht oder der Plattformbetrieb den Notaus aufhebt. Alle Org-Admins
werden benachrichtigt; Stempelterminals, Standortgeräte und
Integrationen laufen weiter, andere Organisationen bleiben unberührt.

**Persönliche Zugänge**: API-Tokens und KI-Verbindungen (MCP) tragen kein
Gerätecookie. Außerhalb der Büronetze funktionieren sie nur mit dem eigenen
Recht „Persönliche Zugänge auch außerhalb der Büronetze nutzen“; die
Zeitfenster und der Notaus gelten für sie immer.

**Geräte und DNS-Namen im Detail**: Eine abgelehnte oder entzogene Freigabe
bleibt stehen – dasselbe Gerät fragt nicht erneut an, bis Sie die Ablehnung
„vergessen“. Je Person sind höchstens drei Anfragen offen. Je Organisation
sind höchstens zehn DNS-Namen erlaubt; sie werden im Hintergrund mit
Zeitlimit aufgelöst, eine erfolgreiche Auflösung gilt höchstens 24 Stunden,
und interne Adressen aus DNS-Antworten zählen nur, wenn der Betreiber es
erlaubt. „Durchsetzen“ verlangt mindestens ein Zeitfenster bzw. ein Netz.

**Passkey-Pflicht und SSO**: Meldet sich jemand trotz Passkey-Pflicht mit
einem Wiederherstellungscode an, erfahren es die Person und alle
Org-Admins; der letzte Passkey lässt sich erst entfernen, wenn ein neuer
eingerichtet ist. Über SSO angemeldete Sitzungen fallen nicht unter die
Passkey-Pflicht – dort bestimmt der Identitätsanbieter den Faktor, der Hebel
ist die SSO-Erzwingung.
