# Lizenzierte Module organisationsbezogen aktivieren und deaktivieren

Status: Umgesetzt (MVP-052) • Quelle:
[Feature 021 — Tarife / Lizenzportal / Abrechnung](features/021-tarife-lizenzportal-abrechnung.md).
• Aufbauend auf:
[Lizenzstatus und Feature-Flags](lizenz-admin.md) (MVP-047).

## 1. Zweck

Nicht jede Organisation benötigt den gesamten lizenzierten Funktionsumfang.
Org-Admins sollen deshalb lizenzierte Module gezielt ausblenden können, damit
Navigation, Dashboards, Suche und Arbeitsabläufe nur die tatsächlich verwendeten
Bereiche zeigen.

Dabei gilt eine harte Trennung:

- Die **Lizenz** definiert, welche Module maximal genutzt werden dürfen.
- Die **Modulkonfiguration** definiert, welche dieser lizenzierten Module die
  Organisation aktuell verwenden möchte.
- Eine lokale Konfiguration darf niemals ein nicht lizenziertes Modul
  freischalten.

## 2. Zustandsmodell

Jedes Modul hat für eine Organisation genau einen fachlichen Zustand:

| Zustand              | Bedeutung                                                       |
| -------------------- | --------------------------------------------------------------- |
| `notLicensed`        | Nicht von Plan, Lizenz oder Add-on umfasst; nicht aktivierbar.   |
| `active`             | Lizenziert und von der Organisation aktiviert.                  |
| `inactiveByCustomer` | Lizenziert, aber vom Org-Admin bewusst deaktiviert.              |
| `blocked`            | Lizenziert, aber durch Lizenz-/Mandantenstatus temporär gesperrt. |

`inactiveByCustomer` ist kein Downgrade:

- vorhandene Daten bleiben unverändert erhalten,
- Aufbewahrungs- und Löschfristen laufen unverändert weiter,
- es wird keine Downgrade-Karenz und keine automatische Bereinigung gestartet,
- erneutes Aktivieren stellt den Zugriff sofort wieder her.

Core-Funktionen wie Anmeldung, Benutzerkonto, persönliche Zeiterfassung,
Administration der Organisation und Lizenzverwaltung sind nicht deaktivierbar.

## 3. Single Source of Truth

Der bestehende Modulkatalog in `config/plans.php` bleibt die technische Quelle
für Modulcodes, Labels und Routenzuordnung. Die effektive Berechtigung wird in
dieser Reihenfolge ermittelt:

1. Plan und signierte Lizenz bestimmen die lizenzierten Module.
2. Gebuchte Add-ons erweitern die Lizenzmenge.
3. Organisationsbezogene Disable-Overrides dürfen die Lizenzmenge nur
   einschränken.
4. Lizenz-, Mandanten- oder Grace-Status kann ein Modul zusätzlich blockieren.

Die vorhandene Tabelle `license_flag_overrides` und der
`FeatureFlagResolver` werden weiterverwendet. Für die UI wird ein eindeutiger
Modulstatus benötigt, der Lizenzquelle und lokale Deaktivierung getrennt
ausweist; ein einzelnes boolesches `enabled` reicht für verständliche Meldungen
nicht aus.

## 4. Admin-Oberfläche

Die Modulkonfiguration wird als eigener Abschnitt der bestehenden Seite
`GET /admin/license` umgesetzt.

Pro Modul zeigt eine kompakte Karte oder Tabellenzeile:

- lesbaren Namen und kurze deutsche Beschreibung,
- Lizenzquelle: Plan, Add-on oder Einzelfreischaltung,
- aktuellen Zustand gemäß §2,
- Schalter „Aktiv“ nur für lizenzierte, konfigurierbare Module,
- bei `notLicensed` einen neutralen Hinweis „Nicht lizenziert“ statt eines
  bedienbaren Schalters,
- bei `blocked` den konkreten Grund ohne irreführende Aktivierungsaktion.

Das Ändern erfolgt modal-first. Vor dem Deaktivieren nennt der Dialog die
sichtbaren Auswirkungen und stellt ausdrücklich klar, dass keine Daten gelöscht
werden. Eine optionale interne Begründung wird im Audit protokolliert.

Permission:

| Permission                        | Wer                                      |
| --------------------------------- | ---------------------------------------- |
| `platform.license.view`           | Org-Admin, Plattform-Admin               |
| `platform.featureFlag.override`   | Org-Admin der eigenen Organisation, Plattform-Admin |

Ein Org-Admin darf ausschließlich Overrides seiner aktuellen Organisation
ändern. Plattformweite Overrides bleiben dem Plattform-Admin vorbehalten.

## 5. Wirkung einer Deaktivierung

Ein deaktiviertes Modul verschwindet konsequent aus:

- Haupt- und Admin-Navigation,
- Dashboard-Widgets und Schnellaktionen,
- globaler Suche und Suchvorschlägen,
- Onboarding-Schritten,
- kontextbezogener In-App-Hilfe und Help-Drawer,
- modulbezogenen Auswahlfeldern und Querverlinkungen.

