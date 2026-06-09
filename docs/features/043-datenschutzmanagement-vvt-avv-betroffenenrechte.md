# Datenschutzmanagement: VVT, AVV und Betroffenenrechte

## Status

Proposed

## Ziel

WorkDiary soll Organisationen und externen Dienstleistern eine gemeinsame,
mandantensichere Arbeitsoberfläche für operative Datenschutzaufgaben bieten.
Das Modul führt Verarbeitungsverzeichnis, Auftragsverarbeiter, Verträge,
Betroffenenanfragen und Datenschutzvorfälle mit Verantwortlichen, Fristen,
Nachweisen und Audit-Trail zusammen.

Es ersetzt keine Rechtsberatung. Entscheidungen zu Rechtsgrundlagen,
Löschpflichten, Meldepflichten und Datenschutz-Folgenabschätzungen bleiben bei
den jeweils Verantwortlichen und deren Datenschutzbeauftragten.

## Warum

Datenschutz wird in kleinen und mittleren Unternehmen häufig über getrennte
Tabellen, E-Mails, Vertragsordner und Kalenderfristen organisiert. Für
IT-Dienstleister kommt hinzu, dass sie je nach Leistung Verantwortlicher,
Auftragsverarbeiter oder Unterauftragsverarbeiter sein können.

WorkDiary besitzt bereits Mandantentrennung, Rollen, Audit-Protokolle,
Dokumente, Fristen und eine Datenschutz-Adminseite. Ein operatives
Datenschutzmanagement kann diese Grundlagen nutzen und Datenschutzaufgaben
nachweisbar in den Arbeitsalltag integrieren.

## Fachliche Bereiche

### Verzeichnis von Verarbeitungstätigkeiten

- Verarbeitungstätigkeiten mit Zweck, Verantwortungsrolle und Fachbereich.
- Kategorien betroffener Personen und personenbezogener Daten.
- Rechtsgrundlagen und berechtigte Interessen als strukturierte Angaben.
- Empfänger, Auftragsverarbeiter und Drittlandtransfers.
- Lösch- und Aufbewahrungsregeln.
- Technische und organisatorische Maßnahmen (TOM).
- Risikoeinstufung und Kennzeichnung eines möglichen DSFA-Bedarfs.
- Versionierung, Review-Termin, Freigabe und stichtagsbezogener Export.

### AVV-, GVV- und Dienstleisterregister

- Vertragspartner mit Rolle: Verantwortlicher, Auftragsverarbeiter,
  Unterauftragsverarbeiter oder gemeinsam Verantwortlicher.
- AVV/DPA mit Version, Gültigkeit, Status und Dokumentenanhang.
- GVV nach Art. 26 DSGVO mit Zuständigkeitsmatrix für Informationspflichten,
  Betroffenenrechte, Datenschutzvorfälle, Aufsichtsbehörden und
  Ansprechpartner.
- Dokumentation, wie das Wesentliche der GVV betroffenen Personen
  bereitgestellt wird.
- Verknüpfung zu Verarbeitungstätigkeiten und betroffenen Datenkategorien.
- TOM-Prüfung, Audit-/Nachweisstatus und nächster Review.
- Unterauftragsverarbeiter, Verarbeitungsorte und Drittlandmechanismen.
- Kündigung, Datenrückgabe und bestätigte Löschung zum Vertragsende.

### Betroffenenanfragen

- Fälle für Auskunft, Berichtigung, Löschung, Einschränkung,
  Datenübertragbarkeit und Widerspruch.
- Eingangskanal, Identitätsprüfung, zuständige Person und Frist.
- Verknüpfung zu betroffenen Systemen, Datenkategorien und Empfängern.
- Aufgabenliste für Recherche, Prüfung, Freigabe und Antwort.
- Dokumentation von Fristverlängerung, Ablehnung und deren Begründung.
- Sichere Bereitstellung eines Antwortpakets mit protokolliertem Abruf.
- Abschlussnachweis ohne unnötige dauerhafte Speicherung von Ausweiskopien.

### Datenschutzvorfälle

- Getrennter Falltyp für Verlust, Fehlversand, unberechtigten Zugriff,
  Offenlegung, Veränderung oder Nichtverfügbarkeit personenbezogener Daten.
- Zeitpunkt von Ereignis, Entdeckung und interner Meldung.
- Betroffene Daten, Personengruppen, Systeme, Umfang und mögliche Folgen.
- Sofortmaßnahmen, Risikobewertung und dokumentierte Meldeentscheidung.
- Fristen und Nachweise für Aufsichtsbehörde und betroffene Personen.
- Lessons Learned und Folgemaßnahmen.

Datenschutzvorfälle sind nicht mit dem bestehenden Hinweisgebersystem
gleichzusetzen. Ein Hinweis kann einen Datenschutzvorfall auslösen, die
Fallakten und Zugriffsrechte bleiben jedoch getrennt.

