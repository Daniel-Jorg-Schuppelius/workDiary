# Changelog

Alle nennenswerten Änderungen an WorkDiary werden in dieser Datei dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt [Semantic Versioning](https://semver.org/lang/de/)
(siehe `release-prozess.md` im Doku-Repository WorkDiary-Architecture).

## [Unreleased]

### Added

- Vereinsverwaltung (Feature 159, MVP-842): **Mitglieder, Gruppen und
  Vertretungen** als neues Plan-Modul `module.club`. Mitglieder existieren ohne
  Benutzerkonto mit laufender Mitgliedsnummer je Organisation, Mitgliedschafts-
  verlauf (aktiv/passiv/fördernd/pausiert) und Austritt, der Gruppenzuordnungen
  beendet, aber Nachweise erhält. Abteilungen und Gruppen mit Leitung,
  Obergrenze, Aufnahmemodus und inklusiven Altersgrenzen; Aufnahme prüft Alter am
  Stichtag, Kapazität und Doppelzuordnung, Ausnahmen nur mit Recht und
  Begründung. Ein täglicher Abgleich verwandelt Geburtstage und geänderte
  Grenzen in Wechselvorschläge, die die Leitung mit Wirksamkeitsdatum bestätigt
  oder verwirft — nichts wird automatisch entfernt. Sorgeberechtigte werden
  ausdrücklich mit erlaubten Handlungen zugeordnet und sehen nur ihre Kinder;
  Gruppenleitung sieht nur eigene Gruppen. CSV-Erstimport über die
  Import-Drehscheibe mit Mitgliedsnummer als Abgleichschlüssel. Rechte
  `club.viewAny`, `club.manage`, `club.groups.lead`; Hilfe ×5.
- Vereinsverwaltung (Feature 159, MVP-843): **Vereinstermine mit Anmeldung
  und Warteliste**. Termine des Kalenders tragen Vereinsdetails (Art,
  Sichtbarkeit Verein/Gruppen/Einladung, Zielgruppen, Anmelde- und
  Abmeldeschluss in Stunden vor Beginn); Mitglieder ohne Login werden von der
  Verwaltung oder Gruppenleitung angemeldet, eingeladen oder spontan ergänzt.
  Die Soll-Liste zählt jedes Mitglied einmal, auch bei mehreren Zielgruppen.
  Kapazität und Warteliste teilen sich Benutzer- und Mitgliedsteilnahmen über
  einen gemeinsamen Platzdienst (aus der Lernplattform herausgelöst, kein
  zweiter Algorithmus); ein frei werdender Platz rückt an die am längsten
  wartende Person. Serien erzeugen eigene Termine mit eigener Liste,
  Änderungen und Absagen gelten wahlweise ab dem gewählten Termin;
  Verschiebung und Absage löschen keine Anmeldung. Hilfe ×5.
- Vereinsverwaltung (Feature 159, MVP-844): **Bestätigte Anwesenheit je
  Vereinstermin**. Eine Liste je Termin mit Soll-Liste (Zielgruppen,
  Angemeldete, spontan Ergänzte, jedes Mitglied einmal) und Stand je Person:
  anwesend, teilweise, entschuldigt, abwesend — nicht bearbeitet bleibt offen
  und zählt nicht. Anwesend übernimmt die durchgeführte Dauer ohne Pausen
  (kürzbar), teilweise über Minuten oder Ankunft/Abgang; ganze Minuten, nie
  über der durchgeführten Dauer; mehrtägige Lehrgänge tragen ihre Blöcke.
  Trainingszeit entsteht erst mit der Bestätigung, die den Stand als Version
  einfriert; Korrekturen danach brauchen Grund und werden mit Akteur und
  Zeitpunkt festgehalten. Ein Sperrzähler weist veraltete Formulare ab statt
  still zu überschreiben. Überlappende bestätigte Nachweise desselben
  Mitglieds werden markiert und bis zur Klärung nicht angerechnet. Nachweis-
  liste mit CSV-Export für den Kopfzeilen-Zeitraum; keine Buchung in
  Arbeitszeitkonten. Gruppenleitung erfasst ihre Gruppen, Verwaltung alle.
  Hilfe ×5.
- Vereinsverwaltung (Feature 159, MVP-845): **„Mein Verein“ und Nachrichten
  an Mitglieder**. Verknüpfte Mitglieder und eingetragene Vertretungen
  sehen — ohne Verwaltungsrecht — die Termine, für die sie anmeldeberechtigt
  sind (Termine der eigenen Gruppen ohne persönliche Einladung), melden sich
  an oder ab, sehen Warteliste, Fristen, Änderungen und ihre bestätigte
  Trainingszeit im Kopfzeilen-Zeitraum; Vertretungen wählen das betreute
  Mitglied ausdrücklich, ein Widerruf beendet den Zugriff sofort. Keine
  fremden Mitglieder, Kontaktdaten oder Fehlzeiten. Neue
  Benachrichtigungsereignisse für Erinnerung (24 h vorher), Verschiebung,
  Absage und Nachrücken gehen an das Benutzerkonto nach den Regeln der
  Organisation, sonst an die Mailadresse des Mitglieds, dazu an
  Vertretungen mit Recht „Nachrichten erhalten“. Ein Zustellprotokoll
  verhindert Doppelmeldungen bei Wiederholung und zeigt Zustellfehler am
  Termin; einen Gelesen-Status gibt es bewusst nicht. Hilfe ×5.
- Vereinsverwaltung (Feature 159, MVP-846): **Optionales Graduierungsmodul**
  (Vereinseinstellung, Recht `club.grading.manage`). Je Disziplin eine
  Ordnung mit benannten, geordneten Graden und versionierten Voraussetzungen
  als UND-Liste: Vorgrad, Mindestanwesenheit in Minuten oder Terminen,
  Zählzeitraum (seit Vorgrad, seit Eintritt, festes Fenster), Wartezeit in
  Kalendermonaten ohne Überlauf, Mindestalter am Prüfungstag, Pflichtlehrgang
  als bestätigter Nachweis, fachliche Freigabe. Die Zulassungsprüfung zählt
  nur bestätigte Anwesenheit vor Prüfungsbeginn in der passenden Disziplin
  und zeigt erfüllte und fehlende Punkte samt Fortschritt („18 Stunden
  45 Minuten von 20 Stunden; noch 1 Stunde 15 Minuten“), unabhängig vom
  Zeitraum der Kopfzeile. Anerkennung mitgebrachter Grade mit Datum und Beleg,
  Widerruf als begründeter Vorgang, Lehrgangs- und externe Trainingsnachweise
  (letztere nur, wenn die Regelversion sie erlaubt). Gruppen können Mindest-
  und Höchstgrad einer Ordnung verlangen; ohne gültigen Grad kein Zugang, ein
  neuer Grad erzeugt einen Wechselvorschlag statt einer automatischen
  Entfernung. Keine Verbandsregeln im Code; Hilfe ×5.
- Vereinsverwaltung (Feature 159, MVP-847): **Prüfungen und Gradvergabe**.
  Prüfungsangebote sind Termine der Art „Prüfung“ mit Ordnung, Zielgraden,
  Prüfern und der beim Anlegen eingefrorenen Regelversion. Kandidaten legt die
  Leitung an oder Mitglieder fragen sie aus „Mein Verein“ an: erfüllte
  Voraussetzungen führen zur Zulassung mit Platz, fehlende bleiben Anfrage;
  fachliche Freigabe und begründete Ausnahmezulassung (nur wenn die Regel sie
  erlaubt) sind eigene Schritte, Zulassung und Platz getrennte Angaben. Am
  Prüfungstag wird neu geprüft; Verschiebung oder Korrektur eines verwendeten
  Nachweises markiert Kandidaten zur Überprüfung, entfernt aber niemanden.
  Prüfer erfassen bestanden, nicht bestanden oder nicht angetreten — nur
  „bestanden“ vergibt den Zielgrad, genau einmal und mit Verweis auf die
  Prüfung; Fehlversuche ändern weder Grad noch Trainingszeit.
  Graduierungsbescheinigung als PDF je gültigem Grad, Widerruf sichtbar
  gedruckt. Recht `club.exams.examine`; Hilfe ×5.
- Vereinsverwaltung (Feature 159, MVP-849): **Beitragstarife und
  Beitragskonten** (Recht `club.fees.manage`, Rolle Buchhaltung). Frei
  benennbare Tarife (Einzel- oder Familienbeitrag, optionale Altersgrenzen)
  mit Sätzen je Gültigkeitsdatum: Rhythmus, Betrag, Abrechnungsanker,
  Fälligkeit, Anteilsregel (volle Periode oder taggenau) und Aufnahmegebühr;
  Abteilungszuschläge als eigene Positionen. Beitragskonten sind
  Zahlungspflichtige im Kunden-/Debitorenstamm (bestehender Kunde oder neuer
  Debitor), Mitglieder werden ausdrücklich mit Zeitraum, Tarif und optionalem
  Nachlass zugeordnet — nie automatisch anhand E-Mail oder IBAN. Familientarif
  ergibt genau eine Grundbeitragsposition je Konto und Periode. Befreiungen
  und Ermäßigungen mit Zeitraum und Grund; eine Mitgliedschaftspause allein
  erlässt nichts. Altersgrenzen erzeugen einen Wechselvorschlag, den die
  Beitragsverwaltung mit Wirksamkeitsdatum bestätigt. Beitragsvorschau je
  Abrechnungsmonat mit Berechnungsgrund und sichtbaren Fehlern; Forderungen
  entstehen erst mit dem Beitragslauf (MVP-850). Keine Vereinssätze im Code;
  Hilfe ×5.
- Vereinsverwaltung (Feature 159, MVP-855): **Individual-, Wettkampf- und
  Schießsport**. Wettkämpfe als Vereinstermin mit Disziplinen des
  Sportartenprofils (Einheit und Vergleichsrichtung), Ort, Ausrichter,
  Meldeschluss und optionaler Meldegebühr, die je Disziplin als eigene
  Beitragsposition im Abrechnungsmonat entsteht. Meldungen je Disziplin
  (Leitung oder Mitglied im Portal): ohne gültiges Startrecht „zur Klärung“
  statt Ablehnung; Klärung mit Vermerk, Rückzug ohne Gebühr. Startrecht als
  dokumentierte Prüfung mit Gültigkeit, je Sportart oder für alle; kein
  Verbandsabgleich. Leistungen manuell mit Wert, Platzierung und Bezug zu
  Training/Wettkampf, Einheit und Richtung eingefroren; Bestätigung als
  eigener Schritt, Korrektur protokolliert und hebt sie auf; Bestleistung je
  Disziplin nur aus bestätigten Werten — kleinere Zeit bzw. größere Weite
  gewinnt je Disziplin. Nachweisliste aus bestätigten Anwesenheiten gegen vom
  Verein konfigurierte Anforderungen (Anzahl je Zeitraum, Gruppe/Abteilung,
  Terminart) mit CSV-Export — keine gesetzlichen Schwellen, keine
  Waffenverwaltung. Standaufsicht als Pflichtrolle bei Schießsport-Terminen
  (Hinweis). Hilfe ×5.
- Vereinsverwaltung (Feature 159, MVP-854): **Reitbetrieb**. Schlankes
  Pferdeprofil (Schul-/Privatpferd mit Besitzer, Kontakt, Reitgruppen,
  Eignung, Einsatzgrenze je Tag, Ruhepuffer) auf einer Ressource der Art
  „Pferd“ — Belegung, Ruhepuffer, Sperrzeiten und Eignungsfreigaben laufen
  über die Sportstätten. Zuordnung Reiter–Pferd je Reitstunde durch die
  Leitung (eigenes Pferd ausdrücklich, Privatpferd nur für die Besitzerin
  bzw. den Besitzer): ein Pferd ist nie zeitgleich doppelt vergeben, gesperrte
  Pferde sind nicht zuteilbar; fehlende Eignungsfreigabe und Einsatzgrenze
  lassen sich nur ausdrücklich mit Begründung übergehen (protokolliert). Der
  Ausfall eines Pferdes markiert betroffene Stunden zur Neuplanung und
  benachrichtigt die Reitleitung; keine automatische Ersatzzuteilung.
  Pferdeeinsatz in Minuten je Pferd, getrennt von der Reiteranwesenheit — ein
  Pferdewechsel verdoppelt keine Trainingszeit. Portal zeigt das zugeordnete
  Pferd. Hilfe ×5.
- Vereinsverwaltung (Feature 159, MVP-853): **Sportstätten und Ressourcen**.
  Hallen, Teilflächen (Hälfte, Drittel), Tische, Plätze, Bahnen/Stände,
  Boote und Geräte als Ressourcenbaum mit gemeinsamer Konfliktprüfung: Die
  Belegung der ganzen Halle sperrt alle Teilflächen und Tische darunter,
  freie Teilflächen sind parallel nutzbar; Ressourcen mit mehreren Einheiten
  (z. B. vier Bahnen) werden mengenweise belegt, Auf-/Abbaupuffer zählen
  mit. Räume werden angebunden (Terminkalender und Ressourcenbelegung teilen
  sich einen Kalender in beide Richtungen), Boote/Geräte an Assets — Sperren
  des gemeinsamen Sperrmodells (neuer Grund „Wartung“) verhindern die
  Belegung. Belegung je Termin/Spieltag prüft Kapazität, Baum, Raumkalender,
  Sperrzeiten und Asset-Sperren in einer Transaktion mit Zeilensperren; zwei
  konkurrierende Buchungen werden nie beide bestätigt. Verschiebung eines
  Termins nimmt Belegungen mit, bei Konflikt bleibt alles beim Alten; Absage
  gibt frei. Sperrzeiten (Witterung, Wartung) markieren bestehende
  Belegungen zur Neuplanung statt sie zu löschen. Einweisungs-/
  Eignungsfreigaben je Mitglied und Ressource, befristbar; fehlende Freigaben
  werden am Termin angezeigt. Hilfe ×5.
- Vereinsverwaltung (Feature 159, MVP-852): **Mannschaften und Spielbetrieb**.
  Eine Sportart ist ein Sportartenprofil (Konfiguration statt Sonderfall):
  Sportfamilie, Positionen, Kadergrößen Feld/Bank, Ergebnisformat (Tore,
  Punkte je Abschnitt, Sätze), Einzel/Doppel, Stichtag der Altersklasse,
  Disziplinen und Ressourcentypen. Gruppen werden zur Mannschaft mit Profil
  (eigenes oder das der Abteilung) und Altersklasse; Alterskriterien gelten
  am Stichtag innerhalb der Saison. Saisons und Saisonkader je Mannschaft
  mit Gültigkeit, Trikot, Position und Spielstärke-Rang; Gastspieler eines
  Partnervereins als Mitglied der Art „Gast“ mit Herkunft — ohne Beitrag,
  Login oder Gruppenmitgliedschaft; Vorsaisons bleiben erhalten. Spieltage
  als Vereinstermin mit Gegner, Wettbewerb, Heim/Auswärts, Spielort und
  Treffzeit. Verfügbarkeit (Zusage ist keine Nominierung), Aufstellung im
  Format des Profils mit Prüfung von Kadergrößen, Positionen, Trikots und
  Paarungen — eine Person in Einzel und Doppel bleibt eine Person —,
  Konfliktprüfung vor der Freigabe (zeitgleicher Einsatz in anderer
  Mannschaft, Absage) mit begründetem Übergehen; Nominierte werden
  Teilnehmer, Anwesenheit bleibt separat. Terminrollen (Schiedsrichter,
  Zeitnehmer, Fahrdienst, Standaufsicht …) zählen als Teilnahme, nicht als
  Kaderplatz. Ergebnis manuell im Profilformat mit Protokoll. Spielplan-
  Import aus CSV/ICS als Vorschlagsliste: vor der Übernahme entsteht nichts,
  bekannte Zeilen werden übersprungen, Dubletten zu bestehenden Spieltagen
  markiert. „Mein Verein“ mit Zusage/Absage und eigener Nominierung.
  Hilfe ×5.
- Vereinsverwaltung (Feature 159, MVP-851): **Zahlungen und Einzug**.
  Zahlungen sind eigene Buchungen je Beitragskonto (Überweisung, Bar, SEPA,
  Sonstiges); ohne Forderung werden sie der Fälligkeit nach zugeordnet
  (Familien-Sammelzahlung), ein Rest wird Guthaben und lässt sich verrechnen.
  Der Bankabgleich kennt Beitragsforderungen als Zieltyp und erkennt eine
  bereits gebuchte Zahlung gleicher Höhe wieder, statt sie doppelt
  anzurechnen. Rücklastschriften kompensieren genau eine Zahlung, öffnen den
  Restbetrag und sperren den erneuten Einzug bis zur Freigabe; Bankgebühren
  werden Nachforderungen. Dreistufige Mahnung (Erinnerung, Mahnung, letzte
  Mahnung) mit je gesetztem Zahlungsziel und Gebühr als Nachforderung, PDF und
  Mail mit Zustellnachweis, Mahnsperre für strittige Forderungen. SEPA-Einzug
  als Sammellauf über die vorhandenen Zahlungsläufe: Vorschlag mit Mandat,
  Reservierung je Forderung mit eindeutiger Versuchsreferenz, Freigabe und
  Export im Finanzmodul; der Export ist keine Zahlung, erst der gebuchte
  Eingang. „Mein Verein“ zeigt zahlungspflichtigen Personen ihre
  Beitragsmitteilungen, Zahlungen und offenen Beträge; Vertretungsrechte
  reichen dafür nicht. Hilfe ×5.
- Vereinsverwaltung (Feature 159, MVP-850): **Beitragslauf, Forderungen und
  Beitragsmitteilung**. Ein Lauf friert die Vorschau eines Abrechnungsmonats
  ein; Fehler sperren die Freigabe. Die Freigabe erzeugt je Beitragskonto
  genau eine Forderung mit Positionen — Wiederholung, Nachholung und
  paralleler Lauf erzeugen keine Doppelung, weil jede fachliche Quelle und
  Periode nur einmal beansprucht werden kann (Unique-Schlüssel); spätere
  Tarif- oder Familienwechsel verändern freigegebene Beträge nicht. Bei
  extern geführter Abrechnung bleiben Vorschau und Übergabeliste (CSV)
  möglich, die lokale Freigabe ist gesperrt. Beitragsmitteilung als PDF
  (neue Dokumentart mit Fallback auf das Rechnungsdesign, Bankblock,
  Zahlungsreferenz, konfigurierbarer Fußtext) mit E-Mail-Versand und
  Zustellnachweis, getrennt von der Freigabe. Offene Posten mit
  Überfälligkeit, Storno unbezahlter Forderungen mit Grund (Periode wird
  frei) und verknüpfte Korrekturen statt Überschreiben. Hilfe ×5.
- Kunden-Sonderkonditionen (Feature 098): **Monatsdetail in der Verwaltung**.
  Der Monat im Abrechnungspanel ist jetzt verlinkt und zeigt dieselben Zeilen
  wie Kundenportal und PDF-Nachweis (Datum, Tätigkeit, Von/Bis, Dauer,
  Anfahrt, Satz, Betrag) plus Tätigkeits-Summen und die Zahlungen des Monats —
  damit ist ohne Umweg über das Portal prüfbar, wie einzelne Zeiten bewertet
  wurden. Zugriff wie beim Panel über das Bearbeiten-Recht am Kunden.
- Kunden-Sonderkonditionen (Feature 098): **Anfahrtspauschale je Zeiteintrag**.
  In der Konditionsmaske lassen sich x Minuten Anfahrt hinterlegen, wahlweise
  nur für ausgewählte Tätigkeiten; sie werden mit dem Satz des Eintrags
  bewertet (Werktag/Wochenende gelten also automatisch mit). Die **erfasste
  Arbeitszeit bleibt unverändert** — die Anfahrt ist eine Preisregel und
  erhöht nur den Erlös, nicht Arbeitszeitkonto, Gleitzeit oder interne
  Kosten; es entsteht bewusst kein fiktiver Zeiteintrag. Kontoauszug, PDF-
  Nachweis und Kundenportal weisen sie in einer eigenen Spalte aus, die
  Rechnungsstellung zählt sie in die abgerechnete Menge (Menge × Satz bleibt
  der Betrag). Keine Anfahrt bei Fahrt-/Bereitschaftszeiten, Festpreis-
  Einträgen oder nicht abrechenbaren Zeiten; im Zeiterfassungs-Dialog je
  Eintrag übersteuerbar (auch auf 0). Neu ist außerdem ein Schalter
  **„Feiertage wie Wochenende abrechnen"** (Standard aus, damit sich
  Bestandsdaten nicht rückwirkend ändern) auf Basis des vorhandenen
  Feiertagskalenders der Organisation.

