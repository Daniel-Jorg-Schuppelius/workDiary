---
title: "Demodaten"
topic: admin.demo-data
version: 2
audience:
    - admin
related:
    - admin.tenants
    - admin.handbook
    - admin.data-transfer
---

Demodaten dienen zum Befüllen einer Organisation mit Beispieldaten für
Test, Schulung und Vorführung. Die Inhalte richten sich nach einer
wählbaren **Musterbranche**: Zu jedem Branchenprofil gibt es genau eine,
mit eigenen Kunden, Projekten, einem vollständigen Hauptauftrag, Material,
Asset, signiertem Protokoll und Prozedurlauf.

Aktionen:

- **Demo-Organisation anlegen** (Plattform-Admin, Mandantenverwaltung):
  erzeugt eine neue, isolierte Organisation mit dem Zusatz „(Demo)"; ein
  Plattform-Admin kann direkt als Mitglied zugewiesen werden.
- **Erzeugen (Seed)**: befüllt die aktuelle, noch leere Organisation mit
  Beispieldaten der gewählten Musterbranche. Die Übersicht zeigt, ob die
  Organisation leer ist.
- **Zurücksetzen (Reset)**: löscht die Demodaten eines Demo-Mandanten und
  erzeugt sie neu; Musterbranche und Funktionsumfang bleiben erhalten.

Funktionsumfang:

- Ohne Haken folgt die Demo der **Modul-Empfehlung des Branchenprofils**:
  Der Funktionsumfang wird entsprechend gesetzt, und Beispieldaten
  entstehen nur für aktive Module.
- **Vollumfang vorführen** legt für alle Module Beispieldaten an
  (Helpdesk, Agile, Bewerbungen, Investitionen, Krisenübung,
  Nachhaltigkeit, Reklamation, Verleih, Leasing, Prüfmittel, Buchhaltung,
  Lernplattform und mehr).

Lizenz:

- Der Dialog zeigt vorab, unter welcher Lizenz die Demo läuft. Kann diese
  Instanz Lizenzen ausstellen, erhält die Demo-Organisation automatisch
  eine befristete Lizenz; sonst gilt die Installationslizenz. Ohne beides
  läuft die Demo im Tarif Free, und die meisten Module bleiben gesperrt.

Risiken und Einschränkungen:

- **Reset ist nur für ausgewiesene Demo-Mandanten erlaubt**
  (`is_demo`). Für reguläre Organisationen wird er abgelehnt, um
  echte Daten zu schützen. Auf einem Demo-Mandanten überschreibt bzw.
  entfernt der Reset jedoch die vorhandenen Demo-Daten.
- Das Erzeugen fügt zusätzliche Datensätze hinzu; prüfen Sie vorab, ob die
  Organisation wirklich leer sein soll.
- Ist eine Aufbewahrungsfrist konfiguriert, löscht der Zeitplan
  Demo-Organisationen nach Ablauf endgültig.

Alle Aktionen erfordern eigene Berechtigungen (Seed, plattformweit Reset
und Anlage) und werden im Audit-Log protokolliert.
