# Hinweisgebersystem und anonyme Meldestelle

Status: Konzept fuer die Umsetzung (Red-Team-Review eingearbeitet, siehe Abschnitt 25)

## 1. Ziel

WorkDiary soll Organisationen einen vertraulichen Meldekanal fuer Hinweise auf
Rechtsverstoesse, Korruption, Betrug und andere erhebliche Compliance-Verstoesse
bereitstellen.

Das Modul besteht aus zwei strikt getrennten Bereichen:

1. einem oeffentlichen Meldeportal ohne WorkDiary-Benutzerkonto,
2. einer internen Fallbearbeitung fuer besonders berechtigte Personen.

Eine meldende Person kann anonym bleiben und trotzdem ueber ein geschuetztes
Postfach mit der Meldestelle kommunizieren. Das System ersetzt keine rechtliche
Beratung und entscheidet nicht automatisch, ob eine Meldung in den
Anwendungsbereich des Hinweisgeberschutzgesetzes (HinSchG) faellt.

## 2. Produktpositionierung

Das Produkt sollte als **Hinweisgebersystem / interne Meldestelle** und nicht nur
als Antikorruptions-Tool bezeichnet werden. Typische Meldekategorien sind:

- Korruption und Bestechung
- Betrug, Untreue und Diebstahl
- Geldwaesche und Terrorismusfinanzierung
- Vergabe- und Wettbewerbsverstoesse
- Datenschutz und Informationssicherheit
- Produktsicherheit und Verbraucherschutz
- Umwelt- und Arbeitsschutzverstoesse
- Diskriminierung, Belaestigung und Machtmissbrauch
- Verstoss gegen interne Richtlinien
- sonstiger moeglicher Rechtsverstoss

Nicht jeder Konflikt am Arbeitsplatz ist ein geschuetzter Hinweis. Das
Meldeformular muss deshalb verstaendlich erklaeren, wofuer der Kanal vorgesehen
ist, ohne Meldende von einer Abgabe abzuschrecken.

## 3. Rechtlicher Rahmen fuer die Produktanforderungen

Die konkrete Konfiguration und der Betrieb muessen vor produktiver Nutzung
rechtlich und datenschutzrechtlich geprueft werden. Fuer ein deutsches MVP sind
insbesondere folgende Anforderungen einzuplanen:

- Arbeitgeber mit in der Regel mindestens 50 Beschaeftigten muessen
  grundsaetzlich eine interne Meldestelle einrichten.
- Ein Dritter darf mit Aufgaben der internen Meldestelle betraut werden.
- Nur zustaendige und unterstuetzende Personen duerfen auf Meldungen zugreifen.
- Meldungen muessen mindestens in Textform oder muendlich moeglich sein.
- Auf Wunsch muss innerhalb angemessener Zeit eine persoenliche Zusammenkunft
  ermoeglicht werden.
- Der Eingang ist grundsaetzlich spaetestens nach sieben Tagen zu bestaetigen.
- Eine Rueckmeldung ueber geplante oder ergriffene Folgemassnahmen ist
  grundsaetzlich innerhalb von drei Monaten erforderlich.
- Identitaeten von meldenden und betroffenen Personen sind vertraulich zu
  behandeln.
- Interne Meldestellen sollen auch anonym eingehende Meldungen bearbeiten.
  Eine allgemeine Pflicht, einen anonymen technischen Kanal anzubieten, besteht
  nach dem HinSchG derzeit nicht. Fuer dieses Produkt ist er dennoch Kernumfang.
- Nicht erforderliche personenbezogene Daten duerfen nicht dauerhaft erhoben
  werden.
- Dokumentation und Loeschung brauchen einen nachvollziehbaren,
  organisationsbezogenen Fristenprozess.
- Beschuldigte Personen behalten Unschuldsvermutung, Anhoerungs-,
  Verteidigungs- und Datenschutzrechte.
- Wissentlich falsche Meldungen duerfen nicht als geschuetzte Meldungen
  dargestellt werden. Das Portal darf gleichzeitig keine abschreckenden
  Drohtexte verwenden.

Relevante Grundlagen:

- Hinweisgeberschutzgesetz, insbesondere §§ 8, 10, 12 bis 18 und 36
- Richtlinie (EU) 2019/1937
- Datenschutz-Grundverordnung, insbesondere Art. 5, 6, 13/14, 25 und 32
- gegebenenfalls Lieferkettensorgfaltspflichtengesetz und branchenspezifische
  Regelungen

## 4. Abgrenzung des MVP

### Im MVP

- organisationsbezogene oeffentliche Meldeseite
- anonyme oder freiwillig vertrauliche Meldung
- zufaelliger Fallcode plus separates Geheimnis fuer den Postfachzugang
- anonyme Zwei-Wege-Kommunikation
- sichere Anhaenge
- Fallstatus, Klassifikation, Zuweisung und interne Notizen
- gesetzliche Fristen und Erinnerungen
- rollenbasierte Fallbearbeitung
- spezialisiertes, manipulationsnachweisbares Ereignisprotokoll
- organisationsbezogene Konfiguration und Portaltexte
- Export einer einzelnen Fallakte
- definierter Abschluss- und Loeschprozess

### Nicht im ersten MVP

- Betrieb als juristisch verantwortliche externe Meldestelle durch den
  Softwareanbieter
- automatische juristische Bewertung
- automatische Meldung an Behoerden
- KI-Zusammenfassung oder KI-Klassifikation von Meldeinhalten
- Telefonie, Sprachaufzeichnung oder Videokonferenz
- organisationsuebergreifende Fallbearbeitung
- mobile App oder Offline-Erfassung
- oeffentliche API fuer Meldeinhalte
- Volltextsuche ueber vertrauliche Meldetexte

Eine muendliche Meldung und persoenliche Zusammenkunft werden im MVP
organisatorisch angeboten. Die zustaendige Person dokumentiert das Ergebnis
anschliessend mit Zustimmung der meldenden Person im System.

## 5. Rollen und Verantwortlichkeiten

Die vorhandene Rolle `admin` erhaelt **nicht automatisch** Zugriff auf
Meldeinhalte. Organisatorische Administration und Fallbearbeitung muessen
getrennt sein.

Vorgeschlagene Permissions:

| Permission                       | Bedeutung                                             |
| -------------------------------- | ----------------------------------------------------- |
| `whistleblowing.settings.manage` | Portal, Kategorien, Fristen und Texte konfigurieren   |
| `whistleblowing.case.viewAny`    | Fallliste der eigenen Organisation sehen              |
| `whistleblowing.case.view`       | einen konkret autorisierten Fall sehen                |
| `whistleblowing.case.process`    | Status, Klassifikation und Folgemassnahmen bearbeiten |
| `whistleblowing.case.assign`     | Bearbeitende Personen zuweisen                        |
| `whistleblowing.case.message`    | mit der meldenden Person kommunizieren                |
| `whistleblowing.case.note`       | interne Notizen anlegen                               |
| `whistleblowing.case.export`     | vollstaendige Fallakte exportieren                    |
| `whistleblowing.case.close`      | Fall fachlich abschliessen                            |
| `whistleblowing.case.retention`  | Loeschsperre und Loeschpruefung bearbeiten            |
| `whistleblowing.audit.view`      | Sicherheits- und Fallereignisse anzeigen              |

Empfohlenes Profil `meldestelle`:

- alle Fallbearbeitungsrechte,
- kein allgemeines Personal-, Zeit-, Rechnungs- oder Organisationsrecht,
- verpflichtende Zwei-Faktor-Authentifizierung,
- keine implizite Freigabe fuer Support oder Plattformadministratoren.

Fuer jeden Fall wird zusaetzlich eine explizite Bearbeiterliste gefuehrt.
`viewAny` allein berechtigt nicht dazu, jeden Inhalt zu lesen. Ein Fall ist nur
sichtbar, wenn die Person zugewiesen ist oder eine dokumentierte
Notfallfreigabe erhalten hat.

## 6. Trennung vom normalen Anwendungskontext

### 6.1 Keine Verbindung zu `User`

Eine anonyme meldende Person:

