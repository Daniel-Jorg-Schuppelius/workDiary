---
title: "Zeitexport & Lohnübergabe"
topic: exports.payroll
version: 1
audience: []
related:
    - admin.surcharge-rules
    - finance.transfers
    - glossary.core
---

Der Zeitexport übergibt freigegebene Monatsdaten an die
Lohnabrechnung – nachvollziehbar, reproduzierbar und mit Audit-Spur.

Typischer Ablauf:

1. **Monatsfreigabe**: Mitarbeitende reichen den Monat ein
   („eingereicht"), die Teamleitung gibt frei („freigegeben"). Nach
   dem Export wird der Monat **gesperrt**.
2. **Export anlegen**: läuft durch „in Vorbereitung" → „bereit" und
   wird nach Übergabe als „übermittelt" oder „abgelehnt" markiert.
3. **Profil wählen**: aktuell **Generischer CSV-Export** mit den
   Spalten Mitarbeiter, Lohnart, Menge, Einheit und Zeitraum.

Ehrlicher Hinweis zum DATEV-Profil: Das **DATEV-Profil** ist eine
**Vorbereitung** (LODAS-nah). Produktiv verfügbar ist heute nur der
**generische CSV-Export** – er ist bewusst **kein zertifiziertes
DATEV-Format**. Ein echtes LODAS-/Lexware-Profil folgt separat.

Lohnarten im Export: Normalstunden, Nacht-/Sonntags-/Feiertagsstunden
(aus den Zuschlagsregeln), Bereitschaft, Urlaubs- und Krankheitstage,
Reisezeit (sofern abrechenbar).

Wichtige Regeln:

- Export nur, wenn **alle betroffenen Monatsfreigaben** freigegeben
  oder gesperrt sind – offene Einreichungen blockieren.
- Jeder Export trägt einen reproduzierbaren **SHA-256-Hash**; nach
  Korrekturen entsteht ein **neuer Export**, der alte wird als
  „ersetzt" markiert – nichts wird still überschrieben.

Berechtigungen: Exporte werden von der Lohnbuchhaltung oder
Organisationsadministration erstellt, übermittelt und bei Bedarf
gelöscht.
