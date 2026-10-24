---
title: "Firefly III als Abgleichquelle"
topic: admin.firefly
version: 1
keywords:
    - Firefly III
    - Kontobewegungen
    - Zahlungseingang
    - Abgleich
    - Verwendungszweck
    - Personal Access Token
audience:
    - admin
related:
    - admin.plugins
    - admin.integrations
    - admin.scheduler
---

Die Seite **Firefly III** liest die Ein- und Ausgänge Ihrer Firefly-III-Installation
und zeigt, zu welcher Rechnung oder Eingangsrechnung eine Bewegung gehört. Sie
finden die Seite im Systemmenü (Zahnrad **System**) unter **Plugins** →
**Firefly III**, sobald das Plugin aktiv ist. WorkDiary schreibt nichts nach
Firefly III und bucht aus den Bewegungen keine Zahlungen.

## Zugang einrichten

1. Legen Sie in Firefly III unter **Optionen** → **Profil** → **OAuth** einen
   **Personal Access Token** an.
2. Öffnen Sie auf der Seite im Menü ⋯ **Zugangsdaten** und tragen Sie die
   **Adresse** (ohne /api) und den Token ein. Läuft Firefly III im eigenen Netz,
   setzen Sie zusätzlich **Private Adressen erlauben** – das wirkt nur, wenn der
   Betreiber es zulässt.
3. Klicken Sie auf **Verbindung prüfen**. WorkDiary zeigt Instanz, Version und
   Konto.

## Was abgeglichen wird

Jeder Lauf liest die Bewegungen im **Zeitfenster** (Vorgabe 60 Tage bis heute,
einstellbar unter **Zugangsdaten**). Umbuchungen zwischen eigenen Konten bleiben
außen vor.

- **Eingang:** Steht im Verwendungszweck, in den Notizen, in der Referenz oder in der SEPA-End-to-End-ID
  genau eine Rechnungsnummer Ihrer Organisation, zeigt die Zeile diese Rechnung.
- **Ausgang:** dasselbe mit der Nummer einer Eingangsrechnung.
- Eine Sammelbuchung (Split) erscheint je Teilbuchung als eigene Zeile.
- Passen mehrere Belege, steht die Bewegung auf **Mehrdeutig**; ohne Treffer auf
  **Ohne Zuordnung**. Betrag und Datum allein ordnen nie zu.

Was im vollständig gelesenen Zeitfenster in Firefly III fehlt, verschwindet aus
der Liste. Ein abgebrochener Lauf gilt als **unvollständig** und entfernt nichts.

## Wann abgeglichen wird

Stündlich; den Takt ändern Sie unter **Geplante Jobs**, **Jetzt abgleichen**
startet sofort. **Trennen** beendet den Abgleich.

## Typische Fehler

- Die Prüfung scheitert mit 401: Der Token ist falsch oder abgelaufen.
- Die Prüfung meldet eine interne Adresse: Die Adresse zeigt ins eigene Netz,
  aber **Private Adressen erlauben** ist aus oder vom Betreiber nicht zugelassen.
