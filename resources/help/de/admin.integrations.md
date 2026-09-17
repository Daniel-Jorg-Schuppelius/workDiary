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

**Stempelterminals, Kiosk und Check-in-Punkte:** Beim Registrieren eines
Terminals erscheinen zwei Adressen genau einmal: die Ingest-Adresse für
Hardware-Terminals und die Kiosk-Adresse, mit der ein Tablet im Browser zum
Terminal wird. Beide enthalten dasselbe Gerätetoken; wer es verliert, rotiert
das Token oder sperrt das Terminal. Ausweise, die am Tablet über dessen eigenen
NFC-Chip gelesen werden (Android-Chrome), müssen als Hex-Kennung ohne
Trennzeichen hinterlegt sein. Check-in-Punkte sind QR-Codes oder
NFC-Aufkleber an Standorten und Fahrzeugen: Die Druckansicht liefert den Code,
dieselbe Adresse lässt sich mit einer NFC-App auf einen Aufkleber schreiben.
Ein Code lässt sich abfotografieren – wer Anwesenheit am Ort belegen will,
hinterlegt einen Umkreis. Die Position wird dann nur geprüft, nicht
gespeichert.

Ausweise lassen sich durch eine **Terminal-PIN** ersetzen: Die Verwaltung setzt
sie je Person mit Personalnummer; gespeichert wird nur ein Hash, nach fünf
Fehlversuchen ist sie 15 Minuten gesperrt und lässt sich hier entsperren.

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

## Welche Anbindungen es gibt

Die Auswahl wächst; die folgende Aufstellung nennt die verfügbaren
Anbindungen nach Zweck, damit du nicht raten musst, wo etwas hingehört:

- **Buchhaltung und Faktura:** lexoffice, orgaMAX, sevDesk, easybill,
  BuchhaltungsButler, InvoicePlane sowie der Peppol-Zugangspunkt für den
  Versand elektronischer Rechnungen.
- **Telefonie und Nachrichten:** sipgate und FRITZ!Box für ein- und
  ausgehende Anrufe, seven.io für SMS an kritische Empfänger.
- **Versand:** DHL, FedEx und UPS für Etiketten und Sendungsverfolgung.
- **Dateien und Sicherungen:** Nextcloud, WebDAV, Dropbox, Google Drive,
  SharePoint und S3 als Ablage- oder Sicherungsziel.
- **Kalender, Kontakte und Post:** Microsoft Graph, Google Kalender, CalDAV,
  CardDAV sowie Calendly für gebuchte Termine.
- **Projekte und Zeiten:** Todoist, OpenProject, GitHub, GitLab, Toggl,
  Clockify, Kimai, Zammad.
- **Handel und Warenwirtschaft:** JTL-Wawi, Billbee, Etsy.

Eine Anbindung, die hier fehlt, gibt es nicht — frag im Zweifel nach, statt
Zugangsdaten an einer Stelle zu hinterlegen, die dafür nicht gedacht ist.
