---
title: "Mannschaften, Spieltage und Aufstellungen"
topic: club.matches
version: 1
audience: []
modules:
    - module.club
related:
    - club.groups
    - club.events
    - club.attendance
---

Mannschafts- und Rückschlagsport (Fußball, Handball, Basketball, Volleyball,
Hockey, Tischtennis, Tennis …) bauen auf Gruppen, Terminen und Anwesenheit auf.
Eine Sportart ist ein **Sportartenprofil**, also Konfiguration statt Sonderfall
im System: Sportfamilie, Positionen, Kadergrößen (Feld/Bank), Ergebnisformat
(Tore, Punkte je Spielabschnitt, Sätze), Einzel/Doppel, Stichtag der
Altersklasse, Disziplinen und Ressourcentypen. Der Verein passt Profile an oder
legt weitere an; Verbandsregeln sind nicht fest einprogrammiert.

**Mannschaften:** Eine Mannschaft ist eine Gruppe mit dem Kennzeichen
„Mannschaft“ und einem Sportartenprofil (eigenes oder das der Abteilung). Die
Altersklasse ist ein frei wählbares Etikett (z. B. U15); Alterskriterien der
Gruppe werden bei Mannschaften am Stichtag des Profils innerhalb der Saison
geprüft, nicht am Kalendertag.

**Saisons und Kader:** Saisons sind Zeiträume mit Namen (z. B. 2026/27). Je
Mannschaft und Saison gibt es einen Kader mit Gültigkeit je Person,
Trikotnummer, Position und — im Rückschlagsport — der manuell gepflegten
Spielstärke-Reihenfolge. Vorsaisons bleiben unverändert erhalten.
**Gastspieler** eines Partnervereins werden mit Herkunftsverein im Kader
geführt; sie sind Personen der Art „Gast“ ohne Beitragszuordnung, ohne
Login und ohne Gruppenmitgliedschaft.

**Spieltage:** Ein Spieltag ist ein Vereinstermin mit Sportdetails: Mannschaft,
Gegner (ohne Kunden- oder Benutzeranlage), Wettbewerb, Heim/Auswärts, Spielort,
Treffzeit und Leitung. Die Mannschaft ist Zielgruppe des Termins; weitere
Gruppen lassen sich ergänzen.

**Verfügbarkeit und Aufstellung:** Mitglieder sagen im Portal zu, ab oder
„vielleicht“ — eine Zusage ist keine Nominierung. Die Leitung stellt aus
(Feld/Bank mit Position und Trikotnummer; im Rückschlagsport Einzel- und
Doppelpaarungen) und gibt frei. Kadergrößen und Positionen kommen aus dem
Profil. Eine Person in Einzel und Doppel bleibt eine Person. Vor der Freigabe
werden Konflikte gezeigt: zeitgleicher Einsatz in einer anderen Aufstellung oder
eine ausdrückliche Absage. Eine Freigabe trotz Konflikt braucht eine
Begründung und wird protokolliert. Nominierte werden Teilnehmer des Termins;
die tatsächliche Anwesenheit erfasst die Anwesenheitsliste separat.

**Terminrollen:** Schiedsrichter, Zeitnehmer/Kampfgericht, Fahrdienst,
Platz-/Kabinendienst oder Standaufsicht werden am Termin vergeben — an ein
Mitglied, eine Mitarbeiterin bzw. einen Mitarbeiter oder als externer Name.
Ein Mitglied mit Rolle zählt als Vereinsteilnahme, nicht als Kaderplatz.

**Ergebnis:** Das Ergebnis wird manuell im Format des Profils erfasst (Tore,
Punkte je Abschnitt mit Summe, Sätze mit Satzstand). Torschützen und Hinweise
gehören in die Notiz. Änderungen werden protokolliert; es gibt keine
automatische Tabelle aus unvollständigen Ergebnissen.

**Spielplan-Import:** CSV oder ICS ergeben eine **Vorschlagsliste**, die vor
der Bestätigung nichts anlegt. Die Leitung prüft Gegner, Ort und Zeit und
übernimmt oder verwirft jeden Vorschlag; bekannte Zeilen werden beim erneuten
Import übersprungen, mögliche Dubletten zu bestehenden Spieltagen markiert.
CSV-Spalten (Kopfzeile, Reihenfolge frei): Datum, Zeit, optional Ende, Gegner
und Heim/Auswärts — oder Heim und Gast als Mannschaftsnamen — sowie Spielort und
Wettbewerb. Eine Verbands-Synchronisation ist nicht enthalten.

## Startpakete je Sportart

Auf der Seite **Sportarten** legt ein **Startpaket** eine Sportart in einem Schritt an:
Sportartenprofil, Abteilung, typische Gruppen bzw. Mannschaften und Sportstätten,
dazu je nach Sportart eine Graduierungsordnung (Kampfsport), Schulpferde (Reiten)
oder eine Nachweisanforderung (Schießsport). Elf Sportarten liegen bei: Kampfsport,
Tischtennis, Hockey, Reiten, Fußball, Handball, Basketball, Volleyball, Tennis,
Leichtathletik und Schießsport. Ein Paket ist Konfiguration, kein Sonderfall:
Alles Angelegte lässt sich danach ändern oder löschen, vorhandene Einträge gleichen
Namens bleiben unverändert, und Verbandsregeln oder gesetzliche Schwellen sind
nicht hinterlegt.

Die Musterbranche **Sportverein** der Demo installiert alle elf Pakete und füllt
sie mit erfundenen Personen, Terminen, Anwesenheiten, Beiträgen, Spieltagen,
Wettkämpfen, Prüfungen und Reitstunden.
