---
title: "Installation"
topic: install.wizard
version: 1
audience: [admin]
related:
    - admin.tenants
    - auth.login
---

Der Installationsassistent führt Sie Schritt für Schritt durch die
Ersteinrichtung von WorkDiary. Jeder Schritt speichert seine Werte
sofort, sodass ein Abbruch jederzeit gefahrlos wiederholbar ist.
Sobald die Installation abgeschlossen ist, wird der Assistent gesperrt
und nicht mehr aufgerufen.

Die Schritte im Überblick:

- **Voraussetzungen**: Prüfung der Server- und PHP-Anforderungen für den
  gewählten Datenbanktreiber.
- **Anwendung**: Name, URL, Umgebung, Sprache und Zeitzone. Dabei wird
  der Anwendungsschlüssel sichergestellt.
- **Datenbank**: Treiber und Zugangsdaten. Die Verbindung wird getestet,
  anschließend werden Migrationen ausgeführt sowie Rollen und
  Berechtigungen angelegt.
- **Administrator**: Anlage der ersten Organisation und des
  Administrator-Kontos.
- **Mail**: Versandweg und Absenderadresse für E-Mails (Protokoll oder
  SMTP).
- **Integrationen**: Optionale Zugänge wie der Lexoffice-API-Schlüssel
  und VAPID-Schlüssel für Web-Push.
- **Abschluss**: Setzt die Sperrdatei, leert die Caches und führt zur
  Anmeldung. Der Administrator meldet sich danach regulär neu an.
