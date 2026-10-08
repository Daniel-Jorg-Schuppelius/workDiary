---
title: "Datenschutzmanagement im Überblick"
topic: privacy.overview
version: 2
keywords:
    - DSGVO
    - Verarbeitungsverzeichnis
    - VVT
    - Auftragsverarbeitungsvertrag
    - AVV
    - TOM
    - Betroffenenrechte
    - Auskunftsersuchen
    - Datenpanne
    - Meldepflicht 72 Stunden
    - Löschkonzept
    - Legal Hold
    - Crypto-Shredding
audience: []
modules:
    - module.datenschutz
related:
    - documents.manage
    - isms.overview
    - glossary.core
    - privacy.portal
---

Das Datenschutzmodul unterstützt die operative Datenschutzarbeit Ihrer
Organisation. Es wird laufend weiterentwickelt – die Grundbausteine:

- **Verarbeitungstätigkeiten (VVT)**: Verzeichnis nach Art. 30 DSGVO
  mit Versionierung. Ablauf: „Entwurf" → „In Prüfung" → „Freigegeben"
  → „Archiviert". Jede Freigabe erzeugt einen unveränderlichen
  Versions-Snapshot (inklusive TOM-Stand).
- **Auftragsverarbeiter & AVV**: Register der Dienstleister und
  Verträge nach Art. 28.
- **Betroffenenanfragen**: Auskunft, Berichtigung, Löschung,
  Einschränkung, Datenübertragbarkeit, Widerspruch (Art. 15–21) mit
  **30-Tage-Frist** ab Eingang, Identitätsprüfung, Zuweisung und
  dokumentierter Entscheidung.
- **TOM**: technische und organisatorische Maßnahmen.
- **Datenschutzvorfälle**: Erfassung mit Blick auf die
  72-Stunden-Meldepflicht (Art. 33). Die Meldung an die Behörde und die
  Benachrichtigung der Betroffenen (Art. 34) werden getrennt vermerkt.

Besonderheiten:

- Anfrageinhalte und Entscheidungsbegründungen werden **verschlüsselt**
  gespeichert (eigener Schlüssel je Fall).
- **Bewusst kein Admin-Bypass**: Datenschutzrechte werden ausdrücklich
  vergeben – auch Plattform-Admins erhalten sie nicht automatisch.

Risiken und unumkehrbare Aktionen: Nach Ablauf der Aufbewahrungsfrist
kann der Fall-Schlüssel vernichtet werden (Crypto-Shredding) – die
verschlüsselten Inhalte sind dann **unwiederbringlich**. Freigegebene
VVT-Versionen sind nicht mehr änderbar.

Nächste Schritte: Nachweise (AVV-Dokumente, Zertifikate) verwalten Sie
im Modul **Dokumente**.

Die Auskunft (Art. 15/20) lässt sich auch für **Vereinsmitglieder** erzeugen,
sofern Ihre Organisation die Vereinsverwaltung nutzt: Stammdaten mit
Erziehungsberechtigten, Anschriften und Bankverbindungen sowie eine Übersicht
der Vereinsdaten (Mitgliedschaftszeiträume, Gruppen, Anwesenheiten, Beiträge,
Spenden, Graduierungen, Leistungen und weitere) mit Auszug je Bereich. Die
Suche findet Mitglieder über Name, E-Mail oder Mitgliedsnummer.

Alle folgenden Seiten liegen in der Seitenleiste unter **Datenschutz**. Zum
Ansehen genügt das Leserecht des Datenschutzmoduls (Ausnahme:
Betroffenenportal); für Änderungen gibt es je Bereich ein eigenes Recht. Die
Rolle **Datenschutz** hat alle diese Rechte.

## Gemeinsame Verantwortlichkeit

**Datenschutz** → **Verzeichnisse** → **Gemeinsame Verantwortlichkeit**. Das
**GVV-Register** führt Vereinbarungen über gemeinsame Verantwortlichkeit nach
Art. 26 DSGVO. Die Liste zeigt **Titel**, **Partner**, **Status** und
**Wesentliches bereitgestellt**.