## MVP 1: VVT und Betroffenenanfragen

- Mandantensicheres VVT mit Entwurf, Review, Freigabe und Versionierung.
- Vorlagen für typische Prozesse aus IT-Service und Handwerk.
- Register für Betroffenenanfragen mit Fristberechnung und Erinnerungen.
- Rollen `privacy.view`, `privacy.manage`, `privacy.approve` und
  `privacy.requests.manage`.
- Anhänge und interne Notizen mit restriktiven Zugriffsrechten.
- Append-only Audit-Ereignisse für Status-, Frist-, Export- und
  Freigabeänderungen.
- JSON-, CSV- und druckbarer Export des VVT sowie eines einzelnen
  Betroffenenfalls.
- Dashboard-Kacheln für überfällige Reviews und offene Fristen.

## MVP 2: AVV, GVV und Unterauftragsverarbeiter

- Dienstleister-, AVV- und GVV-Register mit Vertragsstatus und Review-Fristen.
- Verknüpfung von AVV, TOM, Unterauftragsverarbeitern und
  Verarbeitungstätigkeiten.
- Zuständigkeitsmatrix für Vereinbarungen gemeinsam Verantwortlicher.
- Nachweisworkflow bei Vertragsende für Datenrückgabe oder Löschung.
- Änderungsübersicht für neue Unterauftragsverarbeiter.

## MVP 3: Datenschutzvorfälle und DSFA

- Datenschutzvorfall-Akte mit zeitkritischem Fristen- und
  Entscheidungsworkflow.
- Vorbereitete, aber nicht automatisch versendete Meldedokumente.
- DSFA-Prüfung und vollständiger DSFA-Workflow für risikoreiche
  Verarbeitungstätigkeiten.
- Maßnahmenverfolgung bis zur wirksamen Erledigung.

## Kernmodell

- `privacy_processing_activities`
- `privacy_processing_activity_versions`
- `privacy_processors`
- `privacy_processing_agreements`
- `privacy_joint_controller_agreements`
- `privacy_subprocessors`
- `privacy_data_subject_requests`
- `privacy_request_events`
- `privacy_incidents`
- `privacy_incident_events`
- `privacy_reviews`

Alle Fachtabellen sind organisationsgebunden. Fallbezogene Inhalte dürfen
nicht über globale Suche, Benachrichtigungstexte oder allgemeine Exporte
ungefiltert offengelegt werden.

## Akzeptanzkriterien

- Eine Organisation kann ihr aktuelles VVT stichtagsbezogen exportieren.
- Jede Verarbeitungstätigkeit hat einen Verantwortlichen und einen
  überprüfbaren Review-Status.
- Eine Betroffenenanfrage zeigt Frist, Bearbeitungsstand, Entscheidungen und
  Nachweise in einer nachvollziehbaren Timeline.
- AVV, GVV und Unterauftragsverarbeiter lassen sich den betroffenen
  Verarbeitungstätigkeiten zuordnen.
- Datenschutzvorfälle und Hinweisgeberfälle verwenden getrennte
  Berechtigungen, Daten und Aufbewahrungsregeln.
- Kein Nutzer kann Datensätze oder Anhänge einer anderen Organisation sehen.
- Sensible Exporte und Abrufe werden im Audit-Protokoll erfasst.

## Abgrenzung

- Keine automatische rechtliche Bewertung oder Garantie der
  DSGVO-Konformität.
- Keine automatische Meldung an Behörden ohne explizite menschliche
  Freigabe.
- Kein öffentliches Betroffenenportal im ersten MVP.
- Keine Ablage von Vertrags- oder Identitätsnachweisen in allgemeinen
  Anhängen ohne passende Zugriffskontrolle.
- Kein Ersatz für das Hinweisgebersystem.

## Abhängigkeiten

- Datenschutz, Sicherheit und Datenlebenszyklus
- Rollen, Rechte und Produktprofile
- Dokumentenmanagement
- Benachrichtigungen und Eskalationen
- Suche, Timeline und Fallakte
- Hinweisgebersystem und anonyme Meldestelle

## Fachliche Referenzen

- DSGVO Art. 12 bis 22: Betroffenenrechte und Bearbeitungsfristen.
- DSGVO Art. 28: Auftragsverarbeitung.
- DSGVO Art. 26: Gemeinsam Verantwortliche.
- DSGVO Art. 30: Verzeichnis von Verarbeitungstätigkeiten.
- DSGVO Art. 33 und 34: Meldung von Datenschutzverletzungen.
- DSGVO Art. 35: Datenschutz-Folgenabschätzung.
- EDPB SME Data Protection Guide:
  <https://www.edpb.europa.eu/sme-data-protection-guide_en>

## GitHub Issues

- TBD
