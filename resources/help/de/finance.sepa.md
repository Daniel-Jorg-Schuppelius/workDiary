---
title: "SEPA-Zahlungsausgang"
topic: finance.sepa
version: 1
audience: []
modules:
    - module.finance
related:
    - finance.incoming-invoices
    - invoices.manage
    - contacts.manage
---

Der Zahllauf bündelt freigegebene Eingangsrechnungen zu einer
SEPA-Sammelüberweisung. workDiary erzeugt dabei eine **Datei, keinen
Zahlungsauftrag**: Ausgelöst wird die Zahlung im Banking-Programm mit dessen
eigener Autorisierung.

**Zahlungsvorschlag:** Die Liste enthält alle zur Zahlung freigegebenen,
noch offenen Eingangsrechnungen. Je Rechnung wird das wirtschaftlichste
Ausführungsdatum vorgeschlagen — der Skontotermin, solange er erreichbar
ist, sonst der Fälligkeitstag. Der Zahlbetrag ist dann bereits um das Skonto
gemindert. Jede Position ist abwählbar; eine Rechnung ohne IBAN wird als
gesperrt angezeigt und nicht in den Lauf genommen.

**Drei Schritte:** zusammenstellen (Entwurf) → freigeben → exportieren. Die
Freigabe ist ein eigenes Recht: Wer den Lauf zusammenstellt, muss ihn nicht
freigeben dürfen. Nach dem Export ist der Lauf unveränderlich; ein Storno ist
nur davor möglich und gibt die Rechnungen wieder frei.

**Kürzung:** Einzelne Positionen lassen sich im Entwurf auf einen geringeren
Betrag setzen — etwa bei einem Mängeleinbehalt gegenüber dem Lieferanten. Ein
geringerer Zahlbetrag verlangt einen Grund; Rechnungsbetrag und Zahlbetrag
stehen danach nebeneinander.

**Nachweis:** Die erzeugte Datei wird als vertrauliches Dokument archiviert
und ihr SHA-256-Hash am Lauf festgehalten. Ein zweiter Abruf liefert
dieselbe Datei — nie eine neue mit abweichender Message-ID, die die Bank als
zweite Zahlung verstehen könnte.

**Mandate und Einzug:** Für den Lastschrifteinzug führt das Mandatsregister
Mandatsreferenz, Unterschriftsdatum und Art (einmalig/wiederkehrend). Ein
Mandat wird nie gelöscht, sondern widerrufen — der Widerruf ist der Nachweis,
ab wann nicht mehr eingezogen werden durfte. Nach 36 Monaten ohne Einzug
gilt ein Mandat als verfallen. Die Vorlauffrist beträgt fünf Bankarbeitstage
bei der Erst- und zwei bei der Folgelastschrift. Der Einzug braucht die
Gläubiger-Identifikationsnummer der Organisation (Einstellung „Gläubiger-Identifikationsnummer“ in der
Einstellungs-Registry).

**Zusatzmodul:** Die Dateierzeugung gehört zum kostenpflichtigen
Banking-Format-Modul. Ohne das Modul bleiben Zahllauf und Mandatsregister
bedienbar, nur der Export fehlt.