**Neue GVV anlegen**:

- **Partner (Dienstleister)** aus dem Dienstleisterregister und **Titel**
  (Pflicht), optional **Gültig ab** und **Gemeinsame Anlaufstelle**.
- **Zuständigkeitsmatrix**: Für **Informationspflichten (Art. 13/14)**,
  **Betroffenenrechte**, **Datenschutzvorfälle** und
  **Aufsichtsbehörde-Kontakt** legen Sie jeweils fest, wer zuständig ist:
  **Wir**, **Partner** oder **Gemeinsam** (Vorgabe).
- Häkchen **Wesentliches der GVV den Betroffenen bereitgestellt**.
- Optional das **Vertragsdokument** (PDF, DOC oder DOCX, bis 20 MB).

Eine neue GVV beginnt im Status **Entwurf**. In der GVV ändern Sie die
Matrix, die **Anlaufstelle**, den **Status** (**Entwurf**, **Aktiv**,
**Gekündigt**, **Abgelaufen**) und **Wesentliches bereitgestellt**. Unter
**Verknüpfte Verarbeitungstätigkeiten** haken Sie die betroffenen Tätigkeiten
aus dem Verzeichnis an und speichern mit **Verknüpfungen speichern**. Das
Vertragsdokument laden Sie über den Link in den Eckdaten herunter.

**Berechtigung:** Ansehen mit dem Leserecht; anlegen und ändern mit dem Recht
für Dienstleister und AVV.

## TOM-Katalog

**Datenschutz** → **Verzeichnisse** → **TOM-Katalog**. Der Katalog sammelt die
technischen und organisatorischen Maßnahmen (Art. 32 DSGVO) an einer Stelle.
Die Liste zeigt **Maßnahme**, **Bereich**, **Status** und **Review fällig**;
ist das Review-Datum überschritten, ist es rot hervorgehoben.

**Neue Maßnahme**: **Bezeichnung**, **Maßnahmenbereich** (zum Beispiel
**Zutrittskontrolle**, **Zugangskontrolle**, **Zugriffskontrolle**,
**Weitergabekontrolle**, **Eingabekontrolle**, **Verfügbarkeitskontrolle**,
**Wiederherstellbarkeit**, **Trennungskontrolle** oder
**Datenschutz-Management**), **Beschreibung**, **Adressierte Risiken** und
**Nachweise (Richtlinien, Protokolle, Zertifikate …)**. Die Maßnahme entsteht
mit Version 1 als Entwurf.

In der Maßnahme:

- **Versionen**: Änderungen speichern Sie über **Neue Version** mit
  Beschreibung, adressierten Risiken und **Änderungsnotiz**; ältere Versionen
  bleiben erhalten. **Freigeben** macht eine Version zur **Gültigen
  Version**.
- **Zugeordnete Verarbeitungstätigkeiten**: Tätigkeit wählen und
  **Zuordnen**. Wird eine Verarbeitungstätigkeit freigegeben, friert ihre
  Version den Stand der zugeordneten Maßnahmen mit ein.
- **Wirksamkeitsprüfungen**: **Prüfung dokumentieren** mit **Ergebnis**
  (**Wirksam**, **Abweichung** oder **Unwirksam**), optional **Folgemaßnahme
  fällig** und **Abweichung / Folgemaßnahme**. Die nächste Prüfung wird auf
  das Datum der Folgemaßnahme gesetzt, ohne Datum auf ein Jahr später; dieses
  Datum erscheint in der Liste unter **Review fällig**.
- **Nachweise**: Dateien mit **Nachweis hochladen** ablegen, optional mit
  **Gültig bis (optional)**. Abgelaufene Nachweise sind markiert; die
  Lückenanalyse meldet Nachweise, die bald ablaufen oder abgelaufen sind.

