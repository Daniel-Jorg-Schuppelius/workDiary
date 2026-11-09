---
title: "KI-Dienste"
topic: ai.services
version: 3
keywords:
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
