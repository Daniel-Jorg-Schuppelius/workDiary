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

```bash
mkdir -p storage/app/geoip
curl -fsSL -o storage/app/geoip/dbip-city-lite.mmdb.gz \
  "https://download.db-ip.com/free/dbip-city-lite-$(date +%Y-%m).mmdb.gz"
gunzip -f storage/app/geoip/dbip-city-lite.mmdb.gz
```

Dann in der `.env` (Pfad absolut):

```ini
GEOIP_DATABASE=/pfad/zur/app/storage/app/geoip/dbip-city-lite.mmdb
GEOIP_LOCALE=de
```

Prüfen (`config:clear` nicht vergessen, falls Config gecacht):

```bash
php artisan tinker --execute='var_export(app(\App\Services\Security\IpGeoResolver::class)->label("8.8.8.8"));'
# → "Mountain View, Vereinigte Staaten von Amerika"
```

## Monatliche Aktualisierung

DB-IP veröffentlicht monatlich eine neue Lite-Ausgabe (Dateiname trägt den
Monat). Cron-Beispiel — **innerhalb der Server-Betriebszeit** planen (auf
Servern, die nicht 24/7 laufen, holt Cron nichts nach):

```cron
# /etc/cron.d/workdiary-geoip — am 3. des Monats 22:30, Download atomar
30 22 3 * * www-data curl -fsSL -o /tmp/dbip.mmdb.gz "https://download.db-ip.com/free/dbip-city-lite-$(date +\%Y-\%m).mmdb.gz" && gunzip -f /tmp/dbip.mmdb.gz && mv /tmp/dbip.mmdb /pfad/zur/app/storage/app/geoip/dbip-city-lite.mmdb
```

Der Reader öffnet die Datei je Prozess neu — ein `mv` (atomar) genügt,
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
