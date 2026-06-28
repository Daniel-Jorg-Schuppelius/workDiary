# Sicherheitsprüfung und Release-Gate

## Status

In Progress — verbindlicher P0-Querschnitt des MVP (`MVP-097` bis `MVP-101`).
Die Prüfung ist vor der produktiven Freigabe abzuschließen und nach wesentlichen
Änderungen zu wiederholen.

### Umsetzungsstand (Stand 2026-06-28)

- **MVP-097 (angelegt):** Angriffsflächen-Inventar und ASVS-5.0-Kontrollmatrix
  in [docs/security/release-gate-2026-06.md](../security/release-gate-2026-06.md).
- **MVP-098 (umgesetzt):** Reproduzierbares automatisiertes Gate —
  `composer security:gate` (`scripts/security-gate.sh`) und CI-Job
  „Security gate" (composer audit, npm audit, pint, SBOM). Aktuell grün: keine
  Composer-/NPM-Advisories, PHPStan L8 und Pint sauber. **SBOM:** `composer sbom`
  erzeugt eine deterministische CycloneDX-1.5-Stückliste (307 Komponenten,
  Composer + npm) als reproduzierbares Release-Artefakt.
- **Erste Behebung (MVP-099/100-Muster):** XML-Upload des neuen GAEB-Imports
  gegen XXE gehärtet (DOCTYPE-Ablehnung + `LIBXML_NONET`, keine
  Entity-Substitution) inkl. Regressionstest.
- **Offen:** MVP-099 (vollständige manuelle Whitebox-/dynamische Prüfung),
  MVP-100 (2FA-/Session-Vertiefung beider Guards), **MVP-101 (unabhängiger
  Penetrationstest + formale Freigabe)** sowie der Secret-Historie-Scan
  (gitleaks). Ohne diese Punkte erfolgt keine Produktivfreigabe.

## Ziel

WorkDiary verarbeitet unternehmenskritische und personenbezogene Daten. Deshalb
reicht weder ein reiner Abhängigkeitsscan noch eine einmalige Sichtprüfung. Vor
der produktiven MVP-Freigabe wird der eingefrorene Release-Kandidat vollständig
gegen einen dokumentierten Prüfkatalog untersucht. Gefundene Schwachstellen
werden behoben, durch Regressionstests abgesichert und erneut geprüft.

„Vollständig“ bedeutet dabei eine nachweisbare Abdeckung aller inventarisierten
Angriffsflächen und Prüfkriterien. Es bedeutet nicht das unbeweisbare Versprechen,
dass Software dauerhaft frei von unbekannten Schwachstellen ist.

Das bereits durchgeführte
[Sicherheitsaudit 2026-06](../security/audit-2026-06.md) und die dokumentierte
[Sicherheitshärtung](../security/hardening-2026-06.md) sind Eingaben und
Regression-Baseline. Sie ersetzen die Prüfung des finalen MVP-Release-Kandidaten
nicht.

## Verbindlicher Prüfmaßstab

- OWASP Application Security Verification Standard (ASVS) 5.0, Level 2 für die
  gesamte Anwendung.
- Risikobasiert ausgewählte ASVS-Level-3-Anforderungen für Plattformadministration,
  Rechteverwaltung, Lohn-/Finanzdaten, Meldestelle, Supportzugriffe,
  Schlüssel-/Secret-Verwaltung und Exporte.
- OWASP Web Security Testing Guide für manuelle und dynamische Prüfungen.
- NIST Secure Software Development Framework (SP 800-218) für
  Entwicklungs-, Behebungs- und Nachweisprozess.
- Projektspezifische Anforderungen aus Datenschutz, Mandantentrennung,
  Rollen-/Rechtemodell, Supportzugriff, Backup/Restore und ISMS.

Verwendete Version, nicht anwendbare Anforderungen und Abweichungen werden in
einer Kontrollmatrix festgehalten. „Nicht anwendbar“ benötigt eine Begründung.

Offizielle Referenzen:

