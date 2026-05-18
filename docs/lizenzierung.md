# Lizenzierung in workDiary

Diese Dokumentation beschreibt das Aktivierungs- und Lizenzprüfsystem von
workDiary. Sie richtet sich an den **Herausgeber** (der Lizenzen ausstellt)
und an **Administratoren** beim Kunden (die eine erhaltene Lizenz einspielen).

## 1. Konzeptioneller Überblick

Die App wird unter AGPL-3.0 entwickelt, aber kommerziell als gehostete oder
On-Premise-Lösung vertrieben. Um unautorisierte Klone zu erschweren, prüft
jede Instanz beim Start eine signierte Lizenzdatei.

| Baustein                | Aufgabe                                                                                |
| ----------------------- | -------------------------------------------------------------------------------------- |
| Ed25519-Schlüsselpaar   | Asymmetrische Signatur. Private Key bleibt beim Herausgeber, Public Key liegt der App. |
| Lizenzschlüssel         | Base64-kodiertes JSON-Payload + Signatur, ausgestellt pro Kunde.                       |
| `LicenseService`        | Verifiziert Signatur, prüft Domain, Ablauf, Grace-Period.                              |
| `EnsureValidLicense`    | Globale Middleware, sperrt die App ohne gültige Lizenz.                                |
| `LicenseSeal`           | Hartkodierter Public Key + Datei-Hashes zur Manipulationserkennung.                    |
| Sperrseite (`/license`) | Erlaubt dem Admin die Eingabe eines Lizenzschlüssels.                                  |

## 2. Lizenz-Payload

Jede Lizenz transportiert dieses JSON (vor der Signatur):

```json
{
    "license_id": "a1b2c3d4e5f6g7h8",
    "licensee": "Musterfirma GmbH",
    "email": "kontakt@musterfirma.de",
    "issued_at": "2026-05-18T10:00:00+02:00",
    "expires_at": "2027-12-31T23:59:59+01:00",
    "domain": "*.musterfirma.de",
    "max_users": 25,
    "features": ["fleet", "invoicing"]
}
```

| Feld         | Pflicht | Wirkung                                                                |
| ------------ | ------- | ---------------------------------------------------------------------- |
| `license_id` | ✔       | Eindeutige Kennung (8 Byte hex), zufällig generiert.                   |
| `licensee`   | ✔       | Anzeige in `license:show`. Reine Information, nicht verifiziert.       |
| `email`      | —       | Kontaktadresse, rein informativ.                                       |
| `issued_at`  | ✔       | Ausstellungszeitpunkt (ISO-8601).                                      |
| `expires_at` | —       | Ablaufdatum. Ohne Eintrag ist die Lizenz unbefristet.                  |
| `domain`     | —       | Bindet die Lizenz an einen Host. `*.example.com` als Wildcard erlaubt. |
| `max_users`  | —       | Limit, das in der Anwendung gegen die Nutzerzahl geprüft werden kann.  |
| `features`   | —       | Feature-Flags, die Module freischalten oder sperren.                   |

Der finale Lizenzschlüssel hat die Form `base64(payload).base64(signature)`.

## 3. Zustände

`LicenseStatus` kennt folgende Werte:

| Wert                 | Bedeutung                                                              | Zugriff erlaubt? |
| -------------------- | ---------------------------------------------------------------------- | ---------------- |
| `valid`              | Signatur gültig, Domain passt, nicht abgelaufen.                       | ✔                |
| `grace_period`       | Abgelaufen, aber innerhalb der konfigurierten Schonfrist.              | ✔ (Warnbanner)   |
| `expired`            | Abgelaufen, Schonfrist überschritten.                                  | —                |
| `domain_mismatch`    | Aktueller Host passt nicht zur `domain`-Bindung.                       | —                |
| `bad_signature`      | Signatur ungültig (Schlüssel verändert oder falscher Public Key).      | —                |
| `malformed`          | Format-/JSON-Fehler.                                                   | —                |
| `missing`            | Keine Lizenz installiert.                                              | —                |
| `public_key_missing` | Auf der Instanz ist kein Public Key konfiguriert.                      | —                |
| `tampered`           | Eine lizenzrelevante Datei wurde gegenüber dem Sealing-Hash verändert. | —                |

## 4. Workflow für den Herausgeber

### 4.1 Einmalig: Schlüsselpaar erzeugen

```bash
php artisan license:keygen --out=storage/license-keys.env
```

