# Tarife, Lizenzportal und Abrechnung

## Status

In Progress — Lizenzstatus und Feature-Flags in der Admin-Oberfläche konzipiert
in [`docs/lizenz-admin.md`](../lizenz-admin.md) (MVP-047, Issue #46).
Die organisationsbezogene Aktivierung und Deaktivierung lizenzierter Module zur
Reduktion der Kundenoberfläche ist in
[`docs/modul-konfiguration.md`](../modul-konfiguration.md) (MVP-052)
geschnitten.
Nutzerlimit-Durchsetzung (org-bezogen, bei der Mitglieder-Anlage),
SaaS-Mandantenstatus (trial/active/suspended, sonst aus Lizenz-Ablauf +
Grace abgeleitet: gültig/in Kulanz/abgelaufen) mit Admin-Umschaltung und
Schreibsperre bei gesperrtem/abgelaufenem Mandanten umgesetzt. Online-
Lizenzportal (externe Selbstausstellung) bleibt offen (out of scope).

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
- Fachmodule wie Fertigung/Lager, DATEV/Finanzformate und GAEB/AVA können
  lizenzierbar getrennt und organisationsbezogen deaktivierbar angeboten
  werden.
- Lizenzportal für On-Premise-Kunden.
- SaaS-Mandantenstatus: Testphase, aktiv, gesperrt, gekündigt.
- Nutzerlimit und Feature-Flags aus Lizenz oder Tarif.
- Organisationsbezogene Deaktivierung nicht benötigter, aber lizenzierter
  Module ohne Datenverlust.
- Admin-Ansicht für Lizenz, Tarif, Ablauf und Nutzung.
- Abrechnungsdaten für SaaS-Kunden.

## Akzeptanzkriterien

- Feature-Zugriff folgt Lizenz oder Tarif.
- Kundenadmins können den lizenzierten Funktionsumfang für ihre Organisation
  reduzieren, aber niemals erweitern.
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
