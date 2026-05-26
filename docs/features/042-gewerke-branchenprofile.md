# Gewerke- und Branchenprofile

## Status

Proposed — Konzipiert in MVP-033/034:
[Branchenprofil IT-Service](../branchenprofil-it.md),
[Branchenprofil Handwerk/Service](../branchenprofil-handwerk.md).
Weitere geplante Profile:
[Branchenprofil Elektro](../branchenprofil-elektro.md),
[Branchenprofil SHK](../branchenprofil-shk.md),
[Branchenprofil Facility Management und Hausmeisterdienste](../branchenprofil-facility.md),
[Branchenprofil Gebäudereinigung](../branchenprofil-gebaeudereinigung.md),
[Branchenprofil Bau, Ausbau und Trockenbau](../branchenprofil-bau-ausbau.md),
[Branchenprofil Garten- und Landschaftsbau](../branchenprofil-galabau.md),
[Branchenprofil Veranstaltungstechnik](../branchenprofil-veranstaltungstechnik.md),
[Branchenprofil Sicherheitsdienst](../branchenprofil-sicherheitsdienst.md),
[Branchenprofil Maschinenbau und Anlagenwartung](../branchenprofil-anlagenwartung.md),
[Branchenprofil Kfz- und Fuhrparkservice](../branchenprofil-kfz-fuhrparkservice.md),
[Branchenprofil Steuerberatung](../branchenprofil-steuerberater.md),
[Branchenprofil Ambulante Pflege](../branchenprofil-ambulante-pflege.md),
[Branchenprofil Partyservice und Catering](../branchenprofil-partyservice.md),
[Branchenprofil Spedition und Transportlogistik](../branchenprofil-spedition.md).

## Ziel

WorkDiary soll für unterschiedliche Gewerke und Branchen nutzbar sein, ohne für
jedes Gewerk eine komplett eigene Anwendung zu werden. Dafür sollen
konfigurierbare Profile typische Auftragstypen, Protokolle, Dienstmittel,
Materialien, Qualifikationen, Prüfungen, Abnahmen, Auswertungen und Begriffe
vorbelegen.

## Warum

Ein Elektrofachbetrieb arbeitet anders als ein SHK-Betrieb, IT-Service,
Facility Management oder Bau/Ausbau. Wenn WorkDiary diese Unterschiede nur über
Freitext abbildet, leidet die Bedienung und die Auswertung. Branchenprofile
helfen beim Onboarding, bei Demos, bei Prozeduren und bei fachlich passenden
Protokollen.

## Beispielgewerke

- Elektro.
- SHK: Sanitär, Heizung, Klima.
- Facility Management und Hausmeisterdienste.
- Gebäudereinigung.
- IT-Service, Netzwerk, Software und Managed Services.
- Maschinenbau, Anlagenwartung und Instandhaltung.
- Bau, Ausbau, Trockenbau.
- Maler, Boden, Innenausbau.
- Garten- und Landschaftsbau.
- Sicherheits-, Prüf- und Wartungsdienste.
- Veranstaltungstechnik.
- Partyservice, Catering und Eventverpflegung.
- Spedition, Fuhrpark- und logistiknahe Dienste.
- Pflege-, Sozial- oder betreuungsnahe Dienste, wenn fachlich gewünscht.

## Profilinhalte

- typische Auftragstypen.
- typische Protokolle und Checklisten.
- typische Prozeduren und Pflichtnachweise.
- typische Dienstmittel, Werkzeuge, Fahrzeuge oder Geräte.
- typische Materialien und Verbrauchsarten.
- typische Qualifikationen und Unterweisungen.
- typische Prüf-, Wartungs- und Abnahmeprozesse.
- typische Auswertungen und Kennzahlen.
- typische Risiken, Arbeitsschutz- und Sicherheitsanforderungen.
- typische Dokumente, Zertifikate und Nachweise.
- optionale Fachbegriffe und Feldbezeichnungen.
- optionale raum- und objektbezogene Anforderungen pro Gewerk.

## Beispiele

