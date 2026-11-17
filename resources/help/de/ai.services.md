---
title: "KI-Dienste"
topic: ai.services
version: 6
keywords:
    - Maskierung
    - AVV
    - Datenschutzfreigabe
    - KI
    - künstliche Intelligenz
    - AI
    - KI-Assistent
    - Sprachmodell
    - LLM
    - Ollama
    - Textvorschläge
    - Positionen übersetzen
    - Texte umformulieren
    - Glossar
    - KI aktivieren
audience: []
modules:
    - module.ai
related:
    - invoices.manage
    - quotes.overview
---

Die KI-Assistenz ist optional und standardmäßig aus. Unter
**Verwaltung → KI-Dienste** verbinden Sie Provider (Cloud oder lokal,
z. B. Ollama), schalten einzelne Capabilities frei und legen je
Capability fest, welche Verbindungen erlaubt sind und welche als
Standard gilt.

**Wer die KI nutzen darf:** Vorschläge, Übersetzungen und Sammelaktionen
stehen nur Personen mit dem Recht „KI-Vorschläge anfordern“ zur
Verfügung. Es steckt in allen Standardrollen für Mitarbeitende — von
Geschäftsführung, Buchhaltung und Personalverwaltung bis Innendienst,
Außendienst und Callcenter —, nicht im Kundenportal und nicht in der
lesenden Support-Rolle. Für eigene Rollen vergeben Sie es unter
**Rollen & Rechte**. Verbindungen, Freigaben und Budget pflegt nur, wer
„KI-Provider-Verbindungen und Capability-Routing verwalten“ hat.

**Datenschutz:** Die Datenfluss-Vorschau zeigt je Capability, welche
Datenklassen an welchen Provider gehen. Cloud-Verbindungen sind bei
hoher Sensibilität und im Branchenprofil ambulante Pflege gesperrt;
Tarife, die Eingaben zum Training nutzen, lassen sich nicht anbinden.
API-Schlüssel werden verschlüsselt gespeichert und nie angezeigt.

**Schlüssel tauschen:** Läuft ein API-Schlüssel ab oder ist er
kompromittiert, öffnen Sie „Verbindung bearbeiten“ und tragen ihn
unter „Neuer API-Schlüssel“ ein. Leer gelassen bleibt der bisherige
Schlüssel. Der neue Schlüssel wird sofort geprüft; die Verbindung bleibt
bestehen, und die Zuordnung als Standard je Capability geht nicht verloren.

**KI-Gedächtnis:** Glossarbegriffe, Stilregeln und Beispielpaare je
Organisation, Kunde oder Capability verbessern die Vorschläge — ohne
Training fremder Modelle. Gelernt wird nur nach Ihrer Bestätigung
(„Merken?"-Dialog).

**Leistungstexte:** Im Rechnungs- und Angebotsentwurf erzeugt die
KI Textvorschläge je Position (auch übersetzt). Übernommen wird erst
per Klick — Mengen, Preise und Steuer bleiben unberührt.

**Sammelaktionen:** An einer Rechnung im Entwurf übersetzt „Alle übersetzen“
jede Position, und im Protokoll formuliert „Alle Punkte veredeln“ jeden Punkt
mit Text neu. Beides läuft im Hintergrund. Jede Position und jeder Punkt
erhält einen eigenen Vorschlag, den Sie einzeln übernehmen oder verwerfen;
nichts wird automatisch übernommen.

**Verbindung wählen:** Erlaubt Ihre Organisation für einen Dienst „Nutzer
dürfen wählen“ und sind mehrere Verbindungen zugelassen, zeigt der Vorschlag
eine Auswahl. „Neu erzeugen“ erstellt den Vorschlag mit der gewählten
Verbindung; der bisherige offene Vorschlag wird ersetzt. Die Wahl steht im
Audit-Protokoll.

**Verbindungswahl:** Erlaubt die Organisation für einen Dienst „Nutzer dürfen wählen“, lässt sich ein Vorschlag auch an der Übergabe (Positionstexte), in der Tätigkeitsrecherche der Suche und bei „Plan-Ist erklären“ mit einer anderen zugelassenen Verbindung neu erzeugen.

**Monatsbudget:** Auf der Seite **KI-Verbrauch** legt „Monatsbudget“ je Familie
eine Obergrenze je Kalendermonat fest – für Sprachmodelle in Token, für
Übersetzungen in Zeichen. Ist sie erreicht, lehnt workDiary weitere Aufrufe
dieser Familie bis zum Monatsende mit einer Meldung ab; die Kacheln zeigen die
Auslastung. Leer gilt die Vorgabe des Betreibers, ohne Vorgabe ist der Verbrauch
unbegrenzt.

**Maskierung:** Vor jeder Cloud-Verbindung ersetzt workDiary IBAN,
USt-IdNr., Steuernummer, Steuer-Identifikationsnummer, E-Mail-Adressen und
Telefonnummern durch Platzhalter wie `[IBAN-1]` oder `[EMAIL-2]`. Derselbe Wert
erhält innerhalb einer Anfrage immer denselben Platzhalter. In Entwürfen,
Zusammenfassungen, Übersetzungen und erkannten Feldwerten setzt workDiary die
Werte vor der Anzeige wieder ein; der Anbieter sieht sie nie. Erkannt wird nur,
was die Prüfung besteht (Prüfziffer bei IBAN, USt-IdNr. und Steuer-ID) –
Beträge und Datumsangaben bleiben unberührt. Kundennamen aus dem eigenen
Stamm werden wie bisher immer maskiert. Lokale Verbindungen sehen die Werte im
Klartext, solange unter **Datenschutz** „Auch vor lokalen Verbindungen
maskieren“ aus ist. Das Audit-Protokoll zählt die maskierten Werte je Art,
ohne sie zu nennen.

**Datenschutzfreigabe:** Je Verbindung hält „Datenschutzfreigabe“ fest, dass
ein Auftragsverarbeitungsvertrag vorliegt (bei Cloud-Diensten mit Dokument),
die Verarbeitung in der EU stattfindet und der Anbieter nicht mit Ihren Daten
trainiert – samt Datum und prüfender Person. Bei einer lokalen Verbindung ist
der Vertrag nur nötig, wenn ein Dienstleister das Modell betreibt. Die
Freigabe gilt so viele Monate, wie unter **Datenschutz** eingestellt (Vorgabe
12); 30 Tage vor dem Ablauf erinnert der Arbeitsvorrat. Freigabe und Widerruf
stehen im Audit-Protokoll. Freigaben erteilt, wer das Recht
„Datenschutzfreigabe von KI-Verbindungen erteilen und widerrufen“ hat.

**Nur freigegebene Verbindungen:** Ist dieser Schalter unter **Datenschutz**
an, nutzt keine KI-Funktion eine Verbindung ohne gültige Freigabe; der
Prüflauf und die Fehlermeldung nennen den Grund. Neue Organisationen starten
mit eingeschaltetem Schalter. In Organisationen, die es schon vor dieser
Version gab, bleibt er aus, bis ein Admin ihn einschaltet – bis dahin stehen die
offenen Freigaben als Aufgabe im Arbeitsvorrat.
