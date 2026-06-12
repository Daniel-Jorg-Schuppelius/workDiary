# Zu WorkDiary beitragen

Danke für dein Interesse an WorkDiary. Beiträge in Form von Fehlerberichten,
Dokumentation, Tests und Code sind willkommen. Bitte stimme größere Änderungen
vor der Umsetzung in einem GitHub Issue ab, damit Ziel, Umfang und fachliche
Randbedingungen geklärt sind.

Für alle Beiträge gilt der
[Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md).

## Voraussetzungen

- PHP 8.4 oder neuer mit den in der [README](README.md#voraussetzungen)
  aufgeführten Erweiterungen
- Composer
- Node.js mit npm
- SQLite für die lokale Entwicklung oder eine unterstützte externe Datenbank

## Entwicklungsumgebung einrichten

Repository forken und den eigenen Fork klonen. Danach:

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Anwendung und Frontend können getrennt gestartet werden:

```bash
php artisan serve
npm run dev
```

Alternativ startet `composer dev` den Webserver, Queue-Worker, Log-Viewer,
Vite und Laravel Reverb gemeinsam.

## Änderungen vorbereiten

1. Erstelle einen Branch auf Basis des aktuellen Hauptbranches.
2. Halte den Umfang klein und fachlich zusammenhängend.
3. Ergänze oder aktualisiere Tests für jede Verhaltensänderung.
4. Aktualisiere Dokumentation und den Abschnitt `Unreleased` in
   [CHANGELOG.md](CHANGELOG.md), wenn die Änderung für Anwender relevant ist.
5. Committe keine Zugangsdaten, produktiven Daten, lokalen `.env`-Inhalte,
   Build-Artefakte oder Abhängigkeiten aus `vendor/` und `node_modules/`.

## Code-Konventionen

- Folge den bestehenden Laravel- und Projektmustern.
- Formatiere PHP-Code mit Laravel Pint.
- Halte die Mandantengrenzen bei Queries, Autorisierung, Jobs, Events und
  Exporten strikt ein.
- Prüfe Zugriffe über Policies und Berechtigungen; Sichtbarkeit in der
  Oberfläche ersetzt keine serverseitige Autorisierung.
- Lege Datenbankänderungen als vorwärtskompatible Migrationen an. Bestehende
  Migrationen werden nachträglich nicht verändert.
- Verwende Laravel-Übersetzungen für sichtbare Texte. Änderungen an
  Übersetzungen müssen über alle gepflegten Sprachen konsistent sein.
- Beachte bei Blade- und UI-Änderungen die verbindlichen Vorgaben in
  [.github/copilot-instructions.md](.github/copilot-instructions.md).
- Füge neue Abhängigkeiten nur hinzu, wenn der Nutzen den zusätzlichen
  Wartungs- und Sicherheitsaufwand rechtfertigt. Aktualisiere das zugehörige
  Lockfile.

## Tests und Qualitätsprüfungen

Führe während der Entwicklung zunächst die betroffenen Tests aus:

```bash
php artisan test tests/Feature/Pfad/ZumTest.php
php artisan test --filter=Testname
```

Vor einem Pull Request müssen mindestens diese Prüfungen erfolgreich sein:

```bash
composer format
composer lint
composer test
composer lang:check
composer lang:coverage
npm run build
```

Der vollständige PHP-Prüflauf kann mit folgendem Befehl gestartet werden:

```bash
composer qa
```

Feature-Tests verwenden die in `phpunit.xml` konfigurierte SQLite-Datenbank.
Neue datenbankgestützte Tests sollten dem vorhandenen Muster mit
`RefreshDatabase` folgen. Änderungen an mandantenbezogenem Verhalten benötigen
auch Negativtests, die Zugriffe aus einer anderen Organisation ausschließen.

## Pull Requests

Ein Pull Request sollte:

- das Problem und die gewählte Lösung knapp beschreiben,
- das zugehörige Issue verlinken,
- Risiken, Migrationen und notwendige Konfigurationsänderungen nennen,
- die ausgeführten Tests und Qualitätsprüfungen aufführen,
- bei visuellen Änderungen aussagekräftige Screenshots enthalten,
- keine sachfremden Formatierungen oder Refactorings enthalten.

Reviews können Änderungen an Implementierung, Tests, Dokumentation oder
Abwärtskompatibilität verlangen. Bitte löse Review-Kommentare nachvollziehbar
auf und halte den Branch konfliktfrei.

## Fehler und Sicherheitslücken melden

Fehlerberichte sollten reproduzierbare Schritte, erwartetes und tatsächliches
Verhalten sowie relevante Versions- und Umgebungsangaben enthalten. Entferne
personenbezogene Daten, Zugangsdaten und vertrauliche Kundeninformationen aus
Logs und Screenshots.

Sicherheitslücken dürfen nicht in öffentlichen Issues veröffentlicht werden.
Nutze, sofern im Repository verfügbar, GitHubs private
Sicherheitsberichterstattung oder kontaktiere die Maintainer vertraulich.

## Lizenz

Mit einem Beitrag erklärst du dich damit einverstanden, dass dein Beitrag unter
der Projektlizenz [GNU AGPL v3 oder später](LICENSE) veröffentlicht wird und
dass du zur Einreichung des Beitrags berechtigt bist.
