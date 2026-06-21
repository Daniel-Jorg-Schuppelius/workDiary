---
title: "Lexoffice-Konflikte"
topic: admin.lexoffice
version: 1
audience:
    - admin
related:
    - admin.plugins
    - articles.lexoffice
    - invoices.manage
---

Hier löst du Synchronisationskonflikte mit Lexoffice. Ein Konflikt
entsteht, wenn ein lokaler Datensatz (WorkDiary) und der zugehörige
Datensatz in Lexoffice in einem oder mehreren Feldern auseinander­
laufen und die Synchronisation eine manuelle Prüfung verlangt.

Posteingang:

- Liste offener Konflikte mit den abweichenden Feldern sowie
  Momentaufnahmen der lokalen und der entfernten (Lexoffice-)Daten.
- Betroffen sein können Kontakte/Kunden, Artikel, Belege und
  Rechnungen.

Lösungswege je Konflikt:

- **Lokal übernehmen**: behält die lokalen Werte; die abweichenden
  Lexoffice-Werte werden verworfen.
- **Extern übernehmen**: aktualisiert den lokalen Datensatz mit den
  Lexoffice-Werten der abweichenden Felder.
- **Verwerfen**: ignoriert den Konflikt (z. B. bei bewusst
  unterschiedlichen Daten); er wird als erledigt markiert.

Risiken: „Lokal übernehmen" und „Extern übernehmen" überschreiben
Werte. Prüfe die gegenübergestellten Daten genau, bevor du
entscheidest. Beachte, dass bei Rechnungen die Faktura-Hoheit beim
externen Programm liegt – WorkDiary liefert dorthin zu.
