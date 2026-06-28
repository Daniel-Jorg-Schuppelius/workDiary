# Toolkit-Nutzung und Konsolidierung

## Status

Planned — technischer P0-Querschnitt als `MVP-102`. Die bestehende Anwendung
wird systematisch auf app-lokale Implementierungen geprüft, die bereits durch
die eigenen Toolkits abgedeckt sind oder als allgemein wiederverwendbare
Funktion in das passende Toolkit gehören.

## Ziel

WorkDiary soll die vorhandenen eigenen Toolkits konsequent nutzen, ohne
Geschäftslogik aus der Anwendung in generische Pakete zu verschieben. Dadurch
werden parallele Implementierungen, abweichendes Verhalten und unnötige
Wartung in der Anwendung reduziert.

Das MVP ist kein pauschaler Großrefactor. Jeder Fund wird nachvollziehbar
klassifiziert, einzeln migriert und durch bestehende beziehungsweise ergänzte
Tests abgesichert.

## Verbindliche Toolkit-Reihenfolge

Vor einer app-lokalen Hilfsfunktion wird in dieser Reihenfolge geprüft:

1. `dschuppelius/php-common-toolkit` für generische Daten-, String-, Datums-,
   Zahlen-, JSON-, XML-, Validierungs-, Datei-, Prozess-, CSV-/XLSX- und
   Konvertierungsfunktionen;
2. `daniel-jorg-schuppelius/php-pdf-toolkit` für PDF-Verarbeitung;
3. `daniel-jorg-schuppelius/php-erechnung-toolkit` für XRechnung, CII und
   ZUGFeRD;
4. `daniel-jorg-schuppelius/datev-php-sdk` und
   `daniel-jorg-schuppelius/lexoffice-php-sdk` für die jeweiligen APIs;
5. die transitiv verfügbaren API-, Config- und Error-Toolkits für deren
   Infrastrukturaufgaben;
6. `daniel-jorg-schuppelius/php-financial-formats` ausschließlich optional und
   über `App\Services\Finance\FinancialFormatsSupport` gegatet.

Erst wenn dort keine geeignete Funktion existiert, darf eine neue
Implementierung entstehen. Ist sie fachneutral und auch außerhalb WorkDiary
sinnvoll, wird zuerst das passende Toolkit erweitert, getestet und
veröffentlicht. WorkDiary aktualisiert anschließend die Paketversion und
ersetzt die lokalen Aufrufstellen.

## Prüfumfang

Geprüft werden produktive PHP-Pfade in `app/`, `routes/`, `config/`,
`database/` und `scripts/`. `app/Legacy/`, Vendor-Code und generierter Code
bleiben unverändert. Tests werden als Beleg und zum Erkennen duplizierter
Test-Helper einbezogen, aber nicht allein wegen ähnlicher Test-Fixtures
umgebaut.

Die Bestandsaufnahme sucht insbesondere nach:

- selbst implementierter String-, Datums-, Zahlen-, Währungs-, Einheiten-,
  Länder-, Steuer-, Bank-, E-Mail-, Telefon- und Adressvalidierung;
- app-lokalen JSON-, XML-, CSV-, XLSX-, PDF- und E-Rechnungs-Parsern,
  -Buildern, -Generatoren und -Formatierern;
- wiederholten Datei-, Pfad-, Hash-, Verschlüsselungs- und
  Prozess-/Shell-Hilfen;
- direkter HTTP-/API-Infrastruktur, die vorhandene SDK- oder
  API-Toolkit-Abstraktionen dupliziert;
- lokalen Enums oder Normalisierungen, für die bereits passende typed Enums
  existieren;
- mehrfach vorkommenden privaten Methoden und Services mit identischer
  fachneutraler Logik.

## Fundklassen und Entscheidung

Jeder Fund erhält genau eine Entscheidung:

| Klasse | Bedeutung | Folge |
| ------ | --------- | ----- |
| A | Toolkit wird bereits passend genutzt | Belegen, keine Änderung |
| B | Vorhandene Toolkit-Funktion wird lokal dupliziert | Alle produktiven Aufrufstellen migrieren und Duplikat entfernen |
| C | Toolkit-Funktion ist fachneutral, fehlt aber | Im passenden Toolkit ergänzen, testen, releasen und danach in WorkDiary nutzen |
| D | Logik ist WorkDiary-spezifische Geschäftsregel | In der Anwendung belassen; generische Teilschritte dürfen delegiert werden |
| E | Optionales Toolkit oder installationsabhängige Fähigkeit | Guard und degradierendes Verhalten erhalten oder ergänzen |
| F | Unklarer oder riskanter Ersatz | Nicht automatisch ändern; Entscheidung mit Tests und Begründung vorbereiten |

Namensähnlichkeit allein rechtfertigt keine Migration. Verhalten,
Fehlersemantik, Zeitzone, Locale, Rundung, Encoding, Sicherheitsgrenzen und
Rückwärtskompatibilität müssen vor dem Ersatz verglichen werden.

