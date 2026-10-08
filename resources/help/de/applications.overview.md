---
title: "Bewerbungen & Ausschreibungen"
topic: applications.overview
version: 2
keywords:
    - Recruiting
    - Bewerbermanagement
    - Stellenanzeige
    - Vorstellungsgespräch
    - Talentpool
    - Bewerber absagen
    - Neueinstellung
    - Vergabeverfahren
    - Angebotsabgabe
    - Tender
    - Vertragsverhandlung
    - Go-No-Go-Entscheidung
audience: []
modules:
    - module.applications
related:
    - documents.manage
---

Das Modul führt zwei vorgelagerte Fallakten, bevor operative Aufträge oder
Mitarbeiterdaten entstehen:

**Auftragsbewerbungen (Ausschreibungen):** Akte mit Fristen, Wertpotenzial,
Go-/No-go-Entscheidung, Unterlagen-Checkliste und versionierten
Einreichungspaketen (Snapshot mit SHA-256-Hash). Gewonnene Ausschreibungen
werden kontrolliert in ein Projekt überführt; verlorene bleiben mit
Verlustgrund auswertbar.

**Personalbewerbungen:** Stellenbedarf → Veröffentlichung → Bewerbungsakte
mit Gesprächen, Bewertungen und Entscheidung. Bewerberdaten sind
verschlüsselt gespeichert und nur für den Personalbereich (recruiting-Rechte)
sichtbar. Absagen starten automatisch die Löschvormerkung (Standard sechs
Monate nach AGG-/Klagefrist, konfigurierbar); der Talentpool verlangt eine
ausdrückliche, befristete Einwilligung. Zusagen erzeugen einen
Mitarbeiter-Entwurf — ein Live-Konto entsteht erst durch die bewusste
Einladung. Eine Entscheidung ist endgültig: Gespräche, Terminangebote
und weitere Entscheidungen sind danach gesperrt. Veröffentlichte Anzeigen
werden nach Ablaufdatum oder Bewerbungsschluss täglich auf „Abgelaufen“
gesetzt; sie lassen sich mit neuem Datum erneut veröffentlichen oder
schließen.

Mit der Entscheidung enden offene Gespräche und Terminangebote: geplante
Gespräche werden als storniert markiert (die Notiz bleibt), noch nicht
gewählte Terminlinks verfallen sofort. Einzige Ausnahme ist der Talentpool:
Solange die Einwilligung gilt, setzt „Aus dem Talentpool aufnehmen“ die Akte
wieder an den Anfang der Pipeline; Löschvormerkung und Einwilligung werden
dabei entfernt, die Löschfrist entsteht mit der nächsten Entscheidung neu.
Ohne gültige Einwilligung bleibt die Akte im Talentpool, bis die
Löschvormerkung greift.
**Vertragsverhandlungen:** eigener, versionierter Schritt zwischen Gewinn-
bzw. Zusageentscheidung und Übergabe. Offene Blocker-Punkte und fehlende
Freigaben (kaufmännisch + fachlich, Selbstfreigabe gesperrt) verhindern den
Abschluss. Eine Freigabe gilt der Version, die vorlag: Wird nach einer
erteilten Freigabestufe eine neue Version abgelegt, beginnt die Freigabe mit
einer neuen Runde von vorn; die frühere Runde bleibt in der Akte als Historie
sichtbar.

Die Freigabestufen erscheinen zusätzlich unter „Genehmigungen“ bei der
Rolle, die die Organisation der Stufenart zuordnet — Vorgabe: kaufmännisch
→ Buchhaltung, fachlich → Teamleitung, HR → Personalverwaltung; änderbar
beim Bearbeiten der Organisation im Abschnitt „Genehmigungen“. Eine
Entscheidung dort wirkt wie die Freigabe an der Akte; dort lässt sich eine
Stufe auch ablehnen (mit Begründung) — eine neue Version startet dann die
nächste Runde.

Rechtlicher Hinweis: WorkDiary dokumentiert den Prozess, ersetzt aber keine
Rechtsberatung — insbesondere keine Bewertung, ob Vertragsbedingungen
zulässig oder wirtschaftlich sinnvoll sind.
