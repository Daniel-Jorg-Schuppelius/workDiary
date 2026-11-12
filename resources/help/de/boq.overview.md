---
title: "GAEB-Leistungsverzeichnisse"
topic: boq.overview
version: 3
keywords:
    - LV
    - LV importieren
    - LV exportieren
    - GAEB DA XML
    - X83
    - X86
    - Ausschreibung
    - Aufmaß
    - Nachkalkulation
    - Nachtrag
    - Einheitspreis
    - Angebotsabgabe
audience: []
modules:
    - module.bau
related:
    - projects.manage
    - invoices.manage
---

Leistungsverzeichnisse (LV) bilden Bauleistungen strukturiert ab — vom
importierten GAEB-Datenaustausch über Aufmaß und Kalkulation bis zum
Export des aktuellen Stands.

**Import mit Preflight:** Eingelesen werden GAEB-DA-XML-Dateien der
Version 3.x in den Austauschphasen X81 bis X86 (Leistungsverzeichnis,
Kostenanschlag, Angebotsaufforderung, Angebotsabgabe, Nebenangebot,
Auftragserteilung). Vor dem Schreiben prüft ein Preflight Version,
Austauschphase, Struktur, Eindeutigkeit der Ordnungszahlen sowie die
Plausibilität von Mengen und Einheiten. Blockierende Befunde erzeugen nur
ein Importprotokoll — es wird nichts geschrieben. Ein Reimport in ein
bestehendes LV bricht ab, wenn er Positionen mit Ausführungs- oder
Abrechnungsbezug überschreiben würde.

**Struktur & Preisstände:** Ein LV besteht aus Kopf, hierarchischen
Abschnitten mit Ordnungszahlen und Positionen mit Kurz- und Langtext,
Menge, Einheit und Einheitspreis. Jeder Import legt Preis-Snapshots ab,
sodass frühere Preisstände nachvollziehbar bleiben. Ein LV kann einem
Projekt zugeordnet werden; Positionen lassen sich mit Artikeln bzw.
Material verknüpfen.

**Aufmaß & Nachkalkulation:** Fortschrittsmeldungen werden je Position
additiv erfasst (Menge, Quelle, Notiz). Positionen mit erstem Aufmaß
wechseln automatisch auf „in Arbeit". Die Nachkalkulation stellt Soll
(Sollmenge × Einheitspreis), Ist (aufgemessene Menge × Einheitspreis),
Restleistung und Fortschrittsgrad gegenüber — sie ist eine Auswertung
und ersetzt keine Fakturierung.

**Workflow:** LV und einzelne Positionen durchlaufen gerichtete
Statusübergänge von der Ausschreibung über Angebot und Auftrag bis zu
Ausführung und Abschluss; ungültige Sprünge werden abgewiesen. Nachträge
werden als eigene Positionen geführt, die Restleistungssicht zeigt, was
noch offen ist.

**Export:** Der aktuelle LV-Stand lässt sich als GAEB-DA-XML in einer
wählbaren Austauschphase herunterladen (Standard: Auftragserteilung).
Der Export ist deterministisch und wird mit Inhalts-Hash protokolliert —
derselbe Stand ergibt reproduzierbar denselben Hash.

## Vorbemerkungen und Prüfung vor der Abgabe

Liefert die GAEB-Datei **Vorbemerkungen** (Vertragstexte vor den Positionen), stehen sie in der LV-Ansicht zum Aufklappen, ein Text je Block und unverändert wie geliefert. Ein Reimport ersetzt sie mit der Datei. Gelesen werden sie aus GAEB 90 und aus DA XML (Zusatztexte im Vergabeblock).

Die **Prüfung vor der Abgabe** meldet zusätzlich Positionen, deren Preis in einer anderen Währung steht als das Leistungsverzeichnis, und eine Umsatzsteuer, die nicht zum Satz auf die Nettosumme passt oder mit ihr die Bruttosumme verfehlt (auf den Cent gerechnet).

## Bieterkommentare

In der Angebotsaufforderung legt der Auftraggeber fest, ob Bieter Kommentare zu Positionen abgeben dürfen. Die LV-Ansicht zeigt, was die Datei dazu sagt. Sind Kommentare nicht zugelassen, übernimmt der Import keine Kommentare (das Importprotokoll nennt ihre Zahl), und das Angebot enthält keine. Ein Reimport des Angebots ändert die Vorgabe der Aufforderung nicht.

## Fortschritt mit Beleg und Befunde des Imports

Neben der schnellen Mengeneingabe öffnet das Maßband-Symbol einer Position den Dialog **Fortschritt melden**. Dort wählen Sie zusätzlich einen **Materialverbrauch** des Projekts oder ein **Protokoll** des Projekts. Der gewählte Beleg bestimmt die Quelle der Meldung und bleibt an ihr stehen; der Dialog zeigt die bisherigen Meldungen mit ihrem Bezug. Ein Materialverbrauch lässt sich genau einer Position zuordnen; seine Kosten zählen in der Wirtschaftlichkeitsauswertung zu dieser Position. Für eine reine Kostenzuordnung tragen Sie die Menge 0 ein.

Die **Importhistorie** zeigt je Lauf die Befunde. Ein abgewiesener Reimport steht dort mit dem Status „Konflikt“ und nennt je geschützter Position Menge und Einheitspreis im Bestand neben den Werten der Datei. Am Leistungsverzeichnis selbst ändert er nichts.

Auch am Protokoll selbst steht im Menü **Fortschritt zur LV-Position melden**, wenn das Projekt des Protokolls ein Leistungsverzeichnis mit offenen Positionen hat. Die Meldung erhält die Quelle „Protokoll“ und verweist auf dieses Protokoll.
