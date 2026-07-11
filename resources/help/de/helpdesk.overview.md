---
title: "Helpdesk & Service Desk"
topic: helpdesk.overview
version: 1
audience: []
related:
    - open-issues
    - customer-portal.overview
---

Der Helpdesk bündelt Störungen und Serviceanfragen als Tickets — jeweils mit
Nummer, Titel, Priorität, Status, Kunde, optionalem Gerätebezug und
zuständiger Person.

**Queues:** Tickets werden in Queues (Verantwortungsbereichen) geführt,
jeweils mit zuständigem Team und optionalem SLA-Vertrag. Genau eine Queue
ist die Standard-Queue für neu eingehende Tickets; ein Wechsel erfolgt
kontrolliert. Gelöscht werden kann eine Queue nur, wenn ihr keine Tickets
mehr zugeordnet sind — nichts wird still umgehängt.

**Prioritäten & SLA:** Aus dem SLA-Vertrag ergeben sich Reaktions- und
Lösungsfristen je Priorität. Die laufenden Fristen stehen sichtbar am
Ticket; wird eine Frist überschritten, ohne dass die erste Reaktion bzw. die
Lösung rechtzeitig erfolgte, wird das als Verletzung festgehalten und fließt
in die SLA-Auswertung ein.

**Öffentlich vs. intern:** Antworten an den Kunden und interne Notizen sind
zwei getrennte Aktionen mit unterschiedlichen Rechten. Eine öffentliche
Antwort ist kundensichtbar und kann per E-Mail an Empfänger gehen; eine
interne Notiz bleibt ausschließlich im Team. Die Trennung ist technisch
verankert — ein versehentliches Veröffentlichen interner Anmerkungen ist
ausgeschlossen.

**Eingang:** Tickets entstehen manuell, per E-Mail (Antworten auf ein
bestehendes Ticket werden dem Vorgang automatisch zugeordnet), über das
Kundenportal, aus offenen Punkten, aus Wartungsplänen oder über die
Schnittstelle. Die Quelle bleibt am Ticket vermerkt.

**Routing:** Regeln verteilen eingehende Tickets automatisch — etwa in eine
Queue, mit Priorität oder Zuständigkeit — und werden in definierter
Reihenfolge angewendet. Ein Test-Modus prüft eine Regel gegen ein
Beispiel-Ticket und protokolliert das Ergebnis, ohne irgendetwas zu ändern.

**Zufriedenheit & Berichte:** Nach Abschluss kann der Kunde im Portal eine
Kurzbewertung abgeben — eine je Ticket. Die Berichte zeigen Volumen je
Queue, Reaktions- und Lösungszeiten, SLA-Erfüllung, Wartegründe,
Change-Quoten, Problem-Bestand und Katalognachfrage. Auf Ranglisten
einzelner Bearbeiter wird bewusst verzichtet.
