---
title: "Integrationen verwalten"
topic: admin.integrations
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.lexoffice
---

Diese Hilfe gilt für alle Integrations-Verwaltungsseiten – etwa
CalDAV, WebDAV, Todoist, Zammad, Kimai/Clockify, E-Mail-Eingang,
Telefonie, Team-Messenger, Stempelterminals, Versand und SSO. Alle
Anbindungen folgen denselben Grundprinzipien.

**Pro Organisation:** Integrationen werden je Organisation aktiviert
und konfiguriert. Aktivierung, Zugangsdaten, Gesundheitsstatus und
Fehlerhistorie gelten immer nur für die aktuelle Organisation – in
einer anderen Organisation kann dieselbe Anbindung einen ganz anderen
Zustand haben.

**Zugangsdaten:** Tokens, Passwörter und Gerätekennungen hinterlegst
du in der jeweiligen Plugin-Konfiguration. Sensible Werte werden
verschlüsselt gespeichert und erscheinen nach dem Speichern nicht
mehr im Klartext – weder in der Oberfläche noch im Audit-Protokoll.

**Healthcheck und Auto-Deaktivierung:** Jede Anbindung wird laufend
auf Verbindungsfehler überwacht. Häufen sich Fehler über die
konfigurierbare Schwelle hinaus, wird die Anbindung automatisch
deaktiviert, damit sie keine Folgefehler produziert. Automatisch
deaktivierte Integrationen bleiben in der Übersicht sichtbar und
sind entsprechend markiert – nach Behebung der Ursache (z. B.
abgelaufenes Token erneuert) kannst du sie wieder aktivieren.
Ein einzelnes fehlerhaftes Plugin reißt dabei nie die Anwendung mit:
Fehler werden isoliert aufgezeichnet.

**Eingehende Daten – Inbox-First:** Importe übernehmen nichts blind.
Eingehende Datensätze landen zuerst in der Integrations-Inbox, werden
gegen vorhandene Daten abgeglichen und erst nach eindeutigem Match
oder deiner manuellen Entscheidung übernommen. Unklare Fälle und
Konflikte bleiben als offene Inbox-Einträge liegen, bis du sie
auflöst oder verwirfst.

**Ausgehende Änderungen – Outbox:** Änderungen Richtung Fremdsystem
laufen über eine Outbox mit automatischer Wiederholung. Schlägt eine
Übertragung fehl, wird sie erneut versucht; erkannte Konflikte (z. B.
wenn das Fremdsystem zwischenzeitlich geändert wurde) wandern zur
Klärung zurück in die Inbox. So geht keine Änderung verloren und
nichts wird doppelt geschrieben.

**Empfehlung:** Prüfe nach dem Einrichten einer neuen Anbindung den
Healthcheck, beobachte einige Tage die Inbox auf unerwartete
Konflikte und richte erst dann automatisierte Abläufe darauf ein.
