# GeoIP-Datenbank einrichten (Standortanzeige & Impossible Travel)

Zwei Funktionen lösen IP-Adressen **lokal** gegen eine `.mmdb`-Datenbank auf —
es findet kein externer Netzwerk-Call zur Laufzeit statt:

- **Sitzungs-Standortanzeige** (Feature 085): Land/Stadt in der
  Sitzungsübersicht und in Neues-Gerät-Benachrichtigungen.
- **Impossible-Travel-Erkennung** (MVP-449): Reisegeschwindigkeit zwischen
  zwei Logins; unplausible Ortswechsel erzeugen ein Security-Event und
  benachrichtigen Nutzer und Plattform-Admins.

Ohne Datenbank degradieren beide still: Es wird nur die IP angezeigt, die
Travel-Prüfung ruht. Es gibt keine Fehlfunktion — aber auch keinen Schutz.

## Bezugsquelle

| Quelle | Bezug | Lizenz |
| --- | --- | --- |
| **DB-IP City Lite** (empfohlen) | Direkt-Download ohne Account: `https://download.db-ip.com/free/dbip-city-lite-JJJJ-MM.mmdb.gz` | **CC BY 4.0 — Attribution erforderlich** (s. u.) |
| MaxMind GeoLite2 City | Account + Lizenzschlüssel, Bezug via `geoipupdate` | GeoLite2-EULA (Redistribution eingeschränkt) |

Beide nutzen dasselbe `.mmdb`-Format; die App erkennt beide automatisch.
Die Datei gehört **nicht** ins Repository (Lizenz + Aktualität).

## Einrichtung

In der `.env` den Zielpfad setzen (absolut) und die monatliche Aktualisierung
einschalten:

```ini
GEOIP_DATABASE=/pfad/zur/app/storage/app/geoip/dbip-city-lite.mmdb
GEOIP_LOCALE=de
GEOIP_AUTO_UPDATE=true
```

Dann die Datenbank einmal holen (`config:clear` nicht vergessen, falls Config
gecacht) — der Befehl legt das Verzeichnis an, lädt die Datei des laufenden
Monats (sonst des Vormonats), prüft sie und legt sie ab:

```bash
php artisan security:geoip-update --force
```

Prüfen — die Auflösung liegt im common-toolkit (`IpLocationHelper`), nicht in
einer App-Klasse (nachgeführt am 2026-09-16, `MVP-796`, Befund `P12-40`):

```bash
php artisan tinker --execute='var_export(\CommonToolkit\Helper\Geo\IpLocationHelper::lookup("8.8.8.8"));'
# → array('country' => 'Vereinigte Staaten von Amerika', 'country_iso' => 'US', 'city' => 'Mountain View')
```

## Monatliche Aktualisierung

DB-IP veröffentlicht monatlich eine neue Lite-Ausgabe (Dateiname trägt den
Monat). Seit `MVP-1021` übernimmt das der Scheduler-Job
`security.geoip_update` (Befehl `security:geoip-update`, am 3. des Monats
04:30, verschiebbar unter Administration → Geplante Aufgaben). Er läuft nur
mit `GEOIP_AUTO_UPDATE=true` und

- lädt die Datei des laufenden Monats, bei 404 die des Vormonats — ist die
  installierte Datei schon so aktuell, lädt er nichts;
- entpackt im Datenstrom neben die Zieldatei, prüft sie mit dem Reader (eine
  Probeadresse muss einen Standort liefern) und tauscht sie per `rename`
  atomar aus;
- lässt bei jeder Störung (HTTP-Fehler, kaputtes Archiv, keine gültige
  Datenbank) die laufende Datei unberührt und meldet den Fehler im
  Job-Protokoll.

Die Diagnose-Seite zeigt den Stand der Datei (`geoip_built_at`) und warnt,
wenn die Aktualisierung eingeschaltet ist, die Datei aber älter als 70 Tage
ist. Wer die Datei lieber selbst pflegt, lässt den Schalter aus und nutzt
einen eigenen Cron — **innerhalb der Server-Betriebszeit** (auf Servern, die
nicht 24/7 laufen, holt Cron nichts nach):

```cron
# /etc/cron.d/workdiary-geoip — am 3. des Monats 22:30, Download atomar
30 22 3 * * www-data curl -fsSL -o /tmp/dbip.mmdb.gz "https://download.db-ip.com/free/dbip-city-lite-$(date +\%Y-\%m).mmdb.gz" && gunzip -f /tmp/dbip.mmdb.gz && mv /tmp/dbip.mmdb /pfad/zur/app/storage/app/geoip/dbip-city-lite.mmdb
```

Der Reader öffnet die Datei je Prozess neu — ein atomarer Austausch genügt,
Dienste müssen nicht neu gestartet werden. Ein verpasster Monat ist
unkritisch (die Daten altern nur langsam), die Prüfung läuft mit dem
letzten Stand weiter.

## Attribution (Pflicht bei DB-IP Lite)

Die Lite-Datenbank steht unter **CC BY 4.0**: Der Betreiber muss die Quelle
in zumutbarer Weise nennen. Empfohlen: im Impressum bzw. auf der
Datenschutzseite der Installation den Hinweis

> IP-Geolokalisierung: [DB-IP](https://db-ip.com) — Daten unter CC BY 4.0.

aufnehmen. Bei MaxMind GeoLite2 gilt stattdessen deren EULA (keine
CC-Attribution, dafür Konto-Bindung und Weitergabe-Beschränkungen).

## Datenschutz

Die Auflösung erfolgt vollständig lokal (keine IP verlässt den Server);
gespeichert werden nur grobe Koordinaten auf Stadt-Ebene an bekannten
Geräten (`user_known_devices.latitude/longitude`) sowie Ortslabels in
Security-Events. Der monatliche Download ist der einzige externe Zugriff
(er überträgt keine Nutzerdaten). VVT-Hinweis: siehe
Datenschutzmodul-Eintrag zur Angriffserkennung (MVP-445).