- <https://owasp.org/www-project-application-security-verification-standard/>
- <https://owasp.org/www-project-web-security-testing-guide/latest/>
- <https://cheatsheetseries.owasp.org/cheatsheets/Multifactor_Authentication_Cheat_Sheet.html>
- <https://csrc.nist.gov/pubs/sp/800/218/final>

## Prüfumfang

Vor Prüfungsbeginn entsteht ein versioniertes Inventar des Release-Kandidaten:

- alle Web-, API-, Kundenportal-, Broadcast- und WebSocket-Routen;
- Controller, Form-Requests, Policies, Gates, Middleware und
  organisationsbezogene Scopes;
- Queue-Jobs, Scheduler, Commands, Events, Listener und Benachrichtigungen;
- Datei-Uploads/-Downloads, Anhänge, Importe, Exporte, PDF-/CSV-Ausgaben und
  temporäre Dateien;
- Suche, Kalenderfeeds, Freigabelinks, Passwortrücksetzung, Einladungen,
  Supportzugriffe und Administrationsfunktionen;
- Plugins, optionale Module und externe Integrationen einschließlich ihrer
  Fehler-, Retry- und Webhook-Pfade;
- Datenbank, Cache, Session, Storage, Logging, Backups, Verschlüsselung und
  Schlüsselrotation;
- Composer- und npm-Abhängigkeiten, Build-/Deploy-Konfiguration,
  Sicherheitsheader und produktive Beispielkonfiguration.

Neue oder im Inventar fehlende Angriffsflächen blockieren den Abschluss, bis sie
klassifiziert und geprüft wurden.

## Prüfblöcke

### 1. Bedrohungsmodell und Datenflüsse

- Schutzbedarf und Datenflüsse für personenbezogene, finanzielle,
  arbeitsrechtliche und vertrauliche Unternehmensdaten erfassen.
- Vertrauensgrenzen zwischen Organisationen, Guards, Portalen, Plugins,
  Integrationen, Queue, Storage und Supportzugriff markieren.
- Missbrauchsfälle für IDOR, Rechteausweitung, organisationsübergreifenden
  Zugriff, Massenexport, Datenmanipulation, Löschung und Verfügbarkeitsangriffe
  dokumentieren.
- Prüffälle und ASVS-Anforderungen auf jede relevante Angriffsfläche abbilden.

### 2. Automatisierte Prüfungen

- bekannte Schwachstellen in Composer- und npm-Abhängigkeiten einschließlich
  transitiver Pakete prüfen;
- statische Analyse und sicherheitsbezogene Regeln für PHP, Blade und JavaScript;
- Secret-, Schlüssel- und Zugangsdaten-Scan über Quellcode, Konfiguration und
  relevante Git-Historie;
- SBOM erzeugen und gegen Advisories abgleichen;
- Konfiguration auf Debug-Modus, Cookies, TLS-/Proxy-Vertrauen, CORS, CSP,
  Header, Dateirechte, Logs und unsichere Defaults prüfen;
- alle Projekt-Tests, PHPStan und Pint auf dem exakten Release-Commit ausführen.

Automatisierte Werkzeuge unterstützen die Prüfung, gelten allein aber nicht als
Sicherheitsnachweis. Treffer werden manuell validiert; Fehlalarme werden
begründet dokumentiert.

### 3. Manuelle Whitebox- und dynamische Prüfung

- Authentifizierung, Autorisierung, Mandantentrennung und Objektzugriffe für
  jede Rolle und jeden Guard;
- Injection, XSS, CSRF, SSRF, Pfadmanipulation, unsichere Deserialisierung,
  Datei-/Archivverarbeitung, Mass Assignment und Open Redirects;
- Session-Fixation, Session-Hijacking, Logout, parallele Sessions,
  Remember-me, Passwortwechsel/-reset und Sperr-/Rate-Limit-Umgehungen;
- Business-Logic-Angriffe auf Freigaben, Statuswechsel, Korrekturen, Preise,
  Bestände, Exporte, Integrationen und Hintergrundverarbeitung;
- Informationsabfluss über Fehlerseiten, Logs, Benachrichtigungen, Suche,
  Caches, Exporte, Backups, Supportberichte und zeitliche Seiteneffekte;
