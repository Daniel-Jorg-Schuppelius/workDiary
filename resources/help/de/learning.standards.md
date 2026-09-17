---
title: "Lernstandards: SCORM, cmi5 und LTI"
topic: learning.standards
version: 1
audience: []
related:
    - learning.overview
    - training.overview
    - admin.integrations
---

Neben eigenen Lerneinheiten versteht die Lernplattform die verbreiteten
Austauschformate. Damit lassen sich zugekaufte Kurse einspielen und eigene
Kurse in fremden Systemen starten.

**SCORM 1.2 und 2004** — Ein SCORM-Paket ist ein ZIP mit Beschreibungsdatei.
Beim Hochladen wird es geprüft und entpackt; ausführbare Dateien und Pfade,
die aus dem Paket herausführen, werden abgewiesen. Der Kursinhalt läuft auf
einem **eigenen Inhalts-Host**, damit fremder Kurscode nicht im Ursprung der
Anwendung ausgeführt wird. Fortschritt und Abschluss übernimmt die Plattform
aus den Rückmeldungen des Pakets.

**cmi5 und xAPI** — cmi5-Kurse melden ihre Aktivitäten als Statements an das
mitgelieferte Lernprotokoll. Eine Sitzung nimmt Statements nur innerhalb
eines begrenzten Zeitfensters an; danach ist sie geschlossen.

**LTI 1.3** — Die Plattform arbeitet in beide Richtungen: Sie kann fremde
Werkzeuge als Lerneinheit einbinden **und** sich selbst von einem fremden
Lernmanagementsystem starten lassen. Der Start läuft über signierte Token;
die Schlüssel werden regelmäßig gewechselt, alte Schlüssel bleiben für die
Prüfung laufender Sitzungen gültig.

**Grenzen:** Für alle drei Wege gilt die Abschlussregel des Kurses, nicht die
des Pakets. Ein Paket kann einen Abschluss melden — ob er zählt, entscheidet
die Freischaltung im Kurs.
