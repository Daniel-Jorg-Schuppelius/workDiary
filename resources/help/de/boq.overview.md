---
title: "GAEB-Leistungsverzeichnisse"
topic: boq.overview
version: 1
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