- bekommt keinen Datensatz in `users`,
- benutzt nicht den normalen `web`- oder `customer`-Guard,
- bekommt keine E-Mail als Pflichtfeld,
- wird nicht mit eingeloggten WorkDiary-Sitzungen verknuepft,
- wird nicht ueber Personalnummer, Browser-Fingerprint oder aehnliche Merkmale
  korreliert.

Wenn ein bereits eingeloggter Mitarbeiter das oeffentliche Portal oeffnet,
sollte dort trotzdem eine neue, technisch getrennte Meldesitzung entstehen.
Die Anwendung darf die bestehende Benutzer-ID nicht in den Fall uebernehmen.

### 6.2 Eigener oeffentlicher Middleware-Stack

Der oeffentliche Meldekanal braucht einen kleinen eigenen Stack:

- Organisationsauflosung nur ueber einen oeffentlichen Portal-Slug
- CSRF-Schutz
- eigener Rate Limiter
- keine Auth-, Work-Mode-, Tracking- oder Reverb-Abhaengigkeit
- keine allgemeinen Request- oder Analytics-Listener
- restriktive Content-Security-Policy
- `Referrer-Policy: no-referrer`
- `Cache-Control: no-store`
- keine Drittanbieter-Ressourcen

Der normale globale Webserverzugriffslog muss fuer diese Routen entweder
IP-anonymisiert, stark minimiert oder gemaess dokumentiertem Schutzkonzept
separat behandelt werden. Eine Anwendung kann keine echte Anonymitaet
versprechen, solange vorgeschaltete Proxies, WAFs, Load Balancer oder
Hostingplattformen identifizierende Metadaten unkontrolliert speichern.

## 7. Nutzerablaeufe

### 7.1 Neue Meldung

1. Meldende Person oeffnet `/melden/{portal_slug}`.
2. Das Portal zeigt Zweck, Vertraulichkeit, Datenschutz, externe Meldewege und
   Hinweise fuer akute Gefahren.
3. Die Person waehlt anonym oder vertraulich mit freiwilligen Kontaktdaten.
4. Sie beschreibt den Sachverhalt und kann Anhaenge hochladen.
5. Vor dem Absenden wird ausdruecklich darauf hingewiesen, den Fallcode und das
   Geheimnis sicher aufzubewahren.
6. Das System erzeugt Fallcode und Geheimnis ausschliesslich serverseitig.
7. Nach erfolgreicher Speicherung werden beide einmalig angezeigt.
8. Es wird keine E-Mail versendet, ausser die Person hat dies bewusst gewaehlt.

Pflichtfelder sollten gering bleiben:

- Kategorie
- Beschreibung
- ungefaehrer Zeitraum oder Option `unbekannt`
- Bezug zur Organisation
- Bestaetigung, dass die Angaben nach bestem Wissen gemacht werden

Freiwillig:

- betroffene Organisationseinheit oder Standort
- Namen beteiligter Personen
- bereits ergriffene Schritte
- Gefaehrdungs- oder Dringlichkeitseinschaetzung
- Kontaktdaten
- Anhaenge

### 7.2 Anonymes Postfach

1. Meldende Person oeffnet `/melden/postfach`.
2. Sie gibt ausschliesslich das Geheimnis ein. Der Fallcode ist reine
   Anzeige-Referenz und niemals ein Login-Eingabefeld (sonst Enumerations-
   Orakel ueber den niedrig-entropischen Code, siehe Abschnitt 25).
3. Bei Erfolg wird eine kurzlebige Postfachsitzung in einem
   `HttpOnly; SameSite=Strict; Secure`-Cookie gebunden – kein Token im
   URL-Pfad (siehe Abschnitt 13.1 und 25).
4. Sie sieht nur freigegebene Nachrichten, den groben Status und eigene
   Anhaenge, kann antworten und weitere Dokumente hochladen.
5. Es gibt keinen Passwort-Reset. Ein verlorenes Geheimnis kann ohne
   Identitaetsnachweis nicht wiederhergestellt werden.

### 7.3 Interne Bearbeitung

1. Ein berechtigter Bearbeiter sieht einen neuen Fall ohne Vorschau sensibler
   Inhalte in allgemeinen Benachrichtigungen.
2. Der Eingang wird innerhalb von sieben Tagen bestaetigt.
3. Zustaendigkeit und Anwendungsbereich werden geprueft.
4. Der Fall wird klassifiziert und einer unabhaengigen Person zugewiesen.
5. Rueckfragen erfolgen ueber das geschuetzte Postfach.
6. Ermittlungs- und Folgemassnahmen werden strukturiert dokumentiert.
7. Spaetestens zum Rueckmeldetermin wird eine freigegebene Nachricht gesendet.
8. Der Fall wird mit Begruendung abgeschlossen und in die Aufbewahrungsphase
   ueberfuehrt.

### 7.4 Interessenkonflikt

Ist eine zugewiesene oder administrierende Person selbst Gegenstand der
Meldung, darf sie keinen Fallzugriff erhalten. Fuer das MVP gilt:

- Bearbeiter koennen sich selbst wegen Interessenkonflikts sperren.
- Fallzuweisungen werden gegen benannte betroffene Benutzer geprueft, soweit
  diese strukturiert erfasst wurden. Freitext-Anschuldigungen gegen einen
  Bearbeiter loesen keine automatische Sperre aus (Restrisiko, Abschnitt 25);
  deshalb ist ein externer Ersatzkontakt verpflichtend.
- Eine Notfallfreigabe erfordert Grund, einen zwingend anderen zweiten
  Berechtigten und ein Auditereignis. Sie ist zeitlich begrenzt und laeuft
  automatisch ab. Der Betroffenen-Check gilt auch fuer Notfallfreigaben.
  Plattform-Administratoren und Support sind nie freigabeberechtigt.
- Mindestens zwei geeignete Personen oder ein externer Ersatzkontakt sollten
  pro Organisation konfiguriert werden.

## 8. Zustandsmodell

Vorgeschlagener Enum `WhistleblowingCaseStatus`:

| Status                   | Bedeutung                                                 |
| ------------------------ | --------------------------------------------------------- |
| `submitted`              | Meldung wurde gespeichert                                 |
| `acknowledged`           | Eingang wurde bestaetigt                                  |
| `triage`                 | Zustaendigkeit und Stichhaltigkeit werden geprueft        |
| `investigating`          | Folgemassnahmen oder interne Untersuchung laufen          |
| `waiting_reporter`       | Rueckfrage an meldende Person ist offen                   |
| `referred`               | an eine zustaendige interne oder externe Stelle abgegeben |
| `closed_substantiated`   | Verstoess wurde ganz oder teilweise bestaetigt            |
| `closed_unsubstantiated` | kein ausreichender Nachweis                               |
| `closed_out_of_scope`    | nicht im sachlichen Anwendungsbereich                     |
| `closed_duplicate`       | dokumentiertes Duplikat                                   |
| `retention_review`       | fachlich beendet, Loeschtermin wird geprueft              |
| `legal_hold`             | Loeschung wegen Rechtsgrundlage oder Verfahren gesperrt   |
| `deleted`                | Inhalte kontrolliert geloescht, Minimalnachweis verbleibt |

Statuswechsel erfolgen ausschliesslich ueber einen
`WhistleblowingCaseWorkflowService`. Controller duerfen Statusfelder nicht
direkt setzen.

Wichtige Regeln:

- `submitted -> acknowledged` setzt `acknowledged_at`.
- Geschlossene Zustaende verlangen Abschlussgrund und Abschlussnachricht oder
  dokumentierte Begruendung, warum keine Nachricht moeglich war.
- `legal_hold` verlangt Rechtsgrundlage, Verantwortlichen und Pruefdatum.
- Eine Wiedereroeffnung ist moeglich, aber muss begruendet und protokolliert
  werden.

## 9. Datenmodell

Alle Tabellen tragen `organization_id`. Jeder Zugriff wird sowohl ueber den
Mandantenscope als auch ueber Fallberechtigungen begrenzt.

### 9.1 `whistleblowing_portals`

Organisationsbezogene Portal-Konfiguration:

| Feld                       | Typ / Hinweis                                              |
| -------------------------- | ---------------------------------------------------------- |
| `id`                       | PK                                                         |
| `organization_id`          | FK, unique                                                 |
| `public_slug`              | zufaelliger, nicht aus Organisationsnamen ableitbarer Slug |
| `is_enabled`               | Portal aktiv                                               |
| `allow_anonymous`          | im Produkt standardmaessig `true`                          |
| `allow_confidential`       | freiwillige Kontaktdaten                                   |
| `allowed_locales`          | JSON                                                       |
| `default_locale`           | Locale                                                     |
| `intro_text`               | organisationsspezifischer Hinweis                          |
| `privacy_text_version`     | angezeigte Datenschutzversion                              |
| `external_channels`        | JSON mit Behoerden-/Kontaktinformationen                   |
| `retention_months`         | konfigurierbar, Default 36 (3 Jahre nach Abschluss, HinSchG §11) |
| `created_at`, `updated_at` | Zeitstempel                                                |

`public_slug` darf nicht der normale `organizations.slug` sein. Dadurch kann
das Portal unabhaengig rotiert oder deaktiviert werden.

### 9.2 `whistleblowing_cases`

| Feld                           | Typ / Hinweis                                                                                     |
| ------------------------------ | ------------------------------------------------------------------------------------------------- |
| `id`                           | interne PK                                                                                        |
| `organization_id`              | FK                                                                                                |
| `public_id`                    | voll-zufaellig (UUIDv4), unique – NICHT zeit-geordnet (UUIDv7/ULID leakt Meldezeit, Abschnitt 25) |
| `case_number`                  | intern lesbare, organisationsbezogene Nummer                                                      |
| `access_code_hash`             | Argon2-Hash des langen Postfachgeheimnisses                                                       |
| `access_code_lookup`           | HMAC (eigener, getrennter Key) fuer gezielte Suche, kein Klartext                                 |
| `dek_wrapped`                  | per-Fall-DEK, gewrappt vom Modul-KEK – ermoeglicht Crypto-Shredding (Abschnitt 10/16/25)          |
| `reporter_mode`                | `anonymous` oder `confidential`                                                                   |
| `category`                     | Enum                                                                                              |
| `status`                       | Enum                                                                                              |
| `priority`                     | `normal`, `high`, `critical`                                                                      |
| `subject_ciphertext`           | verschluesselter Betreff                                                                          |
| `description_ciphertext`       | verschluesselter Meldeinhalt                                                                      |
| `contact_ciphertext`           | nullable, verschluesselte freiwillige Kontaktdaten                                                |
| `occurred_from`, `occurred_to` | nullable                                                                                          |
| `acknowledgement_due_at`       | Frist                                                                                             |
| `feedback_due_at`              | Frist                                                                                             |
| `acknowledged_at`              | nullable                                                                                          |
| `feedback_sent_at`             | nullable                                                                                          |
| `closed_at`                    | nullable                                                                                          |
| `retention_due_at`             | nullable                                                                                          |
| `legal_hold_at`                | nullable                                                                                          |
| `created_at`, `updated_at`     | Zeitstempel                                                                                       |

Nicht in dieser Tabelle speichern:

- `reporter_user_id`
- IP-Adresse
- User-Agent
- Session-ID
- Browser-Fingerprint
- Referrer
- Analytics- oder Marketingkennung

`case_number` darf nicht als Zugangsschluessel dienen und ist niemals ein
Login-Eingabefeld (nur Anzeige). Der Postfachzugang erfolgt ausschliesslich
ueber ein ausreichend langes, zufaelliges Geheimnis. Ein Beispiel fuer die
Anzeige ist `WD-7K4P-Q2MV` plus ein separates Geheimnis mit mindestens 128 Bit
Entropie. Das Geheimnis wird nur einmal im Klartext ausgegeben.

### 9.3 `whistleblowing_case_assignments`

| Feld          | Typ / Hinweis                    |
| ------------- | -------------------------------- |
| `case_id`     | FK                               |
| `user_id`     | FK                               |
| `role`        | `owner`, `processor`, `reviewer` |
| `assigned_by` | FK User                          |
| `assigned_at` | Zeitstempel                      |
| `revoked_at`  | nullable                         |

Unique-Regel auf aktivem `case_id`, `user_id`, `role`.

### 9.4 `whistleblowing_messages`

Gemeinsamer Nachrichtenstrom fuer Postfach und interne Kommunikation:

| Feld                  | Typ / Hinweis                                                                          |
| --------------------- | -------------------------------------------------------------------------------------- |
| `id`                  | PK                                                                                     |
| `organization_id`     | FK                                                                                     |
| `case_id`             | FK                                                                                     |
| `author_type`         | `reporter`, `handler`, `system`                                                        |
| `author_user_id`      | nur bei `handler`, sonst null                                                          |
| `visibility`          | `reporter` oder `internal`                                                             |
| `body_ciphertext`     | verschluesselt                                                                         |
| `sent_at`             | Zeitstempel                                                                            |
| `read_by_reporter_at` | nullable; bewusst grob (Reporter-Aktivitaetszeit ist Korrelationssignal, Abschnitt 25) |
| `created_at`          | Zeitstempel                                                                            |

Nachrichten werden nicht editiert. Korrekturen erfolgen als neue Nachricht.
Auch interne Notizen liegen als `visibility=internal` in diesem Strom oder
alternativ in einer eigenen Tabelle. Eine eigene Tabelle ist vorzuziehen, wenn
unterschiedliche Loesch- oder Exportregeln notwendig werden.

### 9.5 `whistleblowing_attachments`

Meldeanhaenge werden **nicht** ueber das allgemeine `attachments`-Modell
verwaltet, solange dieses normale Audit-, Upload- und Autorbeziehungen nutzt.

| Feld                       | Typ / Hinweis                                                                                            |
| -------------------------- | -------------------------------------------------------------------------------------------------------- |
| `id`                       | PK                                                                                                       |
| `organization_id`          | FK                                                                                                       |
| `case_id`                  | FK                                                                                                       |
| `message_id`               | nullable FK                                                                                              |
| `uploaded_by_type`         | `reporter` oder `handler`                                                                                |
| `storage_key`              | zufaelliger, nicht erratbarer Pfad                                                                       |
| `original_name_ciphertext` | verschluesselt                                                                                           |
| `mime_detected`            | serverseitig erkannt                                                                                     |
| `size`                     | Bytes                                                                                                    |
| `sha256`                   | Integritaet; Dedup nur fall-intern bzw. per-Case-Salt (Cross-Case-Hash korreliert Quellen, Abschnitt 25) |
| `scan_status`              | `pending`, `clean`, `rejected`, `failed`                                                                 |
| `metadata_scrubbed`        | Boolean                                                                                                  |
| `created_at`               | Zeitstempel                                                                                              |

### 9.6 `whistleblowing_case_events`

Eigenes append-only Ereignisprotokoll:

| Feld                    | Typ / Hinweis                      |
| ----------------------- | ---------------------------------- |
| `id`                    | PK                                 |
| `organization_id`       | FK                                 |
| `case_id`               | nullable fuer Portalereignisse     |
| `actor_type`            | `reporter`, `user`, `system`       |
| `actor_user_id`         | nullable                           |
| `event`                 | fachlicher Eventcode               |
| `metadata`              | minimiertes JSON ohne Meldeinhalte |
| `previous_hash`, `hash` | manipulationsnachweisbare Kette    |
| `created_at`            | Zeitstempel                        |

Beispiele:

- `case.submitted`
- `case.viewed`
- `case.assigned`
- `case.acknowledged`
- `case.status_changed`
- `message.sent_to_reporter`
- `attachment.uploaded`
- `attachment.rejected`
- `case.exported`
- `case.legal_hold_set`
- `case.deleted`
- `emergency_access.granted`

## 10. Verschluesselung und Schluesselmanagement

Laravel-`encrypted`-Casts sind fuer einen Prototyp nutzbar, reichen als
Langzeitkonzept allein aber nicht aus. Sie verwenden den globalen
Anwendungsschluessel und erschweren organisationsbezogene Schluesselrotation.

Zielbild:

- Envelope Encryption pro Organisation
- eigener Data Encryption Key (DEK) fuer das Hinweisgebermodul
- DEK verschluesselt durch einen Key Encryption Key in KMS, Vault oder einer
  getrennten Secrets-Verwaltung
- getrennte Schluessel fuer Datenbankfelder und Dateien
- dokumentierte Rotation ohne Verlust alter Daten
- keine Schluessel in Datenbankexporten oder Backups derselben Schutzklasse

MVP-Zwischenstufe (gegenueber dem urspruenglichen Konzept verschaerft, Abschnitt 25):

- **eigener Modul-Schluessel** (`WHISTLEBLOWING_KEY`), NICHT der globale
  `APP_KEY` (sonst zu grosser Blast-Radius ueber Sessions/Cookies/CI)
- **per-Fall-DEK schon im MVP**: jeder Fall hat einen eigenen Data Encryption
  Key, gewrappt vom Modul-KEK. Das ermoeglicht Crypto-Shredding (Loeschung =
  DEK vernichten) und loest damit zugleich das Backup-Loeschproblem
  (Abschnitt 16), ohne Backups anzufassen.
- HMAC-Key fuer `access_code_lookup` getrennt vom Verschluesselungs-Key und
  nicht im selben Backup-Schutzlevel
- eigener konfigurierter Filesystem-Disk ausserhalb des Public-Verzeichnisses
- keine Klartextwerte in Logs, Exceptions, Queue-Payloads oder Events
- Architektur so kapseln, dass spaeter ein `WhistleblowingCryptoService` auf
  echtes KMS/Vault-Envelope umgestellt werden kann

## 11. Datei- und Upload-Sicherheit

Anhaenge sind ein wesentlicher Angriffs- und Identifikationsvektor.

Pflichtmassnahmen:

- Speicherung ausschliesslich auf privatem Disk
- zufaellige Storage-Namen ohne Originaldateiname
- MIME-Erkennung aus Dateiinhalt, nicht aus Browserangabe
- Positivliste erlaubter Dateitypen
- harte Groessen- und Mengenlimits
- Viren-/Malwarepruefung vor Freigabe
- kein direkter Webserverpfad; Download nur ueber autorisierten Controller
- `Content-Disposition: attachment`
- `X-Content-Type-Options: nosniff`
- Bild-Metadaten wie EXIF nach Moeglichkeit entfernen
- aktive Inhalte wie HTML und SVG standardmaessig ablehnen
- Office- und PDF-Dateien als potenziell metadatenhaltig kennzeichnen; nicht
  nur EXIF, sondern auch Dokument-Properties, Autor und Revisionshistorie
  strippen (oder zu geflachtem PDF konvertieren) – sonst deanonymisiert ein
  `Author`-Feld die Meldung sofort (Abschnitt 25)
- Quarantaene bei fehlgeschlagenem Scanner
- **Datei-Parsing (MIME-Erkennung, Metadaten-Scrubber, Scanner) gesandboxt**
  ausfuehren: es verarbeitet attacker-kontrollierte Dateien an einem
  unauthentifizierten Endpunkt (RCE/Decompression-Bomb-Risiko). Harte
  Ressourcen- und Zeitlimits; Parser-Fehler fuehren in Quarantaene, nicht in
  einen Retry.

Das Portal muss vor dem Upload darauf hinweisen, dass Dokumente Namen,
Benutzerkonten, GPS-Positionen und andere Metadaten enthalten koennen. Ein
Metadaten-Scrubber reduziert Risiken, kann Anonymitaet aber nicht garantieren.

## 12. Audit und Logging

Das bestehende `AuditLog` ist fuer normale Geschaeftsobjekte ausgelegt und
speichert derzeit unter anderem IP-Adresse, User-Agent und Aenderungsdaten.
Meldeinhalte duerfen deshalb nicht unbesehen den Trait `Auditable` verwenden.

Regeln fuer dieses Modul:

- eigenes minimiertes `whistleblowing_case_events` verwenden,
- keine IP oder User-Agents fuer Reporterereignisse speichern,
- keine Nachrichten, Betreffe, Kontaktdaten oder Originaldateinamen in
  Event-Metadaten schreiben,
- interne Lesezugriffe protokollieren,
- Exporte und Notfallzugriffe protokollieren,
- Ereignisse append-only und mit Hash-Kette absichern (wiederverwendbar: der
  bestehende `HashChained`-Trait + `audit:verify`; WB-Kette in
  `config('audit.chains')` registrieren),
- Log-Viewer nur fuer gesondert Berechtigte bereitstellen,
- Anwendungsausnahmen vor dem Logging redigieren,
- **Telescope, Debugbar und Ignition** fuer die WB-Routen hart deaktivieren
  (auch in Staging) – sie rendern Modell-Attribute und Requests im Klartext,
- Queue-Worker duerfen bei Fehlern keine entschluesselten Inhalte loggen;
  `failed_jobs` enthaelt nur Ciphertext/Referenzen (Abschnitt 25).

Allgemeine Infrastruktur-Logs muessen in einer Datenschutz-Folgenabschaetzung
mit betrachtet werden. Dazu gehoeren Webserver, Reverse Proxy, CDN, WAF,
Containerplattform, Datenbank, Object Storage, Monitoring, Error Tracking,
Backups und E-Mail-Dienst.

## 13. Routen und Controller

### 13.1 Oeffentliche Routen

Vorgeschlagene Datei `routes/whistleblowing.php`:

```text
GET  /melden/{portal}                 portal.show
POST /melden/{portal}                 report.store
GET  /melden/{portal}/erfolg          report.receipt
GET  /melden/postfach                 mailbox.login
POST /melden/postfach                 mailbox.authenticate
GET  /melden/postfach                  mailbox.show            (Sitzung via Cookie)
POST /melden/postfach/nachrichten      mailbox.message.store
POST /melden/postfach/anhaenge         mailbox.attachment.store
POST /melden/postfach/abmelden         mailbox.logout
```

Die Postfachsitzung wird serverseitig gebunden und ueber ein
`HttpOnly; SameSite=Strict; Secure`-Cookie gehalten – **kein** Token im
URL-Pfad. Pfad-Token landen in History, Access- und Error-Logs (Abschnitt 25).
Fallcode und Geheimnis stehen nie in URL, History oder Logs.

Vorgeschlagene Controller:

- `PublicWhistleblowingPortalController`
- `PublicWhistleblowingReportController`
- `ReporterMailboxSessionController`
- `ReporterMailboxController`
- `ReporterMessageController`
- `ReporterAttachmentController`

### 13.2 Interne Routen

```text
GET  /compliance/meldungen
GET  /compliance/meldungen/{case}
POST /compliance/meldungen/{case}/zuweisungen
POST /compliance/meldungen/{case}/status
POST /compliance/meldungen/{case}/nachrichten
POST /compliance/meldungen/{case}/notizen
POST /compliance/meldungen/{case}/anhaenge
POST /compliance/meldungen/{case}/export
POST /compliance/meldungen/{case}/abschluss
POST /compliance/meldungen/{case}/legal-hold
```

Route Model Binding muss den Organisationsscope und die konkrete
Fallberechtigung respektieren. Ein direkter numerischer ID-Zugriff ist nach
aussen unzulaessig.

## 14. Services und Verantwortungsgrenzen

Vorgeschlagene Services:

| Service                             | Verantwortung                               |
| ----------------------------------- | ------------------------------------------- |
| `WhistleblowingReportService`       | Meldung transaktional anlegen               |
| `ReporterCredentialService`         | Codes erzeugen, hashen und pruefen          |
| `ReporterMailboxService`            | anonyme Postfachsitzung und Nachrichten     |
| `WhistleblowingCaseWorkflowService` | erlaubte Statuswechsel und Fristen          |
| `WhistleblowingAssignmentService`   | Zuweisung und Interessenkonflikte           |
| `WhistleblowingAttachmentService`   | Quarantaene, Scan, Metadaten, Download      |
| `WhistleblowingNotificationService` | inhaltsarme interne Hinweise                |
| `WhistleblowingDeadlineService`     | Fristen berechnen und Eskalationen erzeugen |
| `WhistleblowingExportService`       | autorisierte Fallakte                       |
| `WhistleblowingRetentionService`    | Review, Legal Hold und Loeschung            |
| `WhistleblowingEventService`        | minimiertes append-only Audit               |
| `WhistleblowingCryptoService`       | Verschluesselungsabstraktion                |