Ergebnis (gekürzt):

```text
LICENSE_PUBLIC_KEY=base64...
LICENSE_PRIVATE_KEY=base64...
```

- **Public Key**: kommt in jede Auslieferung (siehe Abschnitt 6 zum Sealing).
- **Private Key**: bleibt **ausschließlich** beim Herausgeber. Niemals in das
  Repo, niemals auf eine Kundeninstanz, idealerweise in einem Passwort-Manager
  oder einer Vault-Lösung.

### 4.2 Pro Kunde: Lizenz signieren

```bash
LICENSE_PRIVATE_KEY="<base64>" php artisan license:issue \
    --licensee="Musterfirma GmbH" \
    --email="kontakt@musterfirma.de" \
    --domain="*.musterfirma.de" \
    --expires="2027-12-31" \
    --max-users=25 \
    --features=fleet --features=invoicing \
    --out=musterfirma.license
```

Den ausgegebenen String an den Kunden weiterreichen (z. B. per signierter
E-Mail oder Kundenportal).

### 4.3 Vor jedem Release: Versiegeln

```bash
php artisan license:seal --public-key="<base64-public-key>"
git add app/Services/Licensing/LicenseSeal.php
```

Das schreibt den Public Key und SHA-256-Hashes aller lizenzrelevanten Dateien
in [LicenseSeal.php](../app/Services/Licensing/LicenseSeal.php). Ab diesem
Zeitpunkt:

- Wird der Public Key aus `LicenseSeal` verwendet (nicht mehr aus `.env`).
- Wird vor jeder Lizenzprüfung die Datei-Integrität verifiziert.
- Schlägt eine geänderte Lizenzdatei sofort als `tampered` an.

Bei Änderungen am Lizenzcode oder an `config/license.php` muss erneut versie-
gelt werden.

## 5. Workflow beim Kunden

### 5.1 Lizenz über die Sperrseite einspielen

Beim ersten Aufruf erscheint die Sperrseite unter `/license`. Der Admin fügt
den vollständigen Schlüssel ein und klickt "Lizenz aktivieren". Die Lizenz
wird verschlüsselt nach `storage/app/license.key` geschrieben.

### 5.2 Lizenz per CLI installieren

```bash
php artisan license:install --file=musterfirma.license
# oder
php artisan license:install --key="base64payload.base64signature"
```

### 5.3 Lizenz in der `.env` ablegen (SaaS-Variante)

Für vom Herausgeber selbst betriebene Instanzen reicht:

```env
LICENSE_KEY="base64payload.base64signature"
```

`config:clear` ausführen, damit der Cache neu gefüllt wird.

### 5.4 Status prüfen

```bash
php artisan license:show
```

Zeigt Lizenznehmer, Ablaufdatum, Domain, Features und ob die Signatur gültig
ist.

## 6. Sealing-Schutz

Ohne Sealing reicht es, den Public Key in der `.env` zu ersetzen, um eigene
Lizenzen einzuschleusen. Mit Sealing schützt sich die App auf zwei Ebenen:

1. **Public Key fix**: `LicenseSeal::PUBLIC_KEY` ist eine PHP-Konstante. Eine
   Änderung der `.env` hat keine Wirkung mehr.
2. **Datei-Hashes**: SHA-256 über
   [LicenseService.php](../app/Services/Licensing/LicenseService.php),
   [EnsureValidLicense.php](../app/Http/Middleware/EnsureValidLicense.php),
   [LicenseController.php](../app/Http/Controllers/LicenseController.php),
   [config/license.php](../config/license.php) sowie über `LicensePayload`,
   `LicenseResult`, `LicenseStatus`. Stimmt einer der Hashes nicht, setzt der
   `LicenseService` den Status auf `tampered`.

Tampering wird **vor** den Bypass-Pfaden geprüft – selbst `/login` und `/up`
sind dann gesperrt. Ein normaler Patch reicht nicht mehr; ein Angreifer
müsste zusätzlich `LicenseSeal::FILES` neu schreiben und den Integritätscheck
deaktivieren.

### 6.1 Sealing zurücksetzen

Für Entwicklung oder Tests:

```bash
php artisan license:seal --unseal
```

Setzt `PUBLIC_KEY` und `FILES` auf leer; die App fällt wieder auf die
`.env`-Konfiguration zurück.

## 7. Entwicklungs- und Test-Bypass

