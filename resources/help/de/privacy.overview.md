---
title: "Datenschutzmanagement im Überblick"
topic: privacy.overview
version: 1
audience: []
modules:
    - module.datenschutz
related:
    - documents.manage
    - isms.overview
    - glossary.core
    - privacy.portal
---

Das Datenschutzmodul unterstützt die operative Datenschutzarbeit deiner
Organisation. Es wird laufend weiterentwickelt – die Grundbausteine:

- **Verarbeitungstätigkeiten (VVT)**: Verzeichnis nach Art. 30 DSGVO
  mit Versionierung. Ablauf: „Entwurf" → „In Prüfung" → „Freigegeben"
  → „Archiviert". Jede Freigabe erzeugt einen unveränderlichen
  Versions-Snapshot (inklusive TOM-Stand).
- **Auftragsverarbeiter & AVV**: Register der Dienstleister und
  Verträge nach Art. 28.
- **Betroffenenanfragen**: Auskunft, Berichtigung, Löschung,
  Einschränkung, Datenübertragbarkeit, Widerspruch (Art. 15–21) mit
  **30-Tage-Frist** ab Eingang, Identitätsprüfung, Zuweisung und
  dokumentierter Entscheidung.
- **TOM**: technische und organisatorische Maßnahmen.
- **Datenschutzvorfälle**: Erfassung mit Blick auf die
  72-Stunden-Meldepflicht (Art. 33). Die Meldung an die Behörde und die
  Benachrichtigung der Betroffenen (Art. 34) werden getrennt vermerkt.

Besonderheiten:

- Anfrageinhalte und Entscheidungsbegründungen werden **verschlüsselt**
  gespeichert (eigener Schlüssel je Fall).
- **Bewusst kein Admin-Bypass**: Datenschutzrechte werden ausdrücklich
  vergeben – auch Plattform-Admins erhalten sie nicht automatisch.

Risiken und unumkehrbare Aktionen: Nach Ablauf der Aufbewahrungsfrist
kann der Fall-Schlüssel vernichtet werden (Crypto-Shredding) – die
verschlüsselten Inhalte sind dann **unwiederbringlich**. Freigegebene
VVT-Versionen sind nicht mehr änderbar.

Nächste Schritte: Nachweise (AVV-Dokumente, Zertifikate) verwaltest du
im Modul **Dokumente**.

**Aufbewahrung, Löschung und Legal Hold:** Unter **Aufbewahrung & Löschung**
schlägt das Löschkonzept fristüberfällige Daten vor; gelöscht oder
anonymisiert wird erst nach zweistufiger Bestätigung. Läuft ein Betroffenen-
oder Rechtsverfahren, setzt du unter **Legal Hold** einen Sperrvermerk an die
Person oder den Kunden – mit Pflichtbegründung und optionalem Aktenzeichen. Solange
er aktiv ist, entstehen keine Löschvorschläge, bestätigte Löschungen,
Anonymisierung und Löschen von Konten oder Kunden werden abgewiesen, und die
Standort-Rohpunkte der Person bleiben erhalten. Bei einer Kundenzusammenführung
wandert der Vermerk zum Zielkunden. Aufgehoben wird er ebenfalls nur mit
Begründung; beides bleibt im Protokoll von Person bzw. Kunde sichtbar.
