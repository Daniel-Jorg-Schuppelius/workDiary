---
title: "Auditpakete & Prüfer-Links"
topic: isms.packages
version: 1
audience: []
related:
    - isms.audits
    - isms.conformity
    - isms.overview
    - glossary.core
---

**Auditpakete** frieren den ISMS-Datenstand zu einem Stichtag als
Snapshot ein – als belastbare Grundlage für externe Prüfer.

Typischer Ablauf:

1. **Paket anlegen**: Titel, Stichtag, Geltungsbereich, optional Norm
   und Ausgabe als Filter. Das Paket startet als „Entwurf".
2. **Finalisieren**: erzeugt den JSON-Snapshot mit SHA-256-Hash und
   hält fest, wer wann finalisiert hat.
3. **Integrität prüfen**: vergleicht die Datei jederzeit gegen den
   gespeicherten Hash.
4. **Prüfer-Link erstellen**: zeitlich begrenzter Download-Zugang
   (1–90 Tage), jederzeit widerrufbar.

Paket-Inhalte: SoA, Risikoregister (letzte freigegebene
Netto-Bewertungen), Maßnahmenliste mit Verknüpfungen,
Konformitätsstatus, Audits samt Feststellungen und
Korrekturmaßnahmen, freigegebene Managementbewertungen,
Softwareinventar.

Risiken und unumkehrbare Aktionen:

- **Finalisierte Pakete sind unveränderlich** – Bearbeiten und Löschen
  sind gesperrt.
- Der Stichtag ist der dokumentierte Berichtsstichtag; der Datenstand
  entspricht dem **Zeitpunkt der Finalisierung** (keine rückwirkende
  Rekonstruktion).
- Der vollständige **Prüfer-Link wird nur einmal angezeigt** (beim
  Erstellen) – danach ist nur noch Widerruf möglich.

Berechtigungen: Einsicht erfordert ISMS-Leserechte; Erstellung und
Pflege erfordern ISMS-Pflegerechte. Der Prüfer-Download selbst läuft
über einen geschützten Link, ohne WorkDiary-Konto.