Controller validieren Requests, autorisieren und delegieren. Sie enthalten
keine Statuslogik, Schluesselerzeugung oder direkten Storagezugriffe.

## 15. Benachrichtigungen und Fristen

Interne Benachrichtigungen enthalten nur:

- neue Meldung vorhanden,
- interne Fallnummer,
- Prioritaet,
- Frist,
- Link nach erfolgreicher Authentifizierung.

Sie enthalten niemals:

- Beschreibung oder Betreff,
- Namen beschuldigter oder meldender Personen,
- Anhaenge,
- freiwillige Kontaktdaten.

Geplante Commands:

```text
whistleblowing:deadlines
whistleblowing:retention-review
whistleblowing:verify-event-chain
whistleblowing:purge-expired-mailbox-sessions
```

`whistleblowing:deadlines` laeuft mindestens stuendlich und erzeugt
idempotente Erinnerungen:

- Eingangsbestaetigung faellig in 48 Stunden,
- Eingangsbestaetigung ueberfaellig,
- Rueckmeldung faellig in 14 Tagen,
- Rueckmeldung faellig in 48 Stunden,
- Rueckmeldung ueberfaellig.

Fristen werden beim Anlegen persistiert und nicht bei jedem Aufruf neu aus
aktuellen Einstellungen berechnet.

## 16. Aufbewahrung und Loeschung

Es darf keine unkontrollierte pauschale Loeschung geben. Der fachliche
Verantwortliche legt mit Datenschutz und Rechtsberatung je Organisation eine
Regel fest.

Technischer Ablauf:

1. Beim Abschluss wird `retention_due_at` gesetzt.
2. Vor Ablauf wechselt der Fall in `retention_review`.
3. Eine berechtigte Person prueft offene Verfahren, Aufbewahrungspflichten und
   Rechtsansprueche.
4. Falls erforderlich wird ein zeitlich zu pruefender `legal_hold` gesetzt.
5. Andernfalls werden Meldeinhalte, Nachrichten, Kontaktdaten und Dateien
   kontrolliert geloescht – primaer durch **Crypto-Shredding** (Vernichten des
   per-Fall-DEK, Abschnitt 10), ergaenzt um das Loeschen der Zeilen/Dateien.
   Der Loeschlauf prueft `legal_hold` atomar in derselben Transaktion (sonst
   TOCTOU-Race, Abschnitt 25).
6. Ein minimaler, nicht inhaltsbezogener Nachweis (Tombstone) verbleibt in
   einem eigenen, ueberlebenden Ledger: Fallnummer, Zeitraum,
   Abschlusskategorie, Loeschzeitpunkt und Audit-Hash.

Backups muessen in das Loeschkonzept einbezogen werden. Crypto-Shredding loest
das Kernproblem: ist der DEK vernichtet, ist der Ciphertext auch in
unveraenderlichen Backups wertlos. Zusaetzlich wird beim Restore der
Tombstone-Ledger erneut angewandt, um nach dem Backup geloeschte Faelle wieder
zu sperren bzw. zu loeschen.

## 17. Export

Ein Fallexport ist hochsensibel.

Anforderungen:

- nur `whistleblowing.case.export`,
- zusaetzliche Bestaetigung oder erneute Authentifizierung,
- Exportgrund als Pflichtfeld,
- Export asynchron erzeugen, aber keine Klartextinhalte im Queue-Payload,
- verschluesseltes ZIP oder verschluesselter, kurzlebiger Download,
- automatische Loeschung des Exportartefakts,
- vollstaendiges Exportereignis im Fall-Audit,
- keine Aufnahme in allgemeine Organisations- oder Supportexporte ohne
  explizite Sonderregel.

Der bestehende `OrganizationLifecycleService` entdeckt Tabellen ueber
`organization_id`. Vor Umsetzung muss entschieden werden, ob
Hinweisgeberdaten in Standard-Mandantenexporte aufgenommen werden duerfen.
Empfehlung: standardmaessig ausschliessen und einen getrennten, besonders
autorisierten Exportpfad vorsehen.

## 18. Oberflaeche

### Oeffentliches Portal

- reduzierte, ruhige Seite ohne normales App-Menue
- sichtbarer Organisationsname, aber nicht aus dem Portal-Slug ableitbar
- klare Sprachumschaltung
- Fortschrittsanzeige fuer mehrstufiges Formular
- Zwischenspeicherung nur lokal und nur nach bewusster Zustimmung; im MVP
  besser keine dauerhafte Browser-Speicherung
- Sicherheits- und Notfallhinweise
- externe Meldestellen und alternative Kontaktwege
- druckbare einmalige Zugangsdaten
- barrierearme Bedienung nach vorhandener Accessibility-Checkliste

### Interne Fallakte

1. Kopf mit Fallnummer, Status, Prioritaet und Fristen
2. Meldeinhalt und gesicherte Anhaenge
3. anonyme Kommunikation
4. interne Notizen
5. Zuweisungen und Interessenkonflikte
6. Folgemassnahmen
7. Statushistorie
8. Datenschutz, Aufbewahrung und Legal Hold
9. spezialisiertes Audit

Die Fallliste zeigt keine Textvorschau. Filter:

- Status
- Kategorie
- Prioritaet
- zugewiesene Person
- Friststatus
- Eingangszeitraum

Keine globale Suche und keine Aufnahme der Meldeinhalte in die bestehende
globale Suche.

## 19. Sicherheitsanforderungen

### Pflicht vor Pilotbetrieb

- Threat Model fuer Reporter, Bearbeiter, Admin, Support und Angreifer
- Datenschutz-Folgenabschaetzung pruefen und gegebenenfalls durchfuehren
- 2FA fuer alle internen Fallberechtigten
- sichere Header und keine Drittanbieter-Ressourcen
- Rate Limits und Schutz gegen Credential Stuffing
- konstante Fehlermeldung und moeglichst konstantes Timing beim Postfachlogin
- serverseitige Autorisierung fuer jede Aktion und jeden Download
- Mandantentrennung in Policies und Queries
- Verschluesselung sensibler Inhalte und Dateien
- Secrets nicht in Logs, URLs oder Queue-Payloads
- sichere Backup- und Restore-Prozesse
- dokumentierter Incident-Response-Prozess
- unabhaengiger Penetrationstest vor breiter Vermarktung

### Rate Limiting

Das System darf keine IP dauerhaft im Fachmodell speichern. Technischer
Missbrauchsschutz kann kurzlebige, datensparsame HMAC-Werte im Cache verwenden,
wenn Zweck, Speicherdauer und Zugriff dokumentiert sind.

Beispiel:

- Formular anzeigen: moderates Limit
- Meldung absenden: strengeres Limit plus Bot-Schutz ohne externes Tracking
- Postfachlogin: **nur** das hochentropische Geheimnis (kein Fallcode als
  Eingabe), Dummy-Argon2 bei Miss fuer moeglichst konstante Zeit, plus
  kurzlebiger Netzwerk-Praefix-HMAC und globales Limit. Argon2-Work-Factor so
  tunen, dass der Verify selbst kein DoS-Vektor wird (Abschnitt 25).
- Upload: Mengen-, Groessen- und Frequenzlimit; Gesamt-Quota pro Fall und Portal

CAPTCHAs von Drittanbietern sind wegen Tracking und Datenabfluss zu vermeiden.
Trackingfreier Bot-Schutz stattdessen ueber Honeypot-Felder und/oder einen
clientseitigen Proof-of-Work.

## 20. Tests

### Feature-Tests

