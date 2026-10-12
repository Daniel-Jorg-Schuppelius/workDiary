---
title: "Rechnungseingang"
topic: finance.incoming-invoices
version: 3
keywords:
    - Eingangsrechnung
    - Lieferantenrechnung
    - Kreditorenrechnung
    - Rechnungspostfach
    - Rechnungseingang
    - XRechnung empfangen
    - ZUGFeRD
    - Factur-X
    - E-Rechnung prüfen
    - Rechnungsprüfung
    - Rechnung zuordnen
    - Sammellieferant
    - Sammelkunde
    - Klärfall
    - Rechnung freigeben
    - Zahlungsfreigabe
    - EN 16931
    - Übergabe an Lexware
    - Buchungskategorie
    - Doppel-Eingang
    - doppelte Rechnung
    - Rechnungsordner
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.datev-bookings
---

Der **Rechnungseingang** (Menü Abrechnung → Rechnungseingang) nimmt
Rechnungen entgegen, ordnet sie Lieferanten oder Kunden zu und führt sie
durch Prüfung und Zahlungsfreigabe — ohne die Rechnungshoheit des führenden
Buchhaltungs- bzw. Faktura-Programms anzutasten.

**Eingangskanäle:** Rechnungen kommen über das Rechnungspostfach, den
Datei-Upload, Peppol oder die Cloud-Ablage herein. Alle Kanäle durchlaufen
dieselbe Verarbeitung: Dublettenprüfung, Sicherheitsprüfung, Lesen der
E-Rechnung bzw. Erkennung aus PDF oder Bild, Validierung und Abweichungen.
Das unveränderte Original liegt als Dokument vom Typ Rechnung im DMS.

**Rechnungspostfach:** Unter Verwaltung → E-Mail-Eingang wird ein Postfach
mit dem Schalter „Rechnungspostfach“ zum Rechnungseingang. Jede Mail wird
nach Rechnungen ausgewertet, nicht nach Anhängen:

- Eine XRechnung (XML) ist das Original. Ein mitgeschicktes PDF derselben
  Rechnung hängt als Begleitdatei daran.
- Weitere Anhänge wie AGB oder Lieferscheine werden Begleitdateien.
- Eingebettete Logos und Signaturbilder werden nicht verarbeitet.
- Enthält eine Mail keine erkennbare Rechnung, wird der Anhang ein
  **Klärfall**: Das Original liegt vor, die Werte erfassen Sie selbst.
- Mails ganz ohne Rechnungsanhang (etwa nur mit Download-Link) landen in der
  Zuordnungs-Inbox der Verwaltung, gekennzeichnet als Rechnungspostfach.
- Eine als Anlage weitergeleitete Mail wird ausgepackt; die Rechnung darin
  zählt wie ein direkter Anhang.

**Abruf und Bestand:** Abgerufen wird ab dem Tag unter „Abruf ab“ nach Stand —
auch Mails, die schon jemand gelesen hat. Am besten wählen Sie einen eigenen
Ordner, in den eine Regel die Rechnungen sortiert (Unterordner mit „/“, etwa
Posteingang/Rechnungen). Ältere Rechnungen holt „Ältere Mails nachholen“ in
der Postfach-Verwaltung; dieselbe Rechnung wird dabei nicht doppelt angelegt.
Liegt eine Rechnungsmail in einem anderen Postfach, übernimmt „Als
Eingangsrechnung übernehmen“ in der Zuordnungs-Inbox ihre Anhänge.

**Erkennung:** Eine E-Rechnung (XRechnung oder ZUGFeRD/Factur-X) liefert
verbindliche Werte. Bei PDF und Foto werden die Werte erkannt und sind ein
Vorschlag, den Sie am Original prüfen. Ist eine Rechnung im inländischen
B2B-Verkehr keine E-Rechnung, erscheint ein Hinweis: Das ist nur
übergangsweise zulässig, bis Ende 2026 bzw. für kleine Aussteller bis Ende
2027. Der Hinweis sperrt nichts; Kleinbetragsrechnungen bis 250 € sind
ausgenommen.

