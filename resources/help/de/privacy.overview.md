---
title: "Datenschutzmanagement im Überblick"
topic: privacy.overview
version: 1
audience: []
related:
    - documents.manage
    - isms.overview
    - glossary.core
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
  72-Stunden-Meldepflicht (Art. 33/34).

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