**Berechtigung:** Ansehen mit dem Leserecht; anlegen, versionieren,
freigeben, zuordnen, prüfen und Nachweise hochladen mit dem Recht für den
TOM-Katalog.

## Lückenanalyse

**Datenschutz** → **Vorfälle & Prüfung** → **Lückenanalyse**. Die Analyse
prüft regelbasiert, ob Verträge, Bewertungen und Nachweise fehlen oder
ablaufen:

- Auftragsverarbeiter ohne AVV,
- AVV, die bald ablaufen oder abgelaufen sind (standardmäßig 30 Tage
  Vorlauf),
- gemeinsam Verantwortliche ohne GVV,
- Verarbeitungstätigkeiten mit DSFA-Bedarf ohne abgeschlossene DSFA,
- Verarbeitungstätigkeiten ohne zugeordnete TOM,
- TOM-Nachweise, die bald ablaufen oder abgelaufen sind.

**Analyse jetzt ausführen** startet einen Lauf. Zusätzlich läuft die Analyse
einmal täglich automatisch, sobald die Lückenanalyse in Ihrer Organisation
einmal geöffnet wurde. Oben zeigt eine Ampel die Zahl der Befunde je Status,
darunter stehen die Befunde – offene zuerst – mit **Anforderung**,
**Status**, **Auslöser** und **Bezug** (Link zur Tätigkeit, zum AVV oder zum
Dienstleister).

Die Analyse setzt **Fehlt** oder **Läuft ab**. Unter **Entscheidung** setzen
Sie von Hand **Vorhanden**, **In Prüfung**, **Nicht anwendbar**,
**Abweichung akzeptiert** oder **Wieder offen**, jeweils mit **Begründung**
und **OK**. Für „Nicht anwendbar“ und „Abweichung akzeptiert“ ist die
Begründung Pflicht. Einen von Hand entschiedenen Befund ändern spätere
Analyseläufe nicht mehr. Lücken, die die Analyse selbst gesetzt hat und die
nicht mehr auftreten, setzt der nächste Lauf auf **Vorhanden**.

Im **Anforderungskatalog** unten auf der Seite legen Sie fest, welche
Prüfungen laufen: Jede Anforderung lässt sich umbenennen und mit dem Schalter
deaktivieren; deaktivierte Anforderungen werden übersprungen. Einträge aus
einem Branchenprofil tragen die Kennzeichnung **Branchenprofil**.

**Berechtigung:** Ansehen mit dem Leserecht; Analyse starten, entscheiden und
den Katalog pflegen mit dem Recht für die Lückenanalyse.

## Aufbewahrung & Löschung

**Datenschutz** → **Vorfälle & Prüfung** → **Aufbewahrung & Löschung**. Das
Löschkonzept schlägt Daten mit abgelaufener Aufbewahrungsfrist zur Löschung
vor; gelöscht oder anonymisiert wird erst nach zweistufiger Bestätigung. Oben
steht der Rechtsraum Ihrer Organisation (zum Beispiel DE), nach dem sich die
Fristen richten.

- **Fristen je Bereich**: je Datenbereich – etwa Audit-Protokoll,
  Bewerbungen oder Lernplattform – die **Frist** in Jahren oder Tagen und die
  **Rechtsgrundlage**. Bereiche mit dem Vermerk „nur Ausweis, ohne Scan“
  dokumentieren nur die Frist; für sie entstehen keine Vorschläge.
- **Jetzt scannen** sucht Datensätze mit abgelaufener Frist und legt
  **Lösch-Vorschläge** an. Der Scan läuft zusätzlich regelmäßig automatisch
  (standardmäßig wöchentlich). Datensätze unter Legal Hold und fachliche
  Ausnahmen erhalten keinen Vorschlag.
- Jeder Vorschlag zeigt **Bereich**, **Datensatz**, **Frist abgelaufen
  seit**, **Begründung** und **Status**.