**Richtung:** Sind Sie Käufer, ist es ein Eingangsbeleg mit einem Lieferanten
als Gegenpartei. Sind Sie Verkäufer — etwa bei Rechnungskopien aus Shop oder
Kasse oder bei Gutschriften im Gutschriftverfahren —, ist es ein
Ausgangsbeleg mit einem Kunden als Gegenpartei. Ausgangsbelege erscheinen nie
in Zahlungsvorschlag, Zahllauf oder Einbehalten. Nennt eine Rechnung einen
fremden Käufer, meldet die Prüfung „nicht an uns adressiert“; die Kopie einer
bereits erfassten eigenen Rechnung wird ebenfalls gemeldet.

**Arbeitsliste:** Die Reiter „Zuzuordnen“ und „Zu prüfen“ zeigen alle offenen
Eingänge, „Alle“ den gewählten Zeitraum. Der Menüeintrag zählt die
zuzuordnenden Eingänge. Die Buchhaltung erhält morgens eine Benachrichtigung,
solange etwas zuzuordnen ist.

**Zuordnung:** Automatisch zugeordnet wird nur, wenn genau eine Partei über
ein exaktes Merkmal des Belegs trifft: USt-IdNr., Steuernummer oder IBAN.
Treffen mehrere, bleibt der Eingang zuzuordnen und die Prüfung nennt die
Kandidaten. Die eigenen Kennungen Ihrer Organisation zählen nie als Treffer.
Über „Zuordnen“ wählen Sie selbst:

- eine bestehende Partei (Vorschläge stehen oben),
- den Sammellieferanten bzw. Sammelkunden,
- eine Neuanlage, vorbefüllt aus den Belegdaten,
- „Keine Rechnung“ — der Eingang wird mit Begründung abgelehnt.

Dabei lässt sich auch die Richtung korrigieren. Mit „Absender merken“ ordnet
das System künftige Mails dieses Absenders derselben Partei zu, solange der
Beleg selbst keine andere Partei nennt. Bei Klärfällen und erkannten Belegen
tragen Sie über „Werte erfassen“ Nummer, Datum und Beträge ein. Mehrere
Eingänge derselben Partei ordnen Sie in der Liste gemeinsam zu.

**Sammellieferant und Sammelkunde:** Für Einmallieferanten und Einmalkunden
gibt es je Organisation einen Sammelkontakt. Der Name der echten Partei
bleibt am Beleg. Ein Sammelkontakt wird nie als eigener Kontakt an ein
Buchhaltungssystem übertragen, lässt sich nicht zusammenführen, erhält keinen
Portalzugang und keine eigene Rechnung. Bei Reverse Charge (§ 13b),
innergemeinschaftlichen Fällen und Drittland ist er gesperrt — dort braucht
es einen echten Firmenkontakt.

**IBAN-Prüfung:** Weicht die IBAN der Rechnung von allen hinterlegten
Bankverbindungen des Lieferanten ab, weist die Seite darauf hin, und die
Zahlung verlangt eine Bestätigung. Rechnungen an den Sammellieferanten
verlangen diese Bestätigung immer. Eine Rechnung ändert nie die Stammdaten.

**Dubletten:** Identischer Dateiinhalt wird je Organisation nur einmal
erfasst — auch kanalübergreifend (ein Upload nach vorherigem Mail-Eingang
bleibt eine Dublette).

**Möglicher Doppel-Eingang:** Kommt dieselbe Rechnung als andere Datei ein
zweites Mal herein — etwa als PDF per Mail und als XRechnung über Peppol —,
erkennt der Rechnungseingang das an der Rechnungsnummer zusammen mit der
Steuerkennung des Ausstellers bzw. der zugeordneten Partei. Der spätere
Eingang wird nicht an die Buchhaltung übergeben, bis Sie „Kein Doppel“
bestätigen oder ihn ablehnen. Eine Nummer allein reicht nicht: „1001“ gibt es
bei vielen Lieferanten.