- Sicherheits- und Datenschutzverhalten bei Teilausfällen, Wiederholungen,
  konkurrierenden Requests und manipulierten Fremdsystemantworten.

Positive Funktionsprüfungen reichen nicht. Für Schutzmechanismen sind
Negativ-, Umgehungs-, Parallelitäts- und Wiederholungsprüfungen erforderlich.

## Vertiefungsprüfung Authentifizierung und 2FA

Alle angebotenen Faktoren und beide interaktiven Guards werden geprüft:
TOTP/Authenticator-App, WebAuthn/Passkeys, E-Mail-Code und Recovery-Codes in
Hauptanwendung und Kundenportal.

Mindestens nachzuweisen sind:

- Ohne bestätigten Faktor ist 2FA nicht aktiv; eine vorbereitete Einrichtung
  erzeugt keinen Umgehungspfad.
- Die organisationsweite 2FA-Pflicht gilt für neue und bestehende Sitzungen,
  alle Rollen und sensible Sonderbereiche; direkte URLs, alternative Guards,
  Remember-me und Legacy-Login umgehen sie nicht.
- TOTP akzeptiert nur das festgelegte enge Zeitfenster, ist rate-limitiert und
  kann innerhalb seines Gültigkeitsfensters nicht wiederverwendet werden.
- WebAuthn prüft Challenge, Origin, RP-ID, Signaturzähler und
  Benutzerzuordnung; Challenges sind kurzlebig und einmalig.
- E-Mail-Codes sind kurzlebig, einmalig, nicht im Klartext gespeichert oder
  geloggt und gegen Versand- sowie Rate-Limit-Missbrauch geschützt. Da
  E-Mail-Zugriff nicht immer ein unabhängiger Faktor ist, darf er für
  Hochrisikobereiche nicht allein die verbindliche starke 2FA ersetzen.
- Recovery-Codes werden nur einmal angezeigt, ausschließlich als Hash
  gespeichert, atomar einmalig verbraucht und bei Neugenerierung vollständig
  ungültig.
- Aktivieren, Deaktivieren, Faktorwechsel, Entfernen des letzten starken
  Faktors, Recovery-Code-Neuerstellung und administrative Rücksetzung verlangen
  frische Re-Authentifizierung und lösen ein Sicherheitsereignis aus.
- Der Wiederherstellungs- und Supportprozess ist nicht schwächer als die
  reguläre Anmeldung; es gibt keinen undokumentierten Bypass.
- Fehlerantworten ermöglichen keine Benutzer-, Faktor- oder
  Organisationsauskunft; fehlgeschlagene Versuche werden gedrosselt und
  sicherheitsrelevant protokolliert, ohne Codes oder Secrets zu erfassen.
- Erfolgreiche Faktor-, Passwort- und Recovery-Änderungen widerrufen betroffene
  Sitzungen bzw. Tokens nach einer dokumentierten Regel.

Für privilegierte Rollen und Hochrisikobereiche ist TOTP oder WebAuthn
verbindlich; SMS wird nicht eingeführt. WebAuthn ist wegen seiner
Phishing-Resistenz die bevorzugte Zielmethode.

## Befund- und Behebungsprozess

Jeder Befund enthält mindestens betroffene Version, Angriffsfläche,
Reproduktionsschritte, Auswirkung, CWE/ASVS-Zuordnung, Schweregrad, Belege,
Verantwortlichen und Behebungsfrist. Beweise und sensible Details liegen in
einem zugriffsgeschützten Bericht, nicht in öffentlichen Issues oder Logs.

Für jeden bestätigten Befund gilt:

1. Ursache beheben, nicht nur den sichtbaren Exploit blockieren.
2. Gleichartige Stellen im gesamten Inventar prüfen.
3. Einen reproduzierbaren Regressionstest ergänzen.
4. Auswirkung auf Datenbestand, Geheimnisse, Sitzungen und Meldepflichten
   bewerten.
5. Fix im Vier-Augen-Prinzip prüfen und den ursprünglichen Angriff erneut
   testen.