- anonyme Meldung wird ohne `user_id`, IP und User-Agent angelegt
- vertrauliche Meldung verschluesselt Kontaktdaten
- Zugangsdaten werden einmalig ausgegeben und nicht im Klartext gespeichert
- falsche Postfachdaten liefern keine Information ueber vorhandene Faelle
- Reporter sieht keine internen Notizen
- Reporter kann Nachrichten senden und empfangen
- fremde Organisation kann Fall weder binden noch laden
- normaler Org-Admin ohne Sonderpermission erhaelt `403`
- zugewiesener Bearbeiter kann autorisierte Aktionen ausfuehren
- nicht zugewiesener Bearbeiter erhaelt `403`
- Eingangsbestaetigungs- und Feedbackfristen werden korrekt gesetzt
- unzulaessige Statuswechsel werden abgewiesen
- Abschluss verlangt Begruendung
- Legal Hold verhindert Loeschung
- Download prueft Scanstatus, Organisation und Fallberechtigung
- Standard-Org-Export enthaelt keine Hinweisgeberdaten
- Benachrichtigungen enthalten keine Meldeinhalte

### Sicherheits- und Regressionstests

- IDOR-Test fuer alle Fall-, Nachrichten- und Anhangsrouten
- Tenant-Leak-Tests mit zwei Organisationen
- Tests gegen Mass Assignment sensibler Felder
- keine vertraulichen Werte in Logs bei Validierungs- und Serverfehlern
- Upload von HTML, SVG, ausfuehrbaren und falsch deklarierten Dateien
- MIME-Sniffing und Download-Header
- Brute-Force- und Rate-Limit-Tests
- Hash-Ketten-Verifikation erkennt Aenderung und Loeschung
- Restore-Test beruecksichtigt bereits geloeschte Faelle

### Unit-Tests

- Zustandsautomat
- Fristberechnung
- Credential-Erzeugung und -Verifikation
- Event-Hash-Kette
- Retention-Entscheidungen
- Interessenkonfliktregeln
- Redaction von Event- und Notification-Metadaten

## 21. Umsetzung in Phasen

### Phase 0: rechtliche und betriebliche Festlegung

- Verantwortlicher und Rolle der WorkDiary-Instanz bestimmen
- Datenschutzinformationen, Verfahrensordnung und Rechtsgrundlagen abstimmen
- Fristen, Kategorien und Aufbewahrung festlegen
- Hosting-, Logging-, Backup- und Supportdatenfluesse dokumentieren
- Incident- und Auskunftsprozesse festlegen

### Phase 1: technisches Fundament — UMGESETZT

- Enums, Migrationen und Modelle
- eigenes Crypto-, Credential- und Event-Konzept
- Permissions und Policy
- Portal-Konfiguration
- private Storage-Struktur
- keine UI fuer Meldeinhalte, bevor Autorisierungstests bestehen

Umgesetzt (mit Tests, ohne Inhalts-UI):

- `config/whistleblowing.php`, privater Disk `whistleblowing`, Event-Kette in
  `config('audit.chains')` registriert.
- Enums unter `app/Enums/Whistleblowing/`; Migration
  `*_create_whistleblowing_tables` (7 Tabellen inkl. `dek_wrapped`, zufaellige
  `public_id`, FK-freie Event-Kette, Tombstone-Ledger).
- Modelle unter `app/Models/Whistleblowing/` (`CaseEvent` mit `HashChained`).
- Services: `WhistleblowingCryptoService` (per-Fall-DEK-Envelope, sodium),
  `ReporterCredentialService` (Fallcode + Argon2-Geheimnis + HMAC-Lookup),
  `WhistleblowingEventService`.
- Permissions getrennt von der zentralen Enum (`WhistleblowingPermissions`,
  Rolle `meldestelle`, NICHT an Plattform-Admin); `WhistleblowingCasePolicy`
  ohne Admin-Bypass (Permission UND Fall-Zuweisung); Org-Observer seedet die
  Rolle pro Mandant.
- Tests `tests/Feature/Whistleblowing/`: Krypto/Shredding, Encryption-at-rest,
  Event-Hash-Kette (`audit:verify`), Policy (Zuweisungsbindung, Mandantentrennung,
  Admin ohne Auto-Zugriff).

### Phase 2: oeffentliche Meldung — UMGESETZT

- Portal und Datenschutztexte
- Meldungsformular
- sichere Zugangsdaten
- Upload-Quarantaene
- Erfolgseite
- Feature- und Sicherheitstests

Umgesetzt (mit Tests):

- Eigener schlanker Middleware-Stack `whistleblowing` (Session/CSRF + strikte
  Header, KEIN Auth/Org-Context/Locale/2FA) in `bootstrap/app.php`;
  `routes/whistleblowing.php` (`/melden/{portal}`, `…/erfolg`).
- `ResolvePortal` (Org nur ueber Portal-Slug, 404 bei unbekannt/deaktiviert) +
  `WhistleblowingSecurityHeaders` (CSP `default-src 'none'`, `Referrer-Policy:
  no-referrer`, `Cache-Control: no-store`, keine Drittanbieter-Ressourcen).
- `WhistleblowingReportService` (transaktional, PII-frei, Verschluesselung,
  Zugangsdaten, Fristen, `case.submitted`-Event), `WhistleblowingAttachmentService`
  (privater Disk, MIME aus Inhalt, Positivliste, Limits, sha256, Quarantaene
  `scan_status=pending`; Scan/Scrub via gesandboxtem Worker noch nicht aktiv).
- Controller/Request/Views unter `…/Whistleblowing/` bzw.
  `resources/views/whistleblowing/public/`; statisches `public/whistleblowing.css`
  (kein App-JS/-CSS). Rate-Limiter `wb-view`/`wb-submit` (gehashte IP, datensparsam).
- Zugangsdaten werden nur EINMAL (Session-Flash) angezeigt, Geheimnis als
  Argon2-Hash gespeichert; Login-by-Secret-Vorbereitung (HMAC-Lookup).
- Tests `tests/Feature/Whistleblowing/PublicPortalTest`: 404, Security-Header,
  PII-freie Meldung + Event, einmalige/gehashte Zugangsdaten, vertrauliche
  Kontaktverschluesselung, Validierung, Upload-Quarantaene + Verschluesselung.

### Phase 3: internes Fallmanagement — UMGESETZT

- Fallliste ohne Inhaltsvorschau
- Fallakte
- Zuweisung, Status und interne Notizen
- Eingangsbestaetigung
- Fristen und inhaltsarme Benachrichtigungen

Umgesetzt (mit Tests):

- Interne Routen `compliance/meldungen` im authentifizierten `access.new`-Stack;
  {case}-Binding org-scoped ueber `public_id` (keine sequentiellen IDs).
  `InternalCaseController`: Liste OHNE ciphertext-Spalten, Fallakte, Aktionen.
- `WhistleblowingCaseWorkflowService` (einzige Stelle fuer Statuswechsel,
  erlaubte Uebergaenge §8, Eingangsbestaetigung, Abschluss-mit-Begruendung →
  closed_at/retention_due_at, Legal-Hold). Begruendungen als verschluesselte
  interne Notiz – NICHT in Event-Metadaten.
- `WhistleblowingAssignmentService` (Zuweisen/Widerruf, Mandanten-Check),
  `WhistleblowingMessageService` (interne Notiz / Nachricht an Reporter).
- Jede Aktion ueber die `WhistleblowingCasePolicy` autorisiert (Permission UND
  Zuweisung); Admin ohne Permission erhaelt 403, fremde Org 404.
- Fristen: `WhistleblowingDeadlineService`, Command `whistleblowing:deadlines`
  und `WhistleblowingDeadlineNotification` (inhaltsarm: nur Fallnummer,
  Prioritaet, Frist und Link – kein Meldeinhalt).
- Views `resources/views/whistleblowing/internal/` (Liste + Fallakte).
- Tests: WorkflowTest, InternalCaseTest (Autorisierung + Aktionen),
  DeadlineReminderTest (inhaltsarme Benachrichtigung).

Nachgezogen (mit Tests):