## Umsetzung

### 1. Reproduzierbare Bestandsaufnahme

- Installierte direkte und transitive Toolkit-Versionen aus Composer erfassen.
- Produktive Kandidaten nach Domäne und Fundklasse inventarisieren.
- Für jeden Kandidaten aktuelle Aufrufstellen, vorhandene Tests, Ziel-API,
  Risiko und Entscheidung festhalten.
- Bewusst app-spezifische Logik und akzeptierte Ausnahmen ausdrücklich
  kennzeichnen, damit sie nicht bei jedem Lauf erneut als offener Fund gilt.

### 2. Migration in kleinen Slices

Die Migration erfolgt in getrennten, überprüfbaren Änderungen:

1. reine Helper und Validatoren;
2. Parser, Formatter, Builder und Generatoren;
3. Datei-, PDF-, XML-/CSV- und Prozessoperationen;
4. SDK-, API- und Infrastrukturcode;
5. verbleibende Kandidaten mit höherem fachlichem Risiko.

Ein Slice ersetzt alle bekannten duplizierten produktiven Aufrufstellen.
Kompatibilitätswrapper sind nur zeitlich begrenzt zulässig und erhalten einen
klaren Entfernungsschritt.

### 3. Fehlende Toolkit-Funktionen

Eine neue fachneutrale Funktion wird nicht zuerst in WorkDiary eingebaut.
Sie erhält im zuständigen Toolkit:

- eine kleine, frameworkunabhängige API;
- Tests für Normal-, Rand- und Fehlerfälle;
- eine dokumentierte Fehler- und Rückgabesemantik;
- einen Release beziehungsweise eine für WorkDiary auflösbare Version.

Erst danach wird die Composer-Abhängigkeit in WorkDiary aktualisiert. Lokale
Pfad-Repositories oder unveröffentlichte Paketstände werden nicht in den
Produktionspfad aufgenommen.

### 4. Dauerhafte Absicherung

- Ersetzte lokale Implementierungen und ihre alten Aufrufstellen werden
  entfernt.
- Neue Architekturtests oder statische Regeln decken nur belastbar erkennbare
  Duplikatmuster ab; semantische Entscheidungen bleiben Review-Aufgabe.
- Die Pull-Request-Checkliste verlangt bei neuen Parsern, Validatoren,
  Formattern und Helfern die Prüfung der vorhandenen Toolkits.
- Änderungen am optionalen Finanzformat-Modul bewahren
  `FinancialFormatsSupport`, das Verhalten ohne Paket und die
  `ComposerLockHygieneTest`-Absicherung.

## Abgrenzung

- Domänenregeln, Policies, Mandantengrenzen, Laravel-Orchestrierung und
  WorkDiary-spezifische Workflows werden nicht in generische Toolkits
  verschoben.
- Das MVP ersetzt keine bewährte Implementierung nur wegen eines ähnlich
  benannten Toolkit-APIs.
- `app/Legacy/` wird nicht modernisiert.
- Das private `php-financial-formats` wird weder verpflichtend noch in die
  committete `composer.lock` aufgenommen.
- Toolkit-Repositories und WorkDiary erhalten getrennte, nachvollziehbare
  Änderungen; Paketänderungen werden vor der App-Migration veröffentlicht.

## MVP-Issue

- `MVP-102`: Nutzung der eigenen Toolkits repo-weit prüfen, Funde
  klassifizieren, geeignete lokale Implementierungen durch Toolkit-APIs
  ersetzen und fehlende fachneutrale Funktionen im zuständigen Toolkit
  ergänzen.

## Definition of Done

- Der gesamte definierte Prüfumfang ist inventarisiert; jeder Fund besitzt
  Fundklasse, Entscheidung, Begründung und Status.
- Alle bestätigten Klasse-B-Funde sind an sämtlichen produktiven
  Aufrufstellen migriert und die lokalen Duplikate entfernt.
- Bestätigte Klasse-C-Funde sind im passenden Toolkit getestet und
  veröffentlicht; WorkDiary nutzt eine regulär auflösbare Paketversion.
- Klasse-D-, -E- und -F-Entscheidungen sind begründet und besitzen bei
  sicherheits- oder fachkritischem Verhalten passende Regressionstests.
- Verhalten zu Locale, Zeitzone, Rundung, Encoding, Fehlerfällen und
  Rückwärtskompatibilität ist für jede Migration geprüft.
- WorkDiary funktioniert weiterhin ohne das optionale
  `php-financial-formats`; die committete `composer.lock` bleibt frei davon.
- Für geänderte Toolkits sind deren eigene Qualitäts-Gates grün. In WorkDiary
  laufen `composer test`, `vendor/bin/phpstan analyse` und `vendor/bin/pint`
  erfolgreich.