### Elektro

- Prüfprotokolle, Messwerte, Stromkreis, Sicherung, Verteiler.
- VDE-nahe Prüf- und Dokumentationspflichten.
- Foto- oder Messwertpflicht für bestimmte Arbeiten.
- zweite Person oder Freigabe bei kritischen Tätigkeiten.

### SHK

- Anlagenakte, Wartungsintervall, Druckprüfung, Dichtheitsprüfung.
- Ersatzteile, Seriennummern, Herstellerdokumente.
- Kundenabnahme und Wartungsprotokoll.

### IT-Service

- Konfigurationsbackup vor Änderungen.
- Change-Protokoll, Update, Rollback, Funktionstest.
- Vier-Augen-Freigabe für kritische Änderungen.
- sensible Zugangsdaten nicht im Klartext dokumentieren.
- Kundenrechner, Drucker, Netzwerkdosen, Access Points und weitere IT-Assets
  können Gebäude, Etage, Raum oder Arbeitsplatz zugeordnet werden.

### Facility Management

- Gebäude, Etagen, Räume und Außenbereiche als Objektkontext.
- Objektkontrollen, Mängel, Betreiberpflichten, Schlüssel, Zählerstände und
  Dienstleisterkoordination.
- Raum- oder Bereichsanforderungen wie Brandschutzkontrolle,
  Störungsmeldung, Wartungsrunde oder gesperrter Bereich.

### Gebäudereinigung

- Reinigungsbereiche, Raumgruppen und Sonderflächen.
- Unterhaltsreinigung, Grundreinigung, Sonderreinigung, Qualitätskontrolle und
  Reklamationen.
- Raumbezogene Anforderungen wie besondere Reinigung, Hygienehinweis,
  Zugangsbeschränkung, Materialbedarf oder Foto-/Abnahmepflicht.

### Bau und Ausbau

- Baustellenfotos, Mängel, Aufmaß, Nachtrag.
- Materialverbrauch, Wetter, Baufortschritt.
- Teilabnahme, Restpunkte und Nacharbeit.

## MVP

- Branchenprofil als Startkonfiguration für neue Mandanten.
- Importierbare Vorlagenpakete für Protokolle, Prozeduren, Tags,
  Auftragstypen, Qualifikationen und Dokumenttypen.
- Profil kann beim Onboarding gewählt und später angepasst werden.
- Demo-Daten pro ausgewähltem Profil.
- Profilbestandteile sind transparent und nicht fest im Code verdrahtet.
- Profile können raum- und objektbezogene Anforderungen vorbelegen, damit
  unterschiedliche Gewerke denselben Gebäudebereich fachlich unterschiedlich
  nutzen können.

## Akzeptanzkriterien

- Ein neuer Mandant kann mit einem passenden Branchenprofil starten.
- Profile erzeugen strukturierte, auswertbare Daten statt Freitextlisten.
- Profile können kundenspezifisch angepasst werden.
- Profile können Anforderungen pro Gewerk, Objekt- oder Raumtyp definieren,
  ohne die Kernmodelle pro Branche zu duplizieren.
- Alte Daten bleiben nachvollziehbar, wenn ein Profil später geändert wird.
- Branchenprofile verletzen nicht die einheitliche Bedienung des Produkts.

## Später

- Marketplace oder Katalog für Branchenpakete.
- Versionierte Profilpakete mit Update-Hinweisen.
- Kundenspezifische Profilvarianten.
- Best-Practice-Prozeduren je Gewerk.
- Branchenbezogene Demo- und Vertriebsumgebungen.

## Abhängigkeiten

- Demo-, Testdaten und Musterbranchen
- Vorlagen- und Formularsystem
- Prozeduren, Arbeitsanweisungen und Checklisten
- Dokumentation und Abnahmeprotokolle
- Klassifikationen, Tags und Datenqualität
- Inventar, Dienstmittel und Assets
- Qualität, Sicherheit und Arbeitsschutz
- Einheitliche Bedienung und UX-Konventionen

## GitHub Issues

- TBD
