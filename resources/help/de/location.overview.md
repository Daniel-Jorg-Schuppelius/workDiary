---
title: "Standortbasierte Zeiterfassung"
topic: location.overview
version: 1
audience: []
modules:
    - module.standorterfassung
related:
    - time-entries.start
    - attendance.manage
---

Die standortbasierte Zeiterfassung schlägt Zeitbuchungen automatisch vor,
wenn ein Gerät einen hinterlegten Kundenstandort betritt und wieder
verlässt. Sie ergänzt die manuelle Erfassung — gebucht wird nie automatisch,
sondern erst nach ausdrücklicher Bestätigung.

**Geofences je Kundenstandort:** Für jeden relevanten Kundenstandort wird
ein Umkreis aus Mittelpunkt und Radius definiert. Nur innerhalb dieser Zonen
entstehen überhaupt Aufenthalte; Bewegungen außerhalb bleiben ohne fachliche
Bedeutung.

**Datenquellen:** Positionsmeldungen kommen wahlweise aus den Apps OwnTracks
oder Traccar über einen persönlichen Gerätezugang, direkt aus dem Browser
oder nachträglich per Import einer Google-Standortverlauf-Datei. Jedes Gerät
wird bewusst registriert, und die Erfassung setzt die dokumentierte
Einwilligung der betroffenen Person voraus.

**Vom Signal zum Vorschlag:** Eingehende Punkte werden zu Aufenthalten
verdichtet: Das Betreten und Verlassen eines Geofences ergibt einen Besuch
mit Beginn und Ende. Abgeschlossene Besuche erscheinen als Vorschläge in
einer persönlichen Prüf-Inbox — mit Kunde, gegebenenfalls Projekt und dem
erfassten Zeitraum.

**Prüfen statt Automatik:** Erst die Bestätigung eines Vorschlags erzeugt
einen echten Zeiteintrag; unpassende Vorschläge lassen sich verwerfen.
Zwischen Ortssignal und Buchung steht damit immer eine bewusste
Entscheidung der betroffenen Person selbst.

**Datenschutz:** Ausgewertet werden Ein- und Austritts-Ereignisse an den
hinterlegten Kundenstandorten — eine dauerhafte Ortsüberwachung findet nicht
statt. Jede Person sieht ausschließlich die eigene Bewegungsspur und die
eigenen Vorschläge; auch Administratoren haben darauf keinen Zugriff. Rohe
Standortpunkte werden verschlüsselt gespeichert und nach Ablauf einer
Aufbewahrungsfrist (standardmäßig 90 Tage) automatisch gelöscht. Bestätigte
Zeiteinträge und die daraus abgeleiteten Auswertungen bleiben davon
unberührt — es verschwindet nur die Rohspur, nicht die Arbeitszeit.