- **Interessenkonflikt-Selbstsperre + Notfallfreigabe** (§7.4): Tabellen
  `whistleblowing_case_conflicts` / `whistleblowing_emergency_grants`,
  `WhistleblowingAccessService`. Konflikt sperrt den Zugriff (auch bei
  Zuweisung) und widerruft Zuweisungen; konfliktbehaftete Person ist nicht
  zuweisbar. Notfallfreigabe verlangt einen ANDEREN Zweit-Genehmiger
  (Permission `whistleblowing.case.emergency`), laeuft automatisch ab
  (`emergency_ttl_minutes`), Admin kann nicht freigeben. Policy:
  `... && ! hasConflict && (isAssigned || aktive Notfallfreigabe)`.
- **Idempotente Fristen-Erinnerungen**: `whistleblowing_deadline_reminders`
  (unique case/kind/Tag) → der Command erinnert pro Fall/Art/Tag nur einmal.
- **Anhang-Auslieferung erst nach `clean`**: `InternalAttachmentController`
  liefert nur freigegebene Anhaenge aus (sonst 403, Quarantaene),
  `WhistleblowingAttachmentScanService` + Command `whistleblowing:scan`
  (Default-Scanner `none` = fail-safe, bleibt pending).

Auch nachgezogen (mit Tests):

- **Strukturierte Betroffene** (§7.4): Tabelle `whistleblowing_case_subjects`,
  `CaseSubject`, `WhistleblowingAccessService::markSubject()`. Markierte Personen
  sind gesperrt (`WhistleblowingCase::isBlockedFor()` = Konflikt ODER Betroffener):
  nicht zuweisbar, kein Zugriff, keine Notfallfreigabe; bestehende Zuweisungen
  werden widerrufen. Endpunkt `…/betroffene`.
- **Pluggbarer Malware-Scanner**: `Scanning\ScanDriver`-Interface mit
  `NullScanDriver` (Default, fail-safe) und `ClamAvScanDriver` (clamdscan,
  Exit-Code → clean/rejected; Fehler = fail-safe). Auswahl via
  `config('whistleblowing.scanner')`, im Container gebunden; `whistleblowing:scan`
  nutzt den Treiber. Anhaenge werden weiterhin erst bei `clean` ausgeliefert.

Offen (echte Ops/Infra): ClamAV-Binary + Sandbox produktiv bereitstellen;
Metadaten-Scrubbing (EXIF/Office/PDF) im gesandboxten Worker.

### Phase 4: anonymes Postfach — UMGESETZT

- Postfachlogin
- kurzlebige Postfachsitzung
- Zwei-Wege-Nachrichten
- neue Anhaenge
- Rueckmeldungsworkflow

Umgesetzt (mit Tests):

- Routen `melden/postfach` (vor den `{portal}`-Routen, im schlanken
  `whistleblowing`-Stack). **Login NUR per Geheimnis** (`WhistleblowingMailboxService`,
  Lookup ueber HMAC + Argon2-Verify, Dummy-Verify bei Miss → konstantes Timing);
  der Fallcode ist nie Eingabe.
- **Cookie-Sitzung statt Pfad-Token** (Abschnitt 25): `EnsureMailboxSession`
  bindet die Sitzung serverseitig, kurzlebig + gleitend
  (`mailbox_session_minutes`); Session-Regeneration beim Login; Rate-Limiter
  `wb-login` (streng).
- Reporter sieht nur freigegebene Nachrichten (`visibility=reporter`) und den
  **groben** Status (`CaseStatus::reporterStatus()`) – keine internen Notizen,
  keine Bearbeiterdaten. Zwei-Wege-Antwort (`receiveFromReporter`, inhaltsfreies
  Event), neue Anhaenge landen in Quarantaene.
- Views `mailbox_login` / `mailbox` im Public-Layout; Logout invalidiert die Sitzung.
- Tests `MailboxTest`: Login nur per Geheimnis, falsches Geheimnis ohne Session,
  Fallcode kein Login, interne Notizen unsichtbar, Antwort, Upload-Quarantaene,
  Session-Pflicht, Logout.

### Phase 5: Abschluss, Export und Loeschung — UMGESETZT

- strukturierte Folgemassnahmen und Abschluss
- Fallaktenexport
- Retention Review und Legal Hold
- kontrollierte Loeschung
- Backup-/Restore-Abgleich

Umgesetzt (mit Tests):

- **Aufbewahrungspruefung**: Command `whistleblowing:retention-review` ueberfuehrt
  faellige, abgeschlossene Faelle nach `retention_review` – **keine Auto-Loeschung**.
  Abschluss + Legal-Hold laufen ueber den bestehenden Workflow (Abschluss verlangt
  Begruendung; `transition()` akzeptiert nun einen optionalen System-Actor).
- **Kontrollierte Loeschung** (`WhistleblowingDeletionService`): nur aus
  `retention_review`, **Legal-Hold atomar per `lockForUpdate` geprueft** (TOCTOU).
  Primaer **Crypto-Shredding** (DEK vernichten), zusaetzlich Inhalts-Spalten genullt,
  Nachrichten/Anhaenge + Dateien geloescht, Status `deleted`. Es verbleibt ein
  inhaltsfreier **Tombstone** mit `audit_hash` des `case.deleted`-Events.
  Endpunkt `…/loeschen` (Permission `whistleblowing.case.retention`).
- **Fallaktenexport** (`WhistleblowingExportService`, Endpunkt `…/export`):
  Permission `whistleblowing.case.export` + Zuweisung, **Exportgrund Pflicht**
  (als interne Notiz), ZIP synchron in Temp-Datei (kein Klartext in Queue),
  gestreamt mit `no-store` und nach dem Senden geloescht; nur freigegebene
  Anhaenge beigelegt; vollstaendiges `case.exported`-Event.
- **Leak-Schutz**: alle `whistleblowing_*`-Tabellen sind aus dem Standard-
  Mandantenexport (`OrganizationLifecycleService`) ausgeschlossen (§17/§25).
- Tests: RetentionDeletionTest (Retention, Crypto-Shredding, Legal-Hold-Guard,
  Tombstone), ExportTest (Autorisierung, Grund-Pflicht, Download+Event,
  Standard-Export-Ausschluss).

Offen (Ops/Backup): Restore-Abgleich (Tombstone-Ledger nach Restore erneut
anwenden), AES-passwortgeschuetztes ZIP fuer persistierte/versendete Exporte.

### Phase 6: Pilot und Haertung — TOOLING UMGESETZT (Rest organisatorisch)

- Pilot mit Testorganisation und synthetischen Faellen
- Berechtigungs- und Prozessreview
- Last-, Missbrauchs- und Penetrationstests
- Betriebsdokumentation und Schulung der Meldestellenbeauftragten
- erst danach Freigabe fuer echte Meldungen

Phase 6 ist ueberwiegend organisatorisch (Pentest, Schulung, DSFA,
Freigabe-Entscheidung). Der Code liefert die unterstuetzenden Werkzeuge:

- **`whistleblowing:preflight`** – Produktions-Readiness-/Go-Live-Gate: prueft
  Modul-Key (inkl. Trennung von APP_KEY), Disk, Scanner, Retention und Session-
  Sicherheit; Exit 1 bei kritischen Punkten.
- **`whistleblowing:demo-seed <orgId> --count=N`** – synthetische Pilotfaelle
  ueber den echten Meldepfad (in Produktion nur mit `--force`).
- **Betriebs-Runbook** `docs/hinweisgebersystem-betrieb.md` (Config, Cron,
  Schluesselverwaltung, Scanner, Incident Response, **Go-Live-Checkliste** mit den
  organisatorischen Pflichten).
- Haertungstests: Mass-Assignment-Schutz sensibler Felder, Rate-Limit des
  Postfach-Logins (→ 429).

Organisatorisch/extern (NICHT durch Code leistbar): DSFA, Verfahrensordnung,
Berechtigungs-/Prozessreview, unabhaengiger Penetrationstest, Last-/
Missbrauchstest, Schulung, Infrastruktur-Log-Minimierung, Freigabe-Entscheidung.
Siehe Go-Live-Checkliste im Runbook.

## 22. Vorgeschlagene Dateistruktur

