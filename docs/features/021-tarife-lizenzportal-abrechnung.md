# Tarife, Lizenzportal und Abrechnung

## Status

Proposed — Lizenzstatus und Feature-Flags in der Admin-Oberfläche konzipiert
in [`docs/lizenz-admin.md`](../lizenz-admin.md) (MVP-047, Issue #46).

## Ziel

WorkDiary soll kommerziell als On-Premise-Produkt und SaaS-Dienst steuerbar
sein. Dazu gehören Tarife, Nutzerlimits, Featurepakete, Testphasen,
Lizenzportal, Rechnungsdaten, Vertragsstatus und Upgrade-/Downgrade-Prozesse.

## Warum

Das Produkt braucht nicht nur Funktionen, sondern ein steuerbares
Vertriebsmodell. Kunden müssen wissen, welche Funktionen sie gebucht haben,
wann Lizenzen ablaufen und wie Nutzer- oder Featuregrenzen wirken.

## MVP

- Tarif- und Featurepaket-Definition.
- Lizenzportal für On-Premise-Kunden.
- SaaS-Mandantenstatus: Testphase, aktiv, gesperrt, gekündigt.
- Nutzerlimit und Feature-Flags aus Lizenz oder Tarif.
- Admin-Ansicht für Lizenz, Tarif, Ablauf und Nutzung.
- Abrechnungsdaten für SaaS-Kunden.

## Akzeptanzkriterien

- Feature-Zugriff folgt Lizenz oder Tarif.
- Kunden sehen ihren Vertrags- und Lizenzstatus.
- Lokale Installationen können offline oder mit periodischer Prüfung betrieben
  werden.
- SaaS-Mandanten können kontrolliert gesperrt oder reaktiviert werden.

## Abhängigkeiten

- Lizenzierung
- Mandantenfähigkeit und Betriebsmodelle
- Datenschutz, Sicherheit und Datenlebenszyklus
- Rollen und Rechte

## GitHub Issues

- TBD