Direktaufrufe, Bookmarks und API-Aufrufe werden weiterhin serverseitig gesperrt.
Die Fehlermeldung unterscheidet zwischen „nicht lizenziert“ und „von Ihrer
Organisation deaktiviert“. Die UI-Ausblendung allein ist niemals die
Zugriffskontrolle.

Auch nicht sichtbare Verarbeitung muss das Gate beachten:

- geplante Jobs und Commands,
- Queue-Jobs sowohl beim Dispatch als auch vor der Ausführung,
- Listener, Benachrichtigungen und automatische Exporte,
- API-Endpunkte, Webhooks und Integrations-Synchronisationen.

Eine laufende Transaktion wird nicht mitten in der Verarbeitung abgebrochen.
Bereits eingequeue-te Jobs prüfen den Modulstatus vor ihrer fachlichen Wirkung
und beenden sich nachvollziehbar ohne Datenänderung.

## 6. Abhängigkeiten und Konsistenz

- Modul-Gates verwenden ausschließlich stabile Codes `module.*`.
- Neue Modulrouten müssen in `config/plans.php` vollständig zugeordnet werden.
- Neue Navigation, Widgets, Suche, Hilfe und Hintergrundverarbeitung müssen
  dasselbe zentrale Gate verwenden.
- Permissions bleiben zusätzlich wirksam: Modul aktiv bedeutet nicht
  automatisch, dass ein Benutzer darauf zugreifen darf.
- Per-User-Ausblendung wird nicht über die Modulkonfiguration gelöst, sondern
  weiterhin über Rollen und Rechte.

Falls ein Modul fachlich von einem anderen Modul abhängt, muss diese Abhängigkeit
im Modulkatalog explizit beschrieben werden. Das abhängige Modul darf nicht in
einen inkonsistenten aktiven Zustand gelangen.

## 7. Audit und Diagnose

Folgende Ereignisse werden mit Organisation, Benutzer, Modulcode, Zeitpunkt und
optionaler Begründung protokolliert:

- `license.moduleDisabled`
- `license.moduleEnabled`

Lizenzschlüssel, Signaturen und andere Geheimnisse gehören nicht in das Audit.

Diagnose- und Komponentenseite zeigen für jedes Modul getrennt:

- lizenziert: ja/nein,
- organisationsbezogen aktiviert: ja/nein,
- effektiv verfügbar: ja/nein,
- Sperrgrund, falls nicht verfügbar.

## 8. Akzeptanzkriterien

1. Ein Org-Admin kann jedes lizenzierte, konfigurierbare Modul für die eigene
   Organisation deaktivieren und wieder aktivieren.
2. Nicht lizenzierte Module können weder über UI noch Request-Manipulation
   aktiviert werden.
3. Deaktivierte Module fehlen in Navigation, Dashboard, Suche, Onboarding und
   In-App-Hilfe.
4. Direkte HTML- und API-Aufrufe eines deaktivierten Moduls werden serverseitig
   mit unterscheidbarer deutscher Meldung gesperrt.
5. Modulbezogene Hintergrundverarbeitung erzeugt nach der Deaktivierung keine
   neue fachliche Wirkung.
6. Deaktivieren löscht oder verändert keine bestehenden Moduldaten und startet
   keine Downgrade-Karenz.
7. Reaktivieren stellt Navigation und Zugriff ohne Datenmigration wieder her.
8. Änderungen sind vollständig organisationsbezogen, berechtigungsgeprüft und
   auditierbar.
9. Ein Architekturtest oder eine zentrale Abdeckungsprüfung erkennt
   Modulrouten ohne Katalogzuordnung sowie inkonsistente Modulcodes.
10. Tests decken mindestens zwei Organisationen, Manipulationsversuche,
    Direktaufrufe, Navigation/Suche/Hilfe und einen bereits eingequeue-ten Job
    ab.

## 9. Out-of-scope (MVP-052)

- Kauf, Kündigung oder Erweiterung einer Lizenz.
- Preisberechnung, Rechnungsstellung und Self-Service-Lizenzportal.
- Aktivierung nicht lizenzierter Module als Testphase.
- Per-User-Modulschalter zusätzlich zu Rollen und Rechten.
- Deinstallation von Code oder Datenbanktabellen.
- Automatische Löschung von Daten bei Deaktivierung.
- Individuelle Unterfunktionen innerhalb eines Moduls; diese bleiben
  technische Feature-Flags oder Permissions.

## 10. Umsetzungsreihenfolge

1. Fachlichen Modulstatus als zentrale, getestete Auflösung ergänzen.
2. Bestehende Override-UI auf vollständigen Modulkatalog und Org-Scope
   ausrichten.
3. Navigation, Dashboard, Suche, Onboarding und Hilfe gegen denselben Status
   prüfen.
4. Hintergrundprozesse und Integrationen absichern.
5. Audit, Diagnose und Abdeckungs-/Architekturtests ergänzen.