- DATEV-/Finanzschnittstelle (Feature 045) – Härtung & Nachweis: **Write→Read-
  Validierung** des DATEV-Buchungsstapels (die erzeugte EXTF-V700-CSV wird mit
  `php-financial-formats` über einen unabhängigen Codepfad wieder eingelesen;
  Formaterkennung, Version und Buchungszeilen-Anzahl werden geprüft —
  `finalize()` bricht bei Abweichung ab und legt keine Datei an, Format/Version
  landen im revisionssicheren `finalized`-Event). **Materialpositions-Snapshot**
  vollständig (`billing_transfer_items.unit/unit_price/tax_rate/cost_position`,
  Teil des Payload-Hashs, in der Übergabe-Vorschau sichtbar). DATEV-Vorschau
  zeigt **Formatversion + Roundtrip-Badge** sowie einen Hinweis auf
  **abgeleitete/vereinfachte Felder**. **Capability-Matrix** der
  Import-/Exportformate dokumentiert (inhaltsbasierte Erkennung, nicht per
  Dateiendung). Desktop-API-gebundene Punkte (Rechnungsnummer-Sync, Zugangsdaten-
  Hygiene, vollständiges Mandant-/Auftrag-Mapping) bleiben bewusst offen.
- Kundenportal & Freigaben (Feature 012): **Freigabe/Ablehnung mit offenen
  Punkten** und **Rückfrage-Funktion** für Kunden — additiv auf dem bestehenden
  Protokoll-Signaturlink (`ProtocolSignatureToken` / `PublicProtocolSignatureController`)
  aufgesetzt, **ohne** den Auth-/2FA-Teil des Portals zu berühren. Der Kunde
  kann ein vorgelegtes Protokoll/Abnahme über den (zeitlich begrenzten,
  einmalig nutzbaren) Token **freigeben** (Unterschrift wie bisher) **oder
  ablehnen**: Die Ablehnung verlangt eine **Pflicht-Begründung** und erfasst je
  gemeldetem Mangel einen **Offenen Punkt** (`OpenIssue`, neue Quelle
  `customerRejection`, Sichtbarkeit `customer`, am betroffenen Auftrag/Protokoll
  mit `source_ref_id`); Freigabe wie Ablehnung werden revisionssicher als
  `ProtocolEvent` (`signatureRejected`) mit Entscheidung, Zeitstempel, Token und
  IP protokolliert (neue Spalten `protocol_signature_tokens.decision/
  decision_reason/decided_at`). Neue **Kunden-Rückfrage** (`customer_queries`,
  polymorphes Subjekt, Frage/Antwort/Status): der Kunde stellt über denselben
  Link eine Freitext-Frage, die Org wird über das neue Ereignis
  `customer.queryRaised` (Default an Teamleitung) benachrichtigt, beantwortet
  sie intern auf der neuen Seite **Kunden-Rückfragen** (Permission
  `protocol.customerQuery.manage`, eigene Policy + NavGate-Mapping), und der
  Kunde sieht die Antwort über den Link. Sichtbarkeit strikt am Token-Vorgang
  (kein Zugriff auf fremde/interne Daten, Negativtest); abgelaufene/benutzte
  Tokens werden mit HTTP 410 abgewiesen. Hilfe-Topic `customer.queries` (de/en),
  i18n (de/en/fr/it/es) inkl. öffentlicher Portal-Texte, neue Tests.

- Tarife, Lizenzportal & Abrechnung (Feature 021): **Nutzerlimit-Durchsetzung**
  und **SaaS-Mandantenstatus**. Das in der Lizenz hinterlegte `max_users` wird
  beim Anlegen neuer Mitglieder über die Org-Admin-Oberfläche
  (`OrgMemberController@store`) durchgesetzt — der `LimitGuard` wertet jetzt die
  **org-gebundene** Lizenz (`organizations.license_key`, sonst globale Lizenz)
  aus und zählt die **aktiven Nutzer der Organisation** gegen das Limit; bei
  Erreichen wird die Anlage mit klarer Meldung „Nutzerlimit (X/Y) der aktuellen
  Lizenz erreicht. Bitte Lizenz erweitern." blockiert (HTTP 423 / Flash-Error)
  und ein `limit.exceeded`-Audit-Eintrag geschrieben; unbegrenzte/Enterprise-
  Lizenzen ohne `max_users` werden nicht begrenzt. Neuer **Mandantenstatus**
  (`TenantStatus`: trial/active/suspended/expired) je Organisation: explizit über
  die neue nullable Spalte `organizations.tenant_status` setzbar (Plattform-Admin,
  Permission `platform.license.install`, Audit `tenant.statusChanged`) oder sonst
  aus Testphase (`trial_ends_at`), Aktiv-Flag und Lizenz-Ablauf inkl. Grace-Period
  **abgeleitet** (gültig/in Kulanz/abgelaufen). Bei **gesperrtem oder endgültig
  abgelaufenem** Mandanten sperrt die neue Middleware `EnforceTenantStatus`
  **schreibende** Aktionen (HTTP 423), Lesen sowie Lizenz-/Logout-Routen bleiben
  erreichbar (Sperre aufhebbar) — der Auth-/2FA-/Signatur-Kern bleibt
  unangetastet. Die `admin/license`-Seite zeigt zusätzlich eine
  **Mandantenstatus-Karte** (Status-Badge, Ablauf-Warnung < 30 Tage,
  org-bezogene Nutzungsanzeige X/Y) samt Umschaltung. Hilfe-Topic `admin.license`
  (de/en) ergänzt, i18n (de/en/fr/it/es), neue Tests
  (`UserLimitEnforcementTest`, `TenantStatusTest`). Das **Online-Lizenzportal**
  (externe Selbstausstellung) bleibt bewusst offen.