Kritische und hohe Befunde sperren die Freigabe ausnahmslos. Mittlere und
niedrige Befunde werden vor Freigabe behoben. Nur wenn eine Behebung technisch
noch nicht möglich ist, darf die Produktverantwortung für einen niedrigen
Befund eine befristete, begründete Ausnahme mit Kompensationsmaßnahme,
Verantwortlichem und festem Termin genehmigen. Eine Ausnahme für kritische,
hohe oder mittlere Befunde ist im MVP nicht zulässig.

## Unabhängige Prüfung und Wiederholung

Nach interner Behebung erfolgt ein unabhängiger Penetrationstest des
Release-Kandidaten durch eine fachkundige Person, die die geprüften Änderungen
nicht selbst implementiert hat. Der Test umfasst mindestens die kritischen
Datenflüsse, Rollen-/Mandantengrenzen, Hauptanwendung, Kundenportal,
Authentifizierung/2FA, Upload/Import/Export und die produktionsnahe
Konfiguration.

Die vollständige Prüfung wird wiederholt:

- vor jeder Hauptversion;
- nach wesentlichen Änderungen an Authentifizierung, Autorisierung,
  Mandantentrennung, Kryptografie, Upload/Import, Integrationen oder
  Infrastruktur;
- nach einem relevanten Sicherheitsvorfall;
- mindestens jährlich, falls keine frühere Auslösung eintritt.

Abhängigkeiten und Advisories werden zusätzlich kontinuierlich bzw. bei jedem
Build geprüft.

## MVP-Issues

- `MVP-097`: Angriffsflächen inventarisieren, Bedrohungsmodell erstellen und
  ASVS-5.0-Kontrollmatrix für den Release-Kandidaten festlegen.
- `MVP-098`: Automatisierte Abhängigkeits-, SAST-, Secret-, SBOM- und
  Konfigurationsprüfungen reproduzierbar in CI und Releaseprozess integrieren.
- `MVP-099`: Gesamte Anwendung manuell per Whitebox- und dynamischer Prüfung
  untersuchen, Befunde beheben und Regressionstests ergänzen.
- `MVP-100`: Authentifizierung, Sitzungen, Passwort-/Recovery-Flows und alle
  2FA-Methoden beider Guards einschließlich Umgehungsversuchen vertieft prüfen
  und härten.
- `MVP-101`: Alle Fixes nachtesten, unabhängigen Penetrationstest abschließen
  und das Security-Release-Gate dokumentiert freigeben.

## Definition of Done

- Release-Commit, Konfiguration, Werkzeugversionen und Prüfumgebung sind
  eindeutig reproduzierbar dokumentiert.
- Inventar und ASVS-Kontrollmatrix decken alle in dieser Spezifikation
  genannten Angriffsflächen ab; jede Anforderung hat Ergebnis und Beleg.
- Alle bestätigten kritischen, hohen und mittleren Befunde sind behoben und
  nachgetestet; niedrige Befunde sind behoben oder formal befristet behandelt.
- Für jede behobene Schwachstellenklasse existiert ein Regressionstest oder
  eine begründete gleichwertige automatisierte Kontrolle.
- 2FA-, Recovery-, Legacy-, Kundenportal-, Rollen- und Mandanten-Bypass-Tests
  sind für Erfolgs-, Fehler-, Wiederholungs- und Parallelfälle grün.
- Abhängigkeits-, Secret-, SAST-, SBOM- und Konfigurationsprüfungen sind grün
  oder enthalten ausschließlich dokumentierte Fehlalarme.
- `composer test`, `vendor/bin/phpstan analyse` und `vendor/bin/pint` laufen
  auf dem geprüften Commit erfolgreich.
- Ein unabhängiger Nachtest bestätigt die Behebung und enthält keine offenen
  freigabesperrenden Befunde.
- Die formale Freigabe nennt Verantwortliche, Datum, Restrestrisiken und Termin
  der nächsten Prüfung. Ohne diese Freigabe wird der MVP nicht produktiv
  ausgerollt.