```text
app/
  Enums/Whistleblowing/
  Http/Controllers/Whistleblowing/
  Http/Requests/Whistleblowing/
  Models/Whistleblowing/
  Policies/WhistleblowingCasePolicy.php
  Services/Whistleblowing/
  Console/Commands/Whistleblowing/
config/
  whistleblowing.php
database/
  factories/Whistleblowing/
  migrations/*_create_whistleblowing_*.php
resources/views/
  whistleblowing/public/
  whistleblowing/internal/
routes/
  whistleblowing.php
tests/
  Feature/Whistleblowing/
  Unit/Whistleblowing/
```

## 23. Definition of Done fuer das MVP

Das MVP ist erst fertig, wenn:

1. eine Meldung ohne Benutzerkonto und ohne gespeicherte Reporter-IP moeglich
   ist,
2. Fallcode und Geheimnis keine Identitaetsverknuepfung erzeugen,
3. anonyme Zwei-Wege-Kommunikation funktioniert,
4. Organisations- und Fallberechtigungen serverseitig getestet sind,
5. normale Admins, Support und andere Rollen keinen impliziten Zugriff haben,
6. Fristen technisch ueberwacht werden,
7. Anhaenge privat, geprueft und nur autorisiert ausgeliefert werden,
8. sensible Inhalte verschluesselt und aus Logs ausgeschlossen sind,
9. jeder interne Lese-, Export- und Statuszugriff nachvollziehbar ist,
10. Abschluss, Aufbewahrung, Legal Hold und Loeschung implementiert sind,
11. Datenschutzinformationen und betriebliche Prozesse freigegeben sind,
12. ein externer Sicherheitsreview keine kritischen offenen Befunde enthaelt.

## 24. Offene Entscheidungen vor der Programmierung

- Wird WorkDiary nur Softwarelieferant oder selbst Betreiber/Dienstleister der
  Meldestelle? **Entschieden: reiner Softwarelieferant (Auftragsverarbeiter/AVV).**
  Betrieb als verantwortliche Meldestelle ist ausgeschlossen (§4).
- SaaS, Private Cloud und On-Premise: Welche Betriebsmodelle erhalten das
  Modul? **Empfehlung: MVP nur SaaS** – Anonymitaet haengt an kontrollierter
  Krypto-, Scanner- und Log-Konfiguration; On-Premise spaeter, da Kunden-Infra
  das Anonymitaetsversprechen aushebeln kann.
- Werden freiwillige E-Mail-Benachrichtigungen fuer Reporter angeboten?
  **Empfehlung: Default aus** – E-Mail ist ein Deanonymisierungs- und
  Leak-Vektor; das Postfach ist der Kanal. Falls doch: nur Opt-in mit
  deutlicher Warnung.
- Welche Dateitypen und Maximalgroessen sind zulaessig? **Empfehlung:
  konservative Positivliste (PDF, gaengige Bilder, txt, gaengige Office),
  ca. 25 MB/Datei, max. 10 Dateien, Gesamt-Quota pro Fall.**
- Welcher Malware-Scanner steht in allen Betriebsmodellen zur Verfuegung?
  **Empfehlung: ClamAV als Baseline** (self-hostbar, in allen Modi verfuegbar);
  finale Wahl haengt an der Zielinfrastruktur. *Ops.*
- Welche konkrete Aufbewahrungsregel gilt pro Rechtsraum und Kunde?
  **Entschieden: 3 Jahre nach Abschluss (Default, HinSchG §11),** technisch pro
  Organisation konfigurierbar (`retention_months`, §9.1).
- Welche Personen duerfen Notfallzugriff genehmigen? **Empfehlung: eigene
  Permission, mindestens zwei benannte Personen pro Organisation, nie
  Admin/Support** (Constraints in §7.4/§25); die konkreten Personen sind
  Org-Konfiguration.
- Wie werden muendliche Meldungen und persoenliche Treffen organisatorisch
  dokumentiert? **Empfehlung: die zustaendige Person erfasst das Ergebnis mit
  Zustimmung der meldenden Person als interne Notiz/Nachricht im Fall** (§4/§7).
- Welches KMS oder Vault wird fuer die Zielverschluesselung verwendet?
  **Empfehlung: MVP entschieden (`WHISTLEBLOWING_KEY` + per-Fall-DEK, §10);
  Zielsystem an das Betriebsmodell koppeln** (SaaS → Cloud-KMS, On-Premise →
  Vault), abstrahiert ueber `WhistleblowingCryptoService`. *Ops, spaeter.*
- Werden Hinweisgeberdaten aus vollstaendigen Mandantenexporten und
  Supportdiagnosen technisch ausgeschlossen? **Entschieden: ja, standardmaessig
  ausschliessen** (Abschnitt 17 / 25).

## 25. Red-Team-Befunde und getroffene Sicherheitsentscheidungen

Ergebnis eines adversarialen Reviews. Die folgenden Punkte sind **entschieden**
und in die jeweiligen Abschnitte eingearbeitet; hier die konsolidierte
Begruendung. Severity: hoch / mittel / niedrig.

### Vor Phase 1 verbindlich (hoch)

- **Per-Fall-DEK + Crypto-Shredding schon im MVP** statt APP_KEY-Casts. Eigener
  `WHISTLEBLOWING_KEY`; jeder Fall hat einen gewrappten DEK (`dek_wrapped`).
  Loeschung = DEK vernichten → loest Krypto-Blast-Radius UND Backup-Loeschung in
  einem Zug. Aendert das Datenmodell, daher jetzt festgelegt. (9.2 / 10 / 16)
- **Postfach: Cookie-Sitzung statt Pfad-Token; Login nur per Geheimnis.**
  Pfad-Token landen in History / Access- / Error-Logs; der Fallcode als Eingabe
  waere ein Enumerations-Orakel. (7.2 / 13.1)
- **Datei-Pipeline gesandboxt** und Office/PDF-*Inhalts*metadaten (Autor,
  Revisionen) mit abdecken, nicht nur EXIF. (11)
- **Eigener Modul-Krypto-Key**, nicht APP_KEY; HMAC-Key fuer den Lookup getrennt
  und nicht im selben Backup-Schutzlevel. (10)

### Weitere Entscheidungen (mittel)

- **Notfallzugriff**: zeitlich begrenzt, auto-ablaufend, zwingend anderer
  Zweit-Genehmiger; Betroffenen-Check gilt auch hier; nie durch Admin/Support. (7.4)
- **Bearbeiter-als-Beschuldigter**: Freitext-Anschuldigungen loesen keine
  Auto-Sperre aus → externer Ersatzkontakt verpflichtend. (7.4)
- **Telescope / Debugbar / Ignition** fuer WB-Routen hart aus, auch in Staging;
  Queue / `failed_jobs` nie mit Klartext. (12)
- **Legal-Hold vs. Loeschlauf**: Hold atomar in der Loeschtransaktion pruefen
  (TOCTOU). (16)
- **Login-Timing / DoS**: Dummy-Argon2 bei Miss, Work-Factor getunt, hartes
  Rate-Limit. (19)

### Datensparsamkeit am Datenmodell (niedrig)

- **Zeit-geordnete IDs** (UUIDv7/ULID) leaken die Meldezeit → reporter-sichtbar
  nur voll-zufaellige IDs (UUIDv4). (9.2)
- **Cross-Case-Datei-Dedup** korreliert Quellen → Dedup nur fall-intern bzw.
  per-Case-Salt. (9.5)
- **`read_by_reporter_at`** ist ein Korrelationssignal → grob halten. (9.4)
- **Listen-Metadaten** koennen in sehr kleinen Orgs deanonymisieren →
  Kategorie/Volumen ggf. erst nach Zuweisung zeigen. (5 / 18)
- **Trackingfreier Bot-Schutz** (Honeypot / Proof-of-Work) statt
  Drittanbieter-CAPTCHA. (19)

### Residualrisiken (nicht rein technisch loesbar)

- Anonymitaet vs. Infra-Logs (Proxy / WAF / Hosting / CDN) – gehoert in Phase 0
  und die Betriebsdoku, nicht in den App-Code allein.
- Temporale Korrelation zwischen authentifizierter Sitzung und Meldung aus
  demselben Netz – per eigener Portal-Domain und Nutzerhinweis mindern.
- Stylometrie / einzigartige Fakten im Meldetext – nur durch Nutzerhinweis
  adressierbar.