- Datenschutz, Sicherheit & Datenlebenszyklus (Feature 016): Neue
  **Admin-Sicherheitsseite** (`/admin/security`, Permission `security.view`,
  Admin-only wie `metrics.view`) als **read-only Aggregation**
  sicherheitsrelevanter Zustände — **aktive Sessions** (nur beim
  `database`-Treiber; sonst wird der Treiber ehrlich als „keine Übersicht
  möglich" ausgewiesen; niemals der Session-`payload`), **API-Tokens** (nur
  Metadaten: Name, Abilities, `last_used_at`/`expires_at`/`created_at` —
  **niemals** Token-Wert oder -Hash), **aktive externe Integrationen**
  (aktivierte Plugins + Anzahl externer Referenzen, ohne verschlüsselte
  Plugin-Settings), **letzte Daten-/Zeit-Exporte** (ExportRun/TimeExport, nur
  Metadaten), **letzte Supportzugriffe** (Audit-Log, Ereignis-Präfix
  `support.`) sowie **2FA-Abdeckung** (reine Zählung bestätigter Faktoren) und
  **at-rest-Verschlüsselungs-Status** (APP_KEY-Hinweis + betroffene
  PII-Felder). Mandantentrennung über den vorhandenen `OrganizationScope` bzw.
  explizite Org-User-Filterung; globaler Plattform-Admin sieht plattformweit.
  Neuer `SecurityOverviewService` (read-only), `Admin\SecurityController`,
  Hilfe-Topic `admin.security` (de/en erweitert), i18n (de/en/fr/it/es).
  Die automatisierten **Lösch-/Aufbewahrungsläufe** bleiben bewusst offen
  (Feature 016, „Später").
- Support & Fehlerdiagnose (Feature 041): Der **Supportbericht** wurde um eine
  **Health-Zusammenfassung** und eine **reine JSON-Variante** erweitert. Der
  `SupportReportBuilder` ergänzt nun einen **`release`-Block** (App-Version,
  **Build-Hash**, PHP-/Laravel-/**DB-Version**, aktive Module + Plugins aus dem
  signaturfreien `ReleaseManifestService`-Kern), einen **`health`-Block** aus
  dem vorhandenen Befehl **`system:health --json`** (DB, Migrationen, Storage,
  Queue, APP_KEY, Mail, Lizenz, Backup — über den neuen `SupportHealthSummary`,
  **ohne** den DiagnosticsService zu duplizieren), einen **`plugin_errors`-Block**
  der letzten 7 Tage **(nur Plugin-ID/Phase/Anzahl, keine Meldungen/Payloads)**
  sowie einen **`operations`-Block** (Queue-Stand, letzte Backup-Heartbeats —
  nur Counts/Metadaten). Neue Admin-Aktionen **„Als JSON-Datei herunterladen"**
  (`support-report-{date}.json`) und **„Im Browser anzeigen"** sowie ein
  Artisan-Befehl **`support:report {--output=}`** für CLI/On-Premise. Strikter
  **Whitelist-Ansatz**: der Bericht enthält ausschließlich explizit erlaubte,
  technische Felder — niemals Kundennamen, personenbezogene Daten, Secrets oder
  Klartext-Zugangsdaten (Negativtests sichern Kunden-/`APP_KEY`-Freiheit ab).
  Jede Erzeugung wird im Audit-Log protokolliert. Hilfe-Topic `admin.support`
  (de/en) und i18n (de/en/fr/it/es) ergänzt.
- Demo-, Testdaten & Musterbranchen (Feature 040): Der **`DemoSeederService`**
  erzeugt jetzt je **Musterbranche** ein realistisches **End-to-End-Szenario**
  und setzt dabei auf den bestehenden **`BranchProfileInstaller`** auf (das
  passende Branchenprofil – Klassifikationen, Tags, SLAs, Prozeduren – wird
  mitinstalliert). Neben Kunden, Projekten, Demo-Nutzern und 25 Hintergrund-
  Aufträgen (jetzt in **gemischten Stati**) enthält der Hauptauftrag nun auch
  **Material** (über einen Stundenzettel mit `MaterialUsage`), ein **Asset**,
  ein **signiertes Abnahmeprotokoll** mit Prüfpunkten, einen **offenen Punkt**
  und einen **Kommunikationseintrag** – damit sind Zeiterfassung, Auswertungen,
  Protokolle und Fallakte mit echten Daten erlebbar. Neue **`DemoIndustry`**
  (IT-Service, Elektro, Facility Management) liefert erkennbar unterschiedliche,
  generische Demo-Inhalte (keine echten Firmen/Personen). Neue Artisan-Befehle
  **`demo:seed {org?} --industry=`** und **`demo:reset {org?} --all`** sowie eine
  **Branchen-Auswahl** in der Admin-Aktion „Demo-Mandant". Der **resetbare
  Demo-Modus** ist idempotent und **wasserdicht geschützt**: Reset wirkt
  ausschließlich auf Organisationen mit `is_demo = true` – echte Mandanten
  werden niemals angefasst (Service wirft für nicht-Demo-Orgs eine Ausnahme,
  der Befehl überspringt sie). Der Seeder bindet während des Laufs den
  `currentOrganization`-Kontext korrekt, sodass der Multi-Tenant-Scope auch in
  Konsolen-/Mehr-Org-Läufen sauber greift. i18n (de/en/fr/it/es) ergänzt.
- Import, Migration & Onboarding (Feature 020 / MVP-049): Der bestehende
  **CSV-Import-Wizard** wurde auf **Fahrzeuge** ausgeweitet und das
  **Material**-Importprofil bestätigt/dokumentiert. Beide nutzen exakt das
  vorhandene Muster (Spec-Registry `EntitySpecRegistry`, `CsvPreflightAnalyzer`,
  `ProcessCsvImportJob`, `ImportRun`/`ImportRunError`, Vorschau, Bestätigung,
  Fehler-Download) — keine parallele Import-Mechanik. Die neue `VehicleSpec`
  mappt Kennzeichen, Bezeichnung, Fahrzeugtyp/Antrieb/Eigentum (Enum-validiert),
  Kilometerstand sowie Tank-/Akku-/Verbrauchs-/Kilometersatz-Felder; Idempotenz
  per `(organization_id, license_plate)`, Preflight erkennt fehlende Kennzeichen
  und ungültige Enum-Werte. Neue Permission `vehicle.import` (Admin, in der
  bestehenden Import-Berechtigung). Entitäts-Auswahl des Wizards um „Fahrzeuge"
  ergänzt, i18n (de/en/fr/it/es), `docs/csv-import.md` um Vehicle/Material-Spalten
  aktualisiert. Die **Legacy-Migration** vorhandener WorkDiary-/Tagebuchdaten
  bleibt bewusst ein eigenständiger Folgeschritt.
- Auswertungen & Entscheidungsgrundlagen (Feature 002): **Zielwerte &
  Benchmarks** und **Kohortenvergleich vor/nach Fortbildung** schließen die
  zwei offenen MVP-Punkte. Zielwerte werden in einer neuen Tabelle
  `report_targets` je Kennzahl (Deckungsbeitrags-Marge, abrechenbare Quote,
  Nacharbeitsanteil, SLA-Einhaltungsquote, Auslastung) und Bezugsebene
  (Organisation/Kunde/Projekt/Mitarbeitende, optional mit Gültigkeitszeitraum)
  hinterlegt – Pflege unter **Admin → Zielwerte** (neue Permission
  `report.target.manage`, Geschäftsführung/Admin). Der `ReportTargetEvaluator`
  blendet Soll/Ist samt Ampel-Abweichung **additiv** in bestehende Reports ein:
  Deckungsbeitrags-Marge (org-weit und je Kunde) im **Wirtschaftlichkeits-
  Report** und Einhaltungsquote im **SLA-Report** – ausschließlich gegen bereits
  berechnete Kennzahlen, ohne neue Kennzahlen-Engine. Der neue
  **Kohortenvergleich** (`reports.cohort-comparison`) bildet je Qualifikation
  die Kohorte und vergleicht eine Kennzahl (abrechenbare Quote / Nacharbeits-
  anteil) im gleichlangen Fenster **vor vs. nach dem Erwerbsdatum**, je
  Mitarbeitendem und aggregiert; Datenquelle für das Erwerbsdatum ist
  `user_qualifications.valid_from`, Personen ohne Erwerbsdatum werden ehrlich
  gesondert ausgewiesen. Die Kennzahlen stammen aus denselben TimeEntry-Feldern
  wie die Wirtschaftlichkeitssicht. CSV-Export des Kohortenvergleichs, neues
  Hilfe-Topic `reports.cohort-comparison`, vollständige i18n (de/en/fr/it/es).
- Gewerke- und Branchenprofile (Feature 042, MVP): **Importierbare
  Vorlagenpakete je Gewerk**. Der `BranchProfileInstaller` legt – zusätzlich zu
  den bisherigen Auftragsarten/Kategorien (Klassifikationen), Pflichtregeln,
  Tags, Wartungsplan-, SLA- und Reinigungsprofil-Vorlagen sowie dem
  Softwarekatalog – nun auch **veröffentlichte Checklisten/Prozedurvorlagen**
  (Feature 026, mit Schritten, Zweite-Person- und Nachweispflicht) sowie
  organisationsweite **Raumanforderungs-Vorlagen** (neue Tabelle
  `room_requirement_templates`, Feature 027) an. Sechs Gewerke mit erkennbar
  erweitertem Paket: Elektro (Sicherheitscheck/E-Check), SHK
  (Wartung/Druckprüfung), Gebäudereinigung (QS/Sonderreinigung), Facility
  Management (Objektkontrolle/Schlüssel), IT-Service (Netzwerk-Change/
  Backup-Restore-Test) und GaLaBau (Baumpflege/Abnahme). Der Admin-Katalog
  (`admin.branch-profiles`) zeigt eine **Inhaltsvorschau** je Paket (Anzahl
  Auftragsarten, Kategorien, Pflichtregeln, Checklisten, Raumanforderungen,
  Tags sowie eine Liste enthaltener Auftragsarten und Checklisten) und eine
  **bestätigte** Installations-Aktion. Installation strikt **idempotent**:
  erneutes Installieren erzeugt keine Dubletten, lokal angepasste Org-Daten
  bleiben unberührt und veröffentlichte Checklisten werden nie überschrieben
  (auch nicht bei „Erneut anwenden“). Pakete sind weiterhin rein deklarativ als
  Config (`database/data/branchprofiles/*.php`) hinterlegt; neue Gewerke ohne
  Code-Änderung ergänzbar. Neues Hilfe-Topic `admin.branch-profiles`.
- Produkt-/Objektakte und Lebenszyklus (Feature 027, MVP): **Objektakte /
  Lebenszyklus-Dossier** je Asset (`assets.dossier`) als zusammenhängende,
  druckbare Read-Only-Gesamtsicht – Pendant zur Auftrags-Fallakte
  (`diary/case-file`, Standalone-HTML mit Print-CSS, `?print=1` öffnet den
  Druckdialog). Kopf mit Stammdaten, Standort/Raum, Status/Zustand,
  Inbetriebnahme/Außerbetriebnahme und Garantie; darunter Wartungen,
  Ausgaben/Rückgaben, Defekte/Sperren, Aufträge, Protokolle, Materialeinsatz,
  offene Punkte, Anhänge und die vollständige Lebenszyklus-Timeline. Wiederverwendet
  den bestehenden `AssetTimelineService` (additiv um Ausgabe/Rückgabe, Defekte und
  durchgeführte Wartungen erweitert) sowie die Feature-009-Modelle
  `AssetAssignment`/`AssetDefect`/`MaintenancePlan` – keine parallele
  Timeline-Mechanik. Neuer `AssetLifecycleService` leitet den **Lebenszyklus-Status**
  (in Betrieb / ersetzt / stillgelegt) aus `status` + `decommissioned_on` ab (keine
  neue Statusmaschine) und zeigt ihn im Kopf der Asset-Seite und der Objektakte.
  **Raumbezogene Anforderungen** je Gewerk über eigene 1:n-Tabelle
  `room_requirements` (kind/level/note: Hygienestufe, Sonderreinigung,
  Zugangsbeschränkung, IT-Inventar, technische Prüfung, Betreiberpflicht) –
  ergänzend zum Reinigungsprofil, gepflegt im Raum-Dialog
  (`rooms.requirements.*`, abgesichert über die bestehende Raum-Permission),
  sichtbar in Raumliste, Asset-Detailseite und Objektakte. Objektakte =
  `asset.view`; Hilfetopics `assets.fleet` und `facilities.manage` erweitert.

- Externe Beteiligte, Subunternehmer und Prüfer (Feature 033, MVP):
  **kontextbezogene, befristete externe Einladungen** zu Auftrag, Protokoll oder
  Dokument (`external_participants`, morphes Subject). Pro Einladung ein
  **login-freier, tokenisierter Zugang** (`/extern/{token}`, gedrosselt) auf eine
  schlanke, datensparsame Read-Only-Seite des Subjects mit nur den per
  **`abilities`** (ansehen | kommentieren | hochladen | bestätigen) erlaubten
  Aktionen. Token-Muster strikt analog `ProtocolSignatureToken` /
  `IsmsAuditPackageToken`: es wird nur der **SHA-256-Hash** gespeichert, der
  Klartext-Link wird genau **einmal** angezeigt; abgelaufene, widerrufene oder
  unbekannte Tokens antworten einheitlich 404. Die `abilities` werden
  **serverseitig je Aktion streng durchgesetzt** (view-only ⇒ 403 bei
  Upload/Bestätigung). **Jede externe Aktion** (Zugriff, Kommentar, Upload,
  Bestätigung) wird append-only in `external_participant_events` nachgewiesen
  (Akteur = externer Name/Token, kein interner User). Interne Verwaltung über das
  Panel „Externe Beteiligte" auf der Auftragsdetailseite (Einladen via Modal,
  Einmal-Link, Statusliste, Widerruf); neue Permission
  `externalParticipant.manage` (admin, teamleitung sowie der
  Auftragsverantwortliche über die Subject-Update-Policy). Plattform-Hilfetopic
  `external.participants`.

- ISMS / ISO 27001 (Feature 044, MVP 2/3): **Lieferantenbewertung**
  (`isms.suppliers`, Tabelle `isms_supplier_assessments`) – Kritikalitäts- und
  Risikoeinstufung von Lieferanten, geforderte Sicherheitsanforderungen,
  Vertragsmerkmale (Geheimhaltung/AVV/Prüfungsrecht), wiederkehrende Reviews und
  eine Statusmaschine (Entwurf → bewertet → freigegeben bzw. auffällig). Der
  Lieferantenbezug ist optional (loser FK auf das bestehende Lieferanten-
  Stammdatenmodell oder Freitext-Name); der AVV-Bezug zum Datenschutzmanagement
  bleibt bewusst lose (Flag + Freitext, kein FK). Überfällige Reviews speisen die
  Dashboard-Kennzahl „ungeprüfte Lieferanten" und werden über den Fristen-Scanner
  (`isms.supplierReviewOverdue`) gemeldet/eskaliert. Berechtigungen über die
  bestehenden `isms.*`-Rechte; Plan-Gating `module.isms`.
- ISMS / ISO 27001 (Feature 044, MVP 3): **Reifegrad-/Readiness-Assessment**
  (`isms.readiness`) – begründete Selbsteinschätzung der internen Auditbereitschaft
  je Geltungsbereich. Leitet aus den vorhandenen Registern (SoA-Abdeckung, offene
  hohe Risiken, überfällige/unbewertete Reviews, Nachweislücken, offene
  Nichtkonformitäten/überfällige Korrekturmaßnahmen, kritische Vorfälle/ausnutzbare
  Schwachstellen, ungeprüfte Lieferanten) einen Reifegrad je Domäne (Ampel +
  Score) und daraus eine Gesamteinschätzung „intern auditbereit?" mit Begründung
  ab. Ausdrücklich eine Selbsteinschätzung/Empfehlung – nie eine automatische
  Konformitätsbehauptung oder „zertifiziert" (prominenter Disclaimer).
- Compliance/Audit (Feature 006, MVP): **ArbZG-Compliance-Auswertung auf der
  Ist-Arbeitszeit** (`reports.arbzg-compliance`). Prüft je Mitarbeiter und Tag die
  tatsächlich erfasste Arbeitszeit (Attendance, netto nach Pausen) gegen die
  ArbZG-Schwellen und listet Verstöße auf: Tageshöchstarbeitszeit (> 10 h Netto),
  Ruhezeit (< 11 h zwischen zwei Arbeitstagen), Pflichtpause (ArbZG §4: 30 min ab
  6 h, 45 min ab 9 h) sowie als Hinweis die Wochenhöchstarbeitszeit (> Ø 48 h). Die
  Schwellen werden aus dem Bestand wiederverwendet (Organisations-Compliance-
  Einstellungen wie bei der Dienstplan-Prüfung, Pausenregeln wie im Tagesabschluss)
  – keine abweichenden Zahlen. Verstöße werden on-the-fly berechnet (die
  zugrunde liegenden Anwesenheiten sind über die Audit-Hash-Kette revisionssicher),
  mit Filter nach Verstoßart, Summen je Art, Drill-down zum Tagesabschluss sowie
  CSV-/PDF-Export. Liegt für einen Tag eine genehmigte Zeitkorrektur
  (`TimeCorrectionRequest`) vor, wird der Eintrag als „korrigiert“ markiert. Neue
  Permission `compliance.viewAny` (Admin, Teamleitung, Buchhaltung); Plan-Gating wie
  bei den übrigen Team-Auswertungen.
- Dienstplan-Intelligenz (Feature 007, MVP): **Verfügbarkeiten & Wunschdienste**
  als Self-Service (`schedule.availability.index`) – wiederkehrende oder
  datumsbezogene Verfügbarkeitsfenster (verfügbar/nicht verfügbar/bevorzugt) und
  Wunschdienste (Wunsch/Abneigung je Datum und optionalem Schichttyp); jeder
  Mitarbeiter pflegt nur die eigenen Einträge (Permission
  `availability.manage.own`). **Schichttausch mit Freigabe**
  (`schedule.exchanges.index`): Mitarbeitende beantragen Abgabe oder Tausch einer
  eigenen Schicht (Permission `shift.exchange`), ein Ziel-Kollege kann annehmen,
  die Teamleitung gibt frei (`shift.exchange.approve`) – die Freigabe prüft die
  neue Zuordnung über den bestehenden `ShiftComplianceService` (Ruhezeit,
  Höchstarbeitszeit, Überschneidung, Abwesenheit) und blockt harte Verstöße
  (Override durch die Leitung möglich); erst mit der Freigabe wechselt die
  Schicht-Zuordnung (bei echtem Tausch beide Schichten). **Besetzungsvorschläge**:
  der neue `StaffingSuggester` schlägt für eine offene/unterbesetzte Schicht
  gerankte Kandidaten vor (passende Qualifikation, Verfügbarkeit/Wunsch, kein
  Compliance-Konflikt, Fairness nach Wochenstunden) und blendet Kandidaten mit
  hartem Konflikt aus – nutzbar direkt an der offenen Schicht im Schichtplan,
  die Zuweisung läuft über den regulären Speichern-Pfad mit Compliance-Re-Check.
  **Unter-/Überbesetzungswarnung**: je Tag ein Warn-Badge bei offenen
  Soll-Schichten (wiederverwendet `OpenSlotService`/`CoverageService`).
  Synchrone Benachrichtigungen `shiftExchange.requested`/`.decided` plus
  Scanner-Reminder für ausstehende Freigaben; revisionssichere Audit-Einträge.
  Plan-Gating `module.planung`, eigene Hilfe-Topics
  (`planning.availability`, `planning.exchange`).
- Karten, Standort und Leitstelle (Feature 029, MVP): **Dispatch-Board /
  Leitstellen-Ansicht** (`dispatch.board`) zeigt die offenen und geplanten
  Aufträge eines Zeitraums kompakt – wahlweise als **Spalten nach
  Dispositionsstatus** (ungeplant/geplant/bestätigt/unterwegs/erledigt) oder als
  **Bahnen nach Mitarbeiter**. Jede Auftragskarte nennt Kunde, Zeitfenster und
  Mitarbeiter und markiert **harte Dispositionskonflikte** sowie **SLA-Risiken**
  (gefährdet/verletzt); ein Klick führt zum Auftrag. Ergänzend eine
  **Karten-Sicht** (`dispatch.map`) auf Basis der bestehenden Leaflet-/`map.js`-
  Einbindung: Aufträge werden über ihren eigenen Standort oder den
  **Kundenstandort** verortet, die Marker-Farbe folgt dem Dispositionsstatus,
  **SLA-gefährdete/-verletzte** Aufträge werden rot hervorgehoben; Filter
  „**nur SLA-Risiko**" und „**nur unbestätigte**". Beide Ansichten nutzen den
  neuen `DispatchBoardService`, der **ausschließlich vorhandene Bausteine
  wiederverwendet**: Dispositionsstatus über den `DispatchStatusResolver` und
  Konflikte über den `DispatchConflictChecker` (Feature 028) sowie das
  abgeleitete SLA-Risiko der offenen Service-Tickets (Feature 010, je Auftrag
  über den Kunden zugeordnet). Recht über die bestehende Permission
  `dispatch.viewAny`, Plan-Gating `module.planung`; eigener Hilfe-Topic
  `dispatch.board`. Bewusst **nicht** enthalten (Datenschutz):
  Tourenoptimierung, Echtzeit-Tracking, dauerhafte Standortüberwachung. Keine
  neuen Migrationen/Pakete.
- Terminierung, Einsatzplanung und Disposition (Feature 028, MVP-Kern):
  **Dispositionsstatus** am Auftrag (ungeplant/geplant/bestätigt/unterwegs/
  erledigt) als neuer Enum `DispatchStatus`. Der effektive Status wird vom
  `DispatchStatusResolver` bevorzugt aus der neuen, nullable Spalte
  `diary_entries.dispatch_status` gelesen und sonst aus den vorhandenen
  Planungsfeldern (`planned_at`/`assigned_user_id`/`status`/Lifecycle-
  Zeitstempel) **abgeleitet** — die WIP-Modellklasse `DiaryEntry` bleibt dabei
  unangetastet (Lese-/Schreibzugriff ausschließlich über den Service-/Query-
  Layer). **Konfliktwarnungen vor der Terminbestätigung** über den neuen
  `DispatchConflictChecker`, der die **bestehenden Compliance-Regeln**
  (`OverlapRule`, `RestPeriodRule`, `MaxDailyHoursRule`, `MaxWeeklyHoursRule`,
  `ConsecutiveDaysRule`, `VacationConflictRule`) wiederverwendet, indem er aus
  der geplanten Zuweisung eine transiente `ScheduledShift` baut und dem
  `ShiftComplianceService` füttert; zusätzlich eine Überschneidungsprüfung
  gegen andere Auftrags-Einsätze desselben Mitarbeiters. **Harte Konflikte**
  blockieren die Bestätigung und erfordern eine bewusste, revisionssicher
  protokollierte Übersteuerung mit Begründung; weiche Konflikte sind Hinweise.
  **Fahrzeug-Reservierung** (`vehicle_reservations`) mit
  `VehicleReservationService`, der Doppelreservierungen im selben Zeitfenster
  verhindert; Reservierung am Auftrag und Reservierungsliste je Fahrzeug.
  Anzeige des Dispositionsstatus als Badge in Auftragsliste und -detail. Neue
  Permissions `dispatch.viewAny`/`dispatch.manage` und `vehicle.reserve`
  (Teamleitung + Admin); Plan-Gating über `module.planung` (Disposition) bzw.
  `module.fuhrpark` (Reservierung). Hilfe-Topic `dispatch.overview`; i18n in
  allen fünf Sprachen (de/en/fr/it/es).
- Internationalisierung & Rechtsräume (Feature 034, MVP): **mandantenbezogener
  Feiertags-Rechtsraum**. Eine Organisation wählt unter *Erweiterte
  Einstellungen → Region & Feiertage* das maßgebliche Land/Bundesland
  (Yasumi-Provider, z. B. „Germany\\Bavaria"); damit gelten regionale Feiertage
  wie **Fronleichnam** oder **Reformationstag** nur dort, wo sie rechtlich
  greifen. Die Auflösung erfolgt mandantenbewusst über
  `Setting::get('holidays.provider')` (neue `config/holidays.php`, aus
  `config/app.php` ausgelagert) — der `HolidayService` cacht jetzt **pro
  Rechtsraum**. Da Feiertagszuschläge (`SurchargeCalculator`) und die
  Dienstplan-Compliance ausschließlich über den `HolidayService` lesen, nutzen
  **alle Konsumenten automatisch dieselbe Quelle**; die Feiertagsberechnung
  wurde nicht dupliziert, nur die Region-Auflösung erweitert. Neuer Helper
  `App\Support\HolidayRegions` (alle 16 DE-Bundesländer + bundesweit + AT) als
  Auswahl- und Validierungsregistry. Länderspezifische Spesen-/Pauschalsätze
  (BMF-Auslandstagegelder je Land/Region) sind bereits über `PerDiemRate`
  (`country` + `region_label`) und die Admin-Pflege abgebildet. i18n in allen
  fünf Sprachen (de/en/fr/it/es).
- Release-, Update- und Plugin-Strategie (Feature 022, MVP): **signierte/
  integritätsgesicherte Release-Metadaten** und **Plugin-Kompatibilität**.
  Neuer Befehl `php artisan release:manifest` erzeugt ein `release.json`
  (App-/Build-Version, PHP-/Laravel-/DB-Versionen, aktive Module + Plugins mit
  Kompatibilitätsangaben und **SHA-256-Prüfsummen** der Artefakte SBOM,
  `composer.lock`, `package-lock.json`); ist ein **Ed25519**-Private-Key
  vorhanden, wird das Manifest mit demselben Schlüssel/Mechanismus wie das
  Lizenzsystem (`sodium_crypto_sign_*`) signiert — sonst bleibt es unsigniert
  und nur prüfsummen-integer. `php artisan release:verify` prüft Prüfsummen
  und (falls vorhanden) die Signatur und erkennt Manipulationen (Exit-Code 1).
  Der Plugin-Contract erhält additiv `minAppVersion()` / `maxAppVersion()`
  (Default `null` über `PluginDefaults`, bestehende Plugins unberührt); die
  neue `PluginCompatibility` setzt den Bereich gegen `config('app.version')`
  durch: ein inkompatibles Plugin lässt sich **nicht aktivieren** und wird im
  Healthcheck als `failing` geführt (zählt auf Auto-Disable ein). Auf
  `admin/components` werden jetzt der **system:health**-Status (Hinweis „nach
  Update ausführen", inkl. ausstehender Migrationen) sowie das **Release-
  Manifest** (Erzeugen/Download, Signatur- und Integritätsstatus) angezeigt;
  `admin/plugins` zeigt Kompatibilitätsbereich/-status. `system:health`
  unterstützt zusätzlich `--json` (UI/Monitoring). Der **Build-Hash** steht nun
  neben der Version im Footer. Keine neuen Pakete, keine Migrationen
  (Metadaten als Datei/Command).
- Integrationen & offene API (Feature 008): neues **Webhook-System** für
  ausgehende, signierte Event-Benachrichtigungen. Die kuratierte Enum
  `WebhookEvent` (8 stabile Ereignisse) bindet je Fall genau ein real
  verdrahtetes `NotificationEvent` — die Auslösung hängt additiv im zentralen
  `NotificationDispatcher::notify()` und damit an denselben Stellen
  (Service-Trigger + Fristen-Scanner), die heute schon Benachrichtigungen
  feuern, ohne Umbau der Geschäftslogik. Neue Tabellen `webhook_endpoints`
  (HMAC-Signing-Key verschlüsselt at-rest, `$hidden`; abonnierte Events als
  JSON; Auto-Disable nach N aufeinanderfolgenden Fehlern) und
  `webhook_deliveries` (Zustellprotokoll je Versuch). Versand über
  `WebhookDispatchService` + `WebhookDeliveryJob` (Queue) mit
  **HMAC-SHA256-Signatur** über `<timestamp>.<body>` (Header
  `X-WorkDiary-Signature`, Replay-Schutz), kurzem Timeout, Retry mit Backoff
  und automatischer Endpunkt-Deaktivierung. Admin-UI `admin/webhooks` (CRUD
  als Modal, **Secret-Einmal-Anzeige** und -Rotation, Zustellprotokoll je
  Endpunkt, „Test-Event senden"). Neue Permissions `webhook.viewAny` /
  `webhook.manage` (Admin), Policy mit Org-Bindung, Hilfe-Topic
  `admin.webhooks`. Bewusst NICHT Teil dieses Schritts: Microsoft-365-/
  Google-Kalender-Anbindung (OAuth, separater Pilot).
- Qualität, Sicherheit & Arbeitsschutz (Feature 013, MVP): neues
  **Sicherheitsereignis-Register** (`safety-events.*`) für Unfall,
  Beinaheunfall, Gefährdung und Mangel — mit laufender `event_no` je
  Organisation, Schweregrad, Sofortmaßnahme, Ursachenanalyse, Foto-Nachweisen
  (`HasAttachments`) und Statusmaschine (*gemeldet → in Untersuchung →
  Maßnahmen definiert → geschlossen*; Abschluss erfordert eine
  Ursachenanalyse). Modell `SafetyEvent`, `SafetyEventService`,
  `SafetyEventController`, `SafetyEventPolicy`, Liste/Detail/Modale. **Kritische
  Ereignisse** (Unfall ODER Schweregrad „kritisch") feuern synchron das neue
  Ereignis `NotificationEvent::SafetyCriticalEvent` an die Leitung. Beim
  Schließen kann ein **offener Punkt** als Folgemaßnahme angelegt werden
  (Wiederverwendung des Offene-Punkte-Systems). Neue Permissions
  `safety.viewAny` / `safety.report` / `safety.manage` (Teamleitung führt das
  Register, Außendienst meldet); bewusst ungated (Core-Arbeitsschutz). Neuer
  **Sicherheits-Report** (`reports.safety`, Menü Auswertungen → Team):
  Ereignisse je Art und Schweregrad im Zeitraum, offen vs. geschlossen.
- Qualifikations-/Unterweisungs-Ablaufwarnung (Feature 013): der
  Fristen-Scanner (`notifications:scan-deadlines`) meldet ablaufende
  Mitarbeiter-Qualifikationen über das neue Ereignis
  `NotificationEvent::QualificationExpiring` (Vorlauf `--expiring-days`) an
  Person + Teamleitung; neues Pivot-Modell `UserQualification` für stabile
  Dedup-Subjekte. Pflicht-Sicherheitschecklisten je Auftragstyp laufen über
  das bestehende Prozedursystem (Feature 026, `applicability.diary_entry_type`,
  Vier-Augen über `SecondPersonGate`) — keine Parallelmechanik. Hilfe-Topic
  `safety.overview` (de/en).

- Nachkalkulation & Wirtschaftlichkeit (Feature 014, MVP): neuer
  **Wirtschaftlichkeits-/Deckungsbeitrags-Report** (`reports.economics`,
  Menü „Finanzen & Audit", Plan-Gating `module.auswertungen_team`, nur für
  Admin/`report.view` – Geschäftsführung/Buchhaltung). Je **Kunde** und je
  **Projekt** im gewählten Zeitraum: **Erlös** (abrechenbare Zeiten ×
  `TimeEntry.rate` + abgerechnetes Material `MaterialUsage.line_total_net` +
  abrechenbare, freigegebene Spesen `Expense.amount_net`) gegen **Kosten**
  (interner Zeit-Kostensatz `TimeEntry.internal_rate` + Material-/Beleg-
  Direktaufwand) ⇒ **Deckungsbeitrag** absolut und als **Marge** in Prozent,
  inkl. abrechenbarer vs. nicht-abrechenbarer Stunden. **Top/Flop-5-Ranking**
  je Projekt und Kunde nach Deckungsbeitrag. **Nacharbeit/Kulanz** als ehrlicher
  Proxy über nicht-abrechenbare Zeit (`billable=false`) ausgewiesen (es gibt
  keinen dedizierten Aktivitätstyp). **Plan-vs-Ist** je Projekt in Minuten
  (`Project.time_budget`) und in Geld (`Project.budget`). Reine Auswertung über
  ECHTE Modellfelder; fehlende interne Kostensätze werden transparent markiert
  (`*` + Hinweisbanner „Kostensätze nicht gepflegt"). CSV/PDF-Export mit
  Audit-Log (`report.exported`) wie die übrigen Reports; neuer
  `EconomicsReportBuilder`. Rechnungshoheit bleibt beim externen Faktura-System
  – die Werte hier sind Projektion. i18n de/en/fr/it/es paritätisch,
  Hilfe-Topic `reports.economics` (de/en). Bewusst offen: dedizierter
  Nacharbeit-/Kulanz-Typ, Beleg-/Positions-Drilldown, separater
  Materialkostensatz.
- Klassifikationen, Tags & Datenqualität (Feature 024, MVP): **Tagging über die
  bestehende polymorphe `HasTags`-Mechanik** auf **Kunde, Asset und Protokoll**
  ausgeweitet (Auftrag/Wissensartikel nutzten sie bereits) — keine neue Tag-Mechanik,
  dieselbe `taggables`-Tabelle (ohne Migration). `Tag` erhält die zusätzlichen
  `morphedByMany`-Relationen `customers()`/`assets()`/`protocols()`. Asset: Tag-Picker
  (`x-tag-picker`) im Bearbeiten-/Anlege-Dialog, Anzeige als Badges auf Detailseite
  und in der Liste (`SaveAssetRequest` um `tag_ids`/`new_tags` erweitert, Sqid-Dekodierung
  im Controller). Protokoll: `tag_ids`/`new_tags` in `ProtocolController::{store,update}`,
  Anzeige in der Fallakte. **Datenqualitäts-Hinweise**: neuer `DataQualityInspector`
  leitet fehlende Pflichtklassifikationen rein lesend aus dem vorhandenen
  `ClassificationRequirementValidator` und den am Auftrag persistierten Werten
  (Auftragsart, Priorität) ab; dezentes Badge auf der Auftrags-Detailseite. Der Validator
  erhielt dafür additiv ein `audit`-Flag (Hinweise erzeugen keine Audit-Logs).
  **Stillgelegte Klassifikationen** (`deprecated_at`) werden im Admin mit Datum
  ausgewiesen — über den Resolver nicht mehr neu wählbar, für historische Daten weiterhin
  lesbar. i18n: neue `classification.dataquality.*`-Sprachdateien + JSON-Keys in
  de/en/fr/it/es paritätisch. Bewusst offen: Tag-/Kategorie-Mapping für CSV-Import,
  Datenqualitäts-Report-Widget, Produkt-Tagging.
- Prozeduren, Arbeitsanweisungen & Checklisten (Feature 026, MVP-025): **Vorlagen-
  Designer-UI** und **PDF-/Druckansicht eines Laufs** auf dem bestehenden, bereits
  getesteten Execution-Backend (`ProcedureTemplate*`/`ProcedureRun*`/`ProcedureStepDef`,
  `ProcedureTemplateService`, `ProcedureExecutionService`, `ProcedureApplicabilityResolver`).
  Neue Admin-Seite `procedures.index` (Liste + Anlage-Modal) und Voll-Seiten-Designer
  `procedures.edit`: Stammdaten, Versionsverwaltung (Entwurf bleibt editierbar,
  Veröffentlichen friert die Version ein → Korrekturen erzeugen neue Version),
  Schritt-Editor mit dynamischen Zeilen (echte `ProcedureStepType`-/`ProcedureProofType`-
  Cases, Pflicht/sperrend, Vier-Augen, Rolle/Qualifikation) sowie **Anwendbarkeit**
  (Auftragstypen + Tags, wie vom `ProcedureApplicabilityResolver` genutzt). Neuer Service-
  Zusatz `ProcedureTemplateService::{updateTemplate,updateVersion,syncSteps}` (additiv;
  Sync ersetzt Schritte nur in Draft-Versionen). **Druckbare Read-Only-Lauf-Ansicht**
  `procedure-runs.print` (Standalone-Blade + Print-CSS, Hausmuster diary/case-file):
  Kopf (Vorlage/Version/Subjekt/Status/Zeiten), Schritte mit Ergebnis/Bestätiger/
  Vier-Augen, Abweichungen (`ProcedureDeviation`) und Backup-Nachweise
  (`ProcedureBackupProof`). **Automatische Zuordnung** sichtbar gemacht: Auf der
  Auftragsdetailseite zeigt ein Prozedur-Panel laufende/abgeschlossene Läufe (mit
  Druck-Link) und per Resolver vorgeschlagene, noch nicht gestartete Vorlagen als
  Start-Button. **Bedingte Schritte (wenn-dann)** additiv über `config.depends_on`
  (Bezugsschritt + erwarteter Wert) im Designer erfassbar und in der Druckansicht
  ausgewiesen — ohne Migration. `ProcedureTemplate`/`ProcedureRun` erhalten `HasSqid`
  (opake URLs), NavGate-Mapping + Admin-Menüeintrag (Permission `procedure.template.view`),
  i18n `procedure.*` + `enums.procedure.proof-type.*` in de/en/fr/it/es paritätisch,
  Hilfe-Topic `procedures.designer` (de/en) + Mapping. Bewusst offen: bedingte Schritte
  werden im Execution-Kern noch nicht ausgewertet (nur Vorlagen-Metadaten/Anzeige);
  die ausführende Schritt-für-Schritt-Lauf-UI bleibt außerhalb dieses MVP-Schritts.
- Inventar, Dienstmittel & Assets (Feature 009, MVP): **Ausgabe-/Rückgabe-Workflow
  (Checkout)** und **Defekt-/Sperrstatus**. Neue Tabellen `asset_assignments`
  (offene Zuweisung = ausgegeben; pro Asset höchstens eine, vom Service erzwungen;
  optional Person/Team, Auftragsbezug, erwartete Rückgabe, Zustand bei Ausgabe/Rückgabe)
  und `asset_defects` (Schweregrad low/medium/high/critical, Status open/inRepair/
  resolved/writtenOff mit Statusmaschine, `blocks_usage`-Sperre, Pflicht-Lösungsnotiz
  bei Erledigen/Ausbuchen) — beide `Auditable` + `BelongsToOrganization` + `HasSqid`
  - `softDeletes`. **Verfügbarkeit/Sperre werden aus diesen Tabellen abgeleitet**
  (keine neuen `AssetStatus`-Enum-Werte); der bestehende `Asset.status` wird zur
  Kompatibilität auf die vorhandenen Werte `loanOut`/`blocked` gespiegelt, soweit
  die Statusmaschine es zulässt. Ein gesperrtes oder bereits ausgegebenes Asset
  kann nicht ausgecheckt werden. Auf der Asset-Detailseite die Panels
  „Ausgabe / Rückgabe" (aktuelle Zuweisung + Historie, Checkout/Checkin als Modals)
  und „Defekte / Sperren" (Liste + „Defekt melden" + Status-Aktionen); in der
  Asset-Liste ein Verfügbarkeits-/Sperr-Badge (verfügbar / ausgegeben / gesperrt:
  Defekt). Neues NotificationEvent `asset.returnOverdue` im Fristen-Scanner
  `notifications:scan-deadlines` (überfällige Rückgabe an die ausleihende Person,
  Fallback/Eskalation Teamleitung, Dedup über `notification_dispatch_log`).
  Permissions `asset.checkout` (admin/teamleitung/aussendienst) und
  `asset.defect.manage` (admin/teamleitung), Policy-Abilities `checkout`/
  `manageDefects`, Enums `DefectSeverity`/`DefectStatus`, i18n `asset.*`-UI-Strings
  - `enums.asset.*` + `notification.*` + `access.*` in de/en/fr/it/es, Hilfe-Topic
  `assets.fleet` (de/en) erweitert. Assets sind keinem Plan-Modul für Checkout/Defekt
  zugeordnet — die Funktion bleibt ungated (nur Permission), konsistent zur
  bestehenden Asset-Verwaltung. Bewusst offen: Foto-/Anhang-Verknüpfung am Defekt,
  Wiederholdefekt-Statistik, Prüfintervall-Eskalation.
- SLA, Verträge & Service-Level (Feature 010, MVP): Service-Tickets zeigen auf
  Liste und Detail einen **abgeleiteten SLA-Status** (im Plan / gefährdet bei
  < 20 % Restzeit / verletzt) als Tone-Badge inkl. Restzeit, hergeleitet über
  den bestehenden `SlaTimer` aus den Reaktions-/Lösungsfristen des Tickets. Neues
  **SLA-Verletzungsregister** (`sla_violations`, `Auditable` + `softDeletes`,
  je Ticket+Typ genau eine Zeile) wird **idempotent** befüllt: durch den
  erweiterten Scanner `tickets:scan-sla-breaches` und durch zu späte
  Statusübergänge (erste Reaktion/Lösung) im `ServiceTicketService`. Neuer
  **SLA-Report** (`reports.sla`, Auswertungen → SLA, Permission `sla.viewAny`)
  mit Einhaltungsquote, Aufschlüsselung je Verletzungstyp, Priorität, Kunde und
  Ursache sowie Verletzungsliste mit Drill-down zum Ticket und Quittierung
  (`sla.manage`); CSV- und PDF-Export. **Eskalation** über den Fristen-Scanner
  `notifications:scan-deadlines`: neue NotificationEvents `sla.atRisk`
  (Restzeit < 20 %) und `sla.breached` (Frist überschritten, `supportsEscalation`)
  an den Ticket-Verantwortlichen, Fallback/Eskalation an die Teamleitung
  (Dedup über das `notification_dispatch_log`). Permissions `sla.viewAny`/
  `sla.manage` (admin + teamleitung), Policy `SlaViolationPolicy`, Enums
  `SlaStatus`/`SlaViolationKind`, i18n `sla.*`/`enums.sla.*`/`notification.*`/
  `access.*` in de/en/fr/it/es, Hilfe-Topic `sla.overview` (de/en) inkl.
  Route-Mapping. Service-Tickets sind keinem Plan-Modul zugeordnet — der Report
  bleibt ungated (nur Permission). Bewusst offen: Auftrags-/DiaryEntry-Verknüpfung
  des SLA-Kontexts, Wartungsintervalle, Inklusivzeiten/Kontingente und
  Geschäftszeiten in der Fristberechnung.
- Backup-Statusseite & Restore-Test-Register (Feature 017, MVP): neue
  **plattformweite Admin-Seite** `admin/backup` (Permission `backup.view`,
  Systembetrieb-Menü) zeigt je Quelle die **letzte registrierte Sicherung**
  (Zeitpunkt, Alter, Größe, gekürzter Manifest-Hash) aus den vorhandenen
  `backup_heartbeats` und warnt rot, wenn ein Heartbeat die Frische-Schwelle
  überschreitet (`backup.heartbeat_freshness_hours`, Default 26 h) oder gar
  kein Backup registriert ist. Neues **Restore-Test-Register**
  (`restore_tests`, plattformweit/ohne Tenant-Bezug analog Heartbeat, mit
  `softDeletes` + `Auditable`) inkl. „Restore-Test protokollieren"-Modal
  (Quelle, Datum, Ergebnis `passed|partial|failed`, Umfang, Größe, Dauer,
  Notiz, nächste Fälligkeit) und **Überfälligkeits-Warnung**, wenn der letzte
  erfolgreiche Test älter als `backup.restore_test_overdue_days` (Default 180)
  ist. Beide Schwellen zusätzlich als `system:health`-Checks (Backup-Heartbeat,
  Restore-Test; Tabelle-fehlt ⇒ übersprungen, Exit-Logik unverändert). Enum
  `RestoreTestResult` mit `label()/tone()`; i18n `backup.*` + `enums.backup.*`
  in de/en/fr/it/es; Hilfe-Mapping `admin.backup.*` → `admin.backups`. Bewusst
  offen: automatisierte Restore-AUSFÜHRUNG (Register dokumentiert manuell/Skript
  durchgeführte Tests), SaaS-mandantenbezogenes Restore.

- DATEV-Buchungsstapel (Feature 045, Priorität 2 / Phase 3 — MVP): gestellte und
  bezahlte **Rechnungen**, **Gutschriften** sowie optional freigegebene
  **Spesen** eines abgeschlossenen Zeitraums werden als prüfbarer
  **DATEV-Buchungsstapel (Format V700)** exportiert — über `php-financial-formats`
  (`BookingDocumentBuilder` + `DatevDocumentGenerator`), gekapselt im
  `DatevBookingAdapter`. Je Rechnung ein Debitor-Buchungssatz **Soll
  Debitorenkonto an Haben Erlöskonto** mit BU-Schlüssel (Brutto; 19 %⇒3, 7 %⇒2,
  0 %⇒0, konfigurierbar), Gutschriften umgekehrt; Belegfeld 1 = Rechnungsnummer,
  Belegdatum = Ausstellungsdatum. **Buchhaltungs-Konfiguration je Organisation**
  (`settings['datev']`): Berater-/Mandantennummer, Kontenrahmen (SKR03/SKR04),
  Sachkontenlänge, Erlöskonten (Standard + steuerfrei), Debitoren-Nummernkreis,
  Steuerschlüssel-Mapping, Festschreibekennzeichen (GoBD), Zeichensatz (Default
  ISO-8859-1). **Debitorennummer je Kunde** (`customers.debtor_no`) mit
  deterministischer Vergaberegel als Fallback. Datenmodell `datev_booking_batches`
  / `datev_booking_sources` (morph, Doppel-Übergabe-Schutz) / append-only
  Hash-Kette `datev_booking_events` (`audit:verify`). **Hoheits-Ausschluss**:
  extern (Lexoffice/DATEV) geführte Rechnungen gehören nicht in den lokalen
  Stapel und werden ausgeschlossen + im Preflight gewarnt. Finalisierter Stapel
  unveränderlich (CSV + SHA-256). Berechtigung `finance.booking.export`
  (Buchhaltung + Admin), Konfiguration `finance.config` (Admin); Modul-Gating
  `module.finance`. Prozesshilfe `finance.datev-bookings` (de/en).

- ISMS „Betrieb und Wirksamkeit" (Feature 044, MVP 2 — Kern): **Sicherheits-
  vorfälle** (`isms_security_incidents`) unabhängig vom Personenbezug, mit
  Kategorie/Kritikalität, Statusmaschine (gemeldet → Bewertung → eingedämmt →
  bereinigt → wiederhergestellt → geschlossen; Abschluss erzwingt
  Ursachenanalyse **und** Lessons Learned) und Rückführung in Risiken/Maßnahmen
  (`isms_incident_risk`/`isms_incident_control`). Datenschutz bewusst lose
  gekoppelt: ein Flag weist auf die **separate** Datenschutzmeldung hin
  (Fallakten getrennt), ein optionaler Freitext-Verweis (`privacy_incident_ref`,
  kein FK auf die Privacy-WIP-Tabelle) referenziert den Datenschutzvorfall;
  neue **kritische** Vorfälle melden synchron an die Leitung. **Schwachstellen-
  register** (`isms_vulnerabilities`) mit Kritikalität (aus CVSS-v3 ableitbar),
  Verantwortung, Frist, Inventar-Bezug und Statusmaschine; die **Ausnutzbarkeits-
  Entscheidung** ist eine bewusste, begründete Nutzeraktion (Pflichtnotiz),
  überfällige Schwachstellen werden über `notifications:scan-deadlines` gemeldet
  und eskaliert (`isms.vulnerabilityOverdue`). **Advisory-Import (CSAF/VEX)**
  nativ per `json_decode` (kein neues Paket): Abgleich betroffener Komponenten
  gegen das Softwareinventar und optional die letzte Release-SBOM
  (`workdiary-latest.cdx.json`); `known_affected` ⇒ offen + Ausnutzbarkeit „in
  Untersuchung" (**nie automatisch ausnutzbar**), `known_not_affected` (VEX) ⇒
  „nicht betroffen" mit VEX-Begründung als Pflichtnotiz. Original-Advisory mit
  SHA-256 als Nachweis (`isms_advisories`), Re-Import idempotent. Routen unter
  `compliance/isms` (`isms.incidents.*`, `isms.vulnerabilities.*`,
  `isms.advisories.*`), Plan-Gating `module.isms`, Berechtigungen über die
  bestehenden `isms.viewAny/view/manage` (keine neuen Permissions). Offen:
  Lieferantenbewertung (Stretch), vollständige VEX-Profile, automatischer
  Advisory-Feed.
- Zahlungsabgleich (Feature 045, Priorität 3 / Phase 4): Import von
  Bankauszügen im Format **CAMT.053** (bevorzugt) und **MT940** (Fallback)
  über einen Adapter um `php-financial-formats`
  (`BankStatementParser`). Bankumsätze landen in einem Prüfbereich
  (`bank_statements`/`bank_transactions`) und ändern keinen Beleg; offene
  Rechnungen und freigegebene Spesen werden score-basiert vorgeschlagen
  (`MatchingService`: Rechnungsnummer/Betrag/Skonto/IBAN-Hash/Datumsnähe,
  Skonto-Toleranz Default 3 %, Cent-Toleranz ±0,02). Erst die Bestätigung
  (`ReconciliationService::confirm`) setzt `Invoice.status=paid`/`paid_on`
  bzw. `Expense.reimbursed_at`; Teil-/Überzahlung werden unterschieden,
  Zuordnungen sind reversibel (`payment_allocations` SoftDelete, `unmatch`
  ohne Veränderung des Bankumsatzes). Dublettenschutz über Datei-Hash je
  Organisation und Umsatz-Fingerprint, Saldenketten-Prüfung
  (`balance_check`). Eigene Bankkonten (`bank_accounts`, IBAN verschlüsselt
  at-rest + `iban_hash`-Blindindex, Admin-CRUD via Modal). PII der Bankumsätze
  (Name/IBAN/Verwendungszweck) verschlüsselt; Matching ausschließlich über
  unverschlüsselte Ableitungen. Append-only Hash-Kette
  (`payment_reconciliation_events`, `config('audit.chains')`, `audit:verify`).
  Neue Permissions `finance.payment.import` und `finance.payment.reconcile`
  (Buchhaltung + Admin); Bankkonten über `finance.config`. Modul-Gating
  `module.finance`. Bewusst offen: Fremdwährungs-Kursdifferenz, Sammelbuchungs-
  Auflösung, EBICS/FinTS, Lastschrift-Rückläufer.
- Tagesabschluss (MVP-015, Feature 001): Seite `/tagesabschluss` mit
  Anwesenheit (Durchgriff auf die Stempeluhr), Pausen-Soll/Ist, Buchungsliste
  (bestehendes Buchungs-Modal), 7 Konsistenzprüfungen (⛔ blockierend / ⚠
  Hinweis, `DayClosureValidator`), Soll/Ist-Bilanz inkl. Monats-Saldo und
  sticky Abschluss-CTA. Statusmaschine open → closed → correction → open
  (abgeleitetes `locked` aus der Monatsfreigabe MVP-016) mit Audit-Spur
  (`dayClose.opened/.entrySaved/.closed/.correctionRequested/
  .correctionApproved/.correctionRejected/.reopened`), Korrektur-Workflow
  mit Pflicht-Begründung (≥ 20 Zeichen) und Stempel-Sperre nach Freigabe,
  Admin-Reopen mit Audit-Grund sowie 7 `dayClose.*`-Permissions
  (view.own/team/organization, close.own, requestCorrection.own,
  approveCorrection, reopen). Bewusst offen: Drag-Quick-Buchung (§2.3),
  Ctrl+Enter-Shortcut (§8), Korrektur-Inbox (MVP-017).

- E-Rechnung-MVP (Feature 045, Abschnitt 8): XRechnung-konformes
  UBL-2.1-XML (EN 16931, CIUS XRechnung 3.0) für lokale Ausgangsrechnungen
  im Pfad „WorkDiary führt" — `XRechnungGenerator` mit
  Pflichtfeld-Preflight (Fehler blockieren, Warnungen nicht),
  Verkäuferstammdaten je Organisation (Invoicing-Tab,
  `settings['einvoice']`: Anschrift, USt-IdNr./Steuernummer, Kontakt,
  IBAN/BIC, Zahlungsziel, Kleinunternehmer § 19 UStG ⇒ Steuerkategorie E),
  Leitweg-ID/Käuferreferenz (BT-10) je Kunde (`customers.buyer_reference`),
  Steuerkategorien S/Z/E, SEPA-Zahlweg 58, Einheiten-Mapping
  (Stunde ⇒ HUR, Stück/Default ⇒ C62), Gutschriften als Typ 381 und
  Download-Button auf der Rechnungs-Detailseite (nur gestellt/bezahlt,
  gesperrt bei externer Fakturierungshoheit). Bewusst offen:
  Schematron-/KoSIT-Validierung (Java), Peppol-Versand.

- E-Rechnung auf `php-erechnung-toolkit` umgestellt und ZUGFeRD ergänzt
  (Feature 045, Abschnitt 8): der `XRechnungGenerator` bleibt als Adapter
  mit unveränderter öffentlicher API (preflight/generate), baut das UBL-XML
  intern aber über den `ERechnungDocumentBuilder` des Toolkits. NEU:
  ZUGFeRD-Download (`invoices.zugferd`, Button „ZUGFeRD (PDF)") als
  PDF/A-3 mit eingebettetem CII-XML (Profil EN 16931/COMFORT) über
  `ZugferdPdfGenerator` + `php-pdf-toolkit`; die visuelle Darstellung ist
  die bestehende Rechnungs-PDF-View (`invoices.pdf`). Der Preflight bleibt
  die Validierungsschicht (das Toolkit prüft keine Geschäftsregeln) und ist
  profilabhängig: BT-10/BuyerReference ist nur für die XRechnung Pflicht,
  für ZUGFeRD eine Warnung; zusätzlicher Betragstreue-Check gegen die vom
  Toolkit selbst berechneten Summen. Gutschriften werden jetzt als
  UBL-CreditNote-Dokument emittiert (vorher Invoice mit TypeCode 381).

- Kommunikationsnotizen (MVP-012): Telefonate, E-Mails und Vor-Ort-Gespräche
  als Notizen an Aufträgen, Kunden und Projekten — inkl. Vertraulichkeit,
  Kundenportal-Freigabe und Folgeaktionen.
- Dokumentenmanagement (MVP-031): Verträge, Zertifikate und Prüfberichte mit
  append-only-Versionierung, Gültigkeiten und Archivierung.
- Benachrichtigungsregeln (MVP-018): konfigurierbare Regeln und Eskalationen
  je Organisation.
- Zuschlagsregeln (Feature 005): Nacht-/Sonn-/Feiertagszuschläge für die
  Lohnübergabe.
- Timeline/Fallakte (Feature 023): chronologische Fallakte je Auftrag mit
  allen verknüpften Ereignissen.
- Wissensbasis & Problemhistorie (Feature 011): Artikel mit Problem/Lösung,
  Kategorien, Tags und Redaktions-Workflow.
- Vorlagen- & Formularsystem (Feature 032): Formularvorlagen mit
  versionssicherem Felder-Snapshot beim Ausfüllen.
- Betriebsmetriken (Feature 036): Admin-Seite `admin/metrics` mit Queue-Stand,
  Backup-Heartbeats, Plugin-Fehlern, Speicher-Kennzahlen, Datensatzzählungen
  und datenschutzfreundlicher, rein lokaler Feature-Nutzungsstatistik
  (`feature_usage_counters`).
- Release-Basics (Feature 022): Versions-Anzeige (`config('app.version')`,
  Footer + Metrik-Seite), Health-Check-Command `php artisan system:health`
  für die Prüfung nach Updates sowie Release-Prozess-Doku
  (`docs/release-prozess.md`).
- ISMS MVP1 (Feature 044): Risikoregister mit 5×5-Matrix und Statusmaschine,
  Maßnahmenkatalog mit ISO/IEC-27001:2022-Annex-A-Import (93 Controls) und
  druckbarem Statement of Applicability (`module.isms`, Enterprise).
- Managementsystem-Kern (Feature 046): ISMS auf den gemeinsamen Kern
  refactort — Geltungsbereiche (`isms_scopes`), versionierte
  Normanforderungen (`isms_requirements`, Annex-A als Normprofil
  ISO/IEC 27001:2022), normneutrale Maßnahmen mit
  n:m-Anforderungs-Mapping und SoA als eigene Applicability-Statements je
  Geltungsbereich (inkl. Datenmigration bestehender Controls).
- Normprofil-Registry (Feature 046, Inkrement A): sieben Normprofile als
  Kataloge (ISO/IEC 27001:2022 mit Annex A + HLS-Kapiteln; 27701, 9001,
  22301, 45001, 37301 und 42001 auf HLS-Ebene mit eigenen Kurztiteln),
  Normprofil-Auswahl beim Katalog-Import, Norm-Filter und Mehr-Scope-SoA
  mit Geltungsbereichs-Wechsel.
- Zertifikatsregister (Feature 046, Inkrement B): Konformitätsstatus je
  Geltungsbereich und Norm mit strikter Statuskette — `zertifiziert` nur
  mit hinterlegtem, aktuell gültigem Zertifikat (Zertifizierungsstelle,
  Nummer, Geltungsbereich, Gültigkeit, Überwachungstermine, optionales
  Dokument aus dem Dokumentenmodul); automatischer Verfall und
  Ablauf-Warnung (`isms.certificateExpiring`) über den Fristen-Scanner.
- Risiko-Bewertungshistorie (Feature 046, Inkrement D): Brutto-/Netto-/
  Ziel-Bewertungen als unveränderliche, freigegebene Stände je Risiko
  (Person/Zeitpunkt), Direktbewertungen historisieren automatisch,
  Restrisiko-Akzeptanz erfordert eine freigegebene Netto-Bewertung mit
  Reviewdatum; Scanner-Ereignis `isms.riskReviewDue`.
- Audit- und Verbesserungszyklus (Feature 046, Inkrement C): interne/
  externe/Lieferanten-Audits je Geltungsbereich mit Statuskette und
  Unabhängigkeitsprüfung, Feststellungen (Nichtkonformität major/minor,
  Beobachtung, Verbesserung) mit Anforderungsbezug, Korrekturmaßnahmen mit
  Ursachenanalyse und Wirksamkeitsprüfung (Pflicht-Notiz; unwirksam setzt
  die Feststellung zurück), Managementbewertungen mit unveränderlicher
  Freigabe (Person/Zeitpunkt) sowie Fristen-Scanner-Ereignis
  `isms.correctiveActionOverdue` für überfällige Korrekturmaßnahmen
  (`module.isms`, Enterprise).
- Auditpakete & Prüferzugang (Feature 046, Inkrement E / 044
  „Auditbereitschaft"): stichtagsbezogene, integritätsgeschützte
  Auditpakete je Geltungsbereich (`isms_audit_packages`) — Finalisierung
  friert den Datenstand als JSON-Snapshot ein (SoA, Risikoregister inkl.
  freigegebener Bewertungen, Maßnahmen, Konformität + Zertifikate, Audits
  mit Feststellungen/Korrekturmaßnahmen, freigegebene
  Managementbewertungen, Softwareinventar) mit SHA-256-Integritätsnachweis
  (`isms:verify-packages` + UI-Prüfung; finalisierte Pakete sind
  unveränderlich). Ehrliche Stichtags-Semantik: `as_of_date` =
  dokumentierter Berichtsstichtag, `data_captured_at` = Datenstand bei
  Finalisierung (kein Event-Sourcing). Zeitlich begrenzter, lesender
  Prüfer-Download über tokenisierte öffentliche Links (nur SHA-256-Hash
  gespeichert, Klartext einmalig sichtbar, 1–90 Tage, widerrufbar)
  (`module.isms`, Enterprise).
- Auditbereitschafts-Dashboard & Register-Exporte (Feature 044, MVP1-
  Abschluss): Kennzahlen-Dashboard „Auditbereitschaft" je Geltungsbereich
  als erster Eintrag des ISMS-Bereichs (`ReadinessService`, reine
  Leseaggregation) — SoA-Fortschritt je Norm, hohe Risiken (Score > 12),
  überfällige Bewertungs-Reviews und unbewertete Risiken, überfällige
  Korrekturmaßnahmen, offene Nichtkonformitäten, Nachweislücken
  (anwendbar ohne Evidenz und umgesetzte Maßnahme), Zertifikatsablauf/
  Überwachungstermine < 90 Tage und Software-EOL; KPI-Kacheln mit
  Warn-Tones und Drill-down in die Register (reines Blade/CSS). Dazu
  JSON-/CSV-Direkt-Exporte (`?format=json|csv`) für Risikoregister,
  Anforderungen/SoA (je Scope) und Maßnahmen mit meta-Block
  (Organisation, Geltungsbereich, generated_at, App-Version; CSV mit
  Semikolon + UTF-8-BOM) — „versioniert" leistet weiterhin der
  unveränderliche Auditpaket-Snapshot (`module.isms`, Enterprise).
- Kontextbezogene Prozesshilfe (Feature 039): rechte, nicht-blockierende
  Hilfe-Sidebar am Desktop (mobil Drawer) mit automatischem Seitenkontext
  über eine Route→Topic-Registry (`config/help-topics.php`), Hilfe-Button
  im Header, `?`-Shortcut, gemerktem Auf/Zu-Zustand und Fallback mit
  Suche; 26 neue Hilfe-Topics in Deutsch und Englisch (ISMS-Prozesse,
  Datenschutz, Dokumente, Formulare, Wissensbasis, Kommunikationsnotizen,
  Faktura-Übergabe, Lohnexport, Glossar, 7-teiliges Admin-Handbuch) plus
  rollenbasierte Einstiegshilfen für Außendienst, Teamleitung,
  Buchhaltung, Admin und Geschäftsführung (audience-gesteuert).
- Softwareinventar & Release-SBOM (Feature 044 MVP1):
  organisationsbezogenes Softwareinventar (Produkte, Installationen,
  Support-Status mit EOL-Automatik) sowie `php artisan sbom:generate`
  (CycloneDX 1.5 aus composer.lock/package-lock.json, Modulen und Plugins)
  mit geschützter Admin-Komponentenübersicht (`admin/components`).
- Finanzschnittstelle, erstes Inkrement (Feature 045): Fakturierungsweg je
  Organisation/Kunde (`billing_mode`) mit Rechnungshoheit beim externen
  Programm — lokale Rechnungserstellung ist bei extern geführter Fakturierung
  gesperrt; Übergabenachweise (`billing_transfers`) mit getrennten Kanälen
  Zeit/Material, Payload-Hash und Hash-Ketten-Events (`audit:verify`);
  Lexoffice-Positionsübergabe als Rechnungsentwurf über die bestehende API;
  Datei-Übergabepaket (CSV) für DATEV-/manuelle Abläufe; Sperre von
  Zeitkorrekturen an bereits übergebenen Zeiten (`module.finance`,
  Enterprise).
- Globale Suche erweitert (Feature 023): die Command-Palette findet jetzt
  auch Kommunikationsnotizen (Betreff; vertrauliche nur für Erfasser und
  `communication.confidential.manage`), Dokumente (Titel, nur mit
  `document.viewAny`), Wissensartikel (Titel/Problem; Veröffentlichtes
  plus eigene Entwürfe) und Formular-Submissions (Vorlagen-Name; ohne
  `formTemplate.viewAny` nur eigene) — Dokumente/Wissensbasis/Formulare
  modul-gegatet über Plan/Lizenz, Mandantengrenzen über die
  Organization-Scopes abgesichert.

#### Nachgetragen am 2026-09-16 (`MVP-796`): Features 099-156, `MVP-461`-`MVP-816`

*(Vollscan 2026-09-15, Befund `C4-19`.)*

Dieser Changelog endete inhaltlich bei `MVP-460` und Feature 098; der Vollscan
2026-09-15 hat die Lücke aufgedeckt. Nachgetragen nach Themen statt je MVP —
die Einzelheiten stehen in den verlinkten Feature-Dokumenten des Schwester-Repos
`WorkDiary-Architecture/features/`.

- **Bau, Vergabe und Bau-Abrechnung.** GAEB-Formatfamilien und e-Vergabe
  (Feature 108, Phase 93); Kostengruppen und Kostenermittlung nach DIN 276
  (109, Phase 94); Sicherheitseinbehalte nach § 17 VOB/B (113),
  Bürgschaftsregister (114), Gewährleistungsfristen (115),
  Subunternehmer-Pflichtnachweise (117), Anlagen-Stückliste (118).
- **Beschaffung und Kataloge.** DATANORM-Vollausbau (107, Phase 92);
  B2B-Katalogzugang mit OCI-Punchout und openTRANS-Auftragseingang (099);
  XLSX-Preislisten als Katalogformat (Phase 90).
- **Buchhaltung, Zahlungsverkehr und Auswertung.** Belegfluss als eine Liste
  statt drei Tabs (105, Phase 91); Auslagen als Beleg (106); lokale
  Buchhaltung, Bankwesen und wiederkehrende Vorgänge (125);
  Buchhaltungswechsel mit kontrollierter Migration (110);
  Buchhaltungs-Symmetrie aus Beleg-Pull und Kontakt-Push (122);
  SEPA-Zahlungsausgang mit pain.001 und pain.008 (120); Girocode auf
  Rechnungs-PDFs (111); Zählerstands-Faktura (116); Mahnlauf (127);
  generischer Belegversand (128); DATEV-EXTF um Kostenstelle, Fälligkeit und
  Skonto (135); Anlagenregister mit Jahres-AfA (133);
  13-Wochen-Liquiditätsvorschau (136); Umsatz je Produkt (140); BWA, Budget
  und Kostenstellen (142); Angebots-Nachfassen (112); Kundenrundschreiben (119).
- **Personal, Arbeitszeit und Arbeitsschutz.** Mitarbeiter-Austritt (126);
  Arbeitszeit-Compliance mit MiLoG und ArbZG-Vollregelwerk (131);
  Arbeitsschutz-Register mit Gefährdungsbeurteilung und Unterweisung (132);
  digitale Personalakte (141); Lenk- und Ruhezeiten (144);
  Trainingsmanagement (145); Vertiefung der Personalzeitwirtschaft (103).
- **Datenschutz, Nachweis und Revision.** DSGVO-Auskunft mit echten
  Betroffenendaten (129); Löschkonzept für Personendaten (130);
  GoBD-Verfahrensdokumentation (134); steuerlich anerkanntes Fahrtenbuch (137);
  Fahrzeug-Fristen mit Sperrwirkung (138).
- **Vertrieb, Service und Kundenkontakt.** Leads und Akquise (091),
  Zutritts- und Transponderverwaltung (092), Umfragen und Kundenfeedback (090),
  Wächterrundgänge mit Checkpoints (089) — alle Phase 95;
  Kundenportal-Terminbuchung (087); Folgeauftrag aus offenem Punkt (139);
  Provisionen (146); SMS-Kanal für kritische Alarmierungen (147);
  Altgeräte-Rücknahme und Entsorgungsnachweis (100).
- **Integrationen.** Microsoft 365 mit Graph-Mail für Versand und Eingang
  (102); Etsy-Marktplatz-Plugin (101); Rechnungsdatei-Import zur E-Rechnung
  (104); Kalender-Rückimport aus Google und CalDAV (121); WebDAV als
  Backupziel (123); Toggl- und Clockify-Webhooks (124).
- **KI-Assistenz.** Welle 1 mit Protokoll-Freitexten und Tag-Vorschlägen (143);
  Wellen 2 und 3 mit Zusammenfassen, Erklären und Übersetzen (148).
- **Lernplattform.** Kurse, Prüfungen, Zertifikate und Kompetenzen (149);
  Video-Transcoding mit Auslieferung und Untertiteln (150); der vollständige
  LearnDash-Abgleich aus Phase 98 (`MVP-778`-`MVP-794`).
- **Abo-Verwaltung und Recherche.** Abo- und Lizenz-Reselling-Register (152,
  Phase 91) — löst den zustandslosen Abgleich aus Feature 151 ab, dessen Code
  entfernt wurde; Tätigkeitsrecherche über erledigte Arbeit (153, Phase 92);
  zentrale Notizen (154, Phase 97).
- **Organisations-Kalender-Abo (156, `MVP-816`).** Die öffentlichen Termine
  einer Organisation als tokenisiertes ICS-Abo unter `calendar/org/{token}.ics`.
  Zuvor lag derselbe Inhalt unter einer festen Adresse — ohne Anmeldung und
  über alle Mandanten hinweg.
- **Korrektheit und Sicherheit (`MVP-795`, Phase 99).** Pflichtklassifikationen
  greifen blockierend bei Anlage, Auftragsabschluss und Protokoll-Signatur;
  Architektur-Gate für Rechteschlüssel ohne Prüfstelle; Mandantenfilter im
  Portal-Lerncontroller; Steuerregel für Taxifahrten mit Begründungspflicht;
  Dateiprüfung im E-Rechnungs-Eingang; Scan der Bewerbungsunterlagen;
  Erfassung von Prozedur-Abweichungen; Oberfläche für den SEPA-Lastschriftlauf;
  GAEB-90-Import meldet ungedeutete Satzarten, statt sie still zu verwerfen.
- **Hilfe und Mehrsprachigkeit (`MVP-797`, Phase 99).** Sechs neue
  Hilfe-Themen (E-Learning-Standards, Untertitel, Auskunftsportal, Calendly,
  B2B-Katalog, Portal-Schulungen), alle Erweiterungen in der Hilfe genannt und
  per Architektur-Gate abgesichert; das Homonym „Tag" je Stelle aufgelöst,
  fest verdrahtetes Deutsch im Frontend übersetzt.
- **Endpunkte mit Einstieg (`MVP-798`, Phase 99).** Chat-Kanäle beitreten,
  umbenennen, löschen und angepinnte Nachrichten; Belegungsfenster stornieren;
  Etikettendruck für Variante, Charge und Seriennummer; Karriere-Ausschreibung
  veröffentlichen und pausieren; Domain-Transfer sowie DNS-Einträge hinzufügen
  und löschen; Auftragsverarbeiter bearbeiten und Benachrichtigung Betroffener
  nach Art. 34 vermerken; Modul-Gate der Lernplattform für sieben weitere
  Verwaltungsbereiche; lineare Kurse werden durchgesetzt; gezeichnete
  Unterschrift in der Unterweisung; 22 ausgearbeitete Prozedurvorlagen in fünf
  Branchenprofilen; Austritts-Dialog mit Stichtag und Übergabeliste;
  Oberfläche für die Kompetenzmatrix samt Kompetenz am Kurs. Gezeichnete
  Unterschriften werden über `DataUrlHelper` aus common-toolkit 1.35 geprüft
  (vorher vier app-lokale Kopien).
- **Startseite je Rolle (`MVP-799`, Phase 100).** Die persönliche Startseite
  aus dem Profil wird jetzt tatsächlich angewendet — sie wurde bisher
  gespeichert, aber nie gelesen. Organisationen legen unter „Funktionsumfang"
  eine Startseite je Rolle fest; angeboten und angewendet werden nur Seiten,
  die die Person im Menü sieht. Das Profil zeigt Seitennamen statt
  Routennamen.
- **Kiosk-Modus und QR-/NFC-Check-in (`MVP-800`, Phase 100).** Ein Tablet wird
  über `/kiosk/{token}` zum Stempelterminal (USB-Leser oder NFC des Geräts) und
  stempelt über den vorhandenen Terminal-Ingest. Check-in-Punkte an Standorten
  und Fahrzeugen: QR-Code drucken oder die Adresse auf einen NFC-Aufkleber
  schreiben; Mitarbeitende stempeln angemeldet mit dem eigenen Gerät, optional
  nur im Umkreis — die Position wird geprüft, nicht gespeichert.
- **Legal Hold (`MVP-801`, Phase 100).** Sperrvermerk an Person oder Kunde für
  laufende Betroffenen- und Rechtsverfahren, mit Pflichtbegründung
  (verschlüsselt), Aktenzeichen und Aufhebung nur mit Begründung. Solange er
  aktiv ist, schlägt das Löschkonzept nichts vor, bestätigte Löschungen,
  Anonymisierung und das Löschen von Konten und Kunden (auch per API) werden
  abgewiesen, Standort-Rohpunkte bleiben, und eine Organisation mit aktivem
  Vermerk lässt sich nicht endgültig löschen; bei der Kundenzusammenführung
  wandert der Vermerk zum Ziel. Die Seite „Aufbewahrung & Löschung" hat dabei
  erstmals einen Menüeintrag bekommen.
- **Hinweisgeber-Fallliste und Auslagen-Gegenbeleg (`MVP-802`, Phase 101).**
  Kategorie und Priorität erscheinen in der Fallliste nur noch für Fälle, die
  die Person öffnen darf. Übergebene Auslagen lassen sich per Gegenbeleg
  (Einkaufsgutschrift) korrigieren; die korrigierte Auslage entsteht als Entwurf
  mit Bezug, ein genehmigtes, noch nicht erstattetes Original wird storniert.
- **Abend-Erinnerung und Terminal-PIN (`MVP-803`, Phase 101).** Wer um 19 Uhr
  seit mindestens acht Stunden eingestempelt ist, bekommt einmal je Stempelung
  eine Erinnerung (In-App/Push). Ausweis vergessen: Stempeln mit Personalnummer
  und PIN an Terminal und Kiosk; die PIN liegt nur gehasht vor und ist nach fünf
  Fehlversuchen 15 Minuten gesperrt.
- **Umsatz je Produkt mit Lexoffice und Kupferzuschlag (`MVP-804`, Phase 101).**
  Der Report „Umsatz je Produkt" zählt jetzt auch gespiegelte
  Lexoffice-Rechnungen und -Gutschriften, weist die Quelle je Zeile aus und
  zeigt den Umsatz je Artikelkategorie; an Lexoffice übergebene lokale
  Rechnungen zählen nur einmal. Rechnungs- und Angebotspositionen können den
  Kupferzuschlag zum DEL-Tagespreis als eigene Position anfügen.
- **Acht neue Blocktypen in der Lernplattform (`MVP-806`, Phase 101).** Galerie,
  Audio, Code, Akkordeon, Tabelle, Prozedur, Verständnisfrage und Trenner.
  Galeriebilder brauchen je einen Alternativtext, Audio ein Transkript,
  Tabellen Spaltenköpfe; Akkordeon und Lösung der Verständnisfrage öffnen per
  Tastatur. Freigegebene Übersetzungen ersetzen auch die Texte von Akkordeon,
  Tabelle, Frage und Transkript.
- **Oberfläche, Vertrieb und Demo-Daten (`MVP-807`, Phase 101).** Alle Karten
  laufen über die Kartenkomponente (105 Stellen), ein Architektur-Gate hält das.
  Kunden- und Asset-Kachel auf der Auftragsseite verlinken in Kunden- bzw.
  Produktanalyse. Calendly-Buchungen ohne Kundenbezug können je Organisation
  einen Lead mit der Quelle „Terminbuchung“ anlegen, außer ein Bestandskunde
  kommt infrage. Betriebsmetriken ohne Organisationsbezug und die
  Mandantenliste lassen Demo-Organisationen aus; die Liste zeigt sie per
  Umschalter.
- **Sammlungen (`MVP-809`, Phase 102).** Notizen, Ideenlandkarten,
  Wissensartikel, Dokumente, Lernkurse und Lernpfade lassen sich in Sammlungen
  ordnen — als Baum bis fünf Ebenen, ein Inhalt in mehreren Sammlungen, ohne
  Kopie. Eine Sammlung gibt keinen Zugriff: Jeder sieht darin nur, was er auch
  sonst sehen darf; private Sammlungen nur ihre Verfasserin. Aufnahme über „Zur
  Sammlung hinzufügen“ auf den Detailseiten, Archivieren statt Löschen.
- **Übernahme aus Obsidian und OneNote (`MVP-815`, Phase 102).** Im Einstieg
  „Wissen“ übernehmen Administratoren einen Obsidian-Tresor über eine vorhandene
  Ordner-Anbindung des Cloud-Dokumenteingangs oder ein OneNote-Notizbuch als
  Notizen oder Wissensartikel-Entwürfe: Ordner und Abschnitte werden Sammlungen,
  Schlagwörter wandern mit, `[[Wikilinks]]` werden Verweise, jeder Inhalt zeigt
  seine Herkunft. Nur lesend und auf Anstoß — ein weiterer Lauf übernimmt nur
  Neues. OneNote braucht den zusätzlichen Bereich `Notes.Read` und ist in den
  Microsoft-365-Einstellungen standardmäßig ausgeschaltet. Neue direkte
  Abhängigkeit `symfony/yaml` (war bereits installiert) für die YAML-Köpfe.
- **Kategorie der Wissensartikel wird Sammlung (`MVP-814`, Phase 102).** Die
  bisherige Freitext-Kategorie entfällt; jeder vorhandene Wert wird beim Update
  eine gleichnamige Sammlung, in der die Artikel danach liegen (Known Errors aus
  dem Helpdesk in „Known Errors“). Das Wissensarchiv filtert nach Sammlung, neue
  Artikel lassen sich beim Anlegen direkt einsortieren.
- **Einstieg „Wissen“, Sammeln und Umwandeln (`MVP-813`, Phase 102).** Neue
  Seite „Wissen“: Notizen, Ideenlandkarten, Wissensartikel, Dokumente und
  Lerninhalte in einer Liste oder als Kacheln, links der Sammlungsbaum, oben
  Filter nach Titel, Art und Schlagwort. Mehrere Inhalte — auch Suchtreffer —
  lassen sich auf einmal in eine Sammlung legen. Aus einer Notiz wird per Knopf
  ein Wissensartikel-Entwurf, der auf die Notiz verweist; vertrauliche Notizen
  bleiben davon ausgenommen.
- **Schlagwort und Sammlung in der Recherche (`MVP-812`, Phase 102).** Die
  Suche filtert nach Schlagwort und nach Sammlung (samt Untersammlungen), auch
  ohne Suchbegriff, und zeigt die Schlagwörter der Treffer als anklickbare
  Facette. Gezählt wird nur, was man öffnen darf.
- **Verweise und Rückverweise (`MVP-811`, Phase 102).** Notizen,
  Ideenlandkarten, Wissensartikel, Dokumente, Lernkurse und Lernpfade tragen
  eine Karte „Verweise“: „Verweis setzen“ verbindet zwei Inhalte, „Hier erwähnt
  in“ zeigt nach Art gruppiert alles, was auf die Seite zeigt — auch die
  Verknüpfungen aus Wissensbasis und Ideenlandkarten, die dafür in ein
  gemeinsames Verweismodell umgezogen sind. Kunden-, Projekt- und
  Auftragsseiten zeigen ihre Rückverweise ebenfalls. Quellen erscheinen nur für
  Personen, die sie öffnen dürfen. Beim Zusammenführen von Kunden, Projekten,
  Assets, Lieferanten und Artikeln wandern Verweise jetzt mit; die Artikelseite
  beschriftet Helpdesk-Probleme nicht mehr als „Auftrag“.
- **Schlagwörter für Notizen und Lernkurse (`MVP-810`, Phase 102).** Notizen
  und Kurse lassen sich verschlagworten; zentrale Notizliste und Kurskatalog
  filtern danach, die Recherche findet beides über das Schlagwort. Die
  Filterauswahl zeigt nur Schlagwörter an Inhalten, die man sehen darf.
- **XRechnung in CII-Syntax und für Kleinunternehmer (`MVP-805`, Phase 101).**
  Neues Zustellformat „XRechnung (XML, CII-Syntax)“ für Empfänger, die CII
  verlangen; UBL bleibt Standard, Peppol immer UBL. Wer keine USt-IdNr. hat,
  etwa als Kleinunternehmer, bekommt die Steuernummer zusätzlich als
  Verkäuferkennung in die E-Rechnung — ohne sie lehnt die Prüfung beim
  Empfänger ab. erechnung-toolkit v0.14.
- **Belegimport liest Nachdrucke mit defektem Textlayer (`MVP-808`, Phase 101).**
  pdf-toolkit v0.17.2 und translation-toolkit v0.6. PDFs, deren Textlayer nach
  einem Nachdruck nur Zeichensalat liefert, werden im Rechnungsimport, im
  Auslagen-Scan und bei Quality-Hosting-Rechnungen entziffert; übernommen wird
  das Ergebnis nur, wenn die Summen aufgehen.

### Fixed

- Lizenzierung: Eine frische Installation hatte keinen Public Key und konnte
  deshalb weder Lizenzen noch Release-Manifeste oder den Update-Feed prüfen
  (`public_key_missing`); der Schlüssel lag nur in `storage/license-keys.env`
  des Herausgebers. Der Herausgeber-Public-Key ist jetzt die Vorgabe von
  `license.public_key` in `config/license.php` und damit Teil jeder Auslieferung;
  `LICENSE_PUBLIC_KEY` übersteuert ihn weiterhin, `deploy.sh` versiegelt damit
  auch ohne `license-keys.env`. Der Update-Check dekodierte den Schlüssel bisher
  als striktes Standard-Base64, obwohl `license:keygen` base64url ausgibt — mit
  dem eingebauten Schlüssel wäre jede Feed-Signatur als ungültig verworfen
  worden; er nutzt jetzt denselben Dekoder wie Lizenz- und Manifestprüfung.
  Die Integritätssperre verlangt damit überall ein gegen diesen Schlüssel
  verifizierbares `release.json` statt bei fehlendem Schlüssel durchzuwinken.
  Achtung bei versiegelten Instanzen: `config/license.php` gehört zu den
  versiegelten Dateien — nach dem Update erneut `php artisan license:seal`
  ausführen (`deploy.sh` tut das automatisch), sonst meldet die Instanz
  `tampered`.
- Installer (`php artisan app:install`): Nach „Datenbank konfiguriert & migriert"
  blieb der Befehl ohne weitere Ausgabe stehen. Laravel bindet die Ausgabe der
  Prompts beim Start jedes Commands neu; die inneren Aufrufe von `migrate` und
  `db:seed` ließen sie auf ihrem eigenen Puffer zurück, sodass die Frage nach dem
  Namen der Organisation unsichtbar auf Tastatureingabe wartete. Die Prompts
  werden nach den Migrationen wieder an die Konsole gebunden. Außerdem verwirft
  der CLI-Installer zum Abschluss wie der Web-Installer die Bootstrap-Caches,
  damit ein vorhandener `config:cache` die frisch geschriebenen .env-Werte nicht
  verdeckt.
- Deploy: Während des Updates stand die Wartungsseite ohne CSS und Schriften da.
  Sie wird beim `artisan down` vorgerendert und verweist auf die Hash-Namen des
  laufenden Builds; `npm run build` leerte `public/build`, die Anfrage nach dem
  alten Stylesheet fiel auf `index.php` durch und bekam selbst den 503 des
  Wartungsmodus. `deploy.sh` baut jetzt mit `KEEP_PREVIOUS_ASSETS=1` (Vite leert
  das Verzeichnis nicht) und räumt beim nächsten Deploy nur weg, was der letzte
  erfolgreiche Build nicht mehr geschrieben hat. Offene Tabs finden ihre
  nachgeladenen Chunks damit ebenfalls bis zum nächsten Update.
- E-Rechnung (`MVP-805`, erechnung-toolkit v0.14): XRechnung-Gutschriften in UBL
  waren schemaungültig (`cbc:DueDate` ist in einer CreditNote nicht erlaubt);
  das in ZUGFeRD-PDFs eingebettete CII verletzte die Elementreihenfolge des
  Schemas; Positionsrabatte trugen eine unzulässige Steuerkategorie. Eingehende
  E-Rechnungen ohne USt-IdNr. zeigten die Steuernummer als USt-IdNr.
- Lernplattform-Editor (`MVP-806`): Der Videoblock nahm keine Videos an — die
  Uploadregel kannte nur Bild- und Dokumentendungen. Die Umrechnung aus
  Feature 150 war damit über die Oberfläche nie auslösbar. Außerdem prüft der
  Editor jetzt, dass eine Datei zur Blockart passt (kein PDF im Bildblock).
- Report „Umsatz je Produkt" (`MVP-804`): Der PDF-Fuß behauptete, gespiegelte
  Buchhaltungsbelege trügen keine Positionen; der Hilfetext versprach
  umgekehrt eine Auswertung gespiegelter Belege, die es noch nicht gab.
- Lexoffice-Belegübergabe (`MVP-802`): Auslagen-Push und Zeit-Beleg sendeten
  den Belegtyp als `voucherType` statt `type`; die SDK-Entität verwarf das Feld
  still, der Beleg ging ohne Typ hinaus.
- Sicherheits-Header (`MVP-800`): `Referrer-Policy` und `Permissions-Policy`
  wurden app-weit hart gesetzt und überschrieben seitenbezogene Werte; die
  Ortsabfrage im Browser war damit auf jeder Seite gesperrt. Seiten dürfen die
  Werte jetzt selbst setzen, die Vorgabe bleibt unverändert.
- Export-Löschung (`MVP-798`): gelöschte Exporte wurden hart auf der Ablage
  `local` entfernt, geschrieben aber auf der konfigurierten — bei umgestellter
  Ablage blieben als gelöscht protokollierte Lohndaten-Exporte liegen.
- Kunden-Sonderkonditionen, Pauschal-Modus (Feature 098): vier Lücken aus dem
  ersten Praxiseinsatz. **Bestandszeiten blieben mit 0,00 € bewertet** — Zeiten,
  die vor Anlage der Kondition erfasst wurden, tragen keinen Satz-Snapshot, und
  „Satz neu anwenden" fasste nur Einträge mit gesetztem Konditions-Marker an
  (bei den übrigen wurde kein Feld „dirty", also rechnete auch der Save-Hook
  nicht neu). „Neu berechnen" bewertet sie jetzt nach; manuelle Satz-Overrides
  bleiben unangetastet. **Lexoffice-Zahlungen wurden brutto in einen
  Netto-Saldo gebucht** (voucherlist liefert nur Brutto) — der Nettobetrag wird
  jetzt am Beleg nachgeladen und gecacht, Teilzahlungen anteilig. **Pauschal-
  rechnungen, die direkt in Lexoffice erstellt wurden, waren nicht zuordenbar**
  (der Abgleich kannte nur selbst gepushte Belege) — es gibt jetzt „Beleg
  verknüpfen" am Monat plus einen eng gefassten Auto-Match (Monat, Nettobetrag
  und genau ein Kandidat); bei verknüpftem Beleg entfällt „Pauschale senden",
  damit in Lexoffice keine zweite Rechnung entsteht. **Der Abgleich lief nur im
  stündlichen Cron** — der Belege-Sync an der Kundenakte und der Hintergrund-
  Sync ziehen den Zahlstatus jetzt mit; zudem band `lexoffice:sync-vouchers`
  den Organisations-Kontext nicht, wodurch der Belegabruf den API-Key einer
  fremden Organisation hätte verwenden können.
- Offene Zeiten (MVP-460): Kunden mit laufendem Leistungssaldo (Sonderkonditionen
  im Modus „Kundenkonto" oder „Pauschale") standen in der Fakturierungs-
  Arbeitsliste, obwohl ihre Zeiten nie fakturiert, sondern über den Monatsblock
  der Kundenakte abgerechnet werden — sie wurden erst beim Monatsabschluss
  `exported` und waren bis dahin Dauergäste, die Anzahl, offene Zeit und
  erwarteten Netto-Erlös verfälschten. Sie sind jetzt aus Liste, CSV-Export,
  Digest-Mail und der Massenaktion „Als abgerechnet markieren" ausgenommen; ein
  Hinweis über der Liste nennt die Zahl der ausgeblendeten Einträge, damit die
  Kontrollfunktion erhalten bleibt. Kunden im Modus „monatliche Rechnung"
  bleiben sichtbar — sie laufen über die normale Fakturierung.
- Kunden-Sonderkonditionen, Pauschal-Modus (Feature 098): **die Monatszeile
  zeigte die Zahlung des Vormonats**. Retainer-Rechnungen gehen am Monatsende
  raus und werden Anfang des Folgemonats bezahlt; da Zahlungen bisher strikt
  nach ihrem Zahldatum einsortiert wurden, stand die Januar-Pauschale im
  Februar und der Januar bei „Abgerechnet 0,00 €" — der Endsaldo stimmte,
  die Monatsdarstellung war um einen Monat versetzt. Zahlungen zu einem Beleg,
  der an einem Monat hängt, zählen jetzt in **diesen** Monat (neue Zuordnung
  `customer_account_payments.customer_billing_statement_id`); das echte
  Zahldatum bleibt für den Nachweis erhalten, Bank-, Hand- und Import-
  Zahlungen ordnen sich weiterhin über das Datum ein. Zahlungen in bereits
  abgeschlossenen Monaten behalten ihre Zuordnung.
- Kunden-Sonderkonditionen (Feature 098): **die Tätigkeitsauswahl der
  Anfahrtspauschale bot Kategorien an, die auf Kundenprojekten nie vorkommen**
  (Pause, Krank, Verwaltung …). Angeboten werden jetzt nur Kategorien, die an
  den Zeiten des jeweiligen Kunden tatsächlich auftreten; tragen dessen Zeiten
  gar keine Kategorie, steht dort der Hinweis, dass die Anfahrt für alle
  Einträge gilt.
- Kunden-Sonderkonditionen (Feature 098): **eine Satzänderung wirkte nicht auf
  bereits erfasste Zeiten**. Der Konditionsdialog löschte beim Speichern alle
  Satzzeilen und legte sie neu an; da der Konditionsnachweis am Zeiteintrag
  (`customer_billing_rate_id`) per `nullOnDelete` an der Satzzeile hängt,
  verlor jeder Eintrag seine Zuordnung — auch in abgeschlossenen Monaten —,
  und „Neu berechnen" erkannte ihn danach nicht mehr. Wer den Wochenendsatz
  von 17,50 € auf 18,50 € änderte, sah im offenen Monat weiterhin 17,50 €.
  Satzzeilen werden jetzt anhand von Tätigkeit × Tagtyp fortgeschrieben statt
  ersetzt (mit Soft-Delete für entfernte Zeilen), und der Nachweis am Eintrag
  bleibt erhalten; nur ein tatsächlicher Handeingriff löst ihn noch ab.
  „Neu berechnen" erfasst zudem alle abrechenbaren Zeiten der offenen Monate —
  manuell gesetzte Stundensätze behalten ihren Satz.
- Kunden-Sonderkonditionen, Pauschal-Modus (Feature 098): **„Beleg verknüpfen"
  brach nach einem vorherigen Lösen/Storno mit einem Datenbankfehler ab**
  (Duplicate entry für `uq_cap_source_ref`). Die stornierte Zahlung wird nur
  soft-deleted, blockiert den Unique-Index aber weiter, während
  `updateOrCreate` sie nicht mehr fand und neu anlegen wollte. Der
  Zahlungs-Rücksync sucht die Zeile jetzt inklusive stornierter Einträge und
  belebt sie wieder, statt eine zweite anzulegen.
- Rechnungs-Detailseite: Inline-`@php(...)` in Kombination mit einem
  `@php … @endphp`-Block in derselben View erzeugte über Blades
  Raw-Block-Erkennung ein ungültiges `<?php(` (kein PHP-Open-Tag) — der
  Block wurde nie ausgeführt („Undefined variable $showServiceDates").
  Alle `@php`-Vorkommen der View nutzen jetzt die Blockform.

### Security

- **Datenfluss-Sicherheitsaudit 2026-09-17: alle schweren und mittleren Befunde
  behoben.** Anlass waren zwei CodeQL-Warnungen; geprüft wurde in neun
  Lückenklassen (Quelle → Senke), 54 Befunde, davon 5 schwere und 20 mittlere.
  Im Einzelnen:
  - **Zeitkorrektur-Anträge** schreiben nur noch erlaubte Felder auf eigene
    Zeit- und Anwesenheitseinträge; Mandant und Person sind erzwungen.
  - **Karten und Ideenlandkarten** escapen Namen und Beschriftungen, bevor sie
    in Popups bzw. in die Landkarte gehen; URL-Ziele aus dem DOM müssen
    gleiche Herkunft haben.
  - **Weiterleitungen der Plugin-Clients** werden erneut gegen die
    SSRF-Schranke geprüft; der FTP-Katalogabruf ignoriert die PASV-Adresse des
    Servers; Webhook-Zustellung und Web-Push binden die geprüfte IP an die
    Verbindung (DNS-Rebinding).
  - **Legacy-Bereich**: Migration nur für Plattform-Betreiber und ohne
    Überschreiben bestehender Konten; Archiv und Tagebuch prüfen das Eigentum
    je Eintrag.
  - **Auswertungen, Supportbericht, Auftrags-Export und Touren** prüfen jetzt
    Recht und Sichtbereich statt nur die Organisation.
  - **SSO**: E-Mail-Verknüpfung und automatische Kontoanlage nur für
    nachgewiesene SSO-Domains, bei OIDC zusätzlich nur mit vom Anbieter
    bestätigter E-Mail-Adresse.
  - **Anmeldemittel**: Passkey anlegen, API-Token ausstellen und die
    Anmelde-E-Mail wechseln verlangen eine frische Anmeldung bzw. das
    Passwort; die bisherige Adresse wird über den Wechsel informiert, neue
    Passkeys werden gemeldet.
  - **Sitzungen**: deaktivierte Konten und widerrufene Portalzugänge fliegen
    sofort aus laufenden Sitzungen, ein Passwortwechsel entwertet fremde
    Sitzungen unabhängig vom Sitzungsspeicher. `system:health` warnt, wenn
    `SESSION_DRIVER` nicht `database` ist (dann wirkt nur die gezielte
    Fernabmeldung einzelner Sitzungen nicht).
  - **Lizenz**: Die Domainbindung wird gegen `APP_URL` geprüft statt gegen den
    Host-Header — ein fremder `Host:` kann weder den Lizenz-Cache vergiften
    noch die Betreiberlizenz ersetzen.
  - **Helpdesk-Mail**: Eine Mail mit `[TICKET-NO]` im Betreff landet nur dann
    als kundensichtbare Antwort im Vorgang, wenn der Absender dazugehört.
  - **Anonyme Portale** (Meldeportal, Betroffenenportal, Karriere) bekommen
    eine eigene Sitzung; ihre Sitzungszeile nennt kein Konto mehr.
  Die 29 leichten Befunde sind im Bericht dokumentiert und offen.
- **Drei neue Architektur-Gates** halten den Stand: URL-Senken im Frontend
  müssen über `sameOriginPath()` laufen, `{!! … !!}` in Blade steht auf einer
  begründeten Liste, und rohes SQL darf keine Variablen im String tragen.
- **Die 29 leichten Befunde des Datenfluss-Audits sind ebenfalls behoben.**
  Im Einzelnen:
  - **Rechte**: Kommentare am Auftrag und Tickets über die REST-API prüfen das
    Recht am Vorgang; private Notizen lassen sich nicht mehr von Dritten in
    „intern vertraulich" umwandeln; Fahrzeuge anlegen und ändern verlangt das
    Recht „Fuhrpark verwalten".
  - **Anhänge** folgen der Sichtbarkeit ihres Trägers, auch wenn dieser keine
    eigene Regel hat (Ticket-Notizen, Lern-Abgaben, Prüfprotokolle).
  - **Dateien**: Hochgeladenes Rechnungs-XML wird in der Vorschau als Text
    ausgeliefert, Ordnernamen im Toggl-Import können den Stammordner nicht mehr
    verlassen, die gespeicherte Dateiendung stammt aus dem erkannten Typ, und
    ein Vergabe-ZIP schleust keine fremden Dateitypen ins Dokumentenarchiv.
  - **Oberfläche**: Kostengruppen-Codes aus GAEB-Dateien und der Name eines
    Design-Basisprofils stehen nicht mehr in Alpine-Ausdrücken; die
    Offline-Outbox gehört dem angemeldeten Konto (Kontowechsel leert sie).
  - **Exporte**: Compliance-Nachweise und das Monatsabschluss-Bündel laufen
    durch den Formel-Guard; Maschinenformate (DATEV, GoBD-Z3) bleiben
    ausdrücklich unverändert.
  - **Anmeldung**: Zwei-Faktor-Versuche zählen zusätzlich je Konto (danach ist
    die Anmeldung neu zu beginnen, mit Hinweis an die Person); das Portal-Login
    meldet das Gerät erst nach dem zweiten Faktor; Terminal-PINs sind
    sechsstellig, die Sperre eskaliert (15 min → 1 h → 1 Tag) und verrät bei
    PIN-Nutzung keine Salden mehr; der QR-Check-in verlangt den Aufruf über den
    signierten Link.
  - **Zugänge enden mit dem Austritt**: Kalender-Abo und Standort-Gerätetoken
    werden widerrufen, externe Lernzugänge bei jedem Aufruf neu geprüft.
  - **Mandanten**: Gesperrte Mandanten sind auch über Token-Links und SCIM
    dicht; installationsweite Betriebsdaten (Advisories, Update-Hinweise,
    Plugin-Schema, globale Plugin-Fehler) ändert nur der Plattform-Betreiber;
    eine SSO-Domain blockiert andere erst mit DNS-Nachweis.
  - **SSRF**: Der Betreiber-Schalter für private Netze wirkt jetzt überall,
    der Geocode-Cache gilt je Anbieter, CalDAV/CardDAV folgen keinen fremden
    Adressen aus Server-Antworten, und die Sperrliste kennt CGNAT, NAT64,
    6to4 und Teredo.