**Validierung und Konsistenz:** Jede E-Rechnung wird gegen das XML-Schema und,
sofern eingerichtet, gegen die KoSIT-Prüfregeln (EN 16931) validiert; ob die
Prüfungen verfügbar waren, wird ausgewiesen. Zusätzlich warnt die
Abweichungsprüfung sichtbar — nie stillschweigend — bei widersprüchlichen
Summen (Netto + Steuer ≠ Brutto) und bei Steuerausweis ohne Steuerkennung des
Ausstellers.

**Prüfworkflow:** Ein Eingang wird freigegeben, mit Rückfrage versehen oder
abgelehnt (Ablehnung nur mit Begründung). Erst nach fachlicher Freigabe ist
die Zahlungsfreigabe möglich. Jede Entscheidung wird mit Person und Zeitpunkt
protokolliert.

**Übergabe an die Buchhaltung:** Ist Lexware Office oder DATEV Unternehmen
online angebunden und die Übergabe dort eingeschaltet, geht ein Eingang los,
sobald seine Gegenpartei feststeht. Die fachliche Freigabe bleibt
Voraussetzung der Zahlung, nicht der Übergabe. Vorher prüft das System: kein
Klärfall ohne Werte, nicht abgelehnt, an uns adressiert, keine Kopie einer
eigenen Rechnung, stimmige Summen und kein Sammelkontakt bei Reverse Charge,
innergemeinschaftlichen Fällen oder Drittland.

- Lexware Office erhält den Beleg als „zu prüfen“ mit dem Original, bei einer
  XRechnung zusätzlich mit dem mitgeschickten PDF. Liegt dort schon ein Beleg
  mit derselben Nummer beim selben Kontakt, wird er nur verknüpft.
- Beträge gehen nur mit einer Buchungskategorie mit — am Lieferanten bzw.
  Kunden oder als Vorgabe in den Plugin-Einstellungen —, in Euro und mit den
  Steuersätzen 0, 5, 7, 16 oder 19 %. Sonst geht der Beleg ohne Beträge
  hinaus, und die Buchhaltung ergänzt sie in Lexware.
- DATEV Unternehmen online erhält das Original als Belegbild.
- Fehlt die Rechnungsnummer, sucht die Übergabe in Lexware nach Kontakt,
  Belegdatum und Betrag. Ein Treffer wird nicht automatisch verknüpft: Sie
  wählen „Mit diesem Beleg verknüpfen“ oder „Anderer Beleg – neu anlegen“.
- DATEV lässt sich per Schnittstelle nicht nach vorhandenen Belegen
  durchsuchen. Schicken Sie dieselben Rechnungen deshalb nicht zusätzlich per
  DATEV-Upload-Mail; der Schutz vor Doppeln liegt dort allein beim
  Rechnungseingang.
- Ein dort angelegter Beleg lässt sich per Schnittstelle nicht mehr löschen.
- Senden Sie dieselben Rechnungen nicht zusätzlich an die Beleg-E-Mail-Adresse
  von Lexware: Dort erkannte Belege haben anfangs keine Nummer und entgehen
  der Dublettenprüfung.

Der Stand je Ziel steht auf der Detailseite; die Reiter „Übergabe offen“ und
„Übergabe fehlgeschlagen“ sammeln, was hängt. Fehlgeschlagene Übergaben
wiederholt das System stündlich bis zu fünfmal, „Erneut versuchen“ stößt sie
jederzeit an. Ohne angebundenes Buchhaltungssystem vermerkt „An Buchhaltung
übergeben“ die Übergabe nach der fachlichen Freigabe als Nachweis; ein zweiter
Aufruf ändert nichts.

**XML-Download:** Das Rechnungs-XML lässt sich jederzeit aus dem Original
extrahieren (bei ZUGFeRD aus dem PDF-Anhang). Jeder Abruf wird mit Prüfsumme
als Nachweis protokolliert.
