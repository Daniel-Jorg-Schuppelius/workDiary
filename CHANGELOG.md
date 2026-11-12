# Changelog

Alle nennenswerten Änderungen an WorkDiary werden in dieser Datei dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt [Semantic Versioning](https://semver.org/lang/de/)
(siehe `release-prozess.md` im Doku-Repository WorkDiary-Architecture).

## [Unreleased]

## [1.2.0] - 2026-11-12

Phasen 174–176 seit 1.1.0: Lückenschluss aus dem Nachscan vom 2026-11-09,
Reselling-Register (Einkauf, Anbieterrechnungen aus dem Buchhaltungsprogramm,
Anschlüsse), Legacy-Bereich mit dem Support-Portal des Altsystems und der
Lückenschluss aus dem Ganzbestands-Scan vom 2026-11-10 (MVP-1546–1629) samt
Umsatzsteuer der Empfängerseite, Datenführerschaft in allen Bereichen,
Offline-Erfassung von Zeit und Material und sechs Architektur-Gates gegen
Funktionen ohne Wirkung. Die Tags `v1.0.0` (Commit `067af1fa3`), `v1.1.0`
(Commit `45958a9d6`) und `v1.2.0` sind lokal gesetzt; veröffentlicht werden
sie mit dem nächsten Push.

### Hinzugefügt

- Umsatzsteuer der Empfängerseite (`MVP-1621`): Eine Eingangsrechnung mit
  Steuerschuld des Empfängers – § 13b UStG (Abs. 1 sonstige Leistung aus dem
  übrigen Gemeinschaftsgebiet, Abs. 2 etwa Bauleistungen, Gebäudereinigung,
  Werklieferungen ausländischer Unternehmer) oder innergemeinschaftlicher
  Erwerb – bucht Aufwand netto an Verbindlichkeit und dazu Umsatzsteuer im
  Haben und dieselbe Vorsteuer im Soll. Kennziffern 46/47 bzw. 84/85, beim
  Erwerb 89 bzw. 93, die Vorsteuer in 67 bzw. 61; die kontenbasierte
  Umsatzsteuer-Vorschau zeigt beide Seiten (auch bei Ist-Versteuerung zum
  Rechnungsdatum). Der Steuerfall kommt aus der Steuerkategorie der E-Rechnung
  (`AE`, `K`) und dem Land des Lieferanten, sonst aus der neuen Vorgabe
  „Steuerfall seiner Eingangsrechnungen“ am Lieferanten; auf der Detailseite
  des Eingangs lässt er sich ändern. Neue Kennzeichen-Richtung „Umsatz- und
  Vorsteuer“ mit Vorsteuerkonto, Steuerfall und Vorsteuer-Kennziffer; die
  Vorlagen bringen RC13BEU, RC13B, IGE19 und IGE7 samt Konten (SKR03 1572,
  1574, 1577, 1772, 1774, 1787; SKR04 1402, 1404, 1407, 3802, 3804, 3837) mit.
  Skonto auf einen solchen Posten mindert Umsatz- und Vorsteuer um denselben
  Betrag (§ 17 Abs. 1 Satz 5 UStG). Handbuchung und „Auf Sachkonto buchen“
  erzeugen bei solchen Kennzeichen beide Steuerzeilen. Der Bericht
  „Buchungsqualität“ nennt festgeschriebene Eingangsrechnungen mit
  Steuerschuld des Empfängers am Beleg, aber ohne Steuerbuchung.
- Umsatzsteuer (`MVP-1622`): Eingangsrechnungen mit mehreren Steuersätzen
  buchen Aufwand und Vorsteuer je Satz (aus der Steueraufschlüsselung der
  E-Rechnung bzw. dem Werte-Dialog), mit dem Vorsteuerkennzeichen des Satzes;
  Kennziffer 66 und das Skonto je Satz stimmen damit. Kunden lassen sich als
  „Leistungsempfänger schuldet die Umsatzsteuer (§ 13b UStG)“ führen: Rechnungen
  an sie gehen im Inland ohne Umsatzsteuer und mit Pflichthinweis hinaus,
  Kennziffer 60 über das neue Kennzeichen RC60 (Erlöskonto 8337 bzw. 4337).
  Innergemeinschaftliche Lieferungen buchen mit der Vorlage auf 8125 bzw. 4125.
  Die Zusammenfassende Meldung führt auch die sonstigen Leistungen
  (Kennziffer 21) je Empfänger mit ihrer Art; die Schwelle für die monatliche
  Meldung zählt weiter nur Lieferungen.
- Arbeitszeitkonto (Phase 176, `MVP-1550`): Der gespeicherte Gleitzeitsaldo wird
  jetzt geschrieben. `flex:recompute` läuft nachts je Organisation für alle
  aktiven Personen mit Sollführung über Vormonat und laufenden Monat
  (`--from=JJJJ-MM` rechnet weiter zurück), die Monatsfreigabe und ihre
  Wiederöffnung rechnen den Monat und alle Folgemonate neu. Der Saldo ist der
  laufende Kontostand (Übertrag des Vormonats plus Ist minus Soll); der
  laufende Monat zählt bis gestern. Kachel „Arbeitszeitkonto“,
  Terminal-Anzeige, Prognose und „Flex Δ“ im Personalbericht zeigen damit
  Werte statt „Noch kein Saldo“.
- Tagesabschluss (`MVP-1551`): Der abgeschlossene Tag sperrt auch die
  Projekt- und Verwaltungszeiten des Tages — Anlegen, Ändern und Löschen über
  Erfassung, Eingabeleiste, Schnellbuchung, Stoppuhr, Neuzuordnung,
  Stundenzettel-API und CSV-Import. Änderungen laufen über den
  Korrekturantrag; nach dessen Freigabe sind die Buchungen wieder änderbar.
  Die Seite „Heute“ blendet die Eingabeleiste aus und nennt den Weg.
- Anwesenheit (`MVP-1552`): Homeoffice- und Dienstgang-Minuten stehen in der
  Stempelliste, in der Tagesansicht, im Tagesabschluss und im Bericht
  Anwesenheit samt CSV- und PDF-Export.
- Zeitkorrektur (`MVP-1552`): Ein Korrekturantrag kann den Auftrag einer
  Zeitbuchung ändern oder lösen; der Dialog bietet die Aufträge des Projekts.
- Plan/Ist (`MVP-1552`): Der Kopf des Tages zeigt Soll und Kernzeitbeginn
  neben erster und letzter Stempelung mit Warnmarken, der Auftrag die
  Kennzahl Plan/Ist. Die Schwellen „später Beginn“ und „Stundenabweichung“
  sind je Organisation einstellbar (Vorgabe 15 Minuten, 10 %).
- Schichttausch (`MVP-1553`): Der Antragsdialog lässt eine Gegenschicht
  wählen (Schicht gegen Schicht); die Liste zeigt den Tausch. Verstößt die
  neue Zuordnung gegen Arbeitszeitregeln, gibt die Leitung nur über den Dialog
  „Prüfen und freigeben“ mit Begründung frei; Begründung und übersteuerte
  Regeln stehen im Audit-Protokoll.
- Urlaub (`MVP-1554`): „Korrektur anfordern“ gibt einen ausstehenden Antrag
  mit Hinweis zurück (Status „Korrektur angefordert“); die antragstellende
  Person wird benachrichtigt, findet ihn im Arbeitsvorrat, ändert ihn und
  reicht ihn mit dem Speichern neu ein.
- Tätigkeitskategorien (`MVP-1554`): „Zählt als Arbeit“ wirkt in
  Arbeitszeitkonto, Tagesabschluss und Arbeitsbilanz, „Standardmäßig
  abrechenbar“ belegt die Abrechenbarkeit neuer Verwaltungszeiten vor.
- Buchhaltung (`MVP-1555`): Skontokonto und Ausbuchungskonto des
  OPOS-Ausgleichs stehen im DATEV-Einstellungsformular (leer = Vorgabe des
  Kontenrahmens). Die Vorgabekonten der Ausbuchung (SKR03 2400, SKR04 6930
  „Forderungsverluste“) sind in beiden Kontenplan-Vorlagen; bestehende
  Kontenpläne aus der Vorlage bekommen das Konto beim Update nachgetragen.
  Verbindlichkeiten haben eigene Gegenkonten mit eigenen Einstellungsfeldern:
  erhaltene Skonti (SKR03 3736, SKR04 5736) und für die Ausbuchung ein
  sonstiger Ertrag (SKR03 2709, SKR04 4839) statt gewährter Skonti und
  Forderungsverluste.
- Skonto mit Steuerberichtigung (`MVP-1555`, § 17 UStG): Der Skonto-Ausgleich
  an offenen Posten und das Skonto aus dem Zahlungsabgleich teilen den Betrag
  nach dem Steuersatz des Belegs in Netto und Steueranteil – an Forderungen
  mindert er die Umsatzsteuer, an Verbindlichkeiten die Vorsteuer, bei
  mehreren Sätzen anteilig. Kennziffern der Voranmeldung und
  Umsatzsteuer-Vorschau zeigen die Minderung. Je Satz gilt das Skontokonto des
  Rahmens (19 %: 8736/3736 bzw. 4736/5736, 7 %: 8731/3731 bzw. 4731/5731),
  solange kein eigenes Konto eingestellt ist. Ohne Steuerbezug
  (Kleinunternehmer, Reverse Charge, steuerfrei, Startsaldo) bleibt das Skonto
  ungeteilt; der Dialog sagt, was gilt.
- Umsatzsteuer-Kennziffern (`MVP-1558`): Buchungen aus der Buchungs-Inbox
  tragen Steuerkennzeichen und Steuerbetrag an der Erlös- bzw. Aufwandszeile;
  die Bemessungsgrundlage ist damit exakt. Umsätze ohne Steuer erscheinen in
  ihrer Kennziffer: innergemeinschaftliche Lieferung (41, damit auch in der
  Zusammenfassenden Meldung), sonstige Leistung mit Steuerschuld des
  Empfängers (21), Ausfuhr (43), nicht steuerbar (45); neue Kennzeichen RC21,
  AUSF und NSTB in den Vorlagen. Ältere Buchungen bleiben unverändert und
  werden weiter richtig gelesen.
- Buchungsjournal (`MVP-1556`): Entwürfe lassen sich bearbeiten (Datum, Text,
  Beleg, Gegenpartei, Zeilen) und verwerfen – auch Handbuchungen, Entwürfe aus
  Buchungsvorlagen und aus Inbox-Vorschlägen; der Vorgang einer verworfenen
  Buchungsvorlage gilt als übersprungen. „Kostenstellen“ trägt an jedem Entwurf
  die Kostenstelle je Zeile nach. Wer einen Entwurf ändert, gilt als
  Vorbereiter (Vier-Augen-Prinzip). Festbuchungen bleiben unveränderlich.
- Zahlungsabgleich (`MVP-1557`): „Beleg suchen“ ordnet einem Einzelumsatz jede
  offene Rechnung, freigegebene Spese oder ein Kundenkonto zu – über die
  Vorschläge hinaus, mit Teil- und Überzahlung, Art und Notiz. „Auf Sachkonto
  buchen“ bucht einen Umsatz ohne Beleg (Bankgebühr, Zinsen, Privat,
  Verrechnung) direkt Bank an Sachkonto, auf Wunsch mit Steuerkennzeichen und
  Kostenstelle; der Umsatz gilt danach als beiseitegelegt. Wird der
  Vier-Augen-Entwurf dieser Buchung verworfen, steht der Umsatz wieder offen.
- Buchhaltung (`MVP-1558`): Steuerkennzeichen lassen sich anlegen, ändern und
  stilllegen; Satz, Richtung und Steuerkonto eines bebuchten Kennzeichens
  wechseln nur zum Stichtag (neue Fassung, Altbuchungen bleiben). Handbuchung
  und Buchungsvorlage buchen mehrere Zeilen mit Steuerkennzeichen
  (Nettobetrag, Steuerzeile automatisch), Kostenstelle je Zeile und
  Gegenpartei; solche Buchungen stehen in den Kennziffern der Voranmeldung.
  Das Konto wird im Dialog als Kontonummer mit Vorschlagsliste erfasst.
- Offene Posten (`MVP-1559`): „Als strittig kennzeichnen“ mit Grund und
  „Strittig zurücknehmen“, Statusfilter in der Liste. Ein strittiger Posten
  wird nicht gemahnt (Mahnsperre an der Rechnung) und zählt in
  Liquiditätsübersicht und -vorschau nicht als erwarteter Eingang. Die
  Eingangsrechnung einer strittigen Verbindlichkeit ist im Zahlungsvorschlag
  gesperrt.
- Sicherheitseinbehalte (`MVP-1560`): Die Rechnungsseite zeigt die Karte
  „Sicherheitseinbehalte“ mit Art, Betrag, Freigabetermin und Status. An der
  ausgestellten Rechnung gibt ein Dialog mit Notiz den Einbehalt frei – die
  Route gab es, einen Knopf nicht.
- Bürgschaften (`MVP-1561`): Ein durch Bürgschaft abgelöster Einbehalt lässt
  sich freigeben. Das beendet nur den Sicherungszweck: Der Auszahlungstag
  bleibt, es entsteht keine neue Forderung, und die Urkunde wird zur Rückgabe
  gemeldet. Neu ist „Als verfallen vermerken“; die tägliche Fristenprüfung
  setzt Bürgschaften mit überschrittener Befristung auf „Abgelaufen“ (der
  letzte Tag zählt noch). Verfallene zählen nicht mehr als aktiv, ihre Urkunde
  kann weiter als zurückerhalten vermerkt werden, und eine nachgetragene
  Verlängerung macht sie wieder aktiv.
- Fakturierung (`MVP-1562`): „Freigabe vor der Ausstellung verlangen
  (Vier-Augen-Prinzip)“ und „Nur validierte E-Rechnungen ausstellen“ stehen in
  den Organisationseinstellungen. Die Ausstellung las beide Schalter schon,
  setzen ließen sie sich nicht.
- Zählerabrechnung (`MVP-1563`): Der Dialog der Vereinbarung erfasst
  Mengenstaffel (Zeilen „ab Menge“ und „Preis je Einheit“), Status und Projekt.
  Die Liste zeigt „Staffel“ statt des Einheitspreises und den Status als
  Plakette; „Jetzt abrechnen“ steht nur bei aktiven Vereinbarungen.
- Gewährleistung (`MVP-1564`): Fristen lassen sich bearbeiten; ein verschobenes
  Ende zieht gekoppelte offene Einbehalte nach, gekoppelte Bürgschaften folgen
  dem neuen Ende. „Rüge erfassen“ setzt den Status „Gerügt“ und verknüpft auf
  Wunsch die Reklamationsakte. Die Wahl eines Abnahmeprotokolls belegt den
  Fristbeginn mit dem Abnahmetag vor.
- Mitarbeiter (`MVP-1565`): Stundensatz (Verrechnung) und interner Kostensatz
  je Person stehen im Mitarbeiter-Dialog. Sichtbar und änderbar sind sie nur
  mit dem Recht „Personal-/Lohndaten verwalten“; Änderungen werden
  protokolliert. Bisher ließen sich beide nur per CSV-Import setzen.
- Arbeitsvorrat (Phase 174, `MVP-1525`): Überstundenanträge, Zeitkorrekturen
  (Freigabe und Anwenden), Tagesabschluss-Korrekturen, Dienstpläne zur Freigabe,
  Schichttausch (erst die Annahme durch die angefragte Person, dann die
  Planung), Personalakten-Einreichungen, Kundenanfragen aus dem Portal,
  Verleihanfragen, Kursbuchungen, Lieferanten-Selbstauskünfte und
  Vereinsgruppen-Zuordnungen erscheinen bei der zuständigen Person — mit
  demselben Recht wie auf der Entscheidungsseite, eigene Anträge nie.
- Genehmigungen (`MVP-1522`): Investitionsanträge stehen mit ihren
  Freigabestufen im Genehmigungs-Eingang und im Arbeitsvorrat; die zuständige
  Stufe wird beim Einreichen, beim Nachtrag und beim Stufenwechsel
  benachrichtigt. Die Geschäftsleitungsstufe ist der Geschäftsführung
  zugeordnet (Einstellung „Freigabestufe Geschäftsleitung“ in der
  Organisation). Eigene Anträge erscheinen im Genehmigungs-Eingang nie mehr.
  An der Investitionsakte gilt dieselbe Stufenzuständigkeit (`MVP-1541`):
  Freigeben und Ablehnen nur mit der Rolle der offenen Stufe, auch für Admins;
  trägt niemand die Rolle, bleibt der Antrag offen.
- Lieferanten (`MVP-1526`): „An Buchhaltung übertragen“ in der Lieferantenakte
  und `accounting:push-contacts <plugin> --suppliers` übertragen auch bestehende
  Lieferanten an sevDesk, easybill, orgaMAX oder Lexoffice.
- sevDesk (`MVP-1529`): Führt die Buchhaltung die Stammdaten, übernimmt der
  Kontakt-Rückweg auch Anschrift, E-Mail, Telefon, Mobil, Fax und Webseite.
- Kalender-Abos (`MVP-1531`): Ein Abo kann statt einer iCal-Adresse eine
  Kalenderverbindung (CalDAV, Google, Microsoft 365) nutzen und wird stündlich
  mit `ical:sync` abgeglichen.
- Demo-Mandant (`MVP-1532`, `MVP-1533`): Showcase-Daten für Planung
  (Dienstplan, Mindestbesetzung, Schichttausch), Formulare, Wissen, Lager
  (Bestand unter Meldebestand), Spesen, Team-Auswertungen (Zeiten mehrerer
  Personen über drei Wochen), Standorterfassung, Verträge, Liegenschaften und
  Bau (GAEB-Leistungsverzeichnis mit Aufmaß) — je nach Modul-Empfehlung der
  Musterbranche oder im Vollumfang; der Reset räumt sie mit ab.
- Verleih (`MVP-1523`): Eine Online-Vorauszahlung, die ans Buchhaltungsprogramm
  übergeben ist, gilt als angerechnet; die Akte sagt das. Mit lokaler
  Buchhaltung wird sie als erhaltene Anzahlung gebucht (Umsatzsteuer bei
  Zahlungseingang, SKR03 1718/1711, SKR04 3272/3260) — seit `MVP-1542` auch,
  wenn die lokale Rechnung erst später entsteht; die Rechnung löst die
  Anzahlung auf, ein Stornobeleg dreht das zurück (Mindest-Istversteuerung).

- Abos & Lizenzen (Phase 175, MVP-1534–1540): Anbieterrechnungen, die
  nur im Buchhaltungsprogramm liegen, werden täglich als Einkauf übernommen
  (Lexoffice, Anbieter mit lesbaren Positionen; der Rechnungseingang führt,
  Dubletten über die Rechnungsnummer; Job `resale.import_incoming`). Der
  Überblick meldet Anbieter ohne zugeordneten Lieferanten und Abos, deren
  Einkaufspreis dem Anbieterpreis nicht mehr folgt; die Preisprüfung übernimmt
  den Anbieterpreis je Abo. Das Register speist Arbeitsvorrat, globale Suche
  (Abos, Lizenzprodukte), Leitungssicht (Perioden ohne Rechnung, Marge),
  Frühwarnung (Empfänger ab 90 Tagen ohne Rechnung), die Auskunft nach Art. 15
  und den Demo-Mandanten.
- Legacy-Bereich: Das Support-Portal des Altsystems ist nachgezogen. Das Büro
  (Legacy-Admins und Plattformbetrieb) sieht die Kundenaufträge unter
  „Support-Portal“ mit Jahr, Kunde, Suche und Statuskacheln, trägt Termin,
  Mechaniker, Antwort und interne Notiz ein, legt Aufträge für Kunden an,
  verwaltet die Kundenzugänge („Portalkunden“), archiviert erledigte Aufträge
  und schaltet den Mail-Hinweis bei neuen Kundenaufträgen. Kunden melden sich
  unter `/legacy/support-portal` mit ihrem Zugang an, sehen nur die eigenen
  Aufträge, erteilen neue und ändern ihr Passwort. Schreiben folgt
  `LEGACY_WRITE_ENABLED`; `legacy:import-plan` zählt die Bestände des Portals.
- Legacy-Migration: Das Support-Portal lässt sich in eine Organisation
  übernehmen (`legacy:import --support --organization=<ID|Kürzel>` oder Zeile
  „Support-Portal“ im Migrations-Dashboard). Kundenzugänge werden Kunden,
  Aufträge und Archiv Servicetickets `KP-<Nr.>` mit Termin, Mechaniker, Antwort
  und interner Notiz; ohne SLA-Fristen, Routing und Webhooks, wiederholbar.
  Passwörter werden nicht übernommen.
- Helpdesk (Phase 176, `MVP-1548`): Das Standard-SLA der Queue wirkt — der
  Vertrag eines neuen Tickets ergibt sich aus Projekt, Kunde, Queue,
  Organisationsvorgabe, in dieser Reihenfolge. Am Queue-Formular lassen sich
  unterstützte Ticketarten, Geschäftszeiten, Feiertagsregion, Absendername und
  Antwortadresse pflegen; Routing, Board, Fristberechnung und Antwortmail
  richten sich danach. Extern geführte Queues (Zammad) sind an Queue und Ticket
  gekennzeichnet, Statuswechsel und Kundenantworten dort gesperrt; der
  Abschluss kommt aus Zammad, und beim Zurückstellen des Ticketziels führt
  WorkDiary die Queue wieder selbst. Am SLA-Vertrag sind Eskalationsstufen und
  das OLA-Team pflegbar; ein OLA wird nie als Kunden-SLA gezogen und erscheint
  als internes Ziel an der SLA-Uhr der Tickets des Teams. Tickets lassen sich
  bei Anlage und Klassifizierung als vertraulich kennzeichnen; Liste und Board
  zeigen sie nur Berechtigten.
- Service Desk (`MVP-1549`): Mitarbeitende bestellen Katalogeinträge unter
  „Leistung bestellen“ (neues Recht „Leistungen aus dem Servicekatalog
  bestellen“, alle internen Profile außer Support). Die Angaben des
  Bestellformulars und das Erfüllungsobjekt stehen am Ticket, die Angaben auch
  im Entscheidungsdialog der Genehmigung. Antworten zeigen den Zustellstatus
  ihrer E-Mail. Aus einem offenen Punkt lässt sich ein Ticket eröffnen
  (höchstens eines je Punkt, mit Verweis am Punkt).
- Helpdesk (Nachzug Helpdesk zu `MVP-1546`–`MVP-1549`): Abnahme und
  Wiederöffnung im Kundenportal laufen über den Fachdienst wie intern; in
  extern geführten Queues sind Antwort, Abnahme und Wiederöffnung auch im
  Portal gesperrt. Beim Queue-Wechsel von Hand gilt das Standard-SLA der neuen
  Queue, wenn der Vertrag bisher aus Queue oder Organisation kam oder fehlte.
  Das Eingangspostfach der Queue ist im Formular pflegbar (eines je Queue).
  Vertrauliche Tickets erscheinen in Auswahllisten, Verweisen, Projekt- und
  Auftragssicht nur für Berechtigte und in Berichts-Drilldowns maskiert. Neues
  Benachrichtigungsereignis „Internes Ziel (OLA) überschritten“: einmal an die
  Teamleitung des OLA-Teams, mit Eskalation nach den Benachrichtigungsregeln.
- Prozeduren (`MVP-1566`): Die Lauf-Ansicht zeigt am Backup-Schritt die Karte
  „Backup-Nachweis“. „Backup-Nachweis erfassen“ nimmt gesichertes System,
  Umfang, Zeitpunkt, Ablage (Anhang oder externe Ablage) und Prüfweg auf; eine
  hochgeladene Datei wird gegen die erwartete Prüfsumme geprüft und der
  Nachweis dann selbsttätig bestätigt. Restore-Test und Bestätigung durch die
  Leitung bestätigt eine zweite Person mit dem Recht „Backup-Nachweis
  verifizieren“; „Nachweis ablehnen“ verlangt eine Begründung. Den Ablageort
  einer externen Sicherung sieht nur, wer „Externe Backup-Ablagen einsehen“
  darf. Damit lassen sich Backup-Schritte und Schritte mit „Vorheriges Backup
  nötig“ erstmals über die Oberfläche abschließen; ist kein Backup möglich,
  bleibt die begründete Abweichung.
- Prozeduren (`MVP-1567`): Abweichungen stehen mit Art, Schweregrad,
  Begründung und Folgemaßnahme am Schritt. Eine kritische Abweichung ohne
  offenen Punkt und ohne Folgeauftrag sperrt den Abschluss, bis jemand mit dem
  Recht „Kritische Abweichung akzeptieren“ das Risiko mit Begründung
  akzeptiert; Person, Zeitpunkt und Begründung stehen im Verlauf des Laufs.
  Der Bericht „Prozedur-Abweichungen“ bekommt damit Daten für „Risiko
  akzeptiert“ und „Ø Stunden bis Entscheidung“.
- Prozeduren (`MVP-1568`): Die Qualifikation am Schritt und die
  Mindestqualifikation der zweiten Person werden gegen die gültigen
  Qualifikationen der Person geprüft (Kürzel, ersatzweise Name). Bisher war ein
  Schritt mit eingetragener Qualifikation für niemanden ausführbar.
- Prozeduren (`MVP-1569`): Designer und Schrittbibliothek pflegen die
  Konfiguration je Schrittart: Auswahl-Optionen, Einheit und Grenzen,
  feste Wartezeit, „Vorheriges Backup nötig“, Vorgaben für den Backup-Nachweis
  sowie Rolle und Qualifikation der zweiten Person. Ein Auswahlschritt ohne
  Optionen nimmt im Lauf freien Text.
- Prozeduren (`MVP-1570`): Eine Version mit Risikostufe „Kritisch“ lässt sich
  nur mit mindestens einem Vier-Augen-Schritt veröffentlichen, und ihr Lauf
  wird erst mit einer erledigten, gegengezeichneten Freigabe abgeschlossen.
- Prozeduren (`MVP-1571`): Die automatische Zuordnung kennt zusätzlich Kunden,
  Asset-Kategorien, Produkte und SLA-Verträge; alle sind im Designer wählbar.
  Vorlagen mit Asset-Kriterium stehen an der Asset-Akte in der Karte
  „Prozeduren“ und lassen sich dort starten. An einem gesperrten Asset startet
  nur eine Vorlage mit „Darf an gesperrten Assets laufen“.
- Protokolle (`MVP-1572`): Unterschriften werden gezeichnet und gedruckt. Der
  Dialog „Abschließen / Unterschreiben“ und der Signaturlink haben ein
  Unterschriftsfeld; das Bild liegt als Anhang am Protokoll, steht bei den
  Signaturen und im PDF mit Name, Rolle, Zeitpunkt und Art. Die Papierabnahme
  lädt Foto oder Scan der unterschriebenen Seite hoch und bestätigt, dass das
  Original vorliegt. Kunden nehmen ein kundensichtbares Protokoll, das zur
  Prüfung eingereicht ist, im Kundenportal an Auftrag oder Objekt unter „Zur
  Abnahme“ ab. Der Signaturlink geht zusätzlich per E-Mail an die
  unterzeichnende Person (abschaltbar). Das PDF des Abschlusses liegt als
  Anhang am Protokoll.
- Protokolle (`MVP-1573`): Tags im Anlage- und Bearbeitungsdialog und an der
  Detailseite; Anhänge am ganzen Protokoll mit Verlaufseintrag, nur im
  Entwurf; „Punkte umsortieren“ per Ziehen, Unterpunkte bleiben bei ihrer
  Gruppe. Die Organisationseinstellung „Aufnahmeort (GPS) aus Protokollfotos
  übernehmen“ (Datei-Uploads, Standard aus) entscheidet, ob Koordinaten
  gespeichert werden; angezeigt werden sie mit dem Recht
  `protocol.item.photo.viewGeo`.
- Assets (`MVP-1574`): „Außer Betrieb nehmen“ an der Asset-Akte mit Datum und
  Grund; Wartungspläne ruhen danach, Prüfpflichten werden deaktiviert, offene
  Prüftermine storniert, und das Asset fällt aus Ausgabe, Verleih,
  Reservierung, Druck und Prozedurstart. „Eigentümer wechseln“ schreibt jeden
  Wechsel mit Von, Nach und Notiz in die Eigentümerwechsel-Historie der
  Objektakte; ein anderer Kunde im Bearbeiten-Dialog ist derselbe Wechsel. Die
  Status „Gesperrt“ und „In Reparatur“ legen eine Sperre im gemeinsamen
  Sperrmodell an und sperren damit überall, auch den Prozedurstart an einem
  Auftrag zu diesem Asset. Der Zustand (In Ordnung, Eingeschränkt, Kritisch)
  wird aus Sperren, Defekten und überfälligen Terminen abgeleitet und steht an
  der Akte; ein geänderter Standort erscheint als „Standort geändert“ in der
  Timeline. Die Rücknahme einer Ausgabe fragt Zustand und Notiz ab.
- Prüfwesen (`MVP-1575`): Prüfprofile, Anforderungen und Prüfpflichten lassen
  sich bearbeiten und deaktivieren (nichts wird gelöscht); das Intervall je
  Asset steht im Zuweisungs- und im Bearbeitungsdialog. Prüftermine lassen
  sich mit Begründung stornieren; der Fristenlauf setzt verstrichene Termine
  auf „Versäumt“. Der Prüfdialog nimmt Roh-Messwerte auf, die Liste
  „Prüfprotokolle“ im Prüfkalender zeigt Ergebniszeilen und Roh-Messwerte.
- Protokolle (`MVP-1572`): Eine Unterschrift gilt für ihren Inhaltsstand.
  Unterschriften, die nicht sofort abschließen, sowie Signaturlink und
  Portal-Abnahme gibt es nur „in Prüfung“. Geht das Protokoll zurück in den
  Entwurf, verfallen Unterschriften und offene Links („Unterschrift
  verworfen“ im Verlauf). Kunden können die Abnahme im Portal mit Begründung
  ablehnen. Unterschrift und Ablehnung über Link oder Portal benachrichtigen
  die Person, die den Link ausgestellt bzw. das Protokoll eingereicht hat
  (Ereignisse „Kunde hat ein Protokoll unterschrieben“ und „Kunde hat die
  Abnahme abgelehnt“).
- Reklamation (`MVP-1576`): Rückläufer buchen Bestand. Ankündigung und
  Wareneingang nehmen Artikelvariante, Menge, Lager, Seriennummer und Charge
  auf, vorbelegt aus Artikel und Seriennummer der Akte; die drei Schritte
  laufen als Dialoge. Mit Variante, Lager und Menge bucht der Wareneingang in
  die Quarantäne, die Verwendungsentscheidung bucht um oder aus und führt
  den Seriennummernstatus nach. Eine Seriennummer legt Variante und Menge 1
  fest. Rücksendungen aus dem Kundenportal bringen Variante und
  Seriennummer mit; das Lager ergänzt der Wareneingang.
- Reklamation (`MVP-1577`): Der Anlagedialog und der neue Dialog
  „Bearbeiten“ verknüpfen Rechnung, Auftrag, Projekt, Service-Ticket, Asset,
  Protokoll, Lieferant, Artikel und Bestellung; mit gewähltem Kunden zeigen
  die Listen nur dessen Objekte. Eine im Lager bekannte Seriennummer wird
  der Akte zugeordnet. „Reklamation eröffnen“ steht an Rechnung, Auftrag,
  Asset und Service-Ticket und belegt Objekt und Kunden vor. „Folge
  vorschlagen“ wählt die Rechnung; Gutschrift und Storno lassen sich damit
  aus der Akte ausführen. Der Regress nimmt Lieferant (Auswahl), Bestellung,
  Eingangsrechnung, Artikel, Seriennummer, Garantiebedingungen und eine
  eigene Antwortfrist auf und zeigt sie in der Regresszeile.
- Entsorgung (`MVP-1578`): Der Positionsdialog wählt ein Asset in Betrieb
  und belegt Bezeichnung, Hersteller, Modell und Seriennummer vor; Akte und
  Nachweis zeigen es, der Abschluss nimmt es außer Betrieb. „Entsorgen“ an
  der Asset-Akte öffnet eine Entsorgungsakte mit dem Asset als vorbereiteter
  Position. Je Geräteposition lassen sich Fotos hochladen; mit „Fotos im
  Kundennachweis zeigen“ erscheinen bis zu zwei je Position im Nachweis-PDF.
- Lager (`MVP-1579`): Das Bewertungsverfahren ist einstellbar. Die neuen
  „Lager-Einstellungen“ der Bestandsübersicht wählen gleitenden Durchschnitt,
  FIFO oder FEFO je Organisation, das Artikelformular ein eigenes Verfahren je
  Artikel. Der Wechsel zu FIFO/FEFO legt für vorhandenen Bestand
  Anfangsschichten zum Durchschnittspreis an; zurück zum Durchschnitt geht es
  erst, wenn kein Bestand mehr in Schichten liegt. Das Artikelformular pflegt
  außerdem das Seriennummernschema (Präfix, Stellen, Prüfziffer). Artikel mit
  „Mindesthaltbarkeit erforderlich“ nehmen Zugang nur noch über den
  Wareneingang mit Charge und Mindesthaltbarkeitsdatum an.
- Fertigung (`MVP-1580`): Der Reservierungszeitpunkt (bei Freigabe oder beim
  Start) steht in den Lager-Einstellungen; gelesen wird die Einstellung der
  Organisation des Auftrags.
- Einkauf (`MVP-1581`): Die Kalkulationswarnung nach einer Preisänderung
  erfasst offene Angebote und nicht stornierte Aufträge mit dem Artikel und
  misst die Mindestmarge am Belegpreis der Position. Die Gewichte des
  Lieferanten-Scores sind im Ranking je Organisation einstellbar.
- Leistungsverzeichnisse (`MVP-1582`): Der Dialog „Fortschritt melden“ bindet
  eine Meldung an einen Materialverbrauch oder ein Protokoll des Projekts; der
  Materialverbrauch zählt damit in der Wirtschaftlichkeit zur Position. Ein
  abgewiesener Reimport steht mit Status „Konflikt“ und dem Vergleich von
  Bestand und Datei in der Importhistorie, die jetzt die Befunde jedes Laufs
  zeigt. Ein Kostengruppenkatalog ohne Ausgabe der Norm erzeugt einen Befund.
- Lager (`MVP-1584`): Gepflegte Einheiten eines Artikels wirken. Manuelle
  Buchung, Scan und Bestellposition erfassen die Menge in einer Einheit des
  Artikels; gebucht wird in Basiseinheit, der Wareneingang rechnet Menge und
  Einstandspreis aus der Bestelleinheit um. Eingabemenge und Einheit stehen an
  der Bewegung und im neuen Reiter „Lagerjournal“. Umgerechnet wird
  kaufmännisch gerundet auf vier Nachkommastellen.
- Einstellungen je Organisation (`MVP-1589`): Der Rechtsraum steht in den
  Stammdaten der Organisation (Auswahl aus den Rechtsräumen der
  Aufbewahrungsfristen, Änderung im Audit-Protokoll) und bestimmt
  Löschvorschläge und Verfahrensdokumentation. Neu einstellbar sind der
  Abstand der Führerscheinkontrolle, die Prioritätsmatrix des Helpdesks, die
  Warnschwelle der Kapazitätsplanung, die Schwelle der zweiten Freigabestufe
  bei Investitionen, Trennzeichen und Metazeilen des CSV-Exports der Berichte
  und die Löschfrist abgelehnter Bewerbungen. Im Krisenmanagement pflegt der
  Reiter „Meldefristen“ eigene Fristen je Kategorie (anlegen, ändern,
  deaktivieren, Vorgaben übernehmen); die Vorgaben bleiben Vorlage.
- KI (`MVP-1590`): Erlaubt die Organisation für einen Dienst „Nutzer dürfen
  wählen“, bietet der KI-Vorschlag die zugelassenen Verbindungen an; „Neu
  erzeugen“ erstellt den Vorschlag mit der gewählten Verbindung, die Wahl steht
  im Audit-Ereignis des Aufrufs. Einträge des KI-Gedächtnisses zählen ihre
  Nutzung, sobald sie in einen Prompt einfließen; häufig benutzte Einträge
  haben damit Vorrang, wenn das Budget nicht für alle reicht.
- Lager (`MVP-1584`, Nachtrag): Auch die Inventur zählt in Artikeleinheiten
  (Zählliste je Zeile, Scan, mobile Zählung). Der Bestellvorschlag bestellt in
  der Einheit, deren Faktor der Verpackungseinheit des Lieferantenartikels
  entspricht, und rechnet den Preis je Bestelleinheit um.
- Leistungsverzeichnisse (`MVP-1582`, Nachtrag): „Fortschritt zur LV-Position
  melden“ steht auch am Protokoll, wenn sein Projekt ein Leistungsverzeichnis
  mit offenen Positionen hat.
- Artikelstamm (`MVP-1583`): SKU- und GTIN-Hoheit. Beim Nummernkreis „Artikel“
  lässt sich eine Warenwirtschaft (JTL-Wawi) als vergebende Stelle wählen.
  Dann sind Artikelnummer und GTIN im Artikelformular gesperrt, neue Artikel
  und Varianten erhalten keine eigene Nummer, und nur der Abgleich des
  Programms schreibt sie. Die Lager-Einstellungen zeigen die führende Stelle.
- Lager (`MVP-1585`): Getrennte Rechte für Inventur zählen,
  Inventurdifferenzen freigeben, Reservierungen ändern und externe Buchungen
  kompensieren. Wer `inventory.post` bzw. `inventory.configure` hält, bekommt
  die herausgelösten Rechte mit der Migration. Vier-Augen-Freigabe für
  Inventurdifferenzen ab einem Grenzwert, einschaltbar in den
  Lager-Einstellungen; Grenzwert und Freigebende stehen im Audit-Protokoll.
  Reservierungen lassen sich ändern: Priorität setzen, Teilmenge freigeben.
- Lieferanten (`MVP-1586`): Eigene Nachweisarten je Organisation unter
  „Pflichtnachweise → Nachweisarten“ (anlegen, ändern, deaktivieren,
  Sperrverhalten, Pflicht). Die Pflicht lässt sich auf Lieferanten mit
  bestimmten Tags begrenzen (Gewerk, Leistungsart). Katalogarten bleiben
  Vorlage und lassen sich übernehmen. Die Pflege verlangt das eigene Recht
  „Nachweisarten der Lieferanten verwalten“ (Admin und Geschäftsführung),
  nicht „Lieferant bearbeiten“.
- Aufträge (`MVP-1596`): **Pausieren** und **Stornieren** beenden laufende
  Stoppuhren auf dem Auftrag und buchen die Zeit; **Abschließen** verlangt, dass
  keine Stoppuhr mehr läuft, und nennt, wer noch aktiv ist. Ein Storno
  archiviert offene Protokolle des Auftrags und bricht laufende Prozedurläufe
  ab, unterschriebene Protokolle bleiben. **Fortsetzen** leert Pausenzeitpunkt,
  -grund und -notiz (sie stehen im Verlauf). Neu: **Annahme zurückgeben** mit
  Begründung setzt einen angenommenen Auftrag wieder auf „Geplant“.
- Aufträge (`MVP-1596`): „Berechnet“ setzt jetzt der Beleg. Eine ausgestellte
  Rechnung oder eine vollzogene Übergabe ans Buchhaltungsprogramm, die Zeiten
  eines abgenommenen Auftrags abrechnet, stellt ihn auf „Berechnet“ und trägt
  die Belegnummer ein; der Storno des Belegs nimmt das zurück, solange kein
  anderer Beleg Zeiten des Auftrags hält. Die Eingabe von Hand bleibt.
- Aufträge (`MVP-1596`): Der Verlauf am Auftrag filtert nach Zeitraum,
  mehreren Typen, Person und „Nur kundenrelevant“.
- Offene Punkte (`MVP-1597`): Frist, Schweregrad und Sichtbarkeit lassen sich
  nach der Anlage ändern (Sichtbarkeit „Kunde“ nur mit dem Recht zur
  Kundenfreigabe); jede Änderung steht im Verlauf. Offene Punkte gibt es jetzt
  auch an Projekt, Kunde und Asset. Der neue Dialog **Details** zeigt Verlauf,
  Kommentare und Anhänge; ein geschlossener Punkt nimmt keine Anhänge mehr an.
  Die Liste filtert nach Status, Schweregrad und zuständiger Person, und die
  zuständige Person ist schon bei der Anlage wählbar.
- Lernplattform (`MVP-1598`): Der Pflichtanteil eines Videos („Mindestens
  gesehen (%)“) wirkt. Der Player zählt die tatsächlich abgespielten
  Abschnitte (Vorspulen zählt nicht, dieselbe Stelle nur einmal) und meldet
  sie; der Server rechnet höchstens im Tempo der Uhr und nie über die
  Videodauer hinaus an und speichert je Einschreibung und Video nur den
  erreichten Anteil. Unter dem Pflichtanteil lässt sich die Einheit nicht
  abschließen — in „Meine Schulungen“, im Kundenportal und im
  Offline-Abgleich; der Knopf bleibt gesperrt und nennt den Stand. Für fremde
  Einbettungen lehnt der Editor einen Pflichtanteil ab, der Zugang ohne
  Benutzerkonto weist auf das nicht abspielbare Pflichtvideo hin.
- Lernplattform (`MVP-1599`): Zertifikate lassen sich widerrufen. In der
  Teilnehmerliste eines Kurses steht die Zertifikatsnummer an der Person; mit
  dem Recht „Lernen verwalten“ widerruft man dort mit Pflichtgrund. Prüfseite,
  maschinenlesbarer Nachweis, Ausdruck und Nachweismappe zeigen den Widerruf,
  die Person wird mit dem Grund benachrichtigt (neues Ereignis „Zertifikat
  widerrufen“), der gespiegelte Pflichtnachweis eines Subunternehmers endet am
  selben Tag, und das Audit-Log hält Person, Zeitpunkt und Grund fest. An
  derselben Stelle lässt sich der Einstiegslink einer externen Person
  entwerten.
- Lernplattform (`MVP-1600`): Das Notenschema je Organisation ist pflegbar
  (Einstellungen der Lernplattform: Stufen „Bezeichnung = Mindestprozent“ oder
  Vorlage 1–6, A–F, bestanden/nicht bestanden); Notenbuch, CSV und Zeugnis
  zeigen damit die Note. Die Einheitenarten Prozedur, Umfrage und Extern
  wirken: Der Einheiten-Dialog verlangt den Verweis; ein abgeschlossener Lauf
  der Prozedurvorlage, der abgesendete Fragebogen bzw. die Bestätigung
  „erledigt“ (wahlweise nur mit hochgeladenem Nachweis) schließen die Einheit
  ab, von Hand abhaken lassen sie sich nicht mehr. Die Nachweismappe lässt
  sich zusätzlich nach Person, Projekt und Standort eingrenzen, führt
  Personen, die am Stichtag beschäftigt waren und inzwischen ausgeschieden
  sind, und kommt mit Anlass auch als ZIP mit Zertifikats-PDFs und
  Index-Datei. Der Editor nennt beim Auswählen eines Videos den zu
  erwartenden Speicherbedarf (Original plus Fassungen).
- Hinweisgebersystem (Phase 176, `MVP-1601`): Die Nachricht an die meldende
  Person lässt sich als Rückmeldung nach § 17 HinSchG kennzeichnen; die erste
  setzt `feedback_sent_at` und beendet die Erinnerung, die bisher bis zum
  Abschluss täglich lief. `whistleblowing:deadlines` warnt jetzt in Stufen
  (Eingangsbestätigung 48 Stunden vorher, Rückmeldung 14 Tage und 48 Stunden
  vorher, je einmal; überfällig täglich) und am Prüfdatum einer Löschsperre.
  Die Aufbewahrungsfrist rechnet mit den Monaten des Portals. Neu an der
  Fallakte: Ereignisprotokoll hinter `whistleblowing.audit.view` (der Aufruf
  wird protokolliert), Zuweisung widerrufen, Unterlagen der Meldestelle über
  den verschlüsselten Anhangsweg; die Löschsperre verlangt Rechtsgrundlage,
  Verantwortlichen und Prüfdatum, die Wiedereröffnung eine Begründung. Die
  Fallliste filtert nach Status, Kategorie, Priorität, Person, Friststatus
  und Eingangszeitraum. Das Portal nennt externe Meldewege (Pflichthinweis,
  dazu pflegbare Stellen), lässt sich auf die erlaubten Sprachen umschalten
  und zeigt den Stand des Datenschutzhinweises.
- Datenschutz (Phase 176, `MVP-1602`): Die Frist einer Betroffenenanfrage
  lässt sich nach Art. 12 Abs. 3 DSGVO verlängern – höchstens zwei weitere
  Monate, mit Grund und nur, solange die erste Frist läuft. Der Vorgang
  führt die ursprüngliche Frist weiter, `privacy:deadlines` erinnert vor der
  neuen Frist erneut, und Anfragen mit Kontaktadresse erhalten die Mitteilung
  samt Grund per E-Mail. Der Löschscan schlägt abgeschlossene
  Betroffenenanfragen und (neu, Bereich `privacy_incidents`)
  Datenschutzvorfälle nach der Aufbewahrungsfrist zur Schlüsselvernichtung
  vor; nach Freigabe sind die Inhalte unlesbar, unverschlüsselte Nachweise
  gelöscht, und der Vorgang bleibt als inhaltsfreier Nachweis stehen. Das
  Betroffenenportal erscheint in seiner Standardsprache.
- Umfragen (Phase 176, `MVP-1603`): Der Widerspruch gegen Umfragen lässt sich
  erfassen – als Haken „Keine Umfragen“ am Kunden und über den Link
  „Umfragen abbestellen“ in jeder Einladung an einen Kunden und auf der
  Umfrageseite (ohne Anmeldung, mit Rückfrage, im Änderungsverlauf des Kunden
  vermerkt). Kundenexport und -import führen die Spalte `survey_opt_out`.
- Verleih (`MVP-1604`): Die Akte warnt beim Reservieren, wenn im Zeitraum eine
  weiche Vormerkung eines anderen Vorgangs besteht, und nennt die betroffenen
  Vorgänge; gesperrt wird nicht. Übergabe- und Rücknahmeformular erfassen
  Checkliste, Zubehör, Zustandspositionen und Verbrauch; Prüfpunkte und Zubehör
  kommen aus dem Verleihprofil, Akte und Kundenportal zeigen die Positionen.
  Das Verleihprofil pflegt Heimatstandort, Mindestzustand, aktuellen Zustand
  und die Prüfpunkte; unter dem Mindestzustand ist ein Gerät nicht vergebbar.
  „Reinigung erforderlich“ legt ein Reinigungsfenster an, „Reinigung erledigt“
  gibt das Gerät vorzeitig frei. Migration
  `2027_04_17_230000_add_condition_and_checklist_to_rental_profiles`.
- Krisenmanagement (`MVP-1605`): Maßnahmen können einen Vorgänger aus derselben
  Akte tragen und lassen sich erst erledigen, wenn er erledigt oder abgebrochen
  ist. Der kritische Prozess nimmt Ersatzprozess und verbleibende Einschränkung
  auf. Eine laufende Akte führt mit dem Recht der Liste zur
  Notfall-Anwesenheitsliste. Sicherheitsvorfall, Datenschutzvorfall,
  Arbeitsschutzereignis und Major-Incident-Ticket tragen „Krise eröffnen“; der
  Vorfall wird mit der neuen Akte verknüpft. „Vorgang verknüpfen“ bietet eine
  Auswahl lesbarer Vorgänge statt der Sqid-Eingabe.
- Investitionen und Leasing (`MVP-1606`): Die Variante nimmt die Nutzungsdauer
  auf, der Variantenvergleich zeigt sie. Ein mit der Akte verknüpftes Asset
  übernimmt den Lieferanten der empfohlenen Variante (`acquired_from_supplier_id`),
  die Asset-Akte zeigt Lieferant, Datum und Kosten der Anschaffung. Die
  Investitionsliste filtert nach Zeitraum, Verantwortlichen, Kostenstelle,
  Budgetstatus, Dringlichkeit und Risikohinweis; der Investitionsbericht gibt
  es auch als PDF. Der Leasingvertrag wählt seine Bestellung im Formular.
- Nachhaltigkeit (`MVP-1607`): „Bewertung starten“ wählt ein Bezugsobjekt
  (Asset, Fahrzeug, Standort, Raum, Projekt, Auftrag, Lieferant, Artikel,
  Investition, Vertrag) aus den lesbaren Objekten freigeschalteter Module und
  speichert es als Morph-Alias; die Bewertung verlinkt Objekt und alle
  Bewertungen desselben Objekts. Asset, Lieferant und Investition tragen
  „Nachhaltigkeit bewerten“. Maßnahmen lassen sich einer Bewertung zuordnen,
  Bewertungs- und Maßnahmenliste führen bis zum Objekt.
- Agiles Board (`MVP-1608`): Die Definition of Done wirkt. Beim Zug in eine
  Fertig-Spalte öffnet das Board einen Dialog mit den DoD-Punkten und den
  offenen Akzeptanzkriterien; ohne Übersteuerungsrecht muss jeder Punkt
  bestätigt sein, mit Recht genügt eine Begründung. Geprüft wird
  serverseitig in `AgileBoardService::move()`, die Übersteuerung steht als
  Ereignis `override.dod` mit den unbestätigten Punkten im Verlauf. Die
  Qualitätsreihe weist DoD-Abweichungen und die DoD-Abweichungsquote je Woche
  aus (Sprint-Bericht, PDF, CSV).
- Dokumentdesign (`MVP-1609`): Firmenangaben, Steuerangaben, Ansprechpartner,
  Einleitung, Schluss und Vertraulichkeitsvermerk wirken in Rechnung,
  Gutschrift, Angebot, Auftragsbestätigung, Mahnung und Beitragsmitteilung
  nach ihrem Blockzustand. Firmen- und Steuerangaben kommen aus den
  Verkäuferdaten der E-Rechnung bzw. Branding und Rechtsangaben, der
  Ansprechpartner aus dem Beleg; Kopf- und Fußtext folgen den Blöcken
  Einleitung und Schluss, der Vertraulichkeitsvermerk ist im Editor pflegbar.
  Der Preflight wertet „dynamisch“ bei Firmen- und Steuerangaben nur mit
  gepflegten Stammdaten als erfüllt (Fehler bei zugeordneten Vertriebsbelegen,
  Warnung beim Basisdesign). Ausstellerangaben und Ansprechpartner werden beim
  Ausstellen der Rechnung eingefroren; Belege aus der Zeit vor dieser Änderung
  (Generatorstand `document-design/1.0.0`) bleiben beim Nachdruck unverändert.
- Terminbuchung und Calendly (`MVP-1610`): Eine Anfrage ohne Kundentreffer lässt
  sich im Termin-Eingang über „Zuordnen“ einem Kunden oder Lead zuordnen; ein
  konvertierter Lead bringt seinen Kunden mit. Im Integrations-Eingang steht
  eine solche Calendly-Buchung als Einzelfall mit Ziel „Kunde“ (Vorschläge aus
  dem Kundenprofil, zuordnen oder neu anlegen); die Zuordnung landet an der
  Anfrage. Calendly-Gastgeber mit abweichender Adresse lassen sich einem
  Benutzer zuordnen. Bestätigen und Ablehnen im Calendly-Bereich verlangen wie
  im Termin-Eingang das Dispositionsrecht, Verbinden und Zuordnungen bleiben
  bei der Verwaltung. Buchbare Leistungsarten und der Kopf eines Fragebogens
  (Titel, Zweck, Anonymität bis zur ersten Einladung) sind bearbeitbar.
  Migration `2027_04_17_240100_route_calendly_unmatched_bookings_to_customers`.
- Wächterrundgänge (`MVP-1611`): Routen (Bezeichnung, Objekt, Verantwortliche)
  und Kontrollpunkte sind bearbeitbar; Routen und Kontrollpunkte lassen sich
  deaktivieren. Ein Rundgang, der vor der Deaktivierung begonnen hat, erwartet
  den Punkt weiter — frühere Läufe und der Bericht bleiben unverändert. Eine
  Abweichung erzeugt sofort einen offenen Punkt für die Verantwortliche der
  Route (sonst die Teamleitung der durchführenden Person), je Lauf einen, der
  bis zum Ende fortgeschrieben wird. Läuft ein Rundgang nach Soll-Dauer plus
  Toleranz noch, meldet ihn der Fristenlauf (`patrol_overdue`) einmal als nicht
  beendet. Migration `2027_04_17_240000_add_responsibility_and_deactivation_to_patrols`.
- Kleinere Lücken (`MVP-1612`): Der letzte Kontakt eines Leads rückt bei
  Kommunikationsnotiz, inhaltlicher Bearbeitung und Angebot aus dem Lead nach.
  Die Meldung „Anmeldung von neuem Gerät“ lässt sich im Profil abschalten; An-
  und Abschalten stehen im Audit-Log. Eine neue Integritäts-Baseline hebt den
  Lockdown auf, ohne Baseline der Plattformbetrieb über `integrity:release
  --reason=…` oder den Knopf auf der Integritätsseite (auditiert); ein
  Wartungsmodus aus anderem Anlass bleibt stehen. Eine an ein Objekt gebundene
  Leistungsart bietet Portal und Slot-Suche nur dem Kunden des Objekts an, die
  Bestätigung übernimmt die Objektanschrift als Einsatzort.
- Cloud-Dokumenteingang (`MVP-1613`): Eine Ordnerregel kann alle Dateien einem
  festen Kunden, Projekt, Auftrag, Asset oder Vertrag zuordnen (nur Arten
  aktiver Module, Objekt der eigenen Organisation). Jede Regel braucht einen
  Testpfad: Gespeichert wird nur, wenn er das Muster trifft und ein Ziel ergibt;
  die Rückmeldung und der Regeldialog zeigen erkannte Variablen und Ziel.
  Mehrere Pfadvariablen, unbekannte Objekte und ein Zielbereich ohne aktives
  Modul werden abgewiesen. Zwei aktive Regeln einer Verbindung mit gleichem
  Muster oder einem Testpfad, der beide trifft, blockieren die Aktivierung.
  Den ersten Import beginnt eine Verbindung erst nach Vorschau und „Import
  starten“; ein neuer Stammordner verlangt eine neue Bestätigung (Audit
  `cloudIntake.startConfirmed`). Migration
  `2027_04_17_260200_add_route_test_and_start_confirmation_to_cloud_intake`.
- Domains (`MVP-1614`): Befehle mit unklarem Ausgang gleicht `domain:sync`
  über `StatusDomain` ab — Registrierung, Renewal-Modus, Transfersperre und
  Verlängerung werden bestätigt, als fehlgeschlagen erkannt oder als Konflikt
  mit Hinweis markiert. Transfers, DNS und Hochrisikobefehle bleiben „unklar“
  mit dem Hinweis, beim Anbieter zu prüfen, und lassen sich im Befehlsjournal
  mit Begründung als ausgeführt oder fehlgeschlagen abschließen (Audit
  `domain.commandResolved`). Renewal-Modus und Transfersperre melden einen
  unklaren Ausgang, statt Erfolg anzuzeigen. Neue Benachrichtigungen an die
  Admins: Verlängerung ab der Kostenschwelle der Organisation
  (`domain.renewal_cost_threshold`, je Abbuchungstermin einmal) und
  Anbieterkonto, dessen Guthaben samt Kreditrahmen die Verlängerungen im
  Vorlauf nicht deckt oder unter dem Mindestguthaben liegt
  (`domain.balance_threshold`, je Woche einmal).
- Eigene Felder (`MVP-1615`): Kunden- und Lieferantenexport hängen die eigenen
  Felder als Spalten an; die Suchbereiche von Kunde, Lieferant, Lead, Asset,
  Artikel und Projekt finden die Anzeigewerte; Lieferanten- und Lead-Liste
  zeigen Listenspalten. Der Anzeigetext liegt je Wertesatz in
  `custom_field_values.search_text` und folgt geänderten Feldlisten. Migration
  `2027_04_17_260100_add_search_text_to_custom_field_values` (befüllt den
  Bestand).
- Verein (`MVP-1616`): Gruppen im Modus „Antrag mit Freigabe“ nehmen nur über
  einen Antrag auf — auch ein bestätigter Wechselvorschlag wird dort zum
  Antrag. Mitglieder und Vertretungen beantragen die Aufnahme unter „Mein
  Verein“; der Antrag erscheint im Arbeitsvorrat der Leitung. Prüfungen haben
  die Aktion „Ergebnisse freigeben“ (sobald jeder Kandidat entschieden ist):
  Vorher sehen Prüflinge weder Ergebnis noch neuen Grad noch Bescheinigung;
  danach sind Zulassungsberichte, Nachweise, Kandidaten und Ergebnisse
  eingefroren (Audit `club.exam.resultsReleased`). Migration
  `2027_04_17_260300_release_recorded_club_exam_results`.
- Überblick (`MVP-1617`): Die Dashboard-Kachel „Finanzbewegungen“ (Bereich
  Finanzen, zunächst ausgeblendet) zeigt je Quelle die Bewegungen ohne
  Zuordnung und die jüngsten Bewegungen der letzten 30 Tage; Quellen melden
  sich über den Erweiterungspunkt `FinanceMovementSource` (Kontoauszüge des
  Zahlungsabgleichs, Firefly III, Etsy-Shopkonto). Firefly-Bewegungen ohne oder
  mit mehrdeutiger Zuordnung stehen gebündelt im Arbeitsvorrat, die
  Firefly-Liste filtert auf „Offen“. Das Lieferanten-Cockpit zeigt Kataloge und
  die Scorecard der letzten zwölf Monate; Asset und Lieferant zeigen Leasing-
  und Finanzierungsverträge, das Asset außerdem offene Tickets, Reklamationen
  und Gewährleistungsfristen aus Aufträgen und Protokollen zum Asset, Lead und
  Kunde die Termine aus Terminbuchung und Calendly. Der Arbeitsvorrat filtert
  nach Fälligkeit (überfällig, heute, diese Woche, ohne Frist), der
  Aktivitäts-Feed nach Ereignis, auch nach allen Fachereignissen. Die
  Leitungssicht und der Leitungsbericht haben einen Bereich Personal:
  Krankheitstage wie die Krankheits-Auswertung, abgelaufene und bald
  ablaufende Qualifikationen wie die Qualifikationsmatrix, offene
  Urlaubsanträge. Der Kalender zeigt Vertragsfristen: offene und versäumte
  Pflichten, Vertragsende bzw. Verlängerung und Indexprüfung. Die nie
  geschriebene Spalte `project_time_targets.site_id` entfällt — Zeiten kennen
  kein Objekt, das Soll gilt je Projekt, Person und Tag (Migration
  `2027_04_17_270000_drop_site_id_from_project_time_targets`).
- Lizenz und Diagnose (`MVP-1618`): `license:issue --max-orgs=… --storage-quota-gb=…`
  und die Ausstellerkonsole stellen Organisations- und Speicherlimit aus, die
  Organisationslizenz Nutzer- und Speicherlimit. Die Anlage einer
  Organisation durch den Plattformbetrieb prüft `max_orgs`, das
  Speicherkontingent gilt aus der Lizenz der Organisation; „Limits“ zeigt
  Organisationen und Speicher. Auf der Diagnoseseite lassen sich
  Überwachungszugänge anlegen und widerrufen: `GET /api/monitoring/diagnostics`
  mit `Authorization: Bearer <Token>` liefert den Bericht ohne Namen und
  Adressen, rate-limitiert und höchstens stündlich im Audit. Migration
  `2027_04_17_260000_create_monitoring_tokens_table`.

- Sammelreste (`MVP-1619`): Rollen lassen sich kopieren — das Symbol
  „Kopieren“ an einer Rolle der Organisation öffnet den Anlegen-Dialog mit
  vorbelegten Rechten, das Audit nennt die Vorlage. Eine Spesenkategorie mit
  „Beleg erforderlich“ lässt das Einreichen erst mit hinterlegtem Beleg zu
  (Entwurf bleibt speicherbar, das Formular weist darauf hin, der Belegdialog
  öffnet sich). Die Warnung zum SMS-Kontingent erreicht die Verwaltung als
  Betriebsaufgabe mit Benachrichtigung (`operations.smsBudgetWarning`), je
  Monat einmal an der Warnschwelle und einmal am Deckel. Projektfarbe im
  Projektdialog. Spalte „Top-Ursachen“ in der Auftragstypanalyse aus
  Nacharbeitsgrund und Reklamations-Grundursache, mit Drilldown und im
  CSV-Export. Entwürfe von ISMS-Auditpaketen sind bearbeit- und verwerfbar.
  Scheduler-Jobs haben je Job Zeitzone, Ruhezeit und Wiederholung nach
  Fehlschlag (Wiederholung als zweites Scheduler-Ereignis mit gemeinsamer
  Sperre; Migration `2027_04_17_280000_add_run_options_to_scheduled_job_overrides`).
  Raumanforderungen bearbeiten im eigenen Dialog; Übernahme-Inventur auf der
  JTL-Seite erneut ausführen (nur am Tag des Wechsels auf „lokal“);
  Erstattungsreferenz im Dialog „Als erstattet markieren“. Erfassungsfelder,
  die der Server schon annahm: Erinnerungen je Termin, TOM-Prüfung und
  nächster Review am AVV, Laufzeit der Chat-Umfrage, Sicherheitsabstand und
  Sonderfarben der Druckfreigabe, Interviewer beim direkt geplanten
  Bewerbungsgespräch, Datum und Notiz des Submissionsergebnisses, Wartezeit
  beim Fahrtabschluss (Minuten, vorbelegt aus Warte- und Abholzeit),
  Tätigkeitsstätte der Dreimonatsfrist, Kennzeichen im Fahrtenbuch (auch im
  Export), Notiz an der Vertragsobligation, Steuerklasse am Artikel. Das Foto
  eines Zählerstands ist ein Anhang (Upload im Dialog, Symbol in der Liste).
  WebDAV- und SharePoint-Ablage reihen beim Aktivieren alle bisher
  freigegebenen Objekte ein; einen Zeitplan braucht der Spiegellauf nicht.
- Datenführerschaft (`MVP-1592`, Teil 1): Tickets, Bestände und Kalender
  werden durchgesetzt. Schreibt eine Integration in einen Bereich, den ein
  anderes System führt, schreibt sie nichts; stattdessen entsteht im
  Integrations-Eingang ein Fall „Datenführerschaft“ mit „Einmalig übernehmen“
  (schreibt den zurückgehaltenen Stand genau einmal) und „Verwerfen“. Betroffen
  sind Service-Tickets aus Zammad (Anlage und Abschluss), Termine aus CalDAV,
  Google Kalender und Microsoft 365 (Vorschläge, Serien, Änderungen),
  Calendly-Buchungen und -Absagen sowie die Übernahme-Inventur aus JTL-Wawi;
  Todoist, Microsoft To Do und OpenProject prüfen jetzt auch die Führung der
  Aufgaben. Die Matrix bietet je Bereich nur Integrationen an, die ihn schreiben
  können, zeigt offene Konflikte und nennt beim Wechsel, was mit ihnen
  geschieht; der Wechsel wird mit vorheriger Führung auditiert. Bestände folgen
  der Bestandsführung der Warenwirtschaft und sind in der Matrix nur zu sehen.
  In eine von Zammad geführte Queue legt WorkDiary keine Tickets mehr selbst an
  (manuell, Mail-Buchung, Standard-Queue) und hängt keine Mail-Antworten an.
  Neues Gate `DataOwnershipWriteRuleTest`.
- Datenführerschaft (`MVP-1592`, Teil 2): Dokumente und Kunden/Lieferanten
  werden durchgesetzt, die Aufgabenimporte laufen über denselben Baustein.
  Führt ein anderes System die Dokumente, legt der Cloud-Dokumenteingang
  (Dropbox, Google Drive, Microsoft 365/SharePoint, Nextcloud, Paperless-ngx,
  Seafile) keine DMS-Dokumente und Versionen an, und der easybill-Belegabruf
  archiviert nichts; „Einmalig übernehmen“ lädt die Datei neu. Die Zeile
  „Kunden und Lieferanten“ der Matrix ist jetzt die Stammdatenhoheit
  (`finance.master_data_authority`): Ein gewähltes Buchhaltungsprogramm führt,
  WorkDiary überträgt keine Kontakte dorthin, und nur dieses Programm übernimmt
  Felder (Lexoffice-Kontaktabgleich mit „Lexoffice gewinnt“ und Neuanlage,
  Kontakt-Rückweg aus sevDesk und easybill); „Buchhaltung (jedes Programm, das
  Kunden schreibt)“ lässt alle angebundenen Programme schreiben. Die
  Toggl-Migration legt unter fremder Führung keine Kunden an und bricht mit
  Hinweis ab. Jira, Asana, Gitea, Redmine, Plane, Taiga, Kanboard, Leantime,
  Vikunja, GLPI, FreeScout, Todoist, Microsoft To Do, GitHub, GitLab,
  OpenProject und die Aufgaben aus Zammad legen ihre Fälle als
  Führerschaftskonflikt mit „Einmalig übernehmen“ an (auch der Statusabgleich
  der Git-Issues und der Abschluss aus Zammad); offene Altfälle werden beim
  nächsten Abgleich dazu. Ein übernommener Fall öffnet sich wieder, wenn die
  Quelle einen neuen Stand schreiben will; verworfen bleibt verworfen, und ein
  Fall, den die zurückgekehrte Führung selbst schreibt, gilt als übernommen.
  Lokale Ticketanlage in einer extern geführten Queue zeigt auf jedem Weg
  (Katalog, Portal, Problemmeldung, offener Punkt, Kundeneingang, API) die
  Meldung statt eines Serverfehlers; Lernfragen gehen dann per Mail an die
  Kursverantwortung, Wartungstickets werden ausgelassen und protokolliert.
- Offline (`MVP-1593`): **Projektzeiten offline.** Die Eingabeleiste auf
  „Heute“ speichert Buchungen mit Dauer oder Von/Bis ohne Verbindung und
  überträgt sie als Befehl `time.entry` — über dieselben Regeln und denselben
  Dienst wie online (Monats- und Tagessperre, Abrechnungs-Snapshot,
  Sollzeit-Marker, Aufgabe nur aus dem Projekt). Wurde der Tag bis zum Abgleich
  abgeschlossen oder fehlt eine Pflichtangabe, entsteht ein Konflikt statt
  einer stillen Ablehnung. Den Timer gibt es offline nicht. Eine Aufgabe aus
  einem anderen Projekt meldet die Leiste jetzt als Eingabefehler statt mit
  „Nicht gefunden“.
- Offline (`MVP-1594`): **Material auf „Heute“, auch offline.** Neuer Dialog
  „Material“ (Projekt, Material aus dem Stamm oder frei, Menge, Einheit); das
  Material landet auf dem offenen Stundenzettel von Projekt, Person und Tag,
  der bei Bedarf angelegt wird. Offline geht es als Befehl `material.usage` in
  die Warteschlange und wird über die Befehls-ID genau einmal angelegt. Dialog
  am Zettel, API und „Heute“ schreiben über einen Dienst; einen Lagerabgang
  bucht der Materialverbrauch am Stundenzettel wie bisher nicht.
- Offline (`MVP-1595`): **Stempelung korrigieren, auch offline.** In der
  Stempelliste auf „Heute“ öffnet das Stift-Symbol einen Korrekturdialog
  (Beginn, Ende, Pause, Begründung); die Korrektur läuft als Antrag durch die
  Freigabe bzw. gilt sofort bei erlaubter Selbstkorrektur. Hat sich die
  Stempelung seit dem Öffnen geändert, wird nichts überschrieben — online ein
  Hinweis, offline ein Konflikt.
- Offline (`MVP-1595`): **Konflikte entscheiden.** Die Seite
  „Offline-Änderungen“ zeigt Konflikte aller Offline-Befehle, auch offene von
  anderen Geräten, mit lesbarem Serverstand. Entscheidungen: fremden Stand
  übernehmen bzw. verwerfen, eigene Fassung senden oder bearbeiten und erneut
  senden. Jede Entscheidung wird am Befehl gespeichert und als
  `sync.conflict.resolved` in die Audit-Kette geschrieben; die Admin-Sicht
  „Offline-Synchronisierung“ zeigt sie neben dem Ergebnis bzw. „offen“.

- Lager (`MVP-1626`): Vier-Augen-Freigabe für Entnahmen in den Negativbestand
  (Lager-Einstellungen). Ist sie eingeschaltet, wird eine Entnahme mit
  „Negativen Bestand zulassen“ zum Antrag im Genehmigungs-Eingang und erst
  gebucht, wenn eine andere Person mit dem Recht „Negative Bestände freigeben“
  zustimmt; Antragsteller und Freigebende stehen im Audit-Protokoll. Der
  Genehmigungs-Eingang kennt dafür Stufen nach Recht. Reservierungen lassen
  sich im Dialog „Reservierung ändern“ an eine andere Reservierung desselben
  Bestands umverteilen (sichtbar und auditiert, nie still nach Priorität); die
  Materialposition eines Fertigungsauftrags folgt Umverteilung und
  Teilfreigabe. Billbee ist als Bestandsführung wählbar (Rückkanal der
  Bestände); eine fremde Führung stellen weder die Billbee- noch die
  JTL-Seite um.
- Assets (`MVP-1628`): „Wieder in Betrieb nehmen“ mit Pflichtgrund und Audit,
  dasselbe Recht wie die Außerbetriebnahme; über einen Entsorgungsauftrag
  entsorgte Assets bleiben außer Betrieb, und das Bearbeitungsformular führt
  nicht aus „außer Betrieb“ heraus.
- Krisenmanagement (`MVP-1628`): Der Vorgänger einer Maßnahme lässt sich
  nachträglich ändern oder lösen; Kreise werden abgewiesen.
- Firefly III (`MVP-1628`): Bewegungen ohne Beleg lassen sich mit Begründung
  als erledigt markieren und wieder öffnen; Kachel „Finanzbewegungen“,
  Arbeitsvorrat und Filter „offen“ zählen sie nicht mehr, der Filter
  „Erledigt“ zeigt sie.
- KI (`MVP-1628`): Die Verbindungswahl („Nutzer dürfen wählen“) gilt auch an
  den Positionstexten der Übergabe, in der Tätigkeitsrecherche der Suche und
  bei „Plan-Ist erklären“.
- Prozeduren (`MVP-1627`): Die Folgeaktion „Erneut durchlaufen“ startet einen
  Wiederholungslauf derselben Vorlage am selben Objekt; er zählt als
  Folgemaßnahme einer kritischen Abweichung und steht im Abweichungsbericht.
- Protokolle (`MVP-1627`): Befehl `protocols:tidy-pdfs [--dry-run]
  [--organization=]` überführt den Nachweis abgeschlossener Protokolle aus
  `protocols/{org}` in einen Anhang und entfernt übrige, nicht referenzierte
  Fassungen.
- Architektur (`MVP-1620`): Sechs Gates messen, ob eine Funktion wirkt, statt
  ob es sie gibt: Dienstmethode ohne Aufrufer (`ServiceMethodCallerRuleTest`),
  Einstellung ohne Schreibstelle (`SettingWriterRuleTest`), validiertes Feld
  ohne Sender (`ValidatedFieldSenderRuleTest`), schreibende Route ohne
  Einstieg (`WriteRouteEntryRuleTest`), Statusfall ohne Setzer
  (`StatusCaseSetterRuleTest`) und Spalte ohne Lese- oder Schreibstelle
  (`ColumnUsageRuleTest`). Je Gate eine Baseline mit Grund je Eintrag unter
  `tests/Unit/Architecture/baselines/`, die nur schrumpfen darf; ein zweiter
  Test meldet erfüllte Einträge. Dabei entfernt: 34 öffentliche
  Dienstmethoden ohne Aufrufer und Validierungsregeln für Felder, die kein
  Formular sendet (Ausstellungsdatum des Teilnahmezertifikats, Abruflimit des
  CSAF-Feeds, Knotenposition im Ideen-Update, Auditprogramm im Auditdialog,
  Einzelname der Todoist-Zuordnung).
- Lückenschluss der Gate-Befunde (`MVP-1620`): Die Anlagen-Stückliste
  übernimmt Material aus Einsätzen – unter „Material aus Einsätzen“ stehen die
  am Stundenzettel dem Gerät zugeordneten Verbräuche, „Übernehmen“ trägt sie
  mit dem Einsatztag als Einbaudatum (und dem Artikel des Materials) ein, jeden
  Verbrauch nur einmal (Feature 118). Die Qualifikationsmatrix der Disposition
  zeigt die Einsatz-Ampel aus Feature 149: gelb „in Schulung“, wenn ein Kurs zur
  geforderten Qualifikation zugewiesen oder begonnen ist, bei gelb und rot den
  freigegebenen Kurs, der sie vermittelt; der Sperrhinweis im Schichtplan nennt
  dasselbe (Vertrag `QualificationTrainingSource`, gebunden von der
  Lernplattform). Das KI-Monatsbudget je Familie lässt sich auf der Seite
  „KI-Verbrauch“ pflegen; die Organisationseinstellungen haben Felder für den
  Quellenvermerk eines eigenen Kachelservers (Text ohne HTML) und die Sprache
  der Feiertagsnamen. Der Artikeldialog wählt den Standard-Arbeitsplan
  (veröffentlichte Prozedurversion), den Fertigungsauftrag, Fertigungs- und
  Bedarfsplanung lesen. SLA-Verträge erfassen Inklusivzeit-Kontingente mit
  Zeitraum, Stunden, Warnschwelle, Pauschale und Mehrstundensatz; Vertragsseite
  und SLA-Report nennen Pauschale und Mehrstundenbetrag als Nachweis.
- Aufgeräumt (`MVP-1620`): 28 Dienstmethoden ohne Aufrufer, die nur Tests
  nutzten (darunter `LotStockReader::balanceOf`, `DepreciationCalculator::amountForYear`,
  `InventoryOutboxService::confirmByKey`, die synchrone ZIP-Importvariante,
  `RenderProfileService::assignPageFormat`), die Route
  `inventory.reservations.release` (der Reservierungsdialog gibt die Restmenge
  frei) und die Rückfälle `value_json` (Protokollpunkt), `ratio_part` (Rezeptur)
  sowie `amount_net`/`amount_tax` ohne Steuerzeilen im Werte-Dialog der
  Eingangsrechnung. Spalten ohne Leser und Schreiber entfallen:
  `projects.is_maintenance`, `employee_drafts.planned_start_on`,
  `todoist_connections.webhook_capable`. Die Tests prüfen die Regeln jetzt über
  den lebenden Weg.
- Statusfälle mit Wirkung (`MVP-1620`): Cloud-Dokumenteingang und Backupziele
  stufen sich nach einem Lauf mit abgewiesener Anmeldung (HTTP 401, nicht
  erneuerbares Token) auf „Neu anmelden“ und ab der Abschaltschwelle auf
  „Deaktiviert“ ab; fehlen nach der Anmeldung Pflicht-Scopes, ist die
  Cloud-Verbindung „Blockiert“. Die Karte bietet „Neu anmelden“ für genau diese
  Verbindung, Regeländerungen setzen sie nicht mehr still auf aktiv. Domains
  zeigen „Ausstehend“, „Unklar“ und „Konflikt“ aus den offenen
  Anbieterbefehlen – der Abgleich überschreibt das nicht – und nach dem
  Abschluss „Veraltet“ bis zum nächsten Lesen. Der Fristenlauf setzt
  SEPA-Mandate 36 Monate nach dem letzten Einzug auf „Abgelaufen“. Inventur:
  „Zählung abschließen“ friert die Mengen zur Prüfung ein, die Freigabe kann
  wieder öffnen oder die Inventur ohne Buchung verwerfen. Reklamation:
  Vorschlag im Vier-Augen-Schritt ablehnen. Verleih: angeforderte Kaution mit
  Begründung erlassen. Lieferantenkatalog: Position ignorieren und wieder
  aufnehmen. Betroffenenanfrage: Identitätsnachweis angefordert, Rückfrage,
  Antwort erhalten und Rücknahme (Statusvertrag am Enum). TOM: Umsetzungsstatus
  und Schutzbedarf erfassen und ändern, beides im VVT-Snapshot.
  Datenschutzvorfall: mit der letzten erledigten Maßnahme „Eingedämmt“.
  Angenommener Prüfauftrag setzt seine Termine „In Durchführung“, ein Storno
  gibt sie frei. Eine korrigierte, abgeschlossene Stempelung steht auf
  „Angepasst“. Der Abschluss einer Schichtabrechnung bindet die beendeten
  Fahrten des Fahrers (abgerechnet bzw. ohne Betrag). Eine Auslieferung ohne
  Kunden ist „Nicht erforderlich“ zu berechnen; das Storno eines
  Fertigungsauftrags schließt seine offenen Fehlmaterial-Bedarfe.
- Gestrichene Statusfälle (`MVP-1620`): Fälle, die kein Ereignis setzt, entfallen
  samt Lesern und Texten – Rate „überfällig“ (Leasing), Ereignis „fehlgeschlagen“
  (Domains), Bankumsatz „Dublette“, Steuerregel „Entwurf“, Reservierung
  „storniert“, Seriennummer „reserviert“, Lieferbestand „storniert“, SMS
  „zugestellt“, Umfrage-Einladung „abgelaufen“, Anhangsprüfung „fehlgeschlagen“
  (Hinweisgeber) und Aufgabenlisten-Verknüpfung „pausiert“ (Microsoft To Do).
  Die Übergabeliste Lexware bietet im Filter nur noch die Stände des manuellen
  Wegs an; „übertragen“, „Prüfung erforderlich“ und „fehlgeschlagen“ bleiben der
  automatischen Übergabe (`MVP-834`) vorbehalten. Das Gate zählt den Wert eines
  Match-Arms als Setzer (vorher vier Fehlalarme: Leasing „verlängert“/„gekauft“,
  Druckauftrag „bereit“/„Nacharbeit“).

### Behoben

- Plugins (`MVP-1620`): Wurde der Plugin-Manager im laufenden Prozess neu
  aufgebaut (Tests, Neuregistrierung), geriet die Buchung eines Boot-Fehlers
  in eine Endlosschleife: Der Speicher-Hook des Plugin-Zustands forderte den
  noch nicht fertigen Manager an und baute ihn erneut. Fehler beim Aufbau
  werden jetzt gebucht, sobald der Manager steht.
- Zusammenfassende Meldung (`MVP-1622`, `MVP-1624`): Die Meldung las die
  USt-IdNr. des Kunden von heute. Änderte ein Kunde seine Nummer, meldete eine
  spätere ZM für alte Rechnungen die neue. Jetzt gilt die USt-IdNr. der
  ausgestellten Rechnung (`Invoice::recipient()`); Buchungen ohne
  Rechnungsbezug lesen wie bisher den Kunden.
- Lager (`MVP-1620`): Bei FIFO und FEFO zeigte die Bestandsübersicht den
  Durchschnittspreis und Bestandswert des stillstehenden Durchschnittsbuchs –
  ohne vorherigen Durchschnitt null. Jetzt bewertet sie je Variante nach dem
  wirksamen Verfahren, bei Schichten aus den offenen Restmengen; die
  ABC-Einteilung der zyklischen Inventur nutzt dieselben Werte.
- Lernplattform (`MVP-1600`, `MVP-1617`): Die Nachweismappe mit Auswahl nach
  Standort endete mit einem Serverfehler, weil sie noch die entfernte Spalte
  `project_time_targets.site_id` las. Am Standort eingesetzt ist jetzt, wer im
  Zeitraum an einem Kontrollpunkt des Standorts gestempelt hat oder ein
  Zutrittsmedium dafür hält.
- Umsatzsteuer (`MVP-1623`): Der DATEV-Stapel ohne Hauptbuch buchte jede
  Rechnung ohne Steuer mit BU 0 auf das Konto für steuerfreie Erlöse
  (8200/4200). Jetzt bestimmt der Steuerfall der Rechnung – dieselbe Kategorie
  wie in der Buchhaltung – Konto und Schlüssel aus der Vorlage des
  Kontenrahmens: innergemeinschaftliche Lieferung 8125/4125, sonstige Leistung
  ins übrige Gemeinschaftsgebiet 8336/4336, Steuerschuld des Empfängers im
  Inland 8337/4337, Ausfuhr 8120/4120, nicht steuerbar im Drittland 8338/4338,
  jeweils als DATEV-Automatikkonto ohne BU-Schlüssel. Steuerfreie Umsätze im
  Inland bleiben auf dem eingestellten Konto. Die Kontenplan-Vorlagen führen die
  Erlöskonten für RC21, AUSF und NSTB samt Regel; bisher buchten diese
  Kennzeichen auf das Konto für steuerfreie Umsätze (8100/4100).
- Rechnungen (`MVP-1623`): Änderte sich der Steuerfall des Kunden nach der
  Anlage eines Entwurfs (Merkmal § 13b, neue USt-IdNr., Umzug ins Drittland),
  ging die Rechnung mit dem alten Fall hinaus, während der eingefrorene
  Steuerkontext schon den neuen nannte. Jede Bearbeitung des Entwurfs zieht den
  Fall jetzt aus dem TaxResolver nach; die Ausstellung eines veralteten
  Entwurfs passt ihn an und hält an, bis der neue Stand geprüft ist.
- XRechnung (`MVP-1623`): Bei Reverse Charge trugen Positionen mit eigenem
  Steuersatz diesen Satz weiter, die Datei rechnete darauf Steuer, und der
  Befreiungsgrund fehlte (KoSIT: BR-AE-01, -05, -08, -09, -10). Jetzt stehen
  Positionen und Rabatte ohne Steuer mit 0 %, der Steueraufriss nennt
  „Steuerschuldnerschaft des Leistungsempfängers“ mit Code `VATEX-EU-AE`, und die
  Prüfung vor dem Versand verlangt die USt-IdNr. des Kunden (BR-AE-02) – auch
  bei § 13b im Inland.
- Auslagen (`MVP-1623`): Die Vorsteuer einer Auslage buchte unabhängig vom
  Steuersatz des Belegs auf das Vorsteuerkonto der allgemeinen Regel (7 % also
  auf 19 %). Jetzt gilt das Vorsteuerkennzeichen des Satzes; gibt es zu einem
  Satz keines – meist ausländische Umsatzsteuer –, hält der Buchungsvorschlag
  mit Hinweis an, statt sie als Vorsteuer zu buchen.
- Rechnungsfreigabe (`MVP-1624`): Eine Änderung am freigegebenen Entwurf
  (Positionen, Konditionen, Kunde, Datum, Rechnungstext) ließ die Freigabe
  stehen. Sie wird jetzt aufgehoben und im Protokoll vermerkt; die Sperre der
  Selbstfreigabe bleibt.
- Angebot und Auftragsbestätigung (`MVP-1624`): Aussteller und Ansprechpartner
  werden beim Versand bzw. bei der Bestätigung eingefroren wie bei der
  Rechnung; spätere Stammdatenänderungen deuten das Dokument nicht mehr um.
- Dokumentdesign (`MVP-1624`): Bestellung, Lieferschein und Protokoll (auch
  Aufmaßblatt und Entsorgungsnachweis) druckten Firmen- und Steuerangaben nicht
  nach dem Blockzustand, und der Preflight prüfte für sie nicht, ob die
  Stammdaten gepflegt sind. Beides gilt jetzt wie bei den Vertriebsbelegen.
- Aufträge (`MVP-1624`): Materialrechnungen und Materialübergaben setzten den
  abgenommenen Auftrag nicht auf „Berechnet“; jetzt wie bei Zeiten, über den
  Stundenzettel des Materials. Der Storno nimmt es zurück, solange kein anderer
  Beleg Zeiten oder Material des Auftrags hält.
- Gewährleistung (`MVP-1624`): Gerügte Fristen fielen aus den Erinnerungen und
  aus dem Cockpit; eine gerügte eigene Frist fehlte im Vergleich mit
  Subunternehmer-Fristen.
- E-Rechnung (`MVP-1623`): XRechnung und ZUGFeRD nannten einen Befreiungsgrund
  nur bei Reverse Charge, und „steuerfrei“ (E) trug immer den Text zu § 19 UStG.
  Innergemeinschaftliche Lieferung (K), Ausfuhr (G) und „nicht steuerbar“ (O)
  waren damit ungültig (BR-IC-10, BR-G-10, BR-O-10), jede Befreiung nach § 4
  UStG nannte die Kleinunternehmerregel. Jetzt trägt jeder Steueraufriss seinen
  Grund mit Code (`VATEX-EU-IC`, `VATEX-EU-G`, `VATEX-EU-O`, `VATEX-EU-AE`);
  Kategorie Z bleibt ohne Grund (BR-Z-10), § 19 gilt nur für Kleinunternehmer,
  andere Befreiungen nennen den Hinweistext einer Steuerregel der Satzart
  „Befreit“. Die Prüfung vor dem Versand meldet fehlende USt-IdNrn. bei K und G,
  „nicht steuerbar“ neben anderen Kategorien und Positionen ohne Steuerkategorie
  mit Steuersatz; bei „nicht steuerbar“ entfallen die USt-IdNrn. zugunsten der
  Steuernummer (BR-O-02).
- Rechnungen (`MVP-1623`): Beim Wechsel des Steuerfalls blieb der automatische
  Hinweistext des alten Falls im Entwurf stehen. Er wird jetzt ersetzt oder
  entfällt; einen von Hand geänderten Text lässt workDiary stehen und meldet ihn.
- Rechnungs-PDF (`MVP-1623`): Positionen der Kategorien E (§ 4 UStG), K, G, O
  und einzelne AE-Positionen druckten keinen Befreiungshinweis, wenn der
  Rechnungstext ihn nicht enthielt (§ 14 Abs. 4 Nr. 8, § 14a UStG). Das PDF
  druckt jetzt je Kategorie den Befreiungsgrund der E-Rechnung, sofern der
  Rechnungstext weder ihn noch den automatischen Hinweis der Kategorie trägt;
  Reverse Charge des Kopfes und § 19 behalten ihre Zeile. Rechnungen, die vor
  dem Generatorstand `document-design/1.2.0` ausgestellt wurden, bleiben beim
  Nachdruck unverändert. Gutschrift und Stornorechnung übernehmen jetzt die
  Steuerkategorie jeder Position des Originals (vorher fiel sie weg, E-Rechnung
  und Hinweis folgten dem Kopf).
- Rechnungspositionen (`MVP-1623`): Eine Kategorie ohne Steuer (Z, E, AE, K, G,
  O) setzte den Steuersatz nicht auf 0 %; ein leerer Satz fiel auf den Kopfsatz,
  erst die E-Rechnungs-Prüfung hielt an. Positionsformular und Abrechnungsplan
  setzen jetzt 0 % (Formular über die Vorbelegung, Server in jedem Fall); der
  Hinweis nennt K und O. Die Summenrechnung ist unverändert.
- Angebote (`MVP-1623`): Der Zuständige fürs Nachfassen (Feature 112) ließ sich
  nicht wählen. Der Dialog „Neues Angebot“ und „Nachfasstermin setzen“ haben
  jetzt das Feld; ohne Wahl fasst wie bisher der Ersteller nach.
- Zahlungsabgleich (`MVP-1623`): Cent- und Skonto-Toleranz waren fest
  (0,02 bzw. 3 %), Feature 045 nennt sie konfigurierbar. Sie sind jetzt
  Einstellungen der Organisation (`finance.reconciliation.cent_tolerance`,
  `finance.reconciliation.skonto_percent`, Gruppe „Bankabgleich“) mit den
  bisherigen Werten als Vorgabe. Lastschriften schlagen nur noch privat
  verauslagte Spesen zur Erstattung vor; `PaymentMethod::isReimbursable()`
  zählte die Barkasse mit und widersprach der Kennzahl „Erstattungsfähig“.
- Reklamationen (`MVP-1623`): Eine Maßnahme konnte weder ihren Folgeauftrag
  noch im Formular die verantwortliche Person nennen. Beides ist jetzt
  erfassbar; der Folgeauftrag (beim Kunden der Akte, sichtbar für den
  Bearbeiter) steht verlinkt an der Maßnahme.
- Hilfe (`MVP-1623`): Die Rückmeldung „War das hilfreich?“ wurde gespeichert,
  aber nie ausgewertet und zählte als Aufruf bei den beliebten Themen. Neuer
  Befehl `help:feedback [--days=90] [--limit=20]`; beliebte Themen zählen nur
  Aufrufe.
- Rechnungs-PDF (`MVP-1623`): Die USt-IdNr. des Kunden stand nirgends auf der
  Rechnung, auch nicht bei innergemeinschaftlicher Lieferung und Reverse Charge
  (§ 14a Abs. 1 und 3 UStG). Ist sie hinterlegt, druckt der Kopf jetzt „Ihre
  USt-IdNr.“. Rechnungen, die vor dem Generatorstand `document-design/1.3.0`
  ausgestellt wurden, bleiben beim Nachdruck ohne die Zeile.
- Rechnungen (`MVP-1624`): Der beim Ausstellen eingefrorene Empfänger wurde nie
  gelesen. PDF und E-Rechnung druckten Name, Anschrift und USt-IdNr. aus den
  heutigen Kundendaten, ein Nachdruck nach einer Adressänderung zeigte also eine
  andere Rechnung als die versandte. PDF, XRechnung/ZUGFeRD (Käufer, Anschrift,
  USt-IdNr., Käuferreferenz), GAEB-Rechnungsexport und der DATEV-Buchungstext
  lesen jetzt den eingefrorenen Stand, auch bei älteren Rechnungen; Felder, die
  ältere Snapshots nicht führen (Firma, Käuferreferenz), kommen weiter aus dem
  Kunden. Der Snapshot führt künftig Firma und Käuferreferenz und das Land ohne
  Vorgabe „DE“. Entwürfe zeigen wie bisher die aktuellen Kundendaten;
  Stornobeleg und Korrekturrechnung frieren ihren Empfänger beim eigenen
  Ausstellen ein. Die elektronische Zustelladresse (BT-49) und Mahnschreiben
  folgen bewusst den aktuellen Daten. Der Ausweis der Arbeitskosten nach § 35a
  EStG wird beim Ausstellen festgeschrieben; vorher folgte er beim Nachdruck der
  heutigen Organisationsregel und Kundenart.
- Dokumentdesign (`MVP-1624`): Fertigungsnachweis, Behinderungsanzeige,
  Bedenkenanmeldung, Zertifikat, Zeugnis, Graduierungsbescheinigung,
  Abschlussnachweis der Vertragsunterzeichnung, Unterweisungsnachweis,
  Gefährdungsbeurteilung und Zollrechnung druckten trotz Pflichtblock
  „Firmenangaben“ keine bzw. keine nach dem Blockzustand, und der Preflight
  prüfte ihre Stammdaten nicht. Jetzt gilt beides für jede Dokumentart mit
  diesem Pflichtblock. Versand, Ausstellung, erste Unterschrift, Freigabe bzw.
  Verleihung frieren die Gestaltung ein; vorher abgeschlossene Belege bleiben
  unverändert. Behoben dabei: Der Snapshot einer Behinderungsanzeige passte
  nicht in die Spalte (31 Zeichen), versandte Schreiben hatten deshalb nie einen
  eingefrorenen Stand.
- Datenübertragung (`MVP-1625`): Benutzer- und Projektimport schrieben
  Verrechnungs- und Kostensätze mit dem bloßen Importrecht. Diese Spalten
  verlangen jetzt dasselbe Recht wie in der Oberfläche (Personal- und
  Lohndaten bzw. Projektabrechnung); ohne es übergeht der Import sie mit
  Hinweis im Prüfbericht, der Export lässt sie weg, und eine Exportdatei mit
  diesen Spalten lädt nur herunter, wer das Recht hat.
- SLA-Bericht (`MVP-1625`): Die Liste der Verletzungen nannte bei
  vertraulichen Tickets den Kunden; Liste und Auswertung je Kunde zeigen ihn
  jetzt nur, wer das Ticket sehen darf.
- Domains (`MVP-1625`): Der Verlängerungsmodus „Einmal verlängern“
  (`RENEWONCE`) steht nicht im Handbuch des Anbieters und ist nicht mehr
  wählbar; setzbar sind Auto-Renew, Auto-Expire und Auto-Delete, auch bei der
  Registrierung.
- Lager (`MVP-1626`): Die Offline-Zählung sandte die gewählte Einheit nicht
  mit und zählte in der Basiseinheit; die Reklamationsakte nimmt jetzt die
  Charge chargengeführter Artikel auf (die Mustererkennung „gleiche Charge“
  hatte kein Feld).
- Arbeitszeitkonto (`MVP-1627`): Die Seite rechnete den laufenden Monat gegen
  das volle Monatssoll, Kachel und Terminal bis gestern. Seite und API
  rechnen jetzt wie das gespeicherte Konto und zeigen den Kontostand mit
  Übertrag; ein freigegebener Monat ist im Konto eingefroren (`locked`), bis
  er wieder geöffnet wird.
- MiLoG-Nachweis (`MVP-1627`): je Tag „davon Homeoffice“ und „davon
  Dienstgang“.
- Stundenzettel (`MVP-1627`): Material auf „Heute“ (online und offline)
  prüfte die speicherbare Zettelsumme nicht.
- Protokolle (`MVP-1627`): Unterschrift und Abschluss lesen den Status unter
  Zeilensperre neu; eine Rückgabe in den Entwurf dazwischen weist die
  Unterschrift ab.
- Kunden und Lieferanten (`MVP-1628`): Hinweis im Formular, wenn die
  Buchhaltung die Stammdaten führt (der nächste Abgleich überschreibt lokale
  Änderungen).
- Verleih (`MVP-1628`): Der Mindestzustand gilt auch bei der Übergabe.
- Agile Boards (`MVP-1628`): Folgt eine Karte einer außerhalb des Boards
  erledigten Aufgabe, stehen offene Akzeptanzkriterien und die nicht
  bestätigte Definition of Done als Abweichung im Board-Verlauf.
- Module (`MVP-1628`): `operations:scan` legt Aufgaben zu Fehlermeldungen nur
  mit aktivem Helpdesk an, `compliance:scan-findings` Lenkzeitbefunde nur mit
  aktivem Fuhrpark; offene lösen sich auf.
- Lernen (`MVP-1628`): Der Gastzugang zeigte nur Text, Überschrift und
  Checkliste; jetzt alle Blockarten mit Medien über den Zugang.
- Aufträge (`MVP-1629`): Die Spalte `diary_entries.notes` ohne Schreiber und
  Anzeige entfällt; vorhandene Texte werden Kommentare am Auftrag.
- Wartungspläne (`MVP-1628`): Die Vertragsbindung (SLA-Vertrag,
  „Vertragspflicht“, „Bei Fälligkeit“) war nur in der Anzeige vorhanden –
  kein Formular schrieb sie, die Vertragsseite blieb leer und das
  Fälligkeits-Ticket wurde nie angelegt. Der Dialog des Wartungsplans bietet
  sie jetzt an (Vertrag des Asset-Kunden oder ohne Kundenbindung, nie ein
  OLA; nur mit Leserecht auf SLA-Verträge, die Aktion nur mit Helpdesk).
  „Vertragspflicht“ verlangt einen Vertrag und steht auch am Asset, in der
  Objektakte und im Kundenportal. Das Kundenportal zeigte die Namen der
  Wartungspläne nicht an.
- Startseite (`MVP-1628`): Die Liste der Anbindungen stand auf dem Stand vor
  Phase 157 (Aufgaben-, Dokument-, Shop-, Zahlungs- und Faktura-Plugins
  fehlten). Sie entsteht jetzt aus den Plugins und der Modulzuordnung ihrer
  Manifeste; ein neues Plugin erscheint ohne Handpflege, ein Test hält
  Gruppen und Kennzahl beim Bestand.
- Hilfe (`MVP-1629`): „Branding“ beschrieb den Logo-Upload über die
  Anhänge-Verwaltung; die Seite hat eigene Upload-Felder für beide
  Varianten. „Drilldown“ und „Kundenanalyse“ beschrieben Spalten, Filter und
  Ziele, die es nicht gibt; jetzt Offene Punkte, Defektprotokolle, Ursachen
  und Wiederholdefekte bzw. Kundenanalyse und Kunden & Projekte, wie sie die
  Seite zeigt.
- Assets (`MVP-1628`): Der SLA-Vertrag am Asset hatte keine Schreibstelle,
  und die Ticketauflösung überging ihn. Das Asset-Formular bietet ihn jetzt an
  (Vertrag des Asset-Kunden oder ohne Kundenbindung, nie ein OLA oder ein
  projektgebundener Vertrag; nur mit Leserecht auf SLA-Verträge, sonst bleibt
  die Bindung unverändert); er steht im Kopf der Detailseite. Ein Ticket am
  Asset löst den Vertrag Asset → Projekt → Kunde → Queue → Organisation auf –
  die Zusage an genau dieses Objekt ist die engste; ein Queue-Wechsel lässt
  sie stehen, ein inaktiver Vertrag oder der eines anderen Kunden greift nie.
  Die Objektakte zeigt den so geltenden Vertrag. Wechselt der Kunde des
  Assets, entfallen Bindungen an Verträge des bisherigen Kunden am Asset und
  an seinen Wartungsplänen (samt „Vertragspflicht“).
- Produktanalyse (`MVP-1628`): Der Drilldown „Wiederholdefekte“ hatte keinen
  Einstieg. Die neue Spalte „Wiederholdefekte“ zählt je Zeile die Assets mit
  mindestens drei Defektmeldungen in zwölf Monaten und führt auf genau diese
  Fälle; der Drilldown übernimmt die Filter der Zeile (auch im Export), CSV
  und PDF der Analyse führen die Spalte mit.
- Kundenportal (`MVP-1628`): Die Objektakte listete pausierte Wartungspläne
  mit einer Fälligkeit, die nie eintritt; jetzt nur laufende Pläne.

- Offline (`MVP-1593`): Befehle der Offline-Warteschlange wurden auch für
  Bereiche übernommen, die für die Organisation abgeschaltet sind (etwa
  Lernen, Inventur, Rundgänge); jetzt abgewiesen. Eine fehlende Berechtigung
  in einem Dienst bricht nicht mehr den ganzen Stapel ab, sondern weist den
  einzelnen Befehl ab.
- DATEV-Übergabe aus dem Journal (`MVP-1622`): Der EXTF-Stapel schrieb jede
  Journalzeile als eigenen Buchungssatz – eine Buchung mit zwei Zeilen kam in
  DATEV doppelt an, eine mit mehr Zeilen ohne Gegenkonto, und als BU-Schlüssel
  stand das Kürzel des Kennzeichens („USt19“). Jetzt entstehen je Buchung
  Buchungssätze gegen eine Ankerzeile (Personen- oder Geldkonto); eine Zeile
  mit Steuerkennzeichen geht brutto mit dessen DATEV-BU-Schlüssel hinaus, die
  Steuerzeilen entfallen. Auf DATEV-Automatikkonten (neues Feld
  „DATEV-Automatik“ am Konto) setzt der Stapel keinen Schlüssel; passt die
  Automatik nicht, hebt BU 40 sie auf. Bei Steuerschuld des Empfängers bleibt
  der Betrag netto mit BU 94 bzw. 19/18. Neues Feld „DATEV-BU-Schlüssel“ am
  Steuerkennzeichen.
- Offene Posten (`MVP-1555`): Fehlte das Skonto- oder Ausbuchungskonto im
  Kontenplan, schloss „Ausgleichen“ den Posten ohne Buchung – OPOS-Liste und
  Summen-/Saldenliste liefen auseinander. Der Ausgleich wird jetzt mit Meldung
  abgewiesen, der Dialog warnt vorher. Bereits ohne Buchung geschlossene
  Skonto- und Ausbuchungsausgleiche listet der Bericht „Buchungsqualität“;
  sie sind von Hand nachzubuchen.
- Umsatzsteuer-Voranmeldung (`MVP-1558`): Für Buchungen aus der Buchungs-Inbox
  stand in den Kennziffern der Steuerbetrag als Bemessungsgrundlage (bei einer
  Rechnung über 200 € netto zu 19 % also 38 € in Kennziffer 81). Die
  Aufteilung liest das Steuerkennzeichen jetzt an der Steuerzeile wie an der
  Nettozeile und rechnet die Grundlage aus Steuer und Satz; Festbuchungen
  bleiben unverändert, die Steuerbeträge und die kontenbasierte Vorschau waren
  nicht betroffen.
- Schichttausch (`MVP-1553`): Ein Tausch am selben Tag scheiterte an der
  Überschneidung mit der Schicht, die die andere Seite gerade abgibt; die
  Prüfung lässt die abgegebene Gegenschicht jetzt außen vor. Die Freigabe
  prüft außerdem, dass die Gegenschicht noch der Tauschperson gehört, und
  nimmt die Übersteuerung nur noch mit Begründung an.
- Verwaltungszeit (`MVP-1554`): Neue Verwaltungszeiten galten immer als
  abrechenbar; mit Kategorie gilt jetzt deren Vorgabe.
- Helpdesk (Phase 176, `MVP-1546`): Tickets ließen sich in der Oberfläche
  weder lösen noch schließen, weil das Statusformular den Abschlusscode nicht
  erfasste. „Status ändern“ ist jetzt ein Dialog mit Abschlusscode und
  Lösungszusammenfassung; angeboten werden nur zulässige Übergänge. Damit sind
  Abnahme und Bewertung im Kundenportal sowie der Webhook `ticket.closed`
  wieder erreichbar.
- Helpdesk (`MVP-1547`): Wartezustände verlangen Grund, Wiedervorlage und
  verantwortliche Person und pausieren die SLA-Uhr nach den Regeln des
  Vertrags; Fortsetzen verschiebt die Fristen um die Pausendauer, auch nach
  einer Kundenantwort. Wiederöffnen geht nur mit Grund. Wiedervorlage-Erinnerung
  und Bericht „Wartezeit nach Grund“ erhalten dadurch Daten.
- Rechnungen (`MVP-1521`): Der Storno einer lokalen Rechnung gab nur die
  Auffüllung auf das Projekt-Soll frei; eine Folgerechnung enthielt dann die
  Differenz ohne die gebuchten Stunden. Storno am Entwurf und Stornobeleg geben
  jetzt alles frei wie der Storno einer Übergabe (Zeiten, Material, Anfahrt,
  Auslagen, Mietposten, Auslieferungen, Auffüllung); die Positionen der
  stornierten Rechnung bleiben unverändert. Auslagen ließen sich nach einem
  Storno nicht erneut abrechnen, und die Auslastung zählte stornierte Minuten
  als fakturiert. Eine Rechnung, die nur Online-Vorauszahlungen teilweise
  decken, ließ sich weder stornieren noch korrigieren; der Stornobeleg ist
  jetzt möglich, die Vorauszahlung geht an ihre Buchung zurück (`MVP-1544`).
  Mit lokaler Buchhaltung glich ein Stornobeleg den ältesten offenen Posten
  des Kunden aus statt den der stornierten Rechnung (`MVP-1545`); er zählt
  auch nicht mehr als Zahlung in den Auswertungen. Die Zahlungsbuchung schrieb
  den Klassennamen als Gegenpartei-Typ.
- Buchhaltung (`MVP-1543`, `MVP-1542`): Das Storno einer Buchung verdoppelte
  in der Aufteilung auf die USt-Kennziffern die Steuer, statt sie aufzuheben.
  Lokal berechnete Rechnungen fanden keine Erlös- und Steuerregel (Satz `19`
  gegen `19.00`) und blieben im Buchungseingang hängen; Stornobelege und
  Gutschriften ließen sich nicht buchen (negative Beträge).
- Geplante Befehle der Fachmodule (`MVP-1524`) liefen weiter, wenn eine
  Organisation das Modul abgeschaltet hatte: wiederkehrende Buchungen,
  Rechnungsentwürfe aus Abos, Fremdabrufe und Erinnerungen. Sie überspringen
  solche Organisationen jetzt; Aufbewahrung, Löschung, Virenscans,
  Schlüsselrotation und die Sperre des Fahrtenbuchs laufen weiter.
- Verleih (`MVP-1523`): Bei führendem Buchhaltungsprogramm meldete jede weitere
  Übergabe die volle Vorauszahlung erneut. Die Buchung von Online-Zahlungen
  schrieb den Klassennamen statt des Alias als Gegenpartei-Typ; eine
  Datenmigration schreibt solche Zeilen um.
- Kennungen (`MVP-1527`): Dublettenprüfung, Rechnungseingang, Zahlungsvorschlag
  und Lexoffice-Abgleich fanden USt-IdNr. mit Leerzeichen oder Kleinbuchstaben
  nicht, Scan und Katalog keine GTIN mit Trennzeichen. Das Original-PDF eines
  orgaMAX-Belegs sah nur der Admin, nicht die Buchhaltung mit Faktura-Recht.
  Asset-Auswertung, Drilldown und Prüfrunde zeigten Kategoriecodes statt
  Bezeichnungen.
- Oberflächen (`MVP-1528`): Ein in der Quelle gelöschter Kalendertermin bot
  in der Integrations-Inbox nur „Remote übernehmen“, das immer scheiterte;
  jetzt Zeiteintrag öffnen, löschen (nie abgerechnete) oder zur Kenntnis
  nehmen. Der Buchhaltungswechsel verlor nach einer Ablehnung alle Eingaben.
  Eine nachträglich gesetzte Pflichtqualifikation prüfte die schon zuständige
  Person nicht.
- Legacy-Bereich: „Neuer Eintrag“ stand auch ohne verknüpftes Legacy-Konto in
  der Kopfzeile, das Speichern scheiterte dann; Mitarbeitende ohne Admin-Id
  sahen ihn in der Arbeitsliste nicht. Es gilt eine Regel: eigene Einträge mit
  jedem verknüpften Legacy-Konto. `legacy:import --support` übernimmt einen
  Mechaniker ohne Konto und eine unbekannte Altkundennummer in die interne
  Notiz. Die Seite „Portalkunden“ zeigt die Anmeldeadresse der Kunden.

- Abos & Lizenzen: Der Domain-Einkauf hing an der auslaufenden Periode, weil
  der Anbieter die Verlängerung einige Tage vor dem neuen Laufzeitjahr bucht,
  und trug das negative Vorzeichen des Buchungsjournals; der Margenbericht
  rechnete damit zu hoch. `resale:sync-purchases` ordnet Verlängerungen der
  bezahlten Periode zu, führt den Einkauf positiv und berichtigt den Bestand
  beim nächsten Lauf (`MVP-1534`).
- Abos & Lizenzen: Ein Kunde oder Fremdkunde mit Abos, Lizenzverkäufen oder
  Kontoaufteilungen ließ sich löschen; die Abos verloren dabei ihren Halter.
  Löschen ist jetzt gesperrt (`MVP-1537`).
- Legacy-Bereich: Zeiten aus der Altdatenbank erschienen um eine Stunde (im
  Sommer zwei) verschoben, „heute“ wechselte erst nach Mitternacht UTC, und
  `aktuell` wurde in UTC geschrieben. Das Altsystem speichert Ortszeit; Anzeige,
  Tagesgrenzen und Schreiben laufen jetzt in `APP_DISPLAY_TIMEZONE`.
  `legacy:import` übernahm Ortszeit als UTC und rechnet jetzt um.
- Legacy-Bereich: Wochenansicht, Archivwoche, Arbeitsliste, Archiv, Zentrale,
  Eintragsdetail und Migrations-Dashboard zeigten zwei Scrollleisten, weil ihre
  feste Höhe den Inhaltsbereich überragte. Sie füllen ihn jetzt wie die Seiten
  des neuen Bereichs; die ungenutzte Regel `wd-fill-h` ist entfallen.
- Legacy-Bereich: Der Plattformbetrieb ohne Legacy-Konto sah in Wochenansicht,
  Arbeitsliste, Archiv und Zentrale keine Mitarbeiter bzw. nur „eigene“
  Einträge, obwohl er das Altsystem verwalten darf. Gesamtsicht und
  Verwaltungsknöpfe (Archivwoche, Archivierung, Bereitschaft und Notdienst)
  folgen jetzt einer Regel: Legacy-Admin oder Plattformbetrieb. Tagebuch-
  Einträge anlegen bleibt an ein Legacy-Konto gebunden.
- Bürgschaften (`MVP-1561`): Kachel und Filter „Rückgabe fällig“ zeigten
  immer 0, weil sich ein abgelöster Einbehalt nicht freigeben ließ und die
  gekoppelte Gewährleistung nicht mitzählte. Beide folgen jetzt der
  Fristenprüfung. Solange zu einer gekoppelten Gewährleistung eine Rüge offen
  ist, wird keine Rückgabe gemeldet.
- Fakturierung (`MVP-1562`): Mit Freigabezwang konnte die Person, die die
  Rechnung angelegt hat, sie selbst freigeben. Das lehnt die Freigabe jetzt ab;
  ohne Freigabezwang bleibt sie ein freiwilliger Vermerk.
- Zählerabrechnung (`MVP-1563`): „Jetzt abrechnen“ meldete bei pausierten und
  beendeten Vereinbarungen „0 Entwürfe“ statt des Grundes.
- Prozeduren (`MVP-1569`): Beim Speichern im Designer gingen Angaben der
  Schrittkonfiguration verloren, die das Formular nicht zeigte (etwa aus
  Importen); sie bleiben jetzt erhalten. Die Installation eines
  Branchenprofils übernimmt Konfiguration, Rolle und Qualifikation seiner
  Schritte.
- Protokolle (`MVP-1572`): Abnahme- und Übergabeprotokolle ließen sich ohne
  Unterschrift abschließen. Ein Abnahmeprotokoll braucht jetzt die
  Unterschrift des Kunden, ein Übergabeprotokoll die beider Seiten; der
  Signaturblock ist dort nicht mehr abwählbar. Ein vor der Unterschrift
  erzeugtes PDF wurde nach dem Abschluss weiter ausgeliefert und zeigte die
  Unterschriften nicht.
- Protokolle (`MVP-1573`): Unterpunkte einer Gruppe standen auf der
  Detailseite doppelt. Anhänge eines unterschriebenen Protokolls ließen sich
  über den Admin-Durchgriff noch löschen.
- Protokolle (`MVP-1572`): Eine im Entwurf erfasste Unterschrift blieb
  gültig, wenn der Inhalt danach geändert wurde; ein vor dem Abschluss
  ausgestellter Signaturlink ließ sich danach noch einlösen. Nach
  Unterschrift oder Ablehnung zeigte der Link „abgelaufen“ statt der
  Bestätigung. PDFs von Entwürfen blieben ohne Löschweg auf der Platte.
- Reklamation (`MVP-1576`): Wareneingang und Verwendungsentscheidung
  meldeten „gebucht“, obwohl nie Bestand bewegt wurde. Die Meldung nennt
  jetzt „ohne Bestandsbuchung erfasst“, wenn Lagerartikel oder Lager-Modul
  fehlen; mit Variante ohne Lager oder Menge lehnt der Wareneingang ab.
- Reklamation (`MVP-1577`): Gutschrift, Storno, Minderung und Korrektur
  ohne Rechnung scheiterten erst nach der Freigabe; der Vorschlag verlangt
  die Rechnung jetzt gleich. Ein Storno einer bezahlten Rechnung brach mit
  einem Serverfehler ab und nennt jetzt den Grund.
- Entsorgung (`MVP-1578`): Ein noch ausgegebenes Asset übersprang der
  Abschluss ohne Hinweis; Meldung und Akte nennen es jetzt.
- Modulfreischaltung (`MVP-1587`): Ist ein Modul für eine Organisation aus,
  liefern seine Beiträge dort nichts mehr — keine Fristenmeldung und kein
  Statuswechsel aus dem Fristen-Scan, keine Frühwarnung, kein Eintrag im
  Genehmigungs-Eingang, kein Cockpit-Panel, keine Quelle im Arbeitsvorrat,
  keine Kennzahl der Leitungssicht, kein Kalendertermin, keine Treffergruppe
  und kein Portalhinweis. Das gilt auch für Beiträge eines abgeschalteten
  Plugins. Aufbewahrung und Löschung laufen weiter.
- Klassifikationen (`MVP-1588`): Ein Wert, den Zeiteinträge, Reklamationen,
  Artikel, Zuordnungen oder Import-Zuordnungen noch verwenden, lässt sich nicht
  mehr löschen. Die Meldung nennt die Stellen mit Anzahl; „Stilllegen“ in der
  Zeile nimmt den Wert aus neuen Auswahlen. Der Schutz liest die Fremdschlüssel
  aus dem Schema und gilt damit für jede neue Klassifikationsspalte.
- API (`MVP-1591`): Das Löschen eines Kunden über die API verhält sich wie in
  der Oberfläche — gesperrt bei externen Referenzen, weiteren Projekten und
  Belegen (409) sowie Legal Hold (423); das KI-Gedächtnis des Kunden wird
  auditiert gelöscht, das Standardprojekt geht mit.
- Entfernt (`MVP-1591`): Plugin-Verträge `TaskSyncer` und `AppointmentSyncer`
  samt `syncTasks()`/`syncAppointments()` sowie `intakeAccount()` am
  Dokumenteingang — der Betrieb rief sie nie. Die Fähigkeiten „Aufgaben-Sync“
  und „Termin-Sync“ bleiben als Anzeige in der Plugin-Übersicht.
- Auftragsbestätigung: Eine angenommene Angebotsposition mit Artikel ließ die
  Auftragsbestätigung mit einem Datenbankfehler scheitern, weil die
  Vorbelegung des Arbeitsanteils eine Spalte schrieb, die Auftragspositionen
  nicht haben.
- Einstellungen: Das Bewertungsverfahren des Lagers ließ sich über die
  generische Einstellungsseite am Bestandsübertrag vorbei ändern. Es wird nur
  noch in den Lager-Einstellungen geschrieben.
- Aufträge (`MVP-1596`): Die Abnahme speichert den Namen der Person, die beim
  Kunden unterschrieben hat, statt der Kennung des Mitarbeiters; ohne
  Kundenunterschrift im Protokoll fragt der Dialog nach dem Namen. Der
  handelnde Mitarbeiter steht weiter im Verlauf.

- Domains (`MVP-1619`): Der Verlängerungsmodus geht über
  `SetDomainRenewalMode` (Handbuch 2.3.2.10) statt über `ModifyDomain`, das
  den Parameter nicht kennt; offene Altbefehle gleicht `domain:sync` weiter ab.
- Spesen (`MVP-1619`): Der Verpflegungshinweis im Spesenformular erschien nie,
  weil die Kategorieliste ohne Kennung geladen wurde.
- JTL-Wawi (`MVP-1619`): Die Übernahme-Inventur ließ sich an jedem späteren Tag
  erneut auslösen und hätte den inzwischen lokal geführten Bestand auf den
  JTL-Stand zurückgebucht; jetzt nur am Tag des Wechsels.

### Hinweise zum Update

- Gate-Befunde (`MVP-1620`): `migrate` entfernt die ungenutzten Spalten
  `projects.is_maintenance` (samt Index), `employee_drafts.planned_start_on`
  und `todoist_connections.webhook_capable` – sie hatten keinen Schreibweg, im
  Bestand stehen nur Vorgaben – und ergänzt `asset_components.material_usage_id`
  (Herkunft eines aus dem Materialverbrauch übernommenen Teils).
- Statusfälle (`MVP-1620`): `migrate` (`2027_04_17_360000`) bildet gespeicherte
  Werte gestrichener Statusfälle auf belegte ab – Rate `overdue` → `planned`,
  Domain-Ereignis `failed` → `stored`, Bankumsatz `duplicate` → `ignored`,
  Steuerregel `draft` → `retired`, Reservierung `cancelled` → `released`,
  Seriennummer `reserved` → `in_stock`, Lieferbestand `cancelled` →
  `delivered`, SMS `delivered` → `sent`, Umfrage-Einladung `expired` → `sent`
  (der Ablauf steht in `expires_at`), Anhangsprüfung `failed` → `pending`
  (erneute Prüfung), To-Do-Verknüpfung `paused` → `active`. Kein Code hat diese
  Werte je geschrieben; die Abbildung schützt nur den Enum-Cast.
  `2027_04_17_360100` setzt offene Auslieferungen ohne Kunden auf „Nicht
  erforderlich“.
- Steuer, Belege, Rechte (`MVP-1623`–`MVP-1625`): `migrate` (vier Migrationen)
  trägt in Kontenplänen aus der SKR03-/SKR04-Vorlage die Erlöskonten 8120,
  8336, 8338 bzw. 4120, 4336, 4338 als Automatikkonten der Kennzeichen AUSF,
  RC21 und NSTB samt Erlösregel nach (eigene Konten und Regeln bleiben),
  ergänzt `issuer_snapshot` an Angeboten und Auftragsbestätigungen (Bestand
  bleibt ohne und zeigt die aktuellen Stammdaten), `restricted_columns` an
  Exportläufen und setzt einen gespeicherten Domain-Verlängerungsmodus
  `RENEWONCE` auf unbekannt – der nächste `domain:sync` liest den tatsächlichen
  Modus. Wer im DATEV-Stapel ohne Hauptbuch bisher Rechnungen ohne Steuer auf
  8200/4200 übergeben hat, stimmt die Umstellung auf die Konten des
  Steuerfalls mit der Kanzlei ab. Entwürfe mit veraltetem Steuerfall halten
  bei der Ausstellung einmal an.
- E-Rechnung und Dokumentdesign (`MVP-1623`, `MVP-1624`): `migrate` verbreitert
  `document_render_snapshots.document_kind` auf 50 Zeichen. Für steuerfreie
  Umsätze nach § 4 UStG eine Steuerregel der Satzart „Befreit“ mit dem
  Befreiungstatbestand als Hinweistext anlegen, sonst nennt die E-Rechnung nur
  „Steuerfreier Umsatz“ (Warnung in der Prüfung).
- Spalten ohne Leser (`MVP-1623`): `migrate`
  (`2027_04_17_370000`) entfernt `projects.global_activities` (Kimai-Rest),
  `team_user.is_lead` (wer leitet, steht in `teams.lead_user_id`),
  `timesheets.attendance_total_minutes` und `untracked_minutes` (nie befüllt;
  den Abgleich mit der Anwesenheit führt der Tagesabschluss) und
  `passenger_vehicle_profiles.large_capacity` (die Disposition prüft die
  Sitzplätze; das Feld „Großraum“ entfällt im Fahrzeugprofil). Der
  Generatorstand der Belege steigt auf `document-design/1.2.0`.

- Lager, Zeit, Fachmodule, Nachweise (`MVP-1626`–`MVP-1629`): `migrate` legt
  `negative_stock_requests` an, ergänzt `procedure_deviations.requalify_run_id`
  und an `firefly_transactions` die Erledigung (`settled_at`, `settled_by`,
  `note`) und überführt Texte aus `diary_entries.notes` in Kommentare am
  Auftrag, bevor die Spalte entfällt (der Rückweg legt sie leer an, die Texte
  bleiben Kommentare); gespeicherte Gleitzeitsalden bereits freigegebener
  Monate werden gesperrt. Die Vier-Augen-Freigabe für Negativbestand ist aus;
  einschalten in den Lager-Einstellungen. Danach einmal `php artisan
  protocols:tidy-pdfs --dry-run`, dann ohne `--dry-run`: alte Protokoll-PDFs
  unter `protocols/{org}` werden Anhang bzw. entfernt.

- Offline (`MVP-1595`): `migrate` ergänzt an `sync_commands` die Entscheidung
  über Konflikte (`resolution`, `resolved_at`, `resent_client_uuid`). Bestehende
  Konflikte gelten als offen und erscheinen bei der jeweiligen Person unter
  „Offline-Änderungen“.
- Umsatzsteuer der Empfängerseite (`MVP-1621`, `MVP-1622`): `migrate` (zwei
  Migrationen) ergänzt Vorsteuerkonto, Vorsteuer-Kennziffer, Steuerfall und
  DATEV-BU-Schlüssel an den Steuerkennzeichen, den Steuerfall an
  Eingangsrechnung und Lieferant und das § 13b-Merkmal am Kunden. In
  Kontenplänen aus der SKR03-/SKR04-Vorlage trägt sie die Konten und
  Kennzeichen der Steuerschuld des Empfängers (RC13BEU, RC13B, IGE19, IGE7)
  und RC60 nach, setzt die BU-Schlüssel der Vorlagen-Kennzeichen (USt19 3,
  USt7 2, VSt19 9, VSt7 8, RC21 47, AUSF 1), markiert die DATEV-Automatikkonten
  der Vorlage und ergänzt die Regeln für Vorsteuer 7 % sowie die Erlöskonten
  für IGL und RC60. Eigene Kennzeichen, Schlüssel, Konten und Regeln bleiben;
  ein Konto, dessen DATEV-Konto abweicht, wird nicht als Automatikkonto
  markiert. Wer eigene Kontenpläne führt, legt die Kennzeichen „Umsatz- und
  Vorsteuer“ mit Steuerfall selbst an, trägt die BU-Schlüssel ein und markiert
  seine Automatikkonten – sonst bleiben Eingangsrechnungen mit Steuerschuld des
  Empfängers mit Hinweis in der Buchungs-Inbox stehen, und der DATEV-Stapel
  setzt an Automatikkonten einen Schlüssel. Danach den Bericht
  „Buchungsqualität“ öffnen: Er nennt festgeschriebene Eingangsrechnungen mit
  Steuerschuld des Empfängers ohne Steuerbuchung; nachgebucht wird nichts
  automatisch. Der DATEV-Stapel aus dem Journal hat jetzt einen Buchungssatz je
  Gegenbuchung statt je Zeile – ein früher exportierter Stapel ist nicht
  erneut zu importieren, sondern mit der Kanzlei abzugleichen. Lieferanten, die
  regelmäßig nach § 13b abrechnen, erhalten die Vorgabe im Lieferantenformular.
- Aufträge (`MVP-1596`): `migrate` stellt `diary_entries.accepted_final_by` von
  der Nutzerkennung auf den Namen der abnehmenden Person um und füllt ihn aus
  der Kundenunterschrift des verknüpften Protokolls. Der Mitarbeiter, der die
  Abnahme ausgelöst hat, steht unverändert im Verlauf des Auftrags.
- Arbeitszeitkonto (`MVP-1550`): Der Saldo beginnt mit dem ersten Lauf beim
  Vormonat. Wer ältere Monate im Kontostand führen will, rechnet einmalig mit
  `php artisan flex:recompute --from=JJJJ-MM` zurück; der Lauf ist wiederholbar.
- Urlaub (`MVP-1554`): `php artisan migrate` ergänzt `vacations.revision_note`.
- Buchhaltung (`MVP-1555`, `MVP-1559`): `migrate` (vier Migrationen) trägt die
  Gegenkonten des OPOS-Ausgleichs (SKR03 2400, 3736, 2709; SKR04 6930, 5736,
  4839, dazu 3731 bzw. 5731) in Kontenpläne aus der Vorlage nach, ergänzt die
  Steuerkennzeichen RC21, AUSF und NSTB und setzt die Steuerkategorie an IGL
  und FREI (eigene Kennzeichen und Konten bleiben)
  und ergänzt `accounting_open_items.disputed_at`/`dispute_note`. Danach den
  Bericht „Buchungsqualität“ öffnen: Er nennt Skonto- und
  Ausbuchungsausgleiche, die ohne Buchung geschlossen wurden – sie sind von
  Hand nachzubuchen. Wer eigene Konten führt, trägt Skonto- und
  Ausbuchungskonto in den DATEV-Einstellungen ein. Derselbe Bericht nennt
  Skonto-Ausgleiche, die vor diesem Stand ohne Steuerberichtigung gebucht
  wurden, mit ihrem rechnerischen Steueranteil – es wird nichts automatisch
  nachgebucht; über Korrekturbuchungen entscheidet die Steuerberatung. Wer
  eigene Steuerkennzeichen für Umsätze ohne Steuer führt, trägt dort die
  „Steuerkategorie“ ein (Kontenplan → Steuerkennzeichen), damit die Inbox sie
  findet; eine Erlösregel mit eigenem Steuerkennzeichen geht vor.
- Phase 176 (Helpdesk): `migrate` (eine Migration, Feiertagsregion der Queue).
  `db:seed` (läuft im Deploy) vergibt das neue Recht „Leistungen aus dem
  Servicekatalog bestellen“ an die internen Profile.
- Phase 174: `migrate` (drei Migrationen, darunter die Umschreibung des
  Gegenpartei-Typs). Organisationen mit lokaler Buchhaltung wenden die
  Kontenplan-Vorlage erneut an (ergänzt nur fehlende Konten und Regeln), damit
  die Rolle „Erhaltene Anzahlungen“ belegt ist. `db:seed` (läuft im Deploy) gibt
  der Geschäftsführung das Eingangsrecht für Genehmigungen. Zeiten aus
  Rechnungsstornos vor diesem Stand öffnet
  `scripts/artisan.sh billing:reopen-times --from=… --apply` wieder — vorher
  den Probelauf ohne `--apply` lesen, sie könnten inzwischen von Hand
  abgerechnet sein. Rechnungen, die im Buchungseingang mit „Keine
  Buchungsregel für Erlös“ hängen, erneut vorschlagen lassen. Organisationen
  mit Investitionsanträgen weisen die Stufenrollen (Einstellungen →
  Genehmigungen) zu; ohne Rolleninhaber entscheidet niemand, auch kein Admin.

- Wurden Tagebuch, Bereitschaft oder Notdienst schon mit `legacy:import`
  übernommen, stehen diese Zeiten im neuen System um eine bis zwei Stunden zu
  spät. Ein erneuter Lauf (`scripts/artisan.sh legacy:import --diary --shifts
  --assignments`) korrigiert sie, überschreibt dabei aber wie bisher Änderungen,
  die im neuen System an übernommenen Einträgen gemacht wurden.
- Bürgschaften (`MVP-1561`): Der nächste Lauf von `notifications:scan-deadlines`
  setzt alle aktiven Bürgschaften mit überschrittener Befristung auf
  „Abgelaufen“. Wer eine Verlängerung noch nicht eingetragen hat, trägt das
  neue Datum bei „Befristet bis“ nach – die Bürgschaft ist dann wieder aktiv.
- Fakturierung (`MVP-1562`): Organisationen, in denen `invoicing.require_approval`
  bereits von Hand in den Einstellungen stand, brauchen zur Freigabe ab jetzt
  eine zweite Person mit Ausstellungsrecht.
- Frontend (`MVP-1564`): `npm run build` ausführen – die Vorbelegung aus dem
  Abnahmeprotokoll nutzt eine Erweiterung in `resources/js/app.js`.
- Prozeduren (`MVP-1566`, `MVP-1571`): `scripts/artisan.sh migrate` ergänzt
  `procedure_backup_proofs.registered_by_user_id`, `.rejected_at` und
  `procedure_template_versions.allow_blocked`.
- Prozeduren (`MVP-1566`): Rückspieltest und Bestätigung eines Backups
  brauchen eine zweite Person; wer den Nachweis erfasst hat, bestätigt ihn
  nicht mehr selbst.
- Prozeduren (`MVP-1570`): Kritische Vorlagen ohne Vier-Augen-Schritt lassen
  sich nicht mehr veröffentlichen. Bereits veröffentlichte Versionen ohne
  solchen Schritt bleiben abschließbar; mit der nächsten Version braucht die
  Vorlage einen Vier-Augen-Schritt oder eine niedrigere Risikostufe. Das
  Profil Taxi/Mietwagen legt „Störung / Unfall“ bei neuen Installationen mit
  Risikostufe „Hoch“ an.
- Prozeduren (`MVP-1567`): Eine kritische Abweichung mit offenem Punkt oder
  Folgeauftrag sperrt den Abschluss nicht mehr; die Eskalation allein hebt
  die Sperre nicht auf.
- Protokolle (`MVP-1572`): `scripts/artisan.sh migrate` ergänzt
  `protocol_signatures.signature_attachment_id`. Bereits unterschriebene
  Protokolle bleiben unverändert; offene Abnahme- und Übergabeprotokolle
  brauchen vor dem Abschluss die Unterschriften ihres Typs. Im Dialog im Haus
  stehen nur noch „vor Ort“ (mit gezeichneter Unterschrift) und „Papier“ (mit
  Scan) zur Wahl. Der Signaturlink-Versand nutzt die Mail-Queue.
- Protokolle (`MVP-1573`): `npm run build` ausführen (Umsortieren der Punkte,
  `resources/js/protocol-item-order.js`). Foto-Geodaten werden nur noch
  gespeichert, wenn die Organisation die Einstellung einschaltet.
- Assets (`MVP-1574`): `scripts/artisan.sh migrate` ergänzt
  `assets.decommission_reason` und `asset_assignments.return_note` und legt
  für Assets im Status „Gesperrt“ oder „In Reparatur“ ohne blockierenden
  Defekt eine Sperre an (Grund „Manuell gesperrt“ bzw. „Wartung“) — diese
  Assets sind danach in Verleih, Reservierung, Druck und Prozedurstart
  gesperrt, bis der Status zurückgesetzt wird. Ein anderer Kunde im
  Bearbeiten-Dialog verlangt jetzt das Recht `asset.transferOwnership`
  (den Standardrollen für Mitarbeitende und Außendienst fehlt es). „Außer
  Betrieb“ steht im
  Bearbeiten-Dialog nicht mehr zur Wahl. Der Asset-Import liest die Spalte
  „Zustand“ weiter ein, übernimmt den Wert aber nicht.
- Prüfwesen (`MVP-1575`): `scripts/artisan.sh migrate` ergänzt
  `asset_inspection_schedules.cancel_reason` und
  `asset_compliance_requirements.is_active`. Der erste Fristenlauf nach dem
  Update setzt offene Prüftermine mit verstrichenem Tag auf „Versäumt“.
- Protokolle (`MVP-1572`): Offene Signaturlinks an Entwürfen lassen sich
  nicht mehr einlösen; nach „Zur Prüfung einreichen“ neu ausstellen.
  Unterschriften aus dem Entwurf, die nicht mehr zum Inhalt passen, werden
  beim Einreichen verworfen. Eine Ablehnung über den Link setzt das Protokoll
  jetzt zurück in den Entwurf. PDFs unterschriebener Protokolle werden beim
  nächsten Abruf als Anhang angemeldet.
- Reklamation (`MVP-1577`): `scripts/artisan.sh migrate` ergänzt
  `claim_supplier_recourses.response_due_days`.
- Entsorgung (`MVP-1578`): `scripts/artisan.sh migrate` ergänzt
  `disposal_jobs.is_photo_in_record` (Vorgabe aus). Ein Asset lässt sich nur
  in einer offenen Entsorgungsakte führen.
- Cloud-Dokumenteingang (`MVP-1613`): `migrate` setzt Verbindungen, die schon
  liefen oder nicht mehr im Entwurf stehen, auf „Start bestätigt“ — sie
  importieren weiter. Bestehende Ordnerregeln haben keinen Testpfad; beim
  nächsten Bearbeiten ist einer anzugeben.
- Eigene Felder (`MVP-1615`): `migrate` befüllt den Suchtext aller
  Wertesätze; danach finden die Suchbereiche die eigenen Felder.
- Verein (`MVP-1616`): `migrate` gibt Prüfungen mit schon erfassten
  Ergebnissen frei, damit Mitglieder ihre bisher sichtbaren Grade behalten.
  Neue Ergebnisse brauchen danach „Ergebnisse freigeben“.
- Überblick (`MVP-1617`): `migrate` entfernt `project_time_targets.site_id`
  (nie befüllt). Die Kachel „Finanzbewegungen“ ist ausgeblendet und lässt sich
  unter „Dashboard anpassen“ einblenden.
- Lizenz (`MVP-1618`): `LicenseService` ist versiegelt — nach dem Deploy
  `license:seal` ausführen (deploy.sh tut das). Das Speicherkontingent kommt
  jetzt aus der Organisationslizenz, wenn eine Organisation eine nutzbare
  eigene Lizenz trägt; ohne Kontingent darin gilt kein Speicherlimit.

- Sammelreste (`MVP-1619`): `migrate` ergänzt Zeitzone, Ruhezeit und
  Wiederholung an `scheduled_job_overrides` und entfernt drei Spalten ohne
  Lese- und Formularweg: `meter_readings.photo_path` (Foto ist jetzt ein
  Anhang), `procedure_template_code` an Wartungsplan und -vorlage (die
  Prozedur-Zuordnung läuft über Kategorie, Produkt, SLA-Vertrag und Kunde,
  MVP-1571), `energy_logs.location_lat/lng`. Die GET-Route
  `quotes/{quote}/auftragsbestaetigung` (Altpfad vor MVP-1162) entfällt;
  Request-Felder ohne Wirkung entfallen: `source_reference` an der
  Ticket-Anlage, `notes` am Auftrag (Spalte bleibt bis zur Prüfung auf
  Bestandsdaten). Nach dem Deploy `schema:dump` (Hauptlauf).
- Datenführerschaft (`MVP-1592`): `migrate` entfernt den wirkungslosen
  Matrix-Eintrag für Bestände (`data_ownership.inventory`); maßgeblich ist die
  Bestandsführung (`inventory_mode`, `inventory_plugin_id`). Bitte die Matrix
  prüfen: Wer früher einen Bereich einem Plugin zugewiesen hat, dessen
  Schreibwege das nun sperrt, sieht die Fälle im Integrations-Eingang; ein
  gespeichertes Plugin, das den Bereich nicht schreiben kann, ist markiert.
- Datenführerschaft (`MVP-1592`, Teil 2): Die Stammdatenhoheit
  (`finance.master_data_authority`) stellen Organisationen jetzt in der Matrix
  in der Zeile „Kunden und Lieferanten“ ein; steht sie bereits auf
  „Buchhaltung“, zeigt die Matrix „Buchhaltung (jedes Programm, das Kunden
  schreibt)“ — bitte das führende Programm wählen. Offene Klärfälle „fremd
  geführt“ der Aufgabenimporte werden beim nächsten Abgleich zu Fällen
  „Datenführerschaft“. Keine Migration.

## [1.1.0] - 2026-11-08

Phasen 169–173 seit 1.0.0: Gerätelaufzeit, Projekt-Sollzeiten,
Fernwartungs-Anbieter, rechnungsbegründende Unterlagen in der E-Rechnung und
der Lückenschluss aus dem Scan auf Umsetzungslücken vom 2026-11-08. `1.0.0`
bezeichnet den Stand von Commit `067af1fa3`; beide Git-Tags setzt und
veröffentlicht der Inhaber.

### Hinweise zum Update

- Phase 173 (Lückenschluss): `migrate` bringt Datenmigrationen mit — die
  Richtung gespiegelter sevDesk-Belege wird korrigiert (frühere sevDesk-Umsatz-
  und Einkaufszahlen ändern sich rückwirkend), Domain-Befehle bekommen den
  Morph-Alias, Stellen- und Budgetanträge im Status „Entwurf“ werden
  geschlossen bzw. in die Genehmigung gesetzt. Danach prüfen, ob
  `invoice_commissions` auf sevDesk-Ausgabebelegen liegen.
- orgaMAX spiegelt Belege jetzt in den gemeinsamen Belegspiegel: direkt nach
  dem Deploy `scripts/artisan.sh orgamax:sync`, bei großem Bestand zusätzlich
  einmal `scripts/artisan.sh accounting:pull-vouchers --plugin=orgamax --pages=50`.
- Die Standardrollen bekommen beim Deploy neue Rechte (Buchhaltung:
  orgaMAX-Aktionen; Personalverwaltung: Urlaubsansprüche; alle Mitarbeiter-
  rollen: „KI nutzen“).
- Neue Env-Schlüssel: `SEVDESK_SUPPLIER_CATEGORY_ID` (Vorgabe 2),
  `GITLAB_TIME_WRITEBACK`. Für den Rechnungseingang an sevDesk je Organisation
  den Schalter und die Buchungskonten in den Plugin-Einstellungen setzen.
- Organisationen mit installiertem Branchenprofil sehen einen Update-Hinweis;
  „Erneut anwenden“ bringt Asset-Kategorien und neue Vorlagen. Optional
  `scripts/artisan.sh db:seed --class=ClassificationSeeder --force` für die
  Plattform-Vorgaben der neuen Klassifikationsdomänen.
- Optional `scripts/artisan.sh identifiers:audit --csv=<datei>`: listet
  ungültige IBAN, BIC, USt-IdNr. und Steuernummern im Bestand (sie bleiben
  lesbar).

- Nach dem Deploy einmalig `scripts/artisan.sh incoming-invoices:extract-documents --dry-run`,
  danach ohne Option: löst eingebettete Unterlagen (BG-24) aus dem Bestand des
  Rechnungseingangs. Erst mit diesem Stand laufen lassen, jeder Eingang wird
  nur einmal bearbeitet.
- Für den WorkDiary-Versand extern ausgestellter Rechnungen Versandweg und
  Erklärung zum Anbieterversand in den Organisationseinstellungen setzen.
- Fernwartung: `scripts/artisan.sh remote:merge-sessions --days=120 --dry-run`
  prüfen, dann ohne `--dry-run` laufen lassen — verkettet bestehende
  Verbindungsschnipsel derselben Person, desselben Kunden und Geräts und hebt
  Einträge unter einer Minute auf eine Minute.
- Toggl: Einstellung „Fehlende Toggl-Projekte anlegen“ nach Bedarf
  einschalten, danach `scripts/artisan.sh toggl:push --days=90`.

### Added

- **Buchhaltungs-Parität** (Phase 173, MVP-1495–1507): Belegliste in Kunden-
  und Lieferantenakte, Einkaufsbelege für Reselling und Materialkosten,
  Auslagen-Verknüpfung und Original-PDF für alle gespiegelten Programme;
  orgaMAX als Belegspiegel mit Rechnungen, Positionen, Ausgaben und Katalog;
  Kontakt-Rückweg für sevDesk und easybill bei „Buchhaltung führt“;
  Lieferanten an sevDesk, easybill und orgaMAX; sevDesk-Gutschriften im
  Spiegel; Pauschal-Modus mit sevDesk und easybill; Buchhaltungswechsel
  zwischen Lexoffice, orgaMAX, sevDesk und easybill; Rechnungseingang an
  sevDesk.
- **Zugangsdaten erneuern** (MVP-1492) für KI-Verbindungen (Bearbeitungsdialog)
  und Domain-Anbieter (auch zum Wiederverbinden).
- **Fertigungsauftrag abschließen, warten, sperren, wieder aufnehmen**
  (MVP-1493); der Abschluss füllt die Nachkalkulation.
- **Soll-Auffüllung in der lokalen Rechnung** (MVP-1494) wie in der Übergabe.
- **Materialsuche über den Artikelspiegel und Rufnummern aus easybill und
  orgaMAX** (MVP-1500).
- **GitLab: gebuchte Zeit zurückschreiben** (MVP-1513, Opt-in).
- **Qualifikation: Sperre statt Hinweis** als Einstellung je Organisation
  (MVP-1514).
- **Verleih: Online-Vorauszahlung bei Direktbuchung** (MVP-1515) mit
  Zahlungsfrist und Anrechnung auf die Rechnung.
- **Kalender-Abos (iCal) als Dauer-Sync** (MVP-1516).
- **Branchenprofile** (MVP-1508–1509): Asset-Kategorien der Profile werden
  als Kategorien der Organisation installiert (Klassifikationsdomäne
  „Asset-Kategorien“, Formular und Validierung lesen sie mit); Profile
  Ambulante Pflege (v6) und Partyservice (v3) mit den Vorlagen, Domänen und
  Pflichtregeln ihrer Profilseiten, Beratungsbesuch alle drei Monate als
  Vertragsvorlage; Einzelvorlagen für SHK, Gebäudereinigung, Veranstalter,
  Spedition, Steuerberater und Bau. Abgelöste Vorlagen werden beim erneuten
  Anwenden deaktiviert, nie gelöscht.
- Wege für bisher unerreichbare Zustände (MVP-1517): Leasing-Entwurf
  stornieren, Krisenfall als Fehlmeldung verwerfen, Ausschreibungsakte
  ablegen, Mitarbeiterentwurf verwerfen.

- **Rechnungsbegründende Unterlagen in der E-Rechnung** (Feature 196,
  Phase 172, MVP-1483–1489): Leistungsnachweis aus den abgerechneten Zeiten
  (PDF, Personen mit vollem Namen, Kürzel oder ohne) und hochgeladene Unterlagen
  gehen eingebettet als BG-24 in XRechnung (UBL/CII), Peppol und ZUGFeRD mit;
  Leistungszeitraum je Rechnung und Position, Objektkennungen, Prüfung von
  BR-52, BR-CL-24, BR-DE-22 und den Größengrenzen der Empfänger.
  Empfängerprofil je Kunde (Behörde: Nachweis automatisch, nur XML), Mail und
  Kundenportal mit denselben Dateien. Der Rechnungseingang löst eingebettete
  Unterlagen und legt sie am Eingang ab (Nachlauf
  `incoming-invoices:extract-documents`). Extern ausgestellte Rechnungen
  (Lexware Office, sevDesk, easybill, orgaMAX) übernimmt WorkDiary
  festgeschrieben vom Anbieter, gleicht sie mit der Übergabe ab, ergänzt sie
  ohne Neuerzeugung um die Unterlagen und stellt sie selbst zu (E-Mail oder
  Peppol, unveränderliche Versandakte, Nachsendung); Versandweg je Kunde und
  Organisation. Hilfe ×5; Toolkits erechnung 0.21.0 und pdf 0.31.0.
- **Fernwartung: Splashtop, RustDesk und pcvisit** (Feature 195, Phase 171,
  MVP-1472–1477): Anbieterregister als einzige Stelle, die Anbieter kennt;
  Abruf über RustDesk Server Pro (Verbindungsprotokoll), den Open-Source-API-
  Server, Splashtop Open API (abgeschlossene Sitzungen) und pcvisit (GraphQL);
  CSV-Import erkennt den RustDesk-Pro-Export; Fehler eines Anbieters stoppen
  die anderen nicht. Hilfe ×5.
- **Fernwartung: Verbindungen verketten** (MVP-1480–1482): Eine erneute
  Verbindung derselben Person zu Kunde und Gerät innerhalb der Toleranz
  (Einstellung „Verbindungen zusammenfassen bis (Minuten)“, Vorgabe 30)
  verlängert den bestehenden Zeiteintrag statt einen neuen anzulegen; Notiz
  der Folgeverbindung geht in die Beschreibung; exportierte Einträge und
  abgeschlossene Monate bleiben unberührt; neue Einträge dauern mindestens
  eine Minute; Nachlauf `remote:merge-sessions`.
- **Toggl: fehlende Projekte** (MVP-1478–1479): tägliche Nachfüllung der
  Toggl-IDs für alle Projekte der Organisation, Hinweis in der
  Integrations-Inbox für Projekte ohne Gegenstück, optional Anlage von Kunde
  und Projekt in Toggl (Track und neues Toggl).
- **Projekt-Sollzeiten je Person** (Feature 194, Phase 170, MVP-1467–1471):
  Soll je Person und Tag am Projekt (Projektvorgabe je Wochentag,
  Personenzeilen, Ausnahmen je Datum, Gültigkeit) im Raster „Sollzeiten
  pflegen“ am Zeitreiter; nächtlicher Abgleich `projects:check-time-targets`
  mit Befunden unter Soll (Toleranz `project.target_tolerance_minutes`),
  Meldung an die Projektleitung, Vorgang im Arbeitsvorrat, weiche Warnung im
  Tagesabschluss, Kennzahl der Leitungssicht, Frühwarnung bei Häufung,
  Plan/Ist-Sicht „Projekt je Person“; Projekte mit „Vereinbarte Stunden
  abrechnen“ ergänzen in der Übergabe die fehlende Zeit als Position
  „Vereinbarte Einsatzzeit“, Nachbuchungen auf berechnete Tage sind nicht
  abrechenbar. Recht `project.targets.manage`, Hilfe ×5.
- **Gerätelaufzeit als Eintragsart** (Feature 193, Phase 169, MVP-1463–1466):
  Laufzeit eines Geräts oder Prozesses, etwa das Auslesen einer defekten
  Platte über zwei Tage, als eigene Art neben Arbeit, Anfahrt und
  Bereitschaft im Projektzeit- und Stundenzettel-Dialog. Bis 31 Tage je
  Eintrag (Personenzeit bleibt bei 24 Stunden), zählt nicht zu Gleitzeit,
  Pausenprüfung, Lohnexport, Auszahlung, SLA-Kontingent und der Arbeitszeit
  eines Stundenzettels. Abrechnung zum neuen Stundensatz Gerätelaufzeit der
  Organisation (Einstellung `invoicing.machine_hourly_rate`,
  `INVOICING_MACHINE_HOURLY_RATE`) oder über eine Projekt-Abrechnungsregel
  für die Art; eigener Block in Übergabe und Rechnung. Projektzeiten mit
  Filter „Art“, Kennzeichen und Summe „davon Gerätelaufzeit“, Kachel am
  Projekt; Hilfe ×5.

### Changed

- Belegspiegel von easybill, JTL-Wawi und InvoicePlane laufen stündlich,
  sevDesk lädt die Positionen mit; abgeschaltete Plugins ziehen keine Belege
  (MVP-1490).
- Standardrollen (MVP-1510), Decimal-Casts für alle Dezimalspalten
  (MVP-1511), Identifikator-Casts normalisieren IBAN, BIC, USt-IdNr.,
  Steuernummer, Steuer-ID und GTIN beim Speichern (MVP-1512).
- Klassifikations-Codes dürfen camelCase sein, damit sich mitgelieferte
  Codes wie `accessPoint` in den Organisationseinstellungen überschreiben
  lassen.
- Queue-Jobs eines Fachmoduls laufen nicht mehr, wenn das Modul der
  Organisation aus ist (MVP-1518).
- Entfernt (MVP-1519): Lexoffice-Artikel-Rückschreiben und Artikelkonflikte
  (wurden nie ausgelöst), Plugin-Fähigkeit „Zahlungsabgleich“ ohne Verbraucher;
  Route `admin.ai.rotate` (Schlüssel im Bearbeitungsdialog). Gestrichen:
  unerreichbare Zustände „Entwurf“/„in Erfüllung“ bei Service-Requests,
  „Entwurf“ bei Stellen und Budgetanträgen, „vorbereitet“ bei Krisen.

- Übergabe- und Rechnungsblöcke nennen die Art als Bezeichnung
  („[Anfahrt]“ statt „[travel]“).
- Entwicklung: Jede Klassenkonstante nennt ihren Typ (PHP 8.3), Gate
  `TypedClassConstantRuleTest`.
- Zammad: Anbindung über zammad-api-client-php 3.2. Über `http://` spricht
  sie nur Hosts an, für die die Anbindung private Netze freigibt. Ein
  HTTP-Fehler beim Abruf bricht den Import ab, statt ihn still unvollständig
  zu beenden.
- Abhängigkeiten: spatie/laravel-permission 8.5, minishlink/web-push 11
  (Push-Versand weiter ohne Weiterleitungen und mit gebundenen Adressen),
  npm-Gruppe mit 15 Paketen (u. a. Playwright 1.64, Chart.js 4.6,
  material-symbols 0.48 mit neu erzeugter Icon-Schrift), setup-php 2.40 in
  der CI.

### Fixed

- Domain-Anbieter „Trennen“ löschte Befehlsjournal, Projektionen,
  DNS-Zonen und Buchungen per Kaskade; es ist jetzt ein Statuswechsel, die
  Nachweise bleiben (MVP-1491).
- Belegfluss: lokale Rechnungen erschienen doppelt, wenn sevDesk oder easybill
  die Nummer vergab; eingehende Spiegelbelege zählten negativ, Entwürfe und
  Stornos mit (MVP-1495).
- sevDesk: Ausgabebelege zählten als Umsatz (Richtung `creditDebit`
  vertauscht), Fälligkeit war immer leer (MVP-1520).
- Serienvorlagen bekamen im Spiegel nie Positionen; Domain-Befehle schrieben
  den Klassennamen in die Typspalte; Lexoffice-Materialsuche scheiterte an
  bereits vergebener Artikelnummer; Hinweisgeber-Hilfe beschrieb vorhandene
  Funktionen als fehlend.

- Unter SQLite (CI, kleine Installationen): Storno ausgestellter Rechnungen,
  Übertragung nach easybill und sevDesk und der Artikelspiegel meldeten bei
  unverändertem Steuersatz oder unveränderter Menge eine Änderung;
  Basiszinssätze, Liquiditäts-Snapshots, Gleitzeitberechtigungen und
  Demo-Sollzeitbefunde konnten doppelt entstehen; der GAEB-Export brach am
  Steuersatz ab; die Suche ließ den letzten Tag des Zeitraums weg; der
  Demo-Purge löschte die Organisation aus dem Audit-Eintrag.
- Nightly auf MariaDB lief wegen eines veralteten Testpfads gar nicht.
- Peppol: Eine endgültig abgelehnte Zustellung galt als versendet; der Versuch
  steht weiter im Protokoll, der Versand meldet jetzt den Fehler des
  Zugangspunkts.
- Basiszinssätze: Der Import entfernte Übergänge nicht, die die Bundesbank in
  ihrer Reihe revidiert hat; im abgedeckten Zeitraum ist die Reihe maßgeblich.
- Rechnungsbegründende Unterlagen gehören genau einer Rechnung, einer lokalen
  oder einer extern ausgestellten; vorher ließ sich eine Unterlage an beiden
  oder an keiner speichern.
- Demo-Daten: Der Sollzeitbefund am Freitag passte nicht zum halben Tagessoll.
- ZUGFeRD-Download an der Rechnung und im Kundenportal scheiterte im
  Webbetrieb: Unter PHP-FPM (Arbeitsverzeichnis `public/`) durfte TCPDF 7 das
  Farbprofil für PDF/A nicht lesen. pdf-toolkit 0.31.1.
- Rechnungseingang: Die Projektreferenz einer UBL-Gutschrift (Dokumentart 50)
  kam als Unterlage ohne Inhalt an; die Ergänzung extern ausgestellter
  Gutschriften zählte sie als vorhandene Kennung. erechnung-toolkit 0.21.2, das
  außerdem Gutschriften mit Steuerdatum oder Projektreferenz schemakonform
  schreibt und die XRechnung-Extension mit der Kennung des KoSIT-Szenarios.

### Security

- Symfony-Komponenten auf 8.1.10/8.1.11 (11 Hinweise in filesystem,
  http-foundation, http-kernel, routing, serializer und yaml).

## [1.0.0] - 2026-11-02

Erste versionierte Freigabe nach 168 Phasen: alles, was bis zum
2026-11-02 unter „Unreleased“ stand. Das automatisierte Release-Gate
(Feature 051, `composer security:gate`) ist am 2026-11-02 gelaufen; den
Git-Tag setzt und veröffentlicht der Inhaber.

### Hinweise zum Update

- Neue Umgebungsvariablen (alle in `.env.example`): `SCHEDULER_SKIP_INACTIVE_PLUGINS`
  (Vorgabe `true` — plugin-gebundene Scheduler-Jobs ohne irgendwo aktives
  Plugin werden nicht angemeldet), `TENDER_NOTICE_RETENTION_DAYS` (Vorgabe
  365, Löschung alter Bekanntmachungen ohne Treffer),
  `INVOICING_QUOTE_VALIDITY_DAYS` (Vorgabe 30, Bindefrist für Angebote).
- Migrationen von Kern und Plugins laufen mit `migrate`; `modules:cache` und
  `morph-map:generate` sind Teil von `deploy.sh`.
- Nach dem Deploy einmalig: `scripts/artisan.sh permissions:own-actions-report`
  (gestrichene Rechte ohne Funktion), `scripts/artisan.sh resale:propose`
  (Domain-Vorlagen im Plan-Abgleich), bei Bedarf
  `scripts/artisan.sh lexoffice:sync-vouchers --full` und
  `scripts/artisan.sh resale:import-incoming --since=<datum>` — Abschnitt
  „Manuelle Befehle“ in `betrieb/server-cron-queue.md` des Doku-Repositorys.
- Scheduler: Takt-Jobs laufen jetzt mit Minutenversatz; ein Monitoring, das
  Läufe zur vollen Stunde erwartet, folgt dem erzeugten Scheduler-Register
  (`konventionen/scheduler-register.md`).
- Datenbankserver und PHP-CLI: Empfehlungen für `my.cnf` (Pufferpool,
  `table_open_cache`, Definition-Cache, Slow-Log) und OPcache auf der
  Kommandozeile stehen in der Leistungsanalyse 2026-11-02 — Betreiberschritte.

### Added

- Vollscan 2026-11-02, Behebung (Protokoll `chronik/audits/vollscan-2026-11-02-behebung.md`
  im Doku-Repository), neu:
  - **Katalogquellen für fünf Buchhaltungsprogramme:** Die Produkt-Einstufung
    des Reselling-Registers führt auch die Artikel von easybill, sevDesk,
    Invoice Ninja, Akaunting und InvoicePlane; der Belegabruf liest sie vor den
    Belegen, gespiegelte Positionen tragen den Artikel der Quelle statt nur
    seiner Kennung.
  - **Aufbewahrung:** Bereich „Fernwartung — wartende Sitzungen“ (ein Jahr,
    Beitrag des Fernwartungs-Plugins) und Löschung von
    Ausschreibungs-Bekanntmachungen ohne Treffer nach Fristende
    (`TENDER_NOTICE_RETENTION_DAYS`).
  - **Scheduler:** täglicher Lexoffice-Vollabgleich als eigener Job
    (`lexoffice.sync_vouchers_full`, 05:50), Betriebsaufgabe „Scheduler-Job
    schlägt wiederholt fehl“ ab drei Fehlern in Folge, Scheduler-Register
    `scheduler:doc` (`konventionen/scheduler-register.md`: Schlüssel, Befehl,
    Kadenz samt Versatz, Plugin-Bindung, Vorprüfung, Kritikalität, Laufzeit,
    Hintergrund) mit Gate `SchedulerDocParityTest`.
  - **CHANGELOG-Gate** `ChangelogCoverageRuleTest`: Jede im Feature-Register
    als umgesetzt geführte MVP-Nummer steht in dieser Datei.
  - **Hilfe:** Servicelandkarte im Helpdesk-Thema, Kapitel „Cockpit“ bei den
    Leads, Reselling-Hilfe in sieben Themen je Seite (Lizenzkonten, Abgleich,
    Abgleichstafel, Abo, Perioden, Serienvorlagen, Berichte) mit
    „Nachberechnung und Vorausplanung“ und „Passung“.
  - **Lexoffice:** „Belege jetzt abgleichen“ im Einstellungsdialog des Plugins
    (Slot `plugin-settings.actions` des Plugin-Dialogs; inkrementeller Lauf im
    Hintergrund, nur mit Recht und Zugangsdaten) — die Route
    `lexoffice.vouchers.sync` hat damit einen Einstieg (gap-12); Hilfe ×5.
  - **Browser-Tests:** `tests/e2e/dialog-followup.spec.ts` (Folgedialog
    „Arbeitszeit-Modell“ aus dem Mitarbeiter-Dialog: Speichern, Abbrechen und
    Escape kehren in die Ursprungsmaske zurück, GitHub #106, gap-05) und
    `tests/e2e/resale.spec.ts` (Mappe → Schnellzuordnung → lokaler
    Rechnungsentwurf, Abo-Import bis zur Vorschau, Zählkacheln filtern die
    Tafel; gap-11/lk-38).
- Reselling und Domains, Pilot-Nachträge 2026-10-27 bis 2026-11-02 (Features
  185, 152, 083):
  - Lizenzkonten-Übersicht mit höchstens fünf Produktzeilen und Domains als
    Summe; Domain-Abos werden über Positionen gedeckt, die die Domain nennen,
    unabhängig von der Produkt-Einstufung des Artikels.
  - Sammelrechnungen tragen den Zeitraum je Position im Text; die Zyklusregel
    des Vorschlagslaufs greift nur bei Geschwister-Abos.
  - Buchungsjournal des Domainportals als Belegquelle: je Subuser und Monat ein
    Beleg „Kundennummer-MM-JJJJ“ an dessen Kunden, die Domain als Position,
    Buchungstyp und Quelle benannt. Preisstaffeln des Portals als
    Berechnungsgrundlage: `domain:sync-prices` spiegelt Einkauf und
    Klassenpreise aller Zonen in den Preiskatalog, Domain-Abos bekommen den
    Verkaufspreis aus der Klasse des Subusers bzw. der benannten Klasse, der
    Monatsbeleg rechnet zum Klassenpreis.
  - DomainReselling-Plugin gegen die API-Referenz abgeglichen: toleranter
    Feldzugriff (Wissensdatenbank und Live-Gateway schreiben Eigenschaften
    verschieden), belegter Vertrag je Befehl (`domain:contract`,
    `domain:prices`), Live-Vertrag mit Rechnungskennung, Subuser und
    Statusereignis je Ereignis, Kontakte und Subuser-Felder vollständig.
  - IDN-Domains tragen die lesbare Form als Bezeichnung („onlinehändler-…“
    statt „xn--…“) auf Seiten, in Listen und auf Rechnungspositionen; die
    Anbieter-Kennung bleibt Punycode, der Vergleich mit Rechnungs- und
    Vorlagentexten kennt beide Schreibweisen.
  - Abgleich: Domainrechnungen an andere Empfänger mit Halterwechsel,
    Domainnamen mit Tippfehler als unsicherer Vorschlag mit Notiz, gleiche
    Positionen desselben Belegs als eine Zeile mit Anzahl, Rechnungen über
    ihre Nummer finden (Bezug-Dialog, Abo- und Periodenliste).
  - Abo-Seite und Perioden: Periodenzeile zeigt unter dem Soll das Ist laut
    Bezügen; Kacheln „Planbare Abos“ und „Offene Perioden“ sowie alle
    Zählkacheln des Reselling-Moduls führen zu dem, was sie zählen; der
    Abtretungs-Dialog zeigt die freie Menge je Zeitabschnitt und begrenzt das
    Mengenfeld; Perioden im Rechnungsentwurf zeigen „Im Entwurf“ statt „fehlt“
    oder „Offen“.
  - Lizenzkonto: Vorlagenpositionen, Zuordnungsauswahl und Befunde nennen
    zuerst das Abo, dann Halter und Kennung; Zebra je Vorlagenblock; die
    Zuordnungsauswahl erscheint nur, solange Lizenzen der Position frei sind;
    Lizenzjahre-Matrix mit einzeiligen Abzeichen und angepinnter erster Spalte.
  - „Halter zuordnen“ am Hinweis ohne Halter, Partnervorschlag im Posteingang,
    Suche nach Firma laut Anbieter, Bestellungswechsel statt Verzicht,
    Vorlagenbefunde je Art gruppiert.
  - Lexoffice meldet bei einem Kontakt ohne genau eine Rechnungsadresse
    (HTTP 406 beim Rechnungsentwurf), was zu tun ist, statt rohes JSON.
- Reselling: ruhige Abgleichstafel und Rücknahme (Phase 168, Feature 185,
  MVP-1459–1462): Jede freie Rechnung steht nur bei der Periode, zu der sie am
  besten passt, Zuordnen ist ein Knopf mit der Menge, die die Periode noch
  braucht, und Passungshinweise erscheinen nur, wenn eine Rückfrage nötig ist
  (eine Sammelrechnung über die ganze Periode passt). Rechnungen ohne passende
  Periode stehen als Zeile „ohne Periode“ mit „Abo anlegen“ oder „Einer
  anderen Periode zuordnen“. Die Rücknahme der letzten Abtretung stellt
  entschiedene Vertragsperioden wieder her. Nachtrag: Die Schnellzuordnung der
  Abo-Seite rechnet in Lizenzen der Periode statt der Rechnungsposition.
- Reselling: Mengen- und Deckungsgrenzen (Phase 167, Feature 185,
  MVP-1452–1458): Nirgends lässt sich mehr zuordnen, als da ist — Zuordnen
  höchstens bis zum offenen Bedarf der Periode (auch beim Verschieben, in einer
  Transaktion), Aufteilungen und Stummel beachten berechnete Lizenzen,
  Vorlagenpositionen und Rechnungspositionen von Stücklizenzen sind nicht
  überbuchbar (Befund „Vorlagenposition überbucht“ für Altbestand), teilweise
  durch eine Serienvorlage gedeckte Abos kommen mit dem Rest in den
  Rechnungsvorschlag, eine Abtretung an den eigenen Halter wird abgelehnt und
  als Altfall mit Rücknahme gezeigt.
- Reselling: Abgleichstafel, Passung und Übernahme (Phase 166, Feature 185,
  MVP-1447–1451): Die Arbeitsmappe zeigt je Produkt Perioden und
  Rechnungspositionen auf einem Zeitstrahl (ersetzt vier Karten); jede
  Position trägt eine Passung zur Periode, fragliche Zuordnungen verlangen
  eine Bestätigung — in Mappe, Bezug-Dialog und Schnellzuordnung. Die
  Zyklusregel des Vorschlagslaufs zählt nur gleichzeitig laufende Abos; beim
  Bestellungswechsel wandern die Bezüge der kurzen Restperiode an die erste
  Periode des Nachfolgers. Für Insolvenzen: Übernahme ab Stichtag durch einen
  neuen Halter und „Als Ausfall verbuchen“ an stornierten Rechnungen; Verzicht
  mit Gründen zur Auswahl. Nachtrag: Abtretbar ist nur, was im Zeitraum nicht
  schon über Rechnungen an den Halter berechnet ist.
- Reselling: Arbeitsmappe je Rechnungsempfänger (Phase 165, Feature 185,
  MVP-1443–1446): Der Abgleich eines Empfängers beginnt mit seinen Abos
  (Bearbeiten, Abtreten, Rücknahme in der Zeile), führt die Serienvorlagen mit
  Zuordnung und kehrt aus jedem Dialog auf die aufrufende Seite zurück. Die
  Arbeitsliste sortiert Empfänger nach offenem Betrag, „Fertig“ heißt keine
  fällige Periode ohne Entscheidung und kein Vorlagenbefund, „Nächsten
  Empfänger abarbeiten“ führt aus Überblick und Liste hindurch. Domains im
  Subuser-Konto rechnet das Domainportal ab (MVP-1446): kein Befund „Abo ohne
  Serienvorlage“, nie im Rechnungsentwurf oder Serienlauf des Registers.
- Reselling: Nachberechnung und Serienweg (Phase 164, Feature 185,
  MVP-1436–1442): Der Rechnungsvorschlag bündelt zusammenhängende offene
  Perioden eines Abos zu einer Position (Menge in Jahren oder Monaten,
  Zeitraum vom ersten Beginn bis zum letzten Ende) und kennt einen Stichtag
  für Vorauszahlungen (+1/+2/+3 Jahre); der tägliche Lauf behält gestempelte
  Perioden. Der Serienlauf gilt auch bei externer Rechnungshoheit
  (`resale:draft-external`, monatlich am Ersten, Schalter und Vorlauf im
  Dialog „Abrechnung“) als Übergabe an das Buchhaltungsprogramm des
  Empfängers; der Plan-Abgleich meldet „Abo ohne Serienvorlage“ nicht, wenn
  der Serienlauf den Empfänger deckt. Die Seite „Vorlagen-Vorschlag“ nennt je
  Empfänger die Abos ohne Serienvorlage mit Artikel, Menge, Preis, Intervall
  und erster Ausführung. Nachtrag: Serienvorlagen zeigen den Ausführungsstand
  aus dem Belegspiegel (bezahlt, offen, fehlt) samt Meldung des Programms —
  die Lexoffice-API kennt keinen Versandstatus; Domain-Artikel gelten als
  Abo-Artikel, Domain-Abos werden Vorlagenpositionen nur bei exakter Nennung
  der Domain zugeordnet.
- Reselling: Ausnahmen statt Bestätigungen (Phase 163, Features 152 und 185,
  MVP-1429–1434; MVP-1435 verworfen): Vorschläge gelten ohne Bestätigung, die
  Periodenseite zeigt standardmäßig „Nur Probleme“; eine fällige Periode wird
  erst nach 21 Tagen Karenz zur Aufgabe und nie, solange eine laufende
  Serienvorlage sie noch berechnet; Rechnungspositionen ohne Abo lassen sich
  ignorieren oder als Abo anlegen; der Überblick ist eine Aufgabenliste in
  vier Gruppen (Berechnen, Zuordnen, Plan prüfen, Anbieterstand) mit Zähler,
  Betrag und Sprung auf die Fläche; Zuordnungen Abo ↔ Vorlagenposition aus
  dem Rechnungsbezug gelten sofort. Die vier Reiter mit Menü (MVP-1435) nahm
  der Inhaber nach dem ersten Blick zurück. Belegansicht: „Zugeordnet“ nennt
  je Ziel die Lizenzmonate, Positionen zeigen die Aufteilung aus dem
  Leistungszeitraum.
- Reselling: Pilot auf den Produktionsdaten (Nachbesserung 2026-10-27,
  Feature 185): Ein Lizenzkonto heißt nach seinem Halter, geht die Rechnung an
  einen Partner, steht er dabei; Serienvorlagen des Partners erscheinen nur im
  zugeordneten Endkunden-Konto; die Zuordnung einer Vorlage läuft über die
  Rechnung, die sie erzeugt hat; Kunde und Fremdkunde gleichen Namens landen
  im Posteingang statt in einer geratenen Zuordnung. Dazu: Halter in der
  Abo-Liste in einem Schritt zuordnen (Domains schreiben in die
  Domainverwaltung durch) und die Belegansicht hinter dem Rechnungsbezug mit
  Vergleich zur Periode.
- Lizenzkonto, Abrechnungsplan-Abgleich und Reselling-Arbeitsfläche
  (Phase 162, Feature 185, MVP-1399–1428): Das Reselling-Register zeigt je
  Anbieterkonto, wer wie viele Lizenzen hält und welches Lizenzjahr berechnet
  ist (Raster Produkt × Halter × Jahr), mit Aufteilung je Produkt und
  Nachplanung abgelöster Verträge (MVP-1399–1403). Sofortmaßnahmen:
  vollständige Reiterleiste, Abrechnungs-Einstellung als Dialog, stündlicher
  Vorschlagslauf mit Selbstbestätigung sicherer Treffer, Domains im Register,
  Artikel in Entwürfen (MVP-1404–1408). Lexoffice-Serienvorlagen als
  Plan-Quelle mit Zuordnung Abo ↔ Vorlagenposition und Prüfregeln
  Bestand ↔ Plan (MVP-1409–1413). Arbeitsfläche: Überblick als
  Aufgabenliste, Sammelaktionen, Produkt-Einstufung mit Filter, Import mit
  Diff-Vorschau (MVP-1414–1418). Einkauf aus dem Rechnungseingang:
  Lieferantenzuordnung je Anbieter, positionsgenaue oder anteilige Übernahme
  zugeordneter Eingangsrechnungen, Marge mit Ist-Einkauf (MVP-1419–1421).
  Ein Weg zu allen Buchhaltungsprogrammen: Abo-Perioden als Übergabequelle
  über den Übergabedienst (MVP-1422–1423). Belegspiegel mit Positionen und
  Serienvorlagen auch aus easybill, Invoice Ninja und sevDesk, Positionen aus
  Akaunting und InvoicePlane (MVP-1424–1426). Stücklizenzen mit Bezug auf
  ihre Rechnungsposition, Prüffall „verkauft ohne Rechnung“, Kundenportal
  „Meine Abos“ (MVP-1427–1428).
- Branchenprofil „Vermieter“ (Feature 042, 2026-10-26): Wohnungen und
  Ferienwohnungen als Mietprofil eines Assets im Verleihmodul (Dauer- oder
  Ferienvermietung, Portalbeschreibung, Gästezahl, Mindestaufenthalt, An- und
  Abreisezeit), Mietverhältnisse der Dauervermietung mit Kaltmiete,
  Nebenkosten und Kaution als Verwaltungsdaten (keine Mietabrechnung),
  Belegung unter der Sperre des Objekts mit Reinigungspuffer, Ferienanfragen
  und „Wohnung mieten“ im Kundenportal; das Profil bringt Abläufe (Übergabe,
  Abnahme, Gästewechsel), ein Übergabeprotokoll, Vertragsvorlagen und eigene
  Felder der Wohnung mit; Musterbranche in Demo und Blueprint.
- Rechnungseingang (Feature 163, 2026-10-25/26): Mehrere Steuersätze je
  Beleg erfassen (eine Zeile je Satz; Netto + Steuer muss das Brutto auf den
  Cent treffen; Kleinbetragsrechnung aus dem Brutto) und erkennen
  (Steuerzeilen und Steuertabellen der PDF-Erkennung); Lexoffice bucht daraus
  je Satz eine Position. Einbehalte an der Eingangsrechnung werden im Dialog
  angelegt, die Karte zeigt sie als Zeilenliste. Das Original steht neben den
  Werten in voller Höhe, „Werte korrigieren“ öffnet die Erfassung mit
  Vorbelegung, TIFF-Scans erscheinen in der Vorschau als PDF.
- Shop- und Belegspiegel nach Entscheidung des Inhabers (Nachtrag Phase 158):
  - **Erstattungen:** Shop-Bestellungen zeigen „davon erstattet“, die
    Bestellsumme bleibt wie im Shop. Die Beträge kommen aus `refunds[]`
    (WooCommerce), den abgeschlossenen Erstattungen an den Captures
    (Shopware) und den Gutschriften (PrestaShop, Recht `order_slip`
    optional).
  - **Löschungen:** Bestellungen, die der Shop nicht mehr führt (auch
    Papierkorb), und Akaunting-Belege, die Akaunting nicht mehr führt,
    erkennt ein täglicher Abgleich. Sie stehen auf „in Quelle gelöscht“ und
    zählen im Belegfluss, im Umsatz und im Einkauf nicht mehr.
  - **PrestaShop:** „Verbindung prüfen“ liest die Zeitzone des Shops
    (`PS_TIMEZONE`, Recht `configurations` optional); eine eingetragene
    Zeitzone geht vor.
  - **Fremdbeträge:** Der Gegenwert einer Invoice-Ninja-Ausgabe in
    Fremdwährung und der zweite Betrag einer Firefly-Buchung stehen im
    Belegfluss bzw. in der Firefly-Liste unter dem Betrag.
- Open-Source-Plugins, Welle 4 (Phase 158, Feature 177, MVP-1346–1348):
  **Seafile** als Quelle im Cloud-Dokumenteingang und als verschlüsseltes
  Backupziel.
  - Eingang: Dateien einer gewählten Bibliothek laufen nur lesend über die
    vorhandenen Ordnerregeln in Rechnungseingang oder Dokumentablage.
    Umbenannte oder verschobene Dateien gelten nicht als neuer Beleg. Ist
    seit dem letzten Lauf nichts geändert, liest WorkDiary keinen Ordner.
  - Backupziel: eigene Verbindung mit Konto-Token in eine bestehende,
    unverschlüsselte Bibliothek. Beim Verbinden wird eine Probedatei
    geschrieben, gelesen und gelöscht.
  - Die Hilfe zu den Backupzielen nennt jetzt alle sieben Ziele.
- Open-Source-Plugins, Welle 3 (Phase 158, Features 173 und 174,
  MVP-1336–1345):
  - **Vikunja, Kanboard, Plane, Taiga und Leantime:** Aufgaben ausgewählter
    Projekte kommen als Aufgaben in WorkDiary-Projekte, nur lesend.
  - **FreeScout und GLPI:** Konversationen ausgewählter Postfächer bzw.
    Tickets ausgewählter Entitäten kommen ebenso als Aufgaben an. Bei
    FreeScout ist die erste Nachricht die Beschreibung.
  - Der Status stammt je System aus einem festen Feld (erledigt,
    Statusgruppe, Statusart oder Statusnummer), nie aus frei benannten
    Spalten. Zugang, Vorschau, Bestätigung, Verknüpfen und Klärfälle laufen
    wie bei Gitea und Redmine.
  - Eine gemeinsame Hilfeseite erklärt alle sieben Importe mit ihren
    Unterschieden.

- Open-Source-Plugins, Welle 2 (Phase 158, Features 175 und 176,
  MVP-1325–1335):
  - **Shop-Plugins WooCommerce, Shopware 6 und PrestaShop:** Bestellungen
    werden gelesen, mit Nummer, Status, Betrag und Käufer gespiegelt und
    vorhandenen Kunden zugeordnet. Unklare Käufer landen als Klärfall in der
    Integrations-Inbox; neue Kunden legt der Import nie an.
  - Vor dem ersten Abgleich zeigt eine Vorschau die Bestellungen des
    Zeitfensters. Importiert wird erst nach „Import bestätigen“. Neue
    Stornos werden nur auf Wunsch übernommen.
  - Die Bestellungen erscheinen in der globalen Suche und am Kunden. Name,
    E-Mail und Firma der Käufer entfernt die Aufbewahrungsregel für
    Marktplatz-Käufer.
  - **Invoice Ninja und Akaunting:** Rechnungen, Gutschriften,
    Lieferantenrechnungen und Ausgaben erscheinen nur lesend im Belegfluss.
    Die Belegnummer führt zum Original.
  - **Firefly III:** Ein- und Ausgänge werden gespiegelt und über eine
    eindeutige Belegnummer im Verwendungszweck Rechnungen bzw.
    Eingangsrechnungen zugeordnet. Gebucht wird daraus nichts.
- Open-Source-Plugins, Welle 1 (Phase 158, Features 170–172,
  MVP-1314–1324):
  - **Gitea/Forgejo:** Issues ausgewählter Repositories einer selbst
    gehosteten Instanz kommen als Aufgaben in WorkDiary-Projekte, nur
    lesend.
  - **Redmine:** dasselbe für Redmine-Projekte (ohne Unterprojekte).
    Zusätzlich lassen sich je Zuordnung die Zeiteinträge eines rollierenden
    Zeitfensters übernehmen, als Datum plus Dauer. Gebucht wird nur auf
    Redmine-Benutzer, die einem WorkDiary-Benutzer ausdrücklich zugeordnet
    sind; alle anderen warten als Klärfall.
  - Basis-URL und Token beider Plugins stehen in den Plugin-Einstellungen
    (interne Adressen nur mit Freigabe). „Verbindung prüfen“ hält die Instanz
    fest. Vorschau, Bestätigung, Verknüpfen und Klärfälle laufen wie bei
    Jira und Asana.
  - **Paperless-ngx:** neue Quelle im Cloud-Dokumenteingang. Die Quelle sind
    alle Dokumente oder die eines Tags, Korrespondenten oder Dokumenttyps.
    Der Pfad `Dokumenttyp/Korrespondent/Dateiname` speist die vorhandenen
    Ordnerregeln für Rechnungseingang und Dokumentablage. Übernommen wird
    die Originaldatei, sodass eingebettete E-Rechnungsdaten erhalten bleiben.
    Nachträglich getaggte Dokumente werden ebenfalls erkannt, und Dokument
    bzw. Eingangsrechnung verlinken als Herkunft zurück zum
    Paperless-Original.
- Überblicks- und Aggregationsschicht (Phase 160, Features 179–184,
  MVP-1360–1382): **Zentraler Arbeitsvorrat** (`arbeitsvorrat`) mit
  Genehmigungen, Urlaub, Reisekosten, Monatsabschluss, Preisänderungen,
  Lernzeiten, Eingängen, Rechnungsnachfragen, Aufträgen, offenen Punkten,
  Aufgaben, Tickets und Erinnerungen — eigene Anträge erscheinen nie
  (MVP-1360–1363). **Globale Suche** über alle Domänen: neu Lieferanten,
  Leads, Rechnungen, Eingangsrechnungen, Verträge, Artikel, Aufgaben und
  Bestellungen (MVP-1364–1367). **Fremddaten im Überblick:** Etsy-, Billbee-
  und Shop-Bestellungen sowie Belege des Buchhaltungsprogramms in Suche und
  Cockpit, Herkunfts-Abzeichen an übernommenen Aufgaben (MVP-1368–1370).
  **Entitäts-Cockpits** für Kunde, Lieferant, Projekt, Lead und Asset mit
  Panels der Module (Tickets, Verträge, Vereinbarungen, Gewährleistung,
  Umfragen, Bestellungen, Nachweise, Domains, Abos); Leads mit eigenen
  Feldern, Schlagworten und Anhängen (MVP-1371–1375). **Aktivitäts-Feed** je
  Objekt und als Seite `aktivitaet` (MVP-1376–1378). **Leitungssicht** mit
  Kennzahlen aus Finanzen, Betrieb, Vertrieb, Service und Qualität, PDF und
  wöchentlichem Leitungsbericht per E-Mail (MVP-1379–1382). Dazu zwei
  Entscheidungen des Inhabers: keine Entscheidung über den eigenen Antrag
  (außer als einzige berechtigte Person, mit Audit), Team-Rechte zeigen nur
  die eigenen Teams.
- Jira- und Asana-Plugins (Phase 157, Feature 169, MVP-1304–1312): Vorgänge
  ausgewählter Jira-Cloud-Projekte bzw. Aufgaben ausgewählter Asana-Projekte
  kommen als Aufgaben in WorkDiary-Projekte, nur lesend. Anmeldung über die
  App des Herausgebers (Jira 3LO, Asana mit PKCE), danach Site bzw.
  Workspace ausdrücklich wählen. Je Projektzuordnung zeigt eine Vorschau, was
  der erste Abgleich anlegen würde; angelegt wird erst nach „Import
  bestätigen“, bestehende Aufgaben lassen sich ausdrücklich verknüpfen.
  Abgleich alle 15 Minuten, täglich vollständig; Konflikte, fremde
  Datenführerschaft, mehrdeutige Ziele und nicht mehr sichtbare Aufgaben
  landen als Klärfall in der Integrations-Inbox, nichts wird verschoben oder
  gelöscht. Trennen widerruft bzw. löscht die Anmeldung und pausiert die
  Zuordnungen; Wiederverbinden mit derselben Quelle setzt fort.
- Toggl-Plugin mit Quellenwahl (Phase 159, Feature 178, MVP-1349–1351):
  Unter „Toggl-Produkt“ wählt eine Organisation Toggl Track oder das neue
  Toggl (vormals Toggl 2.0 / Focus) als einzige Quelle für Import,
  Rückschreiben und Übertragung — bei der Live-Synchronisation von Toggl liest
  das Plugin so nichts doppelt. Für das neue Toggl gibt es einen eigenen Client
  (API-Schlüssel `toggl_sk_…`, Zeiten aller Mitglieder über die
  Report-Abfrage, Organisation und Workspace aus den Einstellungen oder über
  das Toggl-Konto). Nach einem Wechsel hängt der Import bereits übernommene
  Einträge an die neue Quelle (Benutzer, Start und Ende), statt sie doppelt
  anzulegen; offene Inbox-Fälle ziehen mit. Die Löschungserkennung und das
  Rückschreiben sehen nur Einträge der gewählten Quelle. Das Plugin heißt
  jetzt „Toggl“.
- Nach dem Toolkit-Release (Phase 156, MVP-1275, 1298, 1300; erechnung-toolkit
  0.20, financial-formats 1.20): MT942 (Zwischenmeldung) wird als vorläufiger
  Auszug gelesen wie CAMT.052. Bieterkommentare folgen der Freischaltung des
  Auftraggebers (`BidCommPerm`): ohne sie übernimmt der Import keine
  Kommentare und das Angebot enthält keine. Vorbemerkungen kommen jetzt auch
  aus DA XML. Die Antwort des Händlers auf dem GAEB-Handelsweg wird gelesen:
  X97 (Auftragsbestätigung) wie die openTRANS-Bestätigung, X94
  (Preisangebot) setzt die Einzelpreise eines Bestellentwurfs; dafür bietet
  der Entwurf die Preisanfrage (GAEB X93) und den Upload des Angebots an.
- GAEB und Vergabe (Phase 156, MVP-1297, 1299, 1303): Die Prüfung vor der
  Abgabe meldet Preise in fremder Währung und eine Umsatzsteuer oder
  Bruttosumme, die nicht zur Nettosumme passt. Fristen offener
  Ausschreibungen (Rückfrage, Teilnahme, Abgabe, Eröffnung, Bindefrist)
  stehen im Kalender und im persönlichen ICS-Feed, über den neuen
  Erweiterungspunkt `CalendarEventSource`. Vorbemerkungen des LV werden aus
  GAEB 90 übernommen und in der LV-Ansicht gezeigt.
- Bank und DATEV (Phase 156, MVP-1274, 1277–1279): CAMT.052
  (Tagesauszug) und CAMT.054 (Umsatzmeldung) werden gelesen — wie die
  eigenen Zahlungsaufträge PAIN.001/008 als vorläufige Auszüge: Vorschau
  ohne Abgleich, Buchung, Liquidität und Kennzahlen, bis der verbindliche
  Kontoauszug (CAMT.053/MT940) desselben Kontos sie ersetzt. Jeder
  verbindliche Auszug zeigt seinen Anschluss an den Vorauszug (Saldo und
  Lücke an Bankarbeitstagen). DATEV-Stapel tragen einen Übermittlungsstand
  (übermittelt, von der Kanzlei bestätigt oder abgelehnt) im Verlauf und
  haben einen Begleitbericht als PDF.
- Faktura und Einkauf (Phase 156, MVP-1280–1285): Gutschriftsverfahren
  als eigener Belegtyp — Gutschrift an Lieferanten mit eigenem Nummernkreis
  (`GS-Jahr-Nummer`), Pflichtangabe „Gutschrift“, XRechnung mit Typcode 389,
  Vereinbarung am Lieferanten als Voraussetzung; ausgestellte Gutschriften
  gehen dem Lieferanten zugeordnet in den Rechnungseingang und von dort an
  DATEV bzw. das angebundene Programm. Lieferanten führen jetzt auch die
  Steuernummer. Angebote zeigen „vom Kunden angesehen“, die Annahme im Portal
  nimmt freiwillig Name und Unterschrift. Einbehalt und Bürgschaft lassen
  sich an eine Gewährleistungsfrist koppeln (Freigabetermin bzw.
  Rückgabemeldung). Nachweis-Ampel des Lieferanten an der Bestellung,
  Materialvorschlag aus der Stückliste an Ticket und Auftrag, Rundschreiben
  mit Anhang und Vorlagen.
- Lernplattform (Phase 156, MVP-1262–1273): Fragen per CSV/Excel in den
  Fragenkatalog; QR-Code zur Prüfseite auf dem Zertifikat; Lernserie (Tage in
  Folge, abschaltbar); große Medien in Teilen mit Wiederaufnahme; Meldung
  „Prüfung endgültig nicht bestanden“ an Person und Teamleitung; Zeitlimit je
  Frage und Zeitverlängerung je Person als Nachteilsausgleich; Freischaltung
  nach einer Vorgängereinheit und ab Präsenztermin; Fristerinnerungen per
  E-Mail für Externe ohne Konto; Konfigurationsausdruck mit Version und
  Audit; laufende Einschreibungen auf die aktuelle Kursversion heben, mit
  Vorschau; Einschreibung über Automationsregeln bei neuen Mitarbeitenden und
  bei der Geräteausgabe (Regelformular mit Zielauswahl).
- Betrieb und Vertrieb (Phase 156, MVP-1286–1296): Calendly-Buchungen melden
  sich bei der Disposition; Ersatztermin vorschlagen (Kunde nimmt im Portal an
  oder lehnt ab) und bestätigte Termine umbuchen; Demodaten für Umfragen,
  Leads, Zutrittsmedien, Rundgänge und Terminanfragen; OCR-Werkzeuge in
  Systemdiagnose und `system:health`; Rundgang offline mit der Scanzeit des
  Geräts und Rundgangsbericht je Route, Objekt und Zeitraum (PDF, CSV, Excel);
  Umfragen nach Auftragsabschluss und im festen Abstand, NPS je Monat; Angebot
  aus dem Lead mit Verlauf und Lead-Auswertung nach Quelle und Konversion.
- Helpdesk (Phase 156, MVP-1256–1261): Fachdienst und Serviceangebot am
  Ticket mit Filter und Bericht; Servicelandkarte mit Abhängigkeiten und
  Auswirkungsanalyse am Ticket; Routing nach Vertrag, Fachdienst, Angebot,
  Sprache und Geschäftszeit sowie Zuweisung reihum oder nach Auslastung;
  Zeiten am Ticket mit Summe und Ticketbezug in der Rechnung; Monatsverläufe
  im Helpdesk-Bericht; Portal-Ticket mit Gerät und Service.
- Vier-Augen-Schritt (Phase 156, MVP-1301): Leiste „Vier-Augen-Freigabe“
  am Schritt mit Stand, „Zweite Person anfordern“ (benachrichtigt die
  Teamleitung), Gegenzeichnen und Widerruf mit Begründung. Wer angefordert
  hat, kann nicht selbst gegenzeichnen; ein Widerruf gibt die Freigabe für
  eine andere Person frei.
- Sicht „Rechteänderungen“ (Phase 156, MVP-1302): Rollen, Rollen- und
  Direktrechte und Gruppenmitglieder mit Akteur, Betroffenem und Änderung,
  lesend mit `access.audit.view` (Support und Administration).
- Bindefrist für Angebote je Organisation (Einstellungen → Faktura,
  Vorgabe 30 Tage, `INVOICING_QUOTE_VALIDITY_DAYS`). Sie belegt „gültig bis“
  neuer Angebote vor und gilt bei Lexware, wenn ein Angebot keine Bindefrist
  hat; dort ist das Ablaufdatum Pflicht.
- Löschen auf der Detailseite (Vollscan 2026-10-21, Entscheidung des
  Inhabers): Kunden, Lieferanten, Fremdkunden, Abrechnungspläne,
  Service-Tickets, Lieferantennachweise, Protokolle und VOB/B-Schreiben (beide
  nur im Entwurf) sowie Sicherheitsereignisse, jeweils mit Bestätigung und nur
  mit dem passenden Recht.
- Oberflächen für Aktionen, die es bisher nur als Route gab (Vollscan
  2026-10-21): Bearbeiten von Wartungsplänen, Protokollen, SLA-Verträgen,
  Verleih-, Vertrags- und Leasingakten, offenen Punkten (auch Zuweisen),
  Stundenzettel-Zeilen und -Material, Verfügbarkeiten und Abrechnungsregeln;
  „Aktuellen Filter speichern“ und gespeicherte Filter in der Filterleiste,
  der Standardfilter einer Liste greift beim Öffnen; Schichttausch beantragen;
  Pause nachtragen; Ticket klassifizieren; genehmigte Spesen und Reisen
  stornieren; Zusagen und Teilnehmerstatus bei Terminen; Kontakt ins
  Buchhaltungsprogramm übertragen; Ideenkarten verknüpfen und das Eigentum
  übertragen; Kursimport als Dialog; LV-Statuswechsel, Katalog-Zuordnung und
  Import der Kostenermittlung; orgaMAX „Rechnung senden“ und „Zahlung buchen“;
  Links zu den maschinenlesbaren Zertifikatsnachweisen.
- Nach dem Einspielen oder Erstellen der Organisationslizenz öffnet die
  Einrichtung „Was möchten Sie erledigen?“.
- Austritt: Ideenkarten der Person lassen sich an eine andere Person
  übertragen; die verwaltende Person sieht nur die Anzahl, nie Titel oder
  Inhalt.
- Rechnungsnachfragen: Antwortet ein Kunde auf eine Antwort aus der Notiz oder
  verweist seine Mail auf eine frühere Nachfrage, ordnet sie sich derselben
  Rechnung zu. Automatisch erfasste Nachfragen tragen die Anhänge der Mail.
- Kundenkonten: Zeitänderungen rechnen die Monatskette selbst nach (im
  Hintergrund, je Konto ein Lauf); nächtlicher Lauf
  `customer-billing:recalculate-statements` als Sicherung.

- Agile Projekte: Akzeptanzkriterien haben eine Oberfläche (Umsetzungslücken
  2026-10-21). Jede Backlog-Zeile zeigt erfüllte und vorhandene Kriterien und
  öffnet über das Listensymbol einen Dialog zum Anlegen, Abhaken und Entfernen;
  bisher gab es dafür nur Routen.
- Hinweisgebersystem: Fallexport, „Betroffene Person markieren“ und
  Notfallfreigabe sind in der Fallansicht erreichbar; der Export verlangt ein
  Passwort und liefert ein AES-256-verschlüsseltes ZIP. Neuer Befehl
  `whistleblowing:apply-tombstones` wendet nach einem Restore die Löschungen
  aus dem überlebenden Tombstone-Ledger erneut an
  (`WHISTLEBLOWING_TOMBSTONE_LEDGER`, Pfad außerhalb des Restore-Baums).
- Löschfristen: erledigte Fahrt- und Zeitvorschläge aus der Standortspur,
  entschiedene Terminanfragen und Käuferdaten von Marktplatz-Bestellungen
  (Etsy) gehen in den Löschfrist-Scan; neue VVT-Vorlage
  „Sicherheitsüberwachung und Angriffserkennung“.
- Rechnungsnachfragen aus E-Mails (Phase 148, MVP-1199–1203, Feature 166):
  Eingehende Mails, die sich über Faden, Rechnungsnummer im Betreff oder Text
  einer Rechnung zuordnen lassen, werden als Nachfrage an der Rechnung erfasst
  (Historie, Absender, Zeitpunkt, Nachrichtenreferenz, mehrere je Rechnung,
  keine Dubletten bei erneuter Verarbeitung); unklare Mails lassen sich in der
  Integrations-Inbox einer Rechnung oder einem Auftrag zuordnen; Arbeitsliste
  „Nachfragen“ mit offen/beantwortet.
- Einfacher Einstieg mit Wachstumspaketen (Phase 147, MVP-1187–1197,
  Feature 165): Paketkatalog, dauerhafte Umfangswahl, Einrichtungsdialog
  „Was möchten Sie erledigen?“, kompakte Arbeitsbereiche und Startseiten,
  Schnellerstellung, „Heute“ mit Kernblock, einklappbare Kernformulare,
  optionaler Angebots- und Rechnungsweg, Erweiterung mit Vorschau,
  Bestandswechsel und Rücknahme.
- Tourvorschlag, Tagesplanung und Tour-Ist (Phase 146, MVP-1170–1186,
  Feature 164): Stopp-Tabelle mit Plan- und Ist-Zeiten, Solver-Vertrag mit
  Heuristik und VROOM, Tourvorschlag als Dialog, Mehrfahrer-Tagesvorschlag,
  „Rest neu planen“, Zonen aus Adressen, Fahreransicht mit Stopp-Status,
  Plan-Ist-Auswertung mit gelernter Servicedauer, Fahrtvorschläge aus der
  Standortspur; Fahrtenbuch aus Straßenkilometern je Abschnitt.
- Lieferanten „In Auswertungen ausblenden“ (Phase 140, MVP-1121), etwa für
  Finanzamt und Krankenkasse.
- Rechnungspostfach (Phase 138, MVP-1107–1112, Feature 163): ein Eingang je
  Rechnung aus dem Postfach, Abgleich der Gegenpartei, Sammelkunde und
  Sammellieferant, Arbeitsliste mit manueller Zuordnung, Übergabe an die
  Buchhaltung nach der Zuordnung.
- Hilfe ohne Lücken (Phase 135, MVP-1083–1089): Themen für
  Organisationseinstellungen, Auswertungen, Integrationen und Einzelseiten,
  ausgebautes Kundenportal, vollständige Übersetzungen und Gates gegen leere
  Themen.
- Hilfesuche findet Funktionen (Phase 134, MVP-1079–1082): Suchdienst mit
  Suchbegriffen je Thema, Thema „Bedienoberfläche und Darstellung“ und
  Sprung direkt zur Funktion.
- Nextcloud als Upload-Kanal für Kundeneingänge (Phase 133, MVP-1078): Der
  Kunde öffnet am eigenen Eingang einen File-Drop-Link; WorkDiary übernimmt
  die Dateien alle 15 Minuten oder auf Knopfdruck als feste Kopie.
- Kundeneingang mit Dateiupload (Phase 132, MVP-1074–1077, Feature 162):
  Portalfreigabe „Anfragen“, Anfrage mit Dateien, Zuständigkeit, Rückfragen,
  Angebot und Annahme; Vorlagen für Druckaufträge und IT-Anfragen.

- Rechnungseingang: Belegvorlagen mit Anker, Vorlagenseite und Probelauf
  (Phase 155, MVP-1252–1255; 1255 = Texte ×5, Hilfe ×5 und Tests). Eine Zone
  kennt jetzt ihre Beschriftung als
  Anker („Gesamt EUR inkl. MwSt.“) und liest den Wert daneben, auf welcher
  Seite und in welcher Höhe er auch steht; der Editor erkennt die Beschriftung
  im gezeichneten Kästchen als Anker, das Kästchen bleibt wie gezeichnet und
  wandert beim Anwenden an den gefundenen Anker, der Anker ist je Zone
  abschaltbar, je Zone sind Seitenbereich (feste, erste, letzte, jede Seite)
  und ein Zeilenmuster wählbar (regulärer Ausdruck auf den Text im Kästchen,
  zeilenweise, erste Klammergruppe ist der Wert), gezeigt werden die ersten
  drei Seiten und die letzte, ein Probelauf prüft die Zonen gegen die offenen
  Belege derselben Form ohne zu speichern. Im Speichern-Block wird die
  bearbeitete Vorlage ausdrücklich gewählt oder neu angelegt, die Zonenliste
  des Editors ersetzt den Stand der Vorlage (entfernte Zonen sind weg), und
  die Vorlage merkt sich die Kopfform des Belegs als weitere Variante ihres
  Fingerabdrucks. Eine Vorlage füllt nur
  Rechnungen (keine Mahnungen, Lieferscheine, Auftragsbestätigungen, Angebote,
  Versandmarken desselben Absenders), bringt ihren Lieferanten mit (Verkäufer
  und Zuordnung; der Verkäufername ist kein Zonenfeld mehr), prüft Werte auf
  Plausibilität (kein Prozentsatz als Betrag, kein Datum als Nummer) und zählt
  Treffer nur, wenn sie ein Feld gelesen hat; gelernte Zonen merken sich die
  Beschriftung links des Wertes. Neue Seite „Belegvorlagen“ (über „Regeln“):
  Felder mit Ankerkennzeichen, Treffer, Eingänge, zuletzt getroffener Beleg,
  Bearbeiten (Name, Lieferant), Zonen-Editor öffnen, Probelauf, Zusammenführen
  doppelter Vorlagen, Löschen; Audit `template_updated`, `template_merged`.
  Texte ×5, Hilfe ×5, Tests.

- Rechnungseingang: Heuristik der PDF-Erkennung (Phase 155, MVP-1251).
  Summenbeschriftungen „Total EUR inkl. MwSt.“ und „Gesamtwert“, ein
  Steuerzusatz hinter der Beschriftung („Rechnungsbetrag inkl.
  Versicherungssteuer“) wird übersprungen, ein Betrag vor einem Prozentzeichen
  ist kein Betrag („Im Endbetrag sind 19,00 % Umsatzsteuer“), ein Betrag
  hinter „aus/von“ ist die Bemessungsgrundlage und nicht die Steuer, die
  Blockpaarung toleriert Währungs- und Prozentzeilen sowie eine Währung hinter
  dem Wert („139,95 EUR“) und kennt Netto- und Steuerbeschriftungen mit Satz
  („MwSt. entspricht 19,0 %“); ohne jede Rechnungsnummer vertritt die
  Auftrags- bzw. Bestellnummer sie mit Hinweis am Eingang. Messung über die
  65 offenen Klärfälle mit Indextext (lokal, nur Lesen): vorher 17, nachher 29
  erkannt; 14 sind Mahnungen und bleiben gewollt Klärfall.

- Rechnungseingang: „Fachlich freigeben“ als Sammelaktion (MVP-1249). In den
  Reitern „Zu prüfen“ und „Alle“ gibt die Sammelaktion die ausgewählten
  Eingänge über denselben Weg frei wie die Einzelentscheidung (Statusübergang,
  Entscheider, Audit); vor dem Absenden wird nachgefragt. Eingänge ohne
  Partei, mit unzulässigem Status oder mit Hindernissen am Übergabe-Tor
  (Summen, Adressierung, möglicher Doppel-Eingang, Sammelkontakt bei Reverse
  Charge) bleiben stehen, die Meldung zählt die Gründe. Texte ×5, Hilfe ×5,
  Tests.
- Rechnungseingang: Eingangs-Validierung wiederholen (MVP-1248). Die
  Sammelaktion „Erneut prüfen“ und der Befehl `incoming-invoices:revalidate`
  (ohne `--all` nur Eingänge ohne KoSIT-Urteil) wiederholen die Schema- und
  KoSIT-Prüfung strukturierter E-Rechnungen am abgelegten Original; Werte,
  Zuordnung, Regeln und Übergabe bleiben unberührt, abgelehnte Eingänge
  bleiben außen vor, jeder Lauf schreibt ein Audit. Anlass: neun UBL-Eingänge
  vom 17./18.10.2026 trugen „KoSIT-Validator nicht verfügbar“ aus dem
  damaligen Toolkit-Stand, obwohl der Validator auf dem Server läuft. Texte
  ×5, Audit-Label ×5, Hilfe ×5, Tests.
- Kundenportal: Portalzugang entfernen (MVP-1246). Ein Zugang, der sich nie
  angemeldet hat (Einladung offen oder abgelaufen, Startpasswort ausstehend),
  lässt sich in der Kundenakte endgültig löschen; ein Zugang mit Anmeldung
  wird nach dem Deaktivieren entfernt: Name, Anschrift, Anmeldemittel,
  Zwei-Faktor-Methoden und Sitzungen fallen weg, die E-Mail-Adresse wird
  frei, der Datensatz bleibt als „Ehemaliger Portalzugang“ erhalten, damit
  Foto-Bestätigungen, Rückfragen, Anfragen und Audit-Einträge ihren Urheber
  behalten. Beides verlangt eine Passwortbestätigung und schreibt ein Audit am
  Kunden; entfernte Zugänge stehen nur noch in einer Summenzeile unter der
  Liste. Neuer Löschfristen-Bereich „Portalzugänge (deaktiviert)“ schlägt
  deaktivierte Konten nach einem Jahr zur Anonymisierung vor. Maßgeblich für
  „nie angemeldet“ ist das Login-Audit. Hilfe ×5, Texte ×5, Audit-Labels ×5,
  Tests.

- Kundenportal: Zeitraum und Filter der Zeiten, Oberfläche, Farbschema,
  Auswertungen (Phase 154, MVP-1237–1245, Feature 168). Die Portalzeiten
  sind auf Kalenderjahre begrenzt (Organisationsstandard 3 Jahre
  einschließlich des laufenden, je Kunde im Panel „Portal-Sichtbarkeit“
  abweichend bis unbegrenzt; eine Sichtgrenze, keine Löschung) und öffnen
  mit dem laufenden Jahr: Jahres-Tabs, Filter nach Projekt, Mitarbeiter
  (nur in Einzelstufen), Zeitraum und Beschreibung (nur veröffentlichte
  Einträge), Sortierung, Kennzahlenzeile; Liste, Startseite und Auswertung
  nutzen eine Grundmenge (`PortalTimeEntries`). Das Portal trägt Logo und
  Rechtsangaben der Organisation, eine Navigation aus einer Liste
  (`PortalNavigation`) mit Symbolen, aktivem Eintrag und Drawer am Handy,
  Kontomenü, Sprachumschalter und Fuß (die Leiste fasst die Bereiche zu den
  Gruppen Aufträge, Finanzen, Service und Dokumente zusammen, eine Gruppe mit
  einem Bereich bleibt ein Link; die Suche steht nur in der Kopfzeile); alle
  Portalseiten nutzen Seitenkopf,
  Karten, Leerzustände mit Symbol und Status-Badges; die Startseite zeigt
  Kennzahlkacheln mit Hinweis (Stunden dieses Jahr, offene Rechnungen) und
  die freigegebenen Bereiche als Kacheln. Hell/Dunkel ist im Portal wählbar
  und bleibt am Portalkonto gespeichert (Kopfzeilen-Umschalter, Karte
  „Darstellung“ im Profil, Hell/Dunkel-Paar der Organisation, gemeinsames
  Theme-Seed-Partial mit dem App-Layout; in der Vorschau als Kunde
  ausgeblendet; neue Komponente `x-brand-logo` zeigt im Dunkelmodus das
  dunkle Logo der Organisation, sobald eines hinterlegt ist — im Portal, in
  der Anwendung und auf den Gastseiten, über `html[data-scheme]` ohne
  Skript). Neue Bereichsfreigabe „Auswertungen“ (Default-Deny) mit
  Jahres-Tabs: Stunden je Monat (mit Vorjahr), je Projekt und je Monat nach
  Projekt; Rechnungsbeträge je Monat mit Gutschriften gesondert, offener
  Betrag je Monatsende, Zahlungsstand, überfällige Beträge; Saldo des
  Abrechnungskontos; Tickets je Monat mit mittlerer Lösungszeit ab fünf
  gelösten Tickets — jeder Diagrammpunkt führt in die gefilterte Liste, die
  Rechnungsliste hat dafür Jahres-Tabs sowie Status- und Monatsfilter; die
  Startseite zeigt mit der Freigabe eine Zeile „Dieses Jahr“. Hilfe ×5
  (neues Thema Auswertungen, Zeiten, Übersicht, Rechnungen, Zugang,
  Kontakte), Texte ×5, Tests.

- Stammdaten: Hauptansprechpartner aus dem Buchhaltungsprogramm. Der
  Lexoffice-Abgleich übernimmt neben der Liste der Ansprechpartner auch den
  Hauptansprechpartner (Anrede, Titel, Vornamen, Nachname): den in Lexoffice
  als primär markierten, sonst den ersten, bei einem Privatkontakt die Person
  selbst — nur, solange er lokal leer ist; ein gepflegter bleibt. Kandidaten
  aus dem Rechnungseingang tragen die Ansprechpartner aller vier Programme
  (Lexoffice contactPersons/Person, easybill Anrede/Titel/Namen des Kunden,
  sevDesk Personen der Organisation über `parent` und Privatperson, orgaMAX
  contactPersons mit Hauptkontakt), die Übernahme legt die Partei damit an.
  Der orgaMAX-Stammdatenimport führt den Hauptansprechpartner und die Liste
  in Zuordnung, Konfliktprüfung und Anlage aus der Integrations-Inbox mit.
  Dialog „Kunde bearbeiten“: das Badge an „Weitere Ansprechpartner“ zählt
  jede Person, nicht die Liste als Ganzes. Gemeinsame Regel
  `ContactPersons::fromExternal()`/`adoptPrimary()`, Tests. Anlass: Stimmgerecht
  oHG kam aus Lexoffice mit zwei Ansprechpartnern, aber leerem
  Hauptansprechpartner.

- Kundenportal: Bereichsfreigabe „Schulungen“ (MVP-1236). Der Menüpunkt und
  die Kursseiten hängen jetzt wie alle anderen Bereiche an der
  Portal-Konfiguration des Kunden (Default-Deny: Bestandskunden sehen
  Schulungen erst nach ausdrücklicher Freigabe); das Lernmodul bleibt
  Voraussetzung. Hilfethema folgt der Freigabe. Labels ×5, Tests.
- Kundenportal: Die Suche findet auch Belege des Buchhaltungsprogramms
  (Belegspiegel) nach Nummer — bisher nur lokale Rechnungen, ein Kunde ohne
  lokale Rechnungen fand seine Belegnummern nicht (Befund 2026-10-19). Test.

- Änderungsverlauf an Kunde, Lieferant und Fremdkunde gekürzt: Die Liste
  zeigt acht Einträge, der Rest steht hinter „Weitere Einträge anzeigen“; ein
  generisches „Geändert“ derselben Person innerhalb einer Minute neben einem
  Fachereignis (etwa „Portal-Sichtbarkeit geändert“) entfällt, weil das
  Fachereignis die Änderung erklärt. Komponente `x-audit-log-list`, Test.

- Abrechnungskonto: Anfahrt wahlweise gesondert ausweisen oder in der Dauer
  mitführen (Feature 098, MVP-1235). Neuer Haken „Anfahrt gesondert ausweisen“
  in der Vereinbarung (Standard an). Ohne den Haken zeigen Kundenportal,
  Monatsliste und Anwesenheitsnachweis-PDF keine Anfahrtsspalte mehr; die
  Dauer je Zeile und die Stunden unter „Gesamt“ enthalten die Anfahrt, die
  Beträge bleiben gleich. Intern bleibt die Anfahrt sichtbar, der Nachweis
  trägt einen Hinweis. Texte und Hilfe ×5, Tests.

- Kundenportal: Vorschau als Kunde und Zugang mit Startpasswort (Phase 153,
  MVP-1229–1234, Feature 167). In der Kundenakte öffnet „Portal ansehen“ das
  Kundenportal in einem neuen Tab in der Sicht eines Portalkontos (Recht
  `customerPortal.preview`, Standard wie die Portalzugangsverwaltung). Die
  Vorschau ist eine Sicht, keine Anmeldung: Das Konto wird je Request per
  `setUser()` gesetzt (kein Login-Ereignis, keine Kundensitzung, Sitzungszeile
  bleibt beim Mitarbeitenden), jede Schreibmethode antwortet 403, Seiten mit
  Anmeldemitteln sind auch lesend gesperrt, „Abmelden“ beendet nur die Vorschau;
  sie endet nach 60 Minuten, bei Logout, Org-Wechsel, Rechteverlust,
  Deaktivierung oder Passwortwechsel des Kontos, nie verschachtelt mit einer
  Support-Sitzung; Audit `customer.portal_preview.started/ended` mit Grund und
  Dauer. „Zugang anlegen“ erzeugt ein Konto mit zufälligem Startpasswort (drei
  Vierergruppen ohne verwechselbare Zeichen), einmal angezeigt, nie per Mail
  oder Log, nach Passwortbestätigung; Zustand „Startpasswort ausstehend“ bis zum
  Pflichtwechsel bei der ersten Anmeldung, „Startpasswort erzeugen“ für
  bestehende Konten. Neu im Portal: „Passwort ändern“ im Profil; die
  Passwortwechsel-Pflicht kennt jetzt Portalkonten (bisher landeten sie auf der
  internen Seite). Texte, Rechte-, Audit-Labels und Hilfe ×5, Tests.

- Rechnungseingang: Zuordnung korrigieren, Sammelaktion ohne gefährlichen
  Standard, Sammelkontakt sichtbar, Partei aus dem Buchhaltungsprogramm
  (Phase 152, MVP-1222–1228). Die Sammelaktion verlangt eine Wahl, kennt „Neu
  aus Belegdaten (je Gegenpartei)“ (bestehende Partei mit gleicher USt-IdNr.
  oder gleichem Namen vor Neuanlage) und fragt bei Sammelkontakt und Neuanlage
  mit den Gruppen der Auswahl zurück; „Zuordnung aufheben“ im Dialog und als
  Sammelaktion (nicht abgeschlossene Übergabe-Journale entfallen, Audit
  `unassigned`), Sammelaktion auch in „Zu prüfen“ und „Alle“. Seite „Regeln“
  zeigt „Am Sammelkontakt geparkt“ nach Gegenpartei mit „Anlegen und
  umhängen“. Neuer Erweiterungspunkt `IncomingPartyLookup`: Lexware Office,
  easybill, sevDesk und orgaMAX suchen Kontakte nach E-Mail und Name, der
  Dialog bietet Treffer als „Aus Buchhaltung übernehmen“ (Stammsatz aus dem
  Kontakt, verknüpft samt Kontaktnummer). Dublettenwarnung vor „Neu aus
  Belegdaten“ (Name, E-Mail, Domain, unscharfer Name), Sperre bei gleicher
  USt-IdNr., Hinweis bei Sammelkontakt mit USt-IdNr. und zur Stammdaten-Hoheit.
  Befund 2026-10-19: 14 JTL-E-Rechnungen über die vorausgewählte Sammelaktion
  am Sammellieferanten.

- Kommunikation: E-Mail-Signatur je Benutzer und E-Mail-Fußzeile je
  Organisation. Die Signatur pflegt jede Person im Profil (Gruppe „E-Mail“,
  Präferenz `mail_signature`; leer: Name und Organisation), die Fußzeile die
  Organisation in ihren Einstellungen (`communication.mail_footer`,
  Org-Override vor System-Override). Beides steht unter Antworten aus WorkDiary:
  Antwort auf eine E-Mail-Notiz (mit Vorschau im Dialog) und Ticket-Antwort
  (nur bei Mitarbeitenden, nicht bei Portal-Antworten). Beleg- und Systemmails
  behalten ihre Vorlagen.
- Rechnungseingang: Belegvorlagen mit Zonen und lernende Erkennung (Phase 151,
  MVP-1218–1221). Das php-pdf-toolkit 0.27 liefert Wortboxen (Rechteck je
  Wort relativ zur Seitengröße, Textlayer oder OCR); die App steigt von 0.17
  auf 0.27. Eine Vorlage trägt einen Fingerabdruck aus Kopf und Fuß der ersten
  Seite und Zonen für Rechnungsnummer, Datum, Fälligkeit, Netto, Steuer, Brutto,
  USt-IdNr. und Verkäufer; beim Eingang schlagen gezeichnete und bestätigte
  Zonen die Heuristik, gelernte unbestätigte füllen nur Lücken. Jede Korrektur
  in „Werte erfassen“ lernt mit (eindeutiger Treffer im Wortgitter wird zur
  Zone, der zweite Treffer bestätigt sie). Neuer Zonen-Editor „Vorlage“ auf der
  Belegseite: gerenderte Seite, Wortboxen als Overlay, Rechtecke ziehen,
  Vorschau des Werts, speichern und auf die offenen Eingänge derselben Form
  anwenden. Vorlagen stehen unter „Regeln“. Nur für PDF-Belege.

- Rechnungseingang: Erkennung von PDF und Scan (Phase 151, MVP-1215–1217).
  Die Textheuristik wertet jetzt auch den zeilentreuen Zweittext aus,
  paart Kopfzeilen mit Wertezeilen und Beschriftungsblöcke mit Werteblöcken
  (pdftotext gibt Spalten nacheinander aus), kennt „Gesamt EUR inkl. MwSt.“,
  „Gesamtpreis (inkl. MwSt.)“, Klammerzusätze und nimmt „Rechnungsbetrag
  Netto“ nie als Brutto; als Rechnungsdatum gelten auch „Ort, Datum“ im
  Briefkopf und ein nacktes „Datum“, ein Lieferdatum nur als gekennzeichneter
  Vorschlag im Dialog „Werte erfassen“. Mahnungen, Lieferscheine,
  Auftragsbestätigungen, Angebote und Versandmarken werden als Belegart
  erkannt (neue Belegart „Mahnung“): Liste und Beleg zeigen „Sieht aus wie“,
  der Zuordnen-Dialog belegt „Keine Rechnung“ vor, Mahnungen verlinken die
  angemahnten Rechnungen; Posteingangsregeln können auf `kind_hint` prüfen.
  Neuer Befehl `incoming-invoices:measure-recognition` misst die Erkennung
  gegen die gespeicherten Belegtexte je Feld, ohne zu schreiben (Analyse
  2026-10-19: 177 Klärfälle, davon 168 mit lesbarem Text).

- Kommunikation: E-Mail-Notizen zeigen die Mail dahinter (Absender,
  Empfangszeit, Postfach, Anhänge, zugehörige Rechnungsnachfrage mit Stand) und
  lassen sich mit **Antworten** direkt aus der Notiz beantworten: Empfänger und
  Betreff vorbelegt, Ursprungsnachricht zitiert, Versand mit Faden-Kopfzeilen
  (Message-ID, In-Reply-To, References) und Reply-To auf das Eingangspostfach,
  die Antwort liegt als ausgehende Notiz mit Versandverweis beim Kunden, eine
  offene Rechnungsnachfrage gilt damit als beantwortet (Ereignis
  `CommunicationNoteReplied`, Listener im Faktura-Modul). Neue Benachrichtigung
  „E-Mail beim Kunden eingegangen“ (`communication.mailReceived`), wenn der
  Postfachabruf eine Rechnungsnachfrage automatisch als Notiz erfasst —
  Standardempfänger ist die Person hinter dem Postfach (Befund 2026-10-19).
- Kommunikation: Im Kommunikations-Panel folgen die Zeilenaktionen dem
  UX-Katalog (Ghost-Symbole, nur Löschen im Fehlerton), jede Notiz hat
  **Anzeigen**, Notizen mit eingegangener Mail zusätzlich **Antworten**; die
  Freigabe für Kunden trägt das Symbol „public“ statt des Auges. Neuer Befehl
  `communication-notes:refresh-mail-text` (`--org`, `--since`, `--dry-run`)
  liest Bestandsnotizen aus HTML-Mails erneut über ihre Message-ID aus dem
  Postfach (Eingangs- und Verarbeitet-Ordner, IMAP und Microsoft 365) und
  setzt Text, Eingangs-Snapshot und Herkunftsverweis mit Zeilenstruktur neu —
  die Migration konnte nur die Entities bereinigen, nicht die Umbrüche
  (`MailboxGateway::find()`, Audit `communication.mail_text_refreshed`).
- Rechnungseingang: Posteingangsregeln (Phase 150, MVP-1208–1214). Wer einen
  Eingang als „keine Rechnung“ ablehnt, legt fest, was mit künftigen Belegen
  dieser Gegenpartei geschieht: „automatisch als keine Rechnung ablegen“ oder
  „gar nicht aufnehmen (verwerfen)“; WorkDiary legt dafür eine Automationsregel
  an. Der neue Auslöser „Rechnungseingang eingegangen“ feuert beim Abruf,
  Upload und bei der Nacherkennung vor dem Abgleich, mit Verkäufer (normalisiert),
  USt-IdNr., Absender, Absender-Domain, Betreff, Anhangsnamen, Dateiname, Land,
  Brutto und Erkennung im Kontext; Aktionen „verwerfen“ (Beleg, Dokument,
  Dateien und Anhänge weg, Audit-Zeile mit Prüfsumme, Nummer, Verkäufer,
  Absender und Grund bleibt), „als keine Rechnung ablegen“ und „zuordnen“;
  Bedingungen kennen zusätzlich „endet mit“, „enthält nicht“ und „entspricht“
  (regulärer Ausdruck). Der Zuordnen-Dialog behandelt auf Wunsch die anderen
  offenen Eingänge derselben Gegenpartei gleich mit, beim Zuordnen wie beim
  Ablehnen. Die Seite „Regeln“ im Menü des Rechnungseingangs zeigt
  Absenderregeln und Posteingangsregeln mit Trefferzahl, schaltet und löscht
  sie. Neuer Reiter „Abgelehnt“ mit Sammelaktion „Endgültig löschen“ und
  Statusfilter in „Alle“; die Aufbewahrung schlägt abgelehnte Eingänge nach
  `INVOICING_REJECTED_RETENTION_DAYS` (Standard 30) zur Vernichtung vor,
  gelöscht wird erst nach Freigabe. Anlass: 270 Testbelege eines Mailsystems
  an einem Vormittag (Befund 2026-10-18).

- Rechnungseingang: Wer einen Eingang mit „Neu aus Belegdaten“ einer neuen
  Partei zuordnet oder den Absender merken lässt, bekommt die bereits
  eingegangenen offenen Eingänge derselben Gegenpartei gleich mit zugeordnet
  (`IncomingInvoiceMatcher::rematchOpen()`, derselbe Abgleich wie beim
  Eingang: USt-IdNr., Steuernummer, IBAN, Absenderregel; auch Grundlage
  von `incoming-invoices:match`, das abgelehnte Eingänge jetzt auslässt).
  Die Meldung nennt die Zahl. Bisher blieben sie offen, bis jemand sie von
  Hand sammelzuordnete (Befund 2026-10-18: 20 E-Rechnungen eines
  Lieferanten, alle offen).

- Rechnungseingang: Nacherkennung offener Klärfälle (MVP-1207). Fehlten dem
  Server beim Eingang die Textwerkzeuge, blieben Mail-Rechnungen Klärfälle
  ohne Nummer und Betrag, und die Erkennung lief nur einmal. Die
  Sammelaktion „Erneut erkennen“ im Reiter „Zuzuordnen“ und der Befehl
  `incoming-invoices:reanalyze` (alle Organisationen oder `--organization=`)
  lesen das abgelegte Original erneut, führen Werte, Richtung, Belegart,
  Vorschläge, Dokumenttitel und die eindeutige Gegenpartei nach und
  protokollieren das im Audit. Erfasste Werte, übergebene und abgelehnte
  Eingänge bleiben unangetastet.

- Deploy installiert die Systemwerkzeuge der Toolkits (`pdftotext`,
  `tesseract`, `ocrmypdf`, Java …): das Install-Werkzeug
  `DSchuppelius/install-dependencies` ist als Git-Submodul `installscript/`
  eingebunden (Composer liefert die Submodule der Toolkit-Pakete nicht mit),
  `deploy.sh` lädt es mit dem Stand und führt es selbst aus: als root
  gestartet (`sudo ./deploy.sh`) laufen die App-Schritte als Eigentümer von
  `storage/`, root installiert danach die Werkzeuge und startet die Worker
  neu; mit passwortlosem sudo direkt nach `composer install`; sonst meldet
  `scripts/check-tools.php` mit der Pfadauflösung des config-toolkit, welche
  Pakete fehlen, samt Root-Befehl. `config/executables.json` nennt die
  App-eigenen Pakete (Java headless, Ghostscript, qpdf, `file`, `sqlite3`,
  `mysqldump`, `catdoc`, `jq`, `curl`, `git`); die CI nutzt dasselbe Submodul
  statt eines eigenen Checkouts. `DEPLOY_SKIP_TOOLS=1` überspringt den
  Schritt, `DEPLOY_TOOLS_ALL=1` nimmt optionale Werkzeuge mit.

- `scripts/artisan.sh`: artisan-Befehle von Hand mit dem passenden
  PHP-Binary (Suche wie `cron.sh`/`deploy.sh`), aus jedem Verzeichnis, alle
  Argumente durchgereicht. Als root gestartet läuft es als Eigentümer von
  `storage/`.

- Belege an Buchhaltungsprogramme übertragen und Belegkette (Phase 145,
  MVP-1152–1169): WorkDiary schreibt die Belege, das Programm vergibt die
  Nummer. Auf der Seite „Nummernkreise“ wählt die Organisation für Angebot,
  Auftragsbestätigung, Lieferschein, Rechnung, Gutschrift und Storno, wer
  vergibt — WorkDiary, Lexoffice, easybill, sevDesk (Rechnung, Storno) oder
  orgaMAX (Angebot, AB, Rechnung);
  der Lexoffice-Schalter für Rechnungsnummern entfällt, die gespeicherte Wahl
  bleibt. Vergibt ein Programm, trägt der Beleg bis zum Festschreiben eine
  Entwurfsnummer: Das Angebot geht beim Versand hinüber, die AB beim
  Bestätigen, der Lieferschein beim Ausliefern, Rechnung, Gutschrift und
  Storno beim Stellen bzw. vor dem Mailversand — mit Vorgänger (AB, Angebot,
  Ursprungsrechnung) und mit der Nummer des Programms. Abschlags- und
  Schlussrechnungen legt man dann im Programm an. Die Auftragsbestätigung und
  der Lieferschein sind eigene Belege mit Nummernkreis, PDF und Versand; der
  Lieferschein übernimmt aus der AB die offenen Mengen (Teillieferung), jede
  Auslieferung der Fertigung bekommt ihren Lieferschein, bisherige
  Auslieferungen behalten ihre Nummer `LS-…`. Rechnung, Angebot, AB,
  Lieferschein, Fertigungsauftrag und Übergabe zeigen „Zugehörige Belege“
  einschließlich der Gegenstücke im Programm; der Belegfluss filtert nach
  Projekt und listet AB und Lieferscheine. orgaMAX legt die Rechnung über
  einen Auftrag an und vergibt die Nummer beim Sperren (orgaMAX-SDK v1.2.1).

- Seite „Nummernkreise“ (Phase 144, MVP-1147–1150): Alle Kreise speichern in
  einem Formular, nur geänderte werden geschrieben; Trenner nach Präfix und
  Jahr lassen sich für alle offenen Kreise auf einmal setzen. Vergibt ein
  Programm oder der Fakturierungsweg die Nummern, sind die Formatfelder
  gesperrt und die Zeile zeigt statt der Vorschau die zuletzt übernommene
  Nummer, wartende Kontakte und bei sevDesk die nächste Nummer. Zertifikate
  beginnen mit `CRT`; ein leerer Präfix erzeugt keinen führenden Trenner mehr.

- Rechnungspostfach: Abruf, Ordner, Microsoft 365 und Bestand (Phase 143,
  MVP-1139–1146): Postfächer werden nach Stand abgerufen statt nach
  Lesestatus — auch Mails, die schon jemand gelesen hat, ab dem Startpunkt
  „Abruf ab“; „Ältere Mails nachholen“ stellt ihn zurück, nur für den Ordner
  des Eintrags. Ordner lassen sich per Pfad angeben (`Posteingang/Rechnungen`),
  auch bei Microsoft 365; ein fehlender Ordner steht als Meldung am Postfach.
  M365 ruft auf Wunsch ein freigegebenes Postfach ab (Postfachadresse), das
  Abo folgt dem eingestellten Ordner. Als Anlage weitergeleitete Mails werden
  ausgepackt. Die Verwaltung der Postfächer arbeitet mit Dialogen, kann
  bearbeiten und wieder aktivieren; dasselbe Postfach darf mit mehreren
  Ordnern eingetragen sein. In der Zuordnungs-Inbox übernimmt „Als
  Eingangsrechnung übernehmen“ Rechnungen aus jedem Postfach. Ein möglicher
  Doppel-Eingang (gleiche Nummer beim selben Aussteller) sperrt die Übergabe
  an die Buchhaltung bis zur Bestätigung „Kein Doppel“; Lexoffice meldet
  einen Treffer ohne Rechnungsnummer als Vorschlag zum Verknüpfen. Bestehende
  Postfächer starten am letzten Abruf minus einem Tag. Wer M365-Postfächer
  abruft, verbindet den Mail-Versand im Microsoft-365-Plugin einmal neu, damit
  die Anmeldung die Leserechte (`Mail.ReadWrite`, bei freigegebenen
  Postfächern `Mail.ReadWrite.Shared`) anfordert.

- Ansprechpartner mit Anrede und Namensteilen (Phase 142, MVP-1135–1138):
  Kunde, Lieferant, Fremdkunde und Lead erfassen den Ansprechpartner mit
  Anrede, Titel, Vorname, weiteren Vornamen und Nachname, ebenso jede Zeile
  der Ansprechpartner-Liste; die Detailseiten zeigen ihn mit Anrede.
  Lexoffice erhält Anrede und Titel im Anredefeld, Vornamen und Nachname
  getrennt, und der Kontaktabgleich bringt sie zurück; easybill, orgaMAX
  (beim Anlegen) und Outlook-Kontakte bekommen die Namensfelder. Import und
  Export kennen die neuen Spalten. Vorhandene Ansprechpartner zerlegt eine
  Migration mit dem neuen `PersonNameHelper` aus dem common-toolkit
  (v2.8.0); die Anzeige bleibt dabei unverändert.

- Kundennummern vom Buchhaltungsprogramm (Phase 141, MVP-1130–1134): Auf
  der Seite „Nummernkreise“ (jetzt im Admin-Menü) wählt die Organisation für
  Kunde und Lieferant, wer die Nummer vergibt — WorkDiary, Lexoffice, sevDesk
  oder easybill. Vergibt ein Programm, überträgt das Anlegen im Formular den
  Kontakt sofort und übernimmt dessen Nummer; ein stündlicher Lauf
  (`accounting:issue-contact-numbers`) holt Importe und Fehlschläge nach. Das
  Nummernfeld ist dann gesperrt. Kunden, die der Lexoffice-Abgleich anlegt,
  tragen ihre Nummer sofort. Gehört eine Programmnummer schon einem anderen
  Kontakt, entsteht ein Fall in der Zuordnungs-Inbox, statt dass der Abgleich
  abbricht. Der Lexoffice-Schalter für Nummernkreise gilt nur noch für
  Rechnung, Gutschrift und Storno und wirkt beim Speichern. Bestehende
  Organisationen behalten ihre Einstellung (Migration
  `number_formats.source`: `external` → `lexoffice`).

- Belege des Buchhaltungsprogramms in Auswertungen (Phase 139,
  MVP-1113–1129): Lexoffice und der allgemeine Belegspiegel (sevDesk,
  easybill, JTL-Wawi, InvoicePlane) fließen nach einer gemeinsamen Belegregel
  in Abrechnungs-Auswertung (Status, Ausgestellt + Bezahlt, offene
  Forderungen, Aging, Top-Kunden), Kundenakte, Kundenwert, Zahlungsverhalten,
  Belegkette, KI-Werkzeuge `invoices`/`open_items`, Lieferantenberichte und
  die Kachel „Offene Posten“. Das Artikelumsatz-Diagramm zeigt auch
  Lexoffice-Artikel ohne Zuordnung zum Artikelstamm. Belege nicht verknüpfter
  Lexoffice-Kontakte werden gespiegelt („ohne Kundenzuordnung“). Das
  Kundenportal listet Rechnungen des Buchhaltungsprogramms mit PDF (Lexoffice).
  Provisionen entstehen auch an in Lexoffice oder im allgemeinen Spiegel
  bezahlten Rechnungen (bei beobachtetem Statuswechsel). Angebote lassen sich
  als „im Buchhaltungsprogramm abgerechnet“ markieren. Buchhaltungsberichte
  zeigen ohne lokale Buchhaltung einen Hinweis. Materialrechnungen tragen den
  Artikel des Materials.
  **Manueller Schritt nach dem Deploy:** einmal
  `scripts/artisan.sh lexoffice:sync-voucher-lines --all --refresh` (läuft über
  alle Organisationen mit Lexoffice; füllt Nettobetrag und Gutschrift-Bezug der
  schon gespiegelten Belege, etwa zehn Minuten je 1000 Belege). Der nächste
  stündliche Belegsync ruft einmal alle Belege ohne Kontakt ab.

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
- Faktura (Feature 160, MVP-856–859): **Freie Rechnungen aus Artikeln,
  Material und Fertigung**. Im Erstell-Dialog steht „Positionen selbst
  zusammenstellen“ neben Zeiten und Material: normaler Entwurf im
  R-Nummernkreis ohne Zeitraum und Quellposten, Rechnungshoheit und
  Kontobindung wie bisher, Doppelklick-Schutz über ein Dialog-Token. Leere
  Entwürfe lassen sich speichern und ergänzen, aber auf keinem Weg stellen
  oder versenden (Ausstellung, E-Mail, Peppol, Lexoffice). Positionen als
  Artikel mit optionaler Variante, Material oder Freitext: Artikelauswahl nach
  Typ gruppiert, Variante belegt Preis und Artikelnummer vor, Beschreibung,
  Artikelnummer/SKU, Einheit und Preis werden als Belegwerte eingefroren; ein
  fehlender Preis verlangt eine Eingabe, ein Preis in fremder Währung wird nie
  still übernommen; keine Lagerbuchung durch Rechnungspositionen.
  Fertigungsauslieferungen mit lokalem Fakturaziel lassen sich über
  „Auslieferung übernehmen“ vollständig als Position übernehmen (Menge
  quellengebunden, Verkaufspreis der Auslieferung, Herkunft am Posten): genau
  eine aktive Reservierung je Auslieferung (Zeilensperre + Unique), Ausstellung
  kennzeichnet sie als abgerechnet, Entfernen/Verwerfen/Vollstorno geben sie
  frei, Gutschriften nicht; Anzeige am Fertigungsauftrag. Demo-Beispiel
  (Regale, Material, Montagepauschale), Hilfe ×5.
- Vereinsverwaltung (Feature 159, MVP-848): **Profile, Startpakete und
  Musterbranche Sportverein**. Branchenprofil „Sportverein“ (Modul-Empfehlung
  mit Vereinsverwaltung, Klassifikationen für Geschäftsstelle und
  Übungsleitung, Prozeduren Mitgliedsaufnahme und Sportfest). Elf Startpakete
  auf der Seite Sportarten (Kampfsport, Tischtennis, Hockey, Reiten, Fußball,
  Handball, Basketball, Volleyball, Tennis, Leichtathletik, Schießsport): je
  Sportartenprofil, Abteilung, Gruppen und Sportstätten, dazu
  Graduierungsordnung, Schulpferde oder Nachweisanforderung — idempotent nach
  Name, alles anpassbar, keine Verbandsregeln oder gesetzlichen Schwellen.
  Musterbranche „Sportverein“ (TSV Musterstadt e. V.) in der Demo: alle
  Pakete plus fiktiver Mehrspartenverein mit Mitgliedern ohne Login,
  Vertretungen, Familienkonto, Training mit bestätigter Anwesenheit,
  Beitragslauf mit Zahlungen und Mahnung, Saisonkadern mit Gastspieler,
  Spieltagen mit Aufstellung und Ergebnis, Wettkämpfen, Prüfungsangebot,
  Reitstunden und Sperrzeit; Demo-Reset räumt die Vereinsdaten. Hilfe ×5.
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

### Changed

- **Zählkacheln führen zu dem, was sie zählen** (Vollscan 2026-11-02, gap-06):
  237 Kacheln in Listen, Detailseiten, Berichten und auf dem Dashboard sind
  jetzt Links auf die gezählte Menge — Listenroute mit dem Filter der Kachel
  (neue Filter `show=…` für Assets, Verträge, Reklamationen, Krisen,
  Schadenfälle, Entsorgung, Domains, Termine, Buchungseingang, DATEV-Stapel,
  offene Zeiten, Bürgschaften, Fahrgastfahrten, Druck, Verleih,
  Gewährleistung, Bestellliste, Bewertungen, Prüfwesen; Auftragsbuch mit den
  Statusgruppen „In Bearbeitung“ und „Offen“), auf Berichtsseiten der
  Sprung zur Drilldown-Tabelle; die aktive Kachel ist markiert. Summen,
  Quoten und Messwerte bleiben ohne Ziel und sind im Gate
  `KpiTileTargetRuleTest` begründet; Hilfe ×5.
- **Leistung** (Leistungsanalyse 2026-11-02 gegen die Produktion, Behebung
  vom selben Tag):
  - Scheduler: Takt-Jobs tragen einen festen Minutenversatz aus ihrem
    Schlüssel (Stundentakt Minute 2–57, Viertel-, Halb- und
    Fünf-Minuten-Takt versetzt; nur der Wächter bleibt auf der vollen Stunde)
    — vorher starteten zur vollen Stunde 68 Prozesse gleichzeitig.
    Plugin-gebundene Jobs ohne irgendwo aktives Plugin werden nicht mehr
    angemeldet; acht Jobs (Chat-Erinnerungen, Cloud-Eingang,
    Sicherheitsereignisse, Mail-Abruf, Terminerinnerungen, SLA-Scan,
    Hinweisgeber-Scan) prüfen vorab, ob es etwas zu tun gibt, und ein
    übersprungener Slot gilt dem Wächter als erfüllt. Der Wächter schließt
    verwaiste Läufe nach dem Sechsfachen der erwarteten Laufzeit als
    fehlgeschlagen.
  - Lexoffice: Der stündliche Belegabgleich liest nur noch Änderungen seit
    dem letzten Lauf; der Vollabgleich je Kontakt mit Archivierung läuft
    täglich (05:50) oder von Hand mit `lexoffice:sync-vouchers --full`.
    Belege ohne Zahlungsdatum werden erst nach sieben Tagen erneut gefragt;
    Kontakte und Artikel mit unveränderter Lexoffice-Version werden nicht
    neu verglichen (Kontaktnummer und Konfliktprüfung bleiben).
  - Rechte-Cache je Team und Version statt eines Neuaufbaus und Schreibens
    des gesamten Rechte-Blobs bei jedem Request; Menü je Nutzer 60 Sekunden
    gecacht (Schlüssel aus Nutzer, Organisation, Modus, Sprache, Rechten,
    Modulstand und Plugin-Stempel); der Downgrade-Hinweis wird fünf Minuten
    je Organisation gecacht; die Kontexthilfe prüft die Sichtbarkeit eines
    Themas, ohne seinen Text zu laden.
- **Konsolidierung** (Audit 2026-11-02): eine Regel für die Nennung eines
  Namens in Rechnungs- und Vorlagentexten (`NameTokenMatcher`),
  Perioden-Scopes „begonnen“/„fällig“ an elf Stellen, gemeinsame Basis für
  Token-Dokumenteingänge (Paperless, Seafile) und für Cloud-Eingangsquellen,
  gemeinsame Verbindungsprüfung und Aufgabenlink der Aufgabenimporte,
  gemeinsame Einstellungen selbst gehosteter Systeme und Shops (46 Kopien
  weniger), Seitenweises Lesen über `RemotePages::offset()` (neun
  Schleifen), Teilstücke für Periodenaktionen und Halterwahl,
  `authorizeBilling()` an 17 und `portalCustomer()` an elf Stellen, Sqids
  gesammelt dekodiert, Periodenlabel und Margenbericht über die Wertobjekte
  für Datum und Geld.
- Toolkit-First- und Wiederverwendungs-Konsolidierung (Phase 161, Feature
  052, MVP-1390–1398): Import-Specs prüfen Länder- und Währungscodes gegen
  die Enums statt per Muster (der Rechnungsimport nahm „XYZ“ an und buchte
  still in EUR); 59 Nutzer-Auflöser, 14 Portal-Auflöser und 29
  Organisationsprüfungen in Controllern laufen über gemeinsame Bausteine;
  Namensverkettungen, Leerwerte, Enum-Regeln, URL-Schemata, Kürzungen und
  Temp-Verzeichnisse über das php-common-toolkit v2.14; Anmelde- und
  Infokarten als Komponenten (Golden-DOM ohne Abweichung); neue Gates
  `ControllerHelperCopyRuleTest` und `EnumValidationRuleTest`.
- Plugin-Healthchecks laufen alle über die gemeinsame Probe (Befund
  Microsoft Graph 2026-10-19): Netzfehler, 5xx und 429 gelten als
  „eingeschränkt“, übrige 4xx als „fehlgeschlagen“, mit Ursachenkette in der
  Plugin-Fehler-Inbox.
- Menü: neuer Bereich „Onlinedienste“ (Abos & Lizenzen, Domains, Reseller,
  Domain-Berichte); das Reselling-Register verlässt „Vertrieb & Abrechnung“,
  der Bereich „Domains“ geht darin auf.
- Ticket anlegen: Kunde, Projekt und Asset werden ausgewählt statt als
  Nummer eingetippt.
- Der Genehmigungs-Eingang gehört zum Kernmodul „Freigaben“ statt zum
  Lizenzmodul Service Desk: Adresse `/genehmigungen`, Menü „Tagesgeschäft →
  Erfassung“, eigenes Hilfethema; die alte Adresse leitet weiter.
- Kundenportal: Kontoauszug und Auswertung lesen nur noch und rechnen beim
  Öffnen nicht mehr nach.
- `.env.example` nennt alle Umgebungsvariablen der Plugins; das Gate prüft
  auch die Plugin-Konfigurationen.
- Rechnungseingang: Der Probelauf des Zonen-Editors liest Wortboxen aus einem
  Cache je Dokument und ist auf sechs Läufe je Minute begrenzt.
- Wiederverkauf: CSV- und Excel-Exporte schreiben das Export-Audit wie das PDF.
- Unterschriften auf Stundenzetteln und Entsorgungsnachweisen halten die
  Grenze `TIMESHEET_SIGNATURE_MAX_KB` ein.
- Plugins: Zugangsdaten stehen nur noch in der Konfiguration, im `Api/`-Ordner
  und in den Admin-Controllern (Gate `PluginCredentialsRuleTest`).
  - Lexoffice-Dienste entstehen je Organisation, ihren SDK-Client baut allein
    `LexofficeClientFactory::for()`. Damit entfallen die Singletons mit
    API-Schlüssel und das Verwerfen von Instanzen beim Organisationswechsel. In
    langlebigen Prozessen konnte ein injizierter Dienst sonst den Schlüssel
    der zuvor bearbeiteten Organisation behalten.
  - „Nicht eingerichtet“ meldet Lexoffice einheitlich mit dem übersetzten Text.
  - Toggl, OpenProject, Clockify und Kimai bauen ihre API-Clients über eine
    eigene Fabrik statt an vier bis zehn Stellen; der Kimai-Healthcheck kennt
    jetzt die Zeitzone der Organisation.
  - easybill, sevDesk, BuchhaltungsButler, Billbee, Mollie, Peppol, seven.io
    und sipgate prüfen „eingerichtet?“ an einer Stelle.
- Lexoffice: Das Plugin spricht Lexware nur noch über das SDK an
  (lexoffice-php-sdk 2.0). Ungültige Belege, etwa mit einer Kontakt-ID ohne
  UUID-Form, werden vor dem Senden abgewiesen. Gespeicherte Antworten enthalten
  nur die Felder, die das SDK kennt. Der Anfrageabstand beträgt mindestens
  0,5 s.

### Removed

- Routen ohne Oberfläche und ohne Aufrufer: Tour optimieren (ersetzt durch
  Vorschläge), das Stoppuhr-Fragment, die Listen-API des Schichtplans, die
  Schicht-Detailseite (gleich dem Bearbeiten-Dialog), der xAPI-Endpunkt im
  Lernbereich (xAPI-Inhalte laufen über cmi5) und der OCI-Warenkorb-Import
  (Shops nutzen den signierten Rücksprung).
- Stempelungen lassen sich nicht mehr direkt bearbeiten oder löschen; der Weg
  führt über den Korrekturantrag mit Freigabe.
- Sechs Rechte ohne Funktion: `organization.view`, `user.view`,
  `user.rates.manage` (Stundensätze pflegt `user.payroll.manage`),
  `timeEntry.create-for-others` (Nachträge laufen über Zeitkorrekturen),
  `timesheet.export` und `shift.manage` (Schichten plant
  `scheduled-shift.manage`). Der Seeder entfernt sie samt Zuordnungen, der
  Rollen-Editor bietet sie nicht mehr an. Demo-Mandanten anlegen prüft jetzt
  `platform.demo.create` (wie das Zurücksetzen `platform.demo.reset`).

### Fixed

- Reselling: Die Spaltenüberschrift „Firma laut Anbieter“ der
  Abo-Importvorschau zeigte den rohen Sprachschlüssel (Vollscan 2026-11-02,
  gap-04).
- Dashboard: Die Kachel „Offene Genehmigungen“ und der Genehmigungs-Stack der
  Finanzkachel zählen jetzt wie der Arbeitsvorrat — nach Zuständigkeit der
  Quellen statt Admin-Rolle, eigene Anträge nie; Genehmigende ohne Admin-Rolle
  sahen die Kachel nicht, Admins zählten ihre eigenen Anträge mit (Vollscan
  2026-11-02, gap-08). Tickets: „offen“ ist ein Scope (`ServiceTicket::open()`,
  nicht gelöst) für Arbeitsvorrat, Cockpit, Kachel, Kennzahlen, Alterung und
  Routing — das Routing zählte angenommene und abgelehnte Tickets als Last;
  `EntityUrl` kennt die Ticketseite.
- Reselling und Domains (Pilot 2026-10-27 bis 2026-11-02): Der
  Domain-Fristen-Scan zählte ein künftiges Lebenszyklusdatum des Anbieters
  als gescheiterte Verlängerung; Domain-Abos begannen am Registrierungstag
  statt am Ablauf-Jahrestag bzw. Laufzeitende (Transfers verloren die
  Vorjahre, der Planer legte um Tage verschobene Jahresperioden doppelt an);
  der Abo-Import kam über die Oberfläche nie an (der Dialog sendete als
  Eingabeformular, die Vorschau verwarf der Dialog-Host mit einem Reload —
  Gate `DialogFormViewResponseRuleTest`); die Schnellzuordnung rechnete in
  Lizenzen der Rechnungsposition statt der Periode, sodass der Rest einer
  Mehrjahresposition nicht buchbar war; der Rechnungsvorschlag ließ Abos mit
  neuer Serienvorlage ganz aus, auch Jahre vor der ersten Ausführung; der
  Übergabe-Dialog aus der Liste nahm fest das Datei-Paket statt des
  Fakturierungswegs des Kunden; das Buchungsjournal des Domainportals schrieb
  seit August leere Zeilen, der Ereignisabruf las seit August keine
  Ereignisse (Parametername), Verfügbarkeitsprüfung und Kontakt-Snapshots
  waren falsch adressiert.
- Belegfluss: Ein Filter, den keine Belegquelle bedient (etwa Herkunft
  „lokal“ bei nur gespiegelten Belegen), brach mit einem Fehler ab, weil die
  leere Ersatzmenge eine Spalte zu wenig hatte.
- **Phase-158-Anbindungen nach API-Doku und Quelltext** (Abgleich vor den
  Testinstanzen, 41 Befunde):
  - Aufgabenimporte: Ein 403 lässt nur die betroffene Zuordnung scheitern.
    Vikunja liest über `GET /tasks` mit Filter. Plane nimmt nur Projekte mit
    Mitgliedschaft und hält 60 Anfragen je Minute ein. Taiga erkennt ein
    fehlendes Leserecht und den Abschluss über Tasks. FreeScout liest nur
    veröffentlichte Konversationen. Bei GLPI wird die Sitzung beendet, die
    Texte kommen unmaskiert, und der Status „Genehmigung“ zählt als offen.
    Gitea/Forgejo verlangt das Seitenende. Redmine prüft den Schlüssel vor
    jedem Lauf (ein ungültiger las sonst anonym) und lässt Unterprojekte auch
    bei Zeiten weg.
  - Seafile wählt nur eindeutige, beschreibbare Bibliotheken. Paperless liest
    die Containerauswahl seitenweise und bestätigt Abgänge vor dem
    Tombstone.
  - Shops:
    - WooCommerce holt den Shopnamen ohne Anmeldung (WordPress wies den
      Schlüssel dort mit 401 ab) und erkennt Bestellungen, die beim Blättern
      in den Papierkorb wandern.
    - Shopware übernimmt Erstattungen aus dem Zahlungsstatus und wiederholt
      die Suche nach 503.
    - PrestaShop wertet „in Vorbereitung“ nicht mehr als abgeschlossen,
      blättert nach ID, liest Währungen anderer Shops nach und verliert
      nach der Zeitumstellung keine Änderungen mehr.
  - Belegspiegel:
    - Jede Liste führt ihre eigene Startmarke.
    - Invoice Ninja liest Ausgaben vollständig, nimmt die Währung von Kunde,
      Gruppe oder Firma statt fest EUR, rechnet aufgeschlagene Steuer auf
      Ausgaben und verlinkt mit Firma.
    - Akaunting nutzt den Änderungsfilter, prüft die Belegart jeder Zeile
      und liefert Netto, Restbetrag und Kundennummer.
  - Firefly III zählt Buchungen statt Gruppen: Split-Buchungen machten jeden
    Lauf unvollständig. Bereinigt wird nur im gelesenen Fenster, und die
    End-to-End-ID gilt als Belegnummer.
  - Lesende POST-Aufrufe (JSON-RPC, Shopware-Suche, GLPI, Taiga-Anmeldung)
    werden bei 429/503 wiederholt.
  - Hilfe und Einstellungen ×5 nennen Mindestversionen, Rechte und Grenzen.
- Aufgabenimporte: „Bestehende Aufgabe verknüpfen“ nimmt jetzt Fremd-IDs mit
  Bindestrich an (UUIDs wie bei Plane). Die Fehlermeldung nennt das Feld nicht
  mehr „Geräte-ID (Fernwartung)“.
- Bestellung: die Nachweis-Ampel zeigte den rohen Textschlüssel, weil eine
  zweite gleichnamige Textgruppe die erste überschrieb.
- Umrandete Warnabzeichen waren auf hellem Grund kaum lesbar (Gelb auf
  Weiß); die Schrift ist jetzt dunkler, im dunklen Schema heller.
- GAEB-Bestellung (X96): die Bestellnummer stand in `OrderConfNo` statt in
  `PartNo`; die Bestätigungsnummer vergibt der Händler mit der X97.
- Prozedurlauf: „Als zweite Person gegenzeichnen“ war nie erreichbar, weil
  ein Vier-Augen-Schritt ohne Gegenzeichnung als gesperrt galt und die
  Ansicht dann keine Aktionen anbot.
- **Lexoffice-Belege nach der API-Referenz:**
  - Die AB aus dem Fertigungsauftrag sendet jetzt Versandbedingungen
    (`none`). Ohne sie hat Lexware die AB abgelehnt.
  - Der Lieferschein aus der Auslieferung trägt das Lieferdatum in
    `shippingConditions`. Das bisherige Feld `deliveryConditions` hat Lexware
    still verworfen.
  - Gutschriften senden weder Versandbedingungen noch ein Feld für den
    Positionsrabatt, beides kennt Lexware bei Gutschriften nicht. Der Rabatt
    steckt im Einzelpreis.
  - Angebote ohne „gültig bis“ und Angebote aus dem Fertigungsauftrag senden
    ein Ablaufdatum, das bei Lexware Pflicht ist. Ohne Datum hat Lexware sie
    abgelehnt.
  - Belegdateien von Verkaufsbelegen kommen über `/file`; der bisherige Weg
    `/document` ist laut Referenz veraltet.
- **Rollenvergabe nach Namen** (Vollscan 2026-10-21): Mitglied anlegen und
  ändern, CSV-Import, Mandanten-Registrierung, Installer, SSO-Erstanmeldung
  und Recruiting lösten Rollen über Spatie `Role::findOrCreate()` auf, das neben
  der Org-Rolle auch die globale `admin`-Zeile ohne Ordnung trifft; die Rolle
  kommt jetzt immer aus der Organisation (`OrganizationRoles::named()`, Gate
  `OrganizationRoleLookupRuleTest`). Dieselbe Unschärfe hatten die Filter nach
  Rollennamen (Benachrichtigungen an Rollen, SLA-Eskalation,
  Support-Kandidaten): Je nach Abfrageplan fehlten Admins, deren Rolle an der
  jeweils anderen Zeile hing; `OrganizationRoles::matching()` nimmt beide.
  `app:admin` ohne Organisation meldet das jetzt, statt an der Rollentabelle
  zu scheitern.
- **Stundenzettel entsperren** setzte immer „signiert“, auch bei Zetteln, die
  ohne Unterschrift gesperrt worden waren; sie gehen jetzt zurück in den
  Entwurf. Entsperren meldet dem Besitzer auch keine Unterschrift mehr.
- **Kunden mit Belegen ließen sich löschen** (Vollscan 2026-10-21): Rechnungen,
  Angebote, Auftragsbestätigungen, Übergaben, SEPA-Mandate,
  Entsorgungsaufträge, Verleihakten und Kundenkonten hängen per
  Fremdschlüssel-Kaskade am Kunden und verschwanden an den GoBD-Guards vorbei;
  Lieferanten nahmen Bestellungen und Nachweise mit. Kunde und Lieferant
  prüfen jetzt vor dem Löschen (auch über die API, 409), ein Gate ordnet jede
  Kaskade ein.
- Die Buchhaltung konnte Kunden nie löschen, weil das automatisch angelegte
  Standardprojekt die Prüfung sperrte.
- Unterschriebene Protokolle ließen sich von Admins löschen; nach dem Löschen
  eines Entwurfs geht es zum Träger statt auf eine leere Seite.
- LV: Katalog-Zuordnungen wurden nie gefunden (Klassenname in der Typspalte).
- Service-Tickets: Klassifizieren mit übersteuerter Priorität brach ab.
- Lernplattform: Der Kursimport lief ins 404 (Route verdeckt).
- Gespeicherte Filtervorlagen ließen sich speichern, aber nie anwenden.
- Installer: `install-system.sh` stürzte ab (Funktion rief sich selbst auf).
- Mails: Inhalte in spitzen Klammern („Max <max@…>“, URLs) verschwanden in
  Ticket-Faden und Ticket aus Mail; Faden-IDs ohne Klammern wurden nicht
  erkannt.
- Kundenportal: Die Lösungszeit rechnete SLA-Pausen mit.
- Lexoffice: Gutschriften mit Positionsrabatt kamen ohne Rabatt an — Lexware
  kennt bei Gutschriften keinen Positionsrabatt und verwirft das Feld. Der
  Rabatt steckt jetzt im Einzelpreis, wie schon beim Betragsrabatt.
- Lexoffice: Der Zahlungsabgleich übersprang Belege mit abgeschriebener
  Forderung; ein fremdes Webhook-Abo eines dem SDK unbekannten Typs ließ das
  Einrichten der Webhooks scheitern.

- Belege: Rechnung stellen, Angebot senden, Auftragsbestätigung bestätigen und
  Lieferschein ausliefern laufen unter einer Sperre und prüfen den Status
  danach neu — ein Doppelklick zog vorher zwei Nummern und konnte im
  Buchhaltungsprogramm zwei festgeschriebene Belege anlegen (Sicherheitsaudit
  2026-10-21). Ebenso Zahlungsfreigabe/Ablehnung im Rechnungseingang,
  Fahrtenbuch aus der Tour und Übernahme eines Tourvorschlags.
- Rechnungseingang: Der Zonen-Editor schrieb Werte in schon übergebene oder
  abgelehnte Eingänge und überschrieb von Hand erfasste Werte; der Lieferant
  einer Vorlage wird jetzt über die normale Zuordnung gesetzt (Audit,
  Übergabe sofort, neue Zuordnungsart „Belegvorlage“). „Zuordnung aufheben“
  wartet auf eine laufende Übergabe.
- Rechnungseingang: Vorlagen lasen „Total 1,234.56 USD“ als 4,56 — Heuristik
  und Zonen lesen Beträge und Daten jetzt über denselben Baustein;
  „31.02.2026“ ist kein Datum mehr, eine USt-IdNr. mit falscher Länge wird
  nicht mehr übernommen.
- Kundenportal: Die Auswertung zeigte als offenen Betrag den vollen Brutto,
  auch nach Teilzahlungen; sie nimmt jetzt den offenen Betrag des
  Mahnwesens. Ein Protokoll-PDF, das eine Mitarbeiterin in der Vorschau als
  Kunde lädt, steht nicht mehr als Kundenabruf im Verlauf.
- Chat: „Angeheftete Nachrichten“ scheiterte immer (falscher Pfad im Skript).
- Service-Tickets: Eine wiederholte Versandaufgabe schickte die Antwort ein
  zweites Mal.
- PHP 8.5: `imagedestroy()` (veraltet) entfernt.
- Befunde aus Phase 135 (Phase 136/137, MVP-1090–1106): Suchtreffer auf
  Dialogseiten öffnen als Dialog, Teamsicht der Personal-Auswertungen über
  Fachrechte, Soll/Ist einheitlich, Lohnfortzahlung nach § 3 EntgFG,
  CalDAV-Referenztyp, Versand (Sendungsverfolgung, Storno, Label),
  Kundenportal (Passwort vergessen, Zugang und zweiten Faktor zurücksetzen,
  Rechnungs-PDF), Krankheit ohne Soll, planübergreifende Soll-Besetzung sowie
  Restbefunde in Auswertungen, Buchhaltung, Organisationseinstellungen,
  Plugins und Kundenportal.

- Rechnungseingang: Eigene Adressen machten fremde Stammsätze „ähnlich“
  (Befund 2026-10-20, MVP-1250). Nennt eine E-Rechnung keine Verkäufer-E-Mail,
  nahm der Zuordnungsdialog den Mail-Absender als Adresse der Gegenpartei;
  bei weitergeleiteten Mails ist das die eigene Adresse, und jeder Lieferant
  mit derselben Domain galt als ähnlicher Stammsatz. „Neu aus Belegdaten“
  schrieb die eigene Adresse zudem als E-Mail der neuen Partei. Absender und
  Domains der eigenen Organisation (E-Rechnungs-Absender, Postfächer,
  Nutzerkonten) zählen jetzt nirgends als Gegenpartei. Tests.
- Cache: Objekte im Cache kamen als `__PHP_Incomplete_Class` zurück (Befund
  2026-10-20). Mit `cache.serializable_classes = false` (Laravel-Vorgabe gegen
  Gadget-Ketten) liest kein Datei-, Datenbank- oder Array-Store ein Objekt
  zurück, auch nicht verschachtelt in Arrays, und `Cache::remember` liefert den
  unvollständigen Wert als Treffer. Folgen: der Zuordnungsdialog des
  Rechnungseingangs brach mit „call a method on an incomplete object“ ab
  (Kandidaten aus dem Buchhaltungsprogramm), die Erinnerungen im Seitenkopf
  hätten denselben Fehler ausgelöst, geplante Wartungsfenster galten nur beim
  ersten Aufruf je Minute (danach leere Liste), Tag- und Mitarbeiterlisten,
  Lizenzprüfung und KI-Ergebnisse umgingen ihren Cache still (jeder Aufruf
  neu, KI-Antworten erneut berechnet). Der Cache hält jetzt nur Arrays,
  Skalare und Enums: Kandidaten, KI-Ergebnisse, Lizenzergebnis und
  Erinnerungen über `toArray()`/`fromArray()`, Wartungsfenster und
  Nachschlagelisten als Rohzeilen mit `hydrate()`; Schlüssel der Kandidaten
  und Erinnerungen versioniert, Altbestand läuft aus. Testsuite: der
  Array-Store serialisiert wie Datei und Datenbank, jeder Schreibzugriff mit
  Objekt bricht den Test ab (`Tests\TestCase`, `CacheValueRuleTest`);
  produktiv protokolliert ein Treffer mit Objekt eine Warnung. Regel in
  CLAUDE.md „Cache“, Tests für Kandidaten, Erinnerungen und Lizenz.
- Rechnungseingang: XML und Sichtrechnung derselben Mail wurden zwei Eingänge
  (Befund 2026-10-20, MVP-1247). Ein niederländischer Shop schickt UBL ohne
  Verkäuferblock, mit „Name, Bestellnummer, USt-IdNr.“ als Rechnungsnummer
  und die PDF dazu; seit die Heuristik die PDF erkennt, glichen sich die
  Nummernschlüssel nicht mehr, und die PDF war keine Begleitdatei mehr. Zwei
  Belege einer Mail gehören jetzt zusammen, wenn der kürzere Nummernschlüssel
  (mindestens vier Zeichen, mit Ziffer) im längeren steckt; leere Felder der
  XML (Nummer, Verkäufer, USt-IdNr., Daten, IBAN, BIC) ergänzt die erkannte
  PDF, vorhandene Werte bleiben, die Detailseite nennt die ergänzten Felder.
  Mit der USt-IdNr. aus der PDF greift der Lieferantenabgleich. Der
  Schemafehler selbst („AccountingCustomerParty … not expected“) liegt beim
  Absender und bleibt sichtbar. Tests.
- Rechnungseingang: Ein Datum vor dem Betrag wurde Teil des Betrags (Befund
  2026-10-20, Belegspiegel mit 19 Milliarden Euro Aufwand aus zwei
  Eingängen). Das Betragsmuster der Beschriftungssuche nahm jede Folge aus
  Ziffern, Punkten und Leerzeichen; aus „16.03.2025 201,93“ wurden
  16.032.025.201,93 EUR, aus „Rechnungsbetrag 16.03.2025“ 1.603,20 EUR. Den
  Betrag hinter einer Beschriftung liest jetzt der `AmountTokenizer` des
  common-toolkit (deutsche, englische und Schweizer Schreibweise, Währung
  davor oder dahinter); er muss direkt auf die Beschriftung folgen, höchstens
  eine Währung dazwischen, sonst bleibt das Feld leer. Die Belegwährung kommt
  aus den Währungen an den Beträgen, sonst aus einem genannten Code oder
  Symbol der Auszugswährungen des `CurrencyCode`-Enums, sonst EUR; die
  hart verdrahteten Listen EUR/USD/CHF/GBP in Beschriftungssuche und
  Positionserkennung sind weg. Die
  Sammelaktion „Erneut erkennen“ liest auch aus PDF bzw. Bild gelesene
  Eingänge ohne erfasste Werte, Übergabe oder Ablehnung neu, damit sich
  solche Fehllesungen nachträglich korrigieren lassen; der Konsolenbefehl
  bleibt auf Klärfälle beschränkt. Der Knopf „Erneut erkennen“ scheiterte
  bisher an der Pflichtfeldprüfung des Partei-Dropdowns im selben Formular
  („Wähle ein Element in der Liste aus“); er nimmt sich jetzt mit
  `formnovalidate` davon aus. Kennzahlkacheln verkleinern lange Werte
  stufenweise und brechen sie um, statt über die Kachel hinauszulaufen.
  Tests.

- Kopfzeile: Laufende Stoppuhr und Stempelung schoben die Symbolleiste über
  den Zeitraum (Befund 2026-10-20, Breiten zwischen 1400 und 1700 Pixel). Die
  rechte Rasterspalte war auf die Hälfte des Restplatzes begrenzt und durfte
  im einzeiligen Band nicht umbrechen; die Pillen liefen deshalb nach links
  über die Datumsfelder. Die rechte Spalte ist jetzt nie schmaler als ihr
  Inhalt, die linke behält mindestens Platz für das Logo (der Seitentitel
  wird gekürzt), und mit laufenden Timern beginnt das einzeilige Band erst ab
  1500 (ein Timer) bzw. 1600 Pixel (beide); darunter steht der Zeitraum in der
  zweiten Zeile. Im zweizeiligen Band bekommt die Symbolleiste zuerst ihren
  Platz, bei 1280 Pixel mit Timern bricht sie nicht mehr um. Gemessen im
  Headless-Browser bei 700–1920 Pixel.
- Datenbank: Sperrende Lesezugriffe scheiterten unter MariaDB ab 11.6.2 mit
  Fehler 1020 „Record has changed since last read“ (Befund 2026-10-19,
  Produktion 11.8: Speichern einer Abrechnungsvereinbarung brach beim
  Audit-Insert ab, während ein zweiter Prozess dieselbe Kette fortschrieb).
  Ursache ist `innodb_snapshot_isolation`, dort seit 11.6.2 standardmäßig an:
  ein `FOR UPDATE` nach einem ungesperrten Lesen derselben Transaktion wird
  abgewiesen, statt auf die Sperre zu warten. Die App schaltet die Variable
  jetzt je Verbindung ab (eigene Connectors `App\Support\Database\MySqlConnector`
  und `MariaDbConnector`, gebunden als `db.connector.mysql`/`mariadb`; auch
  nach Reconnects); Server ohne die Variable (ältere MariaDB, MySQL) bleiben
  unberührt. Betriebsseite `betrieb/datenbank-mariadb.md`, Tests
  (Versionsmatrix; gegen MariaDB: neue Verbindung ohne die Variable, Ablauf der
  Audit-Kette mit zwei Sitzungen, Gegenprobe erwartet 1020).

- Kommunikation: Notizen aus HTML-Mails (Postfachabruf per IMAP und
  Microsoft 365) zeigten `&nbsp;` und `&amp;` wörtlich und klebten Absätze
  aneinander, weil die Gateways HTML nur von Tags befreiten. Die Umwandlung
  übernimmt jetzt `StringHelper::htmlToText()` des common-toolkit (Absätze,
  Umbrüche, Listen und Tabellenzeilen bleiben Zeilen, Entities werden
  dekodiert) — für den Mail-Eingang, Microsoft-To-do-Beschreibungen und
  OneNote-Seiten gleichermaßen. Eine Migration dekodiert die Entities in
  bereits übernommenen Mail-Texten (Eingangs-Snapshots, Herkunftsverweise,
  daraus gebuchte Notizen, Tickets und Ticket-Nachrichten); verlorene
  Zeilenumbrüche lassen sich nicht nachträglich herstellen. Im
  Kommunikations-Panel öffnet der Betreff jetzt die Notiz in der Vollansicht,
  die Vorschau zeigt die ersten vier Zeilen ohne Leerzeilen (Befund 2026-10-19).
- Rechnungseingang: Die Sammelaktion „Zuordnen“ meldete nur „:n
  übersprungen“, ohne den Grund — etwa wenn die Vorgabe „Sammelkontakt“ auf
  Rechnungen aus dem EU-Ausland oder mit Reverse Charge traf, die einen
  echten Firmenkontakt brauchen, oder ein Eingang schon übergeben war. Die
  Meldung nennt jetzt je Grund die Zahl (Befund 2026-10-18).

- Rechnungseingang: PDFs, die der Absender als `application/x-download`
  o. ä. meldet und die vor `%PDF-` Vorspann tragen, hielt der Eingang für
  XML (`<<` steht in jedem PDF-Kopf) und gab sie an den E-Rechnungs-Parser,
  der bei jedem Eingang und jeder Nacherkennung `Failed to parse XML: Start
  tag expected` ins Fehlerlog schrieb; `extractXml()` lieferte die PDF-Bytes
  als Rechnungs-XML. PDF oder XML entscheidet jetzt der Inhalt
  (common-toolkit `File::mimeTypeFromContent()`, XML ohne Prolog an der
  ersten Klammer hinter BOM und Leerraum), der gemeldete Typ zählt nur noch,
  wenn der Inhalt nichts hergibt (Befund 2026-10-18).

- Rechnungseingang: ZUGFeRD-1.0-Rechnungen (Altformat, seit dem
  erechnung-toolkit 0.17.3 strukturiert gelesen) liefen bei jeder Erkennung
  durch den KoSIT-Validator, obwohl dessen XRechnung-Konfiguration kein
  Prüfszenario für das Format kennt: je Dokument ein JVM-Start von sechs bis
  acht Sekunden, das Urteil „abgelehnt“ ohne Einzelmeldung und eine Warnung
  `java endete mit Exit-Code 1` im Log. Für Altprofile entfällt die
  KoSIT-Prüfung jetzt (`validation.kosit_applicable`), die Abweichungen
  nennen das Altformat, und die Detailseite erklärt eine Ablehnung ohne
  Einzelmeldungen (kein passendes Prüfszenario) statt eine leere Liste zu
  zeigen; bestandene Prüfungen nennen das Prüfszenario
  (`validation.kosit_scenario`, auch in der Ausgangs-Validierung). Die
  Toolkits ziehen nach: der KoSIT-Aufruf wertet den Exit-Code nicht mehr als
  Fehler (common-toolkit `Shell`/`Java` mit `expectedResultCode: null`,
  erechnung-toolkit `ValidationResult::isScenarioMatched()`), das pdf-toolkit
  meldet eine leere zeilentreue Extraktion als Info und räumt nur noch
  vorhandene Temp-Dateien auf.

- Lexoffice (Phase 145): Rechnungen trugen bei jeder Position den
  Steuersatz der Rechnung, Positions- und Belegrabatte fehlten, und
  Positionen gingen als `service` ohne Artikel-ID hinüber; das PDF einer
  übertragenen Rechnung lief über den abgekündigten Weg `/document`.
  Gutschrift und Storno gingen als normale Rechnung statt als
  Rechnungskorrektur mit Bezug; Angebot und AB aus der Fertigung konnten in
  Lexoffice doppelt entstehen; „Stellen“ und der Mailversand stellten bei
  Programmvergabe einen Entwurf mit `ENTWURF-…`-Nummer aus.
- orgaMAX (Phase 145): Eine Namensänderung am Kunden kam nicht an — orgaMAX
  liest den Namen nur aus der Rechnungsanschrift.
- Überführen eines Angebots in eine Rechnung und Umwandeln einer Pro-forma
  prüften den Fakturierungsweg nicht (Phase 145, MVP-1152).
- sevDesk (Phase 144, MVP-1151): Rechnungsentwürfe entstanden mit Status 50
  (deaktivierte Serienrechnung) statt 100.

- Lexoffice (Phase 142, MVP-1138): Der Ansprechpartner stand komplett im
  Nachnamen, Anrede und Vorname blieben leer; der Kontaktabgleich verlor die
  in Lexoffice gepflegte Anrede, und das nächste Übertragen löschte sie dort.

- Kontakt-Push in die Buchhaltung (Phase 141, MVP-1133): sevDesk, easybill
  und orgaMAX überschrieben einen fremden Kontakt, der zufällig dieselbe
  Kundennummer trug; die Belegübergabe von sevDesk und easybill buchte die
  Rechnung auf ihn. Jetzt bricht die Übertragung mit einer Meldung ab.
  Lexoffice legte beim erneuten Übertragen eines verknüpften Kontakts mit
  geänderter E-Mail einen zweiten Kontakt an; verknüpfte Kontakte werden
  jetzt über ihre ID aktualisiert, Suchtreffer nur bei gleicher USt-IdNr.
  bzw. ähnlichem Namen genutzt und bei Stammdatenführung durch die
  Buchhaltung nur verknüpft.
- Lexoffice-Kontaktabgleich (MVP-1132): Eine Lexoffice-Nummer, die lokal
  schon ein anderer Kunde trug, brach den ganzen Abgleich ab — stündlich
  erneut.
- Nummernkreise (MVP-1130): Der stündliche Lexoffice-Kontaktabgleich setzte
  Präfix, Stellenzahl und Startwert der Kunden-, Lieferanten- und
  Rechnungskreise auf die Standardwerte zurück.

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

- **Sicherheitsaudit 2026-11-02** (Reselling-Integrität unter
  Nebenläufigkeit, kein hoher Befund): Rechnungsentwurf und Übergabe laufen
  unter einer Sperre je Empfänger und prüfen die Perioden unter Zeilensperre
  neu (ein zweiter Klick meldet den laufenden Entwurf, statt einen zweiten
  anzulegen); Abtretung und Übernahme prüfen die freie Menge unter der Sperre
  des Vertrags; der Periodenstatus folgt einem Übergangsvertrag im Enum, jede
  Statusänderung läuft über den gemeinsamen Weg mit Guard; Bestätigen braucht
  einen offenen Vorschlag, Verzichten eine fällige unentschiedene Periode —
  einzeln wie gesammelt; Plan-Zuordnungen werden in einer Transaktion mit
  geprüfter Position gespeichert; eine Übernahme mit berechneten Perioden vor
  dem Stichtag wird abgelehnt; abgelehnte Eingangsrechnungen lassen sich nicht
  mehr zuordnen, bewerten oder mit Einbehalt versehen. `symfony/mime`
  8.1.7 → 8.1.10.
- Zeiterfassung: Die Monatssperre beim Buchen prüfte eine `user_id` aus der
  Eingabe statt des Eigentümers — mit der ID eines Kollegen ließ sich in den
  eigenen freigegebenen Monat buchen, und beim Bearbeiten fremder Einträge
  galt die Sperre des Bearbeiters. Jetzt zählt beim Anlegen der Angemeldete,
  beim Bearbeiten der Eigentümer.
- Kunden: Den CSV-Export der Kundenliste konnte jedes Konto abrufen; er
  verlangt jetzt `customer.export` wie der Kundenexport im Exportzentrum
  (Standard: Admin, Geschäftsführung, Buchhaltung).
- Plugins: SDK-Clients (Lexoffice, orgaMAX, DATEV Online, Übersetzungsdienste)
  verbanden sich ohne Bindung an die geprüften Adressen und folgten
  Weiterleitungen ungeprüft; sie nutzen jetzt dieselben Schranken wie die
  übrigen Plugin-Abrufe.
- Routing: Organisationen konnten private Netzziele für OSRM, VROOM und
  Nominatim freischalten, obwohl die Instanz solche Opt-ins sperrt
  (`plugins.allow_private_network_opt_in`); die Prüfung liegt jetzt an einer
  Stelle (Konsolidierungs-Audit 2026-10-21).
- **Sicherheitsaudit 2026-10-21** (Vollscan über die Phasen 132–155): kein
  hoher Befund. Behoben: Belegfestschreibung ohne Sperre, Zonen-Editor an
  übergebenen Eingängen, Mail-Anhänge am Speicherkontingent vorbei (die
  Ablage aus Inhalten prüft jetzt das Kontingent), vorhersehbares
  Arbeitsverzeichnis des root-Restore-Tests in `/var/tmp` (Zufallsendung,
  atomares Anlegen, Aufräumen nur eigener Reste), Automationsregeln ohne
  Audit-Spur, Hinweisgeber-Export unverschlüsselt und Meldeformular ohne
  Honeypot. Neues Architektur-Gate `StatusCheckLockRuleTest`: Statusprüfung
  mit anschließendem Schreiben braucht eine Sperre (Bestand als
  Schrumpf-Baseline).

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

