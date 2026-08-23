---
title: "Lokale Buchhaltung"
topic: accounting.overview
version: 1
audience:
    - admin
    - buchhaltung
related:
    - accounting.posting
    - accounting.closing
---

Die lokale Buchhaltung führt ein eigenes Hauptbuch in WorkDiary — für
Organisationen ohne separate Buchhaltungssoftware. Sie ersetzt weder die
Buchhaltungs-Plugins noch deren Datenführerschaft: Je Zeitraum führt entweder
WorkDiary oder genau ein externes System.

**Drei Führungsfragen, die nicht vermischt werden:**

1. *Fakturahoheit* — wer stellt Rechnungen aus?
2. *Stammdatenhoheit* — wer führt Kunden und Lieferanten?
3. *Buchungshoheit* — wer führt das Hauptbuch? Nur diese Achse ist neu.

**Einrichtung** (Finanzen → Buchhaltung einrichten): Profil wählen
(Einnahmenüberschussrechnung oder doppelte Buchführung), Basiswährung,
Geschäftsjahr und Buchungsbeginn festlegen. Der Preflight prüft, ob die
Organisation ab dem Stichtag lückenlos selbst buchen kann; erst wenn kein
Punkt mehr rot ist, lässt sich die lokale Buchhaltung aktivieren.

Belege vor dem Stichtag bleiben Historie und werden nicht rückwirkend
gebucht.