Gelöscht wird in zwei Stufen: Zuerst **Bestätigen** (Status **bestätigt**)
oder **Ablehnen** (**abgelehnt**), danach bei bestätigten Vorschlägen
**Endgültig löschen** – einzeln oder gebündelt je Bereich mit **Bestätigte in
… löschen**. Je nach Bereich wird der Datensatz gelöscht oder anonymisiert.
Liegt inzwischen ein Legal Hold vor, werden Bestätigung und Löschung
abgewiesen; beim gebündelten Löschen werden solche Datensätze übersprungen
und in der Meldung gezählt. Jede Entscheidung wird protokolliert.

**Berechtigung:** Ansehen mit dem Leserecht; scannen und entscheiden mit dem
Recht für die Lückenanalyse.

## Legal Hold

**Datenschutz** → **Vorfälle & Prüfung** → **Legal Hold**. Ein Legal Hold ist
ein Sperrvermerk für laufende Betroffenen- oder Rechtsverfahren: Solange er
aktiv ist, wird zu der Person oder dem Kunden nichts gelöscht oder
anonymisiert.

Die Liste zeigt aktive Vermerke zuerst, mit **Betroffen** (Name, Person oder
Kunde), **Aktenzeichen**, **Grund** (nur für Personen mit dem
Entscheidungsrecht lesbar), **Gesetzt** (Datum und wer) und **Status**
(**aktiv** oder „aufgehoben am …“).

**Legal Hold setzen**: Unter **Art** **Person** oder **Kunde** wählen und dann
genau eine Person der Organisation oder einen Kunden; optional ein
**Aktenzeichen**. Der **Grund** ist Pflicht (mindestens 10 Zeichen) und wird
verschlüsselt gespeichert.

Solange der Vermerk aktiv ist, entstehen keine Löschvorschläge; bestätigte
Löschungen, Anonymisierung und das Löschen von Konten oder Kunden werden
abgewiesen, und die Standort-Rohpunkte der Person bleiben erhalten. Bei einer
Kundenzusammenführung wandert der Vermerk zum Zielkunden.

**Legal Hold aufheben** verlangt einen **Grund der Aufhebung** (mindestens
10 Zeichen). Danach greifen Löschkonzept und Aufräumläufe wieder; die
Begründung bleibt als Nachweis erhalten. Setzen und Aufheben stehen im
Protokoll der Person bzw. des Kunden.

**Berechtigung:** Ansehen mit dem Leserecht; setzen und aufheben mit dem
Recht für die Lückenanalyse – wer über Löschungen entscheidet, sperrt sie
auch.

## Betroffenenportal

**Datenschutz** → **Vorfälle & Prüfung** → **Betroffenenportal** (Seitentitel
**Betroffenenportal verwalten**). Hier richten Sie das öffentliche Formular
ein, über das betroffene Personen ihre Anfragen stellen; wie das für die
Person abläuft, beschreibt das Thema zum Auskunftsportal.

- Solange noch kein Portal besteht, zeigt die Seite einen Hinweis; das erste
  **Speichern** legt das Portal mit einem zufälligen Link an.
- **Öffentlicher Link**: Diesen Link veröffentlichen Sie in Ihrer
  Datenschutzerklärung; er lässt sich nicht aus dem Organisationsnamen
  ableiten. Daneben steht, ob das Portal **aktiv** oder **inaktiv** ist.
  **Link rotieren** erzeugt nach einer Rückfrage einen neuen Link – bereits
  veröffentlichte Links werden ungültig.
- **Einstellungen**: **Portal aktiv (öffentlich erreichbar)** – anfangs
  ausgeschaltet –, **Anhänge erlauben**, **Einleitungstext (optional)** und
  **Standardsprache (optional, z. B. de)**.

Eingehende Anfragen erscheinen als Vorgang unter **Betroffenenanfragen**.
Dort sind sie als Portal-Eingang gekennzeichnet; die Angaben zur Identität
gelten als ungeprüfte Selbstauskunft.

**Berechtigung:** ein eigenes Recht zur Verwaltung des Betroffenenportals;
ohne dieses Recht fehlt der Menüeintrag.
