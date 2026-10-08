---
title: "Installation"
topic: install.wizard
version: 3
keywords:
    - Ersteinrichtung
    - Erstinstallation
    - Einrichtungsassistent
    - Setup
    - Systemvoraussetzungen
    - Datenbank einrichten
    - Administrator anlegen
    - SMTP einrichten
    - Mailserver
    - Web-Push
    - VAPID
    - Anwendungsschlüssel
audience: [admin]
related:
    - admin.tenants
    - auth.login
---

Der Installationsassistent führt Sie Schritt für Schritt durch die
Ersteinrichtung von WorkDiary. Jeder Schritt speichert seine Werte
sofort, sodass ein Abbruch jederzeit gefahrlos wiederholbar ist. Mit
**Weiter** gelangen Sie zum nächsten Schritt, mit **Zurück** zum
vorigen. Sobald die Installation abgeschlossen ist, wird der Assistent
gesperrt und lässt sich nicht mehr aufrufen.

Die Schritte im Überblick:

- **Voraussetzungen**: Prüfung, ob der Server alle Anforderungen für den
  gewählten **Datenbank-Treiber** erfüllt. Nach dem Beheben markierter
  Punkte prüfen Sie mit **Aktualisieren** erneut.
- **Anwendung**: **Anwendungsname**, **Anwendungs-URL**, **Umgebung**,
  **Sprache** und **Zeitzone**. Fehlt noch ein Anwendungsschlüssel, wird
  er dabei automatisch erzeugt; ein vorhandener bleibt unverändert.
- **Datenbank**: **Treiber** und Verbindungsdaten. **Verbinden &
  migrieren** testet die Verbindung, richtet die Datenbank ein und legt
  Rollen und Berechtigungen an. Die Option zum Leeren der Datenbank vor
  der Migration aktivieren Sie nur, wenn die Datenbank leer sein soll oder
  ein früherer Versuch abgebrochen wurde.
- **Administrator**: Anlage der ersten Organisation (**Name der
  Organisation**) und des Administrator-Kontos mit **Administrator
  anlegen**.
- **E-Mail**: Versandweg (**Mailer**) und Absender für E-Mails. Mit
  „log“ werden E-Mails nur protokolliert und nicht versendet; mit
  „smtp“ tragen Sie **SMTP-Host**, Port, Zugangsdaten und
  **Verschlüsselung** ein, dazu **Absender-Adresse** und
  **Absender-Name**.
- **Integrationen**: Optionale Zugänge wie der **Lexoffice
  API-Schlüssel** und das Schlüsselpaar für **Web-Push (VAPID)**, das
  **Schlüssel generieren** automatisch erzeugt. Alles lässt sich später
  ergänzen.
- **Abschluss**: **Installation abschließen** sperrt den Assistenten,
  verwirft zwischengespeicherte Einstellungen, damit die neuen Werte
  sofort gelten, und führt zur Anmeldung. Der Administrator meldet sich
  danach regulär neu an.
