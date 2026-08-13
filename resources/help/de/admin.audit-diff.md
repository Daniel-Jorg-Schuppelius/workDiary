---
title: "Änderungsverlauf & Versionsvergleich"
topic: admin.audit-diff
version: 1
audience: [admin]
related:
    - audit.log
---

Der Änderungsverlauf macht die revisionssichere Audit-Kette lesbar: Für
einen ausgewählten Datensatz (Mitglied, Kunde, Arbeitszeit-Modell,
Schichttyp, Zeitkonto, Organisation) zeigt die Timeline alle
protokollierten Änderungen mit Zeitpunkt, Ereignis und Benutzer.

Wählen Sie zwei Stände (A = älter, B = jünger) und vergleichen Sie:
Die Diff-Tabelle zeigt je Feld den Wert vor Stand A und nach Stand B —
so ist in Sekunden geklärt, seit wann ein Wert steht und wer ihn
geändert hat.

Sensible Felder (Passwörter, Geheimnisse, Steuer- und
Sozialversicherungsnummern) werden maskiert dargestellt. Der Vergleich
ist bewusst reine Anzeige: Korrekturen bleiben fachliche, auditierte
Vorgänge — es gibt kein automatisches Zurückrollen.
