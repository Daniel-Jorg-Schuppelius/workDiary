---
title: "Prozedur-Designer"
topic: procedures.designer
version: 4
keywords:
    - Arbeitsanweisung
    - Checkliste erstellen
    - SOP
    - Standardarbeitsanweisung
    - Ablaufvorlage
    - Prozessvorlage
    - Workflow
    - Pflichtschritte
    - Vier-Augen-Prinzip
    - Bedingung wenn dann
    - Version veröffentlichen
audience: []
related:
    - procedures.run
---

Im **Prozedur-Designer** legen Sie verbindliche Abläufe (Arbeitsanweisungen,
Checklisten) an, die später auf Aufträgen ausgeführt werden. Die Vorlagen
finden Sie unter **System** → **Regeln & Prozesse** → **Prozedurvorlagen**;
**Bearbeiten** öffnet den Designer einer Vorlage.

## Vorlage und Versionen

- Eine **Vorlage** (**Vorlage anlegen**) hat einen eindeutigen **Code**,
  einen **Namen**, einen optionalen **Bereich** (z. B. `it`, `hvac`) und
  eine **Beschreibung**; im Designer kommt die **Risikostufe** hinzu.
- Schritte gehören immer zu einer **Version**. Solange eine Version ein
  **Entwurf** ist, können Sie Schritte frei bearbeiten und mit
  **Speichern** sichern; eine **Änderungsnotiz** hält fest, was sich
  geändert hat.
- Mit **Veröffentlichen** wird die Version gültig gesetzt und
  **unveränderlich**. Korrekturen erfordern eine **Neue Version** –
  laufende und alte Aufträge behalten ihre damalige Version.

## Schritte

Mit **Schritt hinzufügen** oder **Aus Bibliothek einfügen** (aus der
**Schrittbibliothek**) ergänzen Sie Schritte. Jeder Schritt hat einen
**Code**, eine **Bezeichnung**, eine optionale **Beschreibung** und einen
**Typ**, etwa „Bestätigung“, „Text“, „Zahl/Messwert“, „Auswahl“, „Foto“,
„Datei“, „Backup-Nachweis“, „Unterschrift“, „Materialerfassung“,
„Messreihe“, „Freigabe (Vier-Augen)“ oder „Wartezeit“. Zusätzlich
steuerbar:

- **Pflicht**: muss vor Abschluss des Laufs einen finalen Status haben.
- **Sperrend**: blockiert nachfolgende Schritte, bis dieser erledigt ist.
- **Vier-Augen**: verlangt die Gegenzeichnung einer zweiten Person.
- **Nachweis** („Backup“, „Datei“, „Foto“, „Messwert“, „Unterschrift“
  oder „Keiner“) sowie optional **Erforderliche Rolle** und
  **Qualifikation**.
- **Bedingung: Schritt** und **Bedingung: Wert** (wenn-dann): Der Schritt
  wird nur relevant, wenn ein anderer Schritt einen bestimmten Wert hat.

## Konfiguration je Schrittart

Je nach **Typ** zeigt ein Schritt weitere Felder:

- „Auswahl“: **Auswahl-Optionen**, kommagetrennt. Ohne Optionen nimmt der
  Schritt im Lauf freien Text.
- „Zahl/Messwert“ und „Messreihe“: **Einheit**, **Untergrenze** und
  **Obergrenze** (Toleranz).
- „Wartezeit“: **Feste Wartezeit (Sekunden)**; leer wird die Dauer im Lauf
  eingegeben.
- „Backup-Nachweis“: **Umfang**, **Ablage**, **Prüfweg**, **Mindestgröße
  (KB)** und **Höchstalter des Backups (Minuten)**. „Wird im Lauf gewählt“
  überlässt die Angabe der ausführenden Person.
- Jeder andere Schritt: **Vorheriges Backup nötig** sperrt ihn, bis ein
  Backup-Schritt desselben Laufs erledigt und sein Nachweis bestätigt ist,
  auf Wunsch mit Höchstalter.
- Vier-Augen-Schritte: **Rolle der zweiten Person**, **Qualifikation der
  zweiten Person** und **Ausführende Person darf selbst gegenzeichnen**
  (im Regelfall aus).

**Qualifikation** meint das Kürzel einer Qualifikation der Mitarbeitenden,
ersatzweise ihren Namen; geprüft wird, ob die Person sie heute gültig
besitzt. Die **Schrittbibliothek** kennt dieselben Felder unter
**Konfiguration je Schrittart**. Angaben aus Branchenprofilen oder
Importen, die der Designer nicht zeigt, bleiben beim Speichern erhalten.

Die Risikostufe „Kritisch“ verlangt mindestens einen Vier-Augen-Schritt:
Ohne ihn lässt sich die Version nicht veröffentlichen, und ein Lauf wird
erst mit erledigter Vier-Augen-Freigabe abgeschlossen.

## Automatische Zuordnung

Eine Vorlage wird vorgeschlagen, wenn **alle** gesetzten Kriterien passen:
**Auftragstypen**, **Tags**, **Kunden**, **Asset-Kategorien**, **Produkte**
und **SLA-Verträge**. Kategorie, Produkt und SLA beziehen sich auf das
Asset des Auftrags. Ohne Kriterien gilt die Vorlage für jeden Auftrag.

- Auf der Auftragsdetailseite erscheinen passende, veröffentlichte Vorlagen
  in der Karte **Prozeduren** unter „Für diesen Auftrag vorgeschlagene
  Prozeduren:“ als Start-Button.
- Nennt die Vorlage eine Asset-Kategorie, ein Produkt oder einen
  SLA-Vertrag, steht sie auch an der Asset-Akte unter „Für dieses Asset
  vorgesehene Prozeduren:“.
- **Darf an gesperrten Assets laufen** erlaubt den Start an einem
  gesperrten Asset, etwa für Reparatur- und Prüfabläufe.
