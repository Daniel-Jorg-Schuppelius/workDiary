# Integrationen und offene API

## Status

In Progress

## Ziel

WorkDiary soll sich in bestehende Betriebsabläufe einfügen: Buchhaltung,
Kalender, Kommunikation, Lohnabrechnung, Projektabrechnung und eigene Tools.

## Warum

Integrationen senken Wechselhürden. Für viele Betriebe ist entscheidend, ob ein
System mit DATEV, Lexware, Lexoffice, Microsoft 365, Google Calendar oder
internen Schnittstellen zusammenarbeitet.

## MVP

- Dokumentierte REST-API für Kernobjekte: Zeiten, Projekte, Kunden, Schichten,
  Abwesenheiten, Spesen und Rechnungen.
- Webhooks für wichtige Ereignisse.
- API-Token mit scopes.
- Microsoft-365- und Google-Kalender-Anbindung als Ergänzung zu ICS.
- Erweiterung des Plugin-Systems für Buchhaltungs- und Exportadapter.

## Akzeptanzkriterien

- API-Zugriffe sind rollen- und organisationssicher.
- Webhooks sind signiert und wiederholbar.
- Exporte laufen nachvollziehbar und mit Fehlerprotokoll.
- Integrationen sind modular, nicht fest in Controller eingebaut.

## Abhängigkeiten

- API-Token
- Plugin-System
- Kalenderfeeds
- Lexoffice-Plugin
- Rollen/Rechte
- Audit

## GitHub Issues

- TBD