Beim Entwickeln will man nicht jedes Mal eine Lizenz einspielen. Es gibt
zwei Stellschrauben in [config/license.php](../config/license.php):

| Schalter                | Wirkung                                                                                                     |
| ----------------------- | ----------------------------------------------------------------------------------------------------------- |
| `LICENSE_ENFORCE=false` | Lizenzprüfung global deaktivieren (komplettes Aus).                                                         |
| `LICENSE_DEV_HOSTS`     | Komma-Liste von Hosts, die ohne Lizenz nutzbar sind. Wildcards via `*`. Default: `127.0.0.1,localhost,::1`. |
| `LICENSE_DEV_HOST_ENVS` | Komma-Liste der `APP_ENV`-Werte, in denen `LICENSE_DEV_HOSTS` greift. Default: `local,testing,development`. |

Wichtig: In `APP_ENV=production` wird die Lizenz auch auf `127.0.0.1`
erzwungen, damit ein falsch konfigurierter Reverse-Proxy keine Hintertür
öffnet.

## 8. Konfiguration

Vollständige Liste der `.env`-Variablen:

| Variable                | Default                     | Bedeutung                                                                               |
| ----------------------- | --------------------------- | --------------------------------------------------------------------------------------- |
| `LICENSE_PUBLIC_KEY`    | —                           | Public Key zur Signaturprüfung. Wird ignoriert, wenn `LicenseSeal::PUBLIC_KEY` gesetzt. |
| `LICENSE_KEY`           | —                           | Optionaler Lizenzschlüssel direkt in der `.env` (SaaS).                                 |
| `LICENSE_KEY_PATH`      | `license.key`               | Pfad zur On-Premise-Lizenzdatei (relativ zu `storage/app`).                             |
| `LICENSE_GRACE_DAYS`    | `14`                        | Tage Schonfrist nach Ablauf. `0` = harte Sperre.                                        |
| `LICENSE_ENFORCE`       | `true`                      | Lizenzprüfung global an/aus.                                                            |
| `LICENSE_CACHE_TTL`     | `300`                       | Cache-Dauer in Sekunden für das verifizierte Lizenzobjekt.                              |
| `LICENSE_DEV_HOSTS`     | `127.0.0.1,localhost,::1`   | Komma-Liste, Wildcards via `*`. Greift nur in `LICENSE_DEV_HOST_ENVS`.                  |
| `LICENSE_DEV_HOST_ENVS` | `local,testing,development` | Liste der `APP_ENV`-Werte, in denen der Dev-Host-Bypass aktiv ist.                      |

## 9. Artisan-Befehle

| Befehl            | Zweck                                                                           |
| ----------------- | ------------------------------------------------------------------------------- |
| `license:keygen`  | Erzeugt ein Ed25519-Schlüsselpaar.                                              |
| `license:issue`   | Stellt einen signierten Lizenzschlüssel aus (benötigt Private Key).             |
| `license:install` | Spielt eine Lizenz auf der laufenden Instanz ein.                               |
| `license:show`    | Zeigt Status, Lizenznehmer, Ablauf, Features.                                   |
| `license:seal`    | Versiegelt Public Key + Datei-Hashes in `LicenseSeal`. `--unseal` setzt zurück. |

## 10. Bedrohungsmodell

Was das System leistet:

- Verhindert "wilde Kopien" durch normale Anwender (Code kopieren, woanders
  installieren reicht nicht – ohne signierte Lizenz keine App).
- Erkennt Manipulation der Lizenzdateien nach dem Sealing.
- Verhindert simples Umgehen über die `.env` (`LICENSE_PUBLIC_KEY` ersetzen).

Was es **nicht** leistet:

- Schutz gegen Reverse Engineering. Mit Codezugriff lässt sich jeder PHP-
  Check theoretisch entfernen. Für echten Bytecode-Schutz wäre ein
  kommerzieller Encoder wie ionCube oder SourceGuardian nötig.
- Schutz vor weitergegebenen Lizenzen mit Wildcard-Domain. Wer eine
  `*.example.com`-Lizenz weitergibt, kann sie auf beliebigen Subdomains nutzen.
  Daher Lizenzen so eng binden wie möglich (`app.kunde.de` statt
  `*.kunde.de`), sofern fachlich vertretbar.

Realistisches Ziel: eine hohe Hürde gegen Hobby-Klone, kombiniert mit
juristischer Klarheit über die Lizenzbedingungen.
