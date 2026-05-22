# ADR: Attachment-Speicherpfade ohne `organization_id`-Pr&auml;fix

**Status:** Akzeptiert (Mai 2026, im Rahmen MVP-001)
**Bezug:** [tenant-audit-2026.md](./tenant-audit-2026.md),
[Feature 015](../features/015-mandantenfaehigkeit-betriebsmodelle.md),
[Feature 011 Datei-Anh&auml;nge](../features/011-datei-anhaenge-pdf-upload.md)

## Kontext

`attachments.path` enth&auml;lt aktuell **keinen** Mandantenpr&auml;fix; Dateien liegen unter
generischen Pfaden auf dem konfigurierten Storage-Disk. Diskussion im Rahmen
MVP-001 (Mandantensicherheits-Audit): Soll der Pfad k&uuml;nftig auf
`orgs/{organization_id}/...` migriert werden, um eine zus&auml;tzliche
physische Trennung zu erreichen?

## Entscheidung

**Wir behalten die aktuelle Pfadstruktur** und sichern Mandantengrenzen weiterhin
ausschlie&szlig;lich auf Anwendungsebene ab. Eine Migration auf einen
org-pr&auml;fixierten Pfad wird in ein eigenes Folge-Issue ausgelagert
und ist **nicht Teil des MVP**.

## Begr&uuml;ndung

Die Mandantentrennung der Attachments ist heute durch eine
**Defense-in-Depth-Kette** gew&auml;hrleistet:

1. **Global Scope** auf `Attachment` (Trait `BelongsToOrganization`) — verhindert
   bereits den lesenden Direktzugriff auf fremde Datens&auml;tze
   ([OrganizationScope](../../app/Models/Scopes/OrganizationScope.php)).
2. **Policy** [`AttachmentPolicy`](../../app/Policies/AttachmentPolicy.php) —
   pr&uuml;ft im Controller-Path zus&auml;tzlich, dass der Aufrufer dem Anhang &uuml;ber
   die `attachable`-Beziehung legitimiert ist.
3. **Signierte URLs** mit kurzer Laufzeit
   ([AttachmentController::downloadUrl()](../../app/Http/Controllers/AttachmentController.php))
   und expliziter `hasValidSignature()`-Pr&uuml;fung im Download-Endpunkt.

Diese drei Schichten sind durch die neue Test-Suite unter
`tests/Feature/Tenant/AttachmentTenantTest.php` abgedeckt; sowohl
Cross-Org-Direkt-Lookups als auch signierte Cross-Org-Downloads sowie
unsignierte Direktzugriffe scheitern dort verl&auml;sslich.

### Gegen eine sofortige Migration spricht:

- **Datenmigration ist riskant:** Bestehende Dateien m&uuml;ssten umkopiert/umbenannt
  werden, Backups und externe Referenzen (signierte URLs, Mail-Archive) k&ouml;nnten
  brechen. Ohne Down-Time-Plan und Backfill-Job ist das nichts f&uuml;r MVP-Scope.
- **Vorlieben des Storage-Drivers:** Nicht jeder Disk-Treiber (S3, lokal) macht
  das Pr&auml;fix gleich teuer/sicher; ein sauberer Schnitt braucht eine
  abgestimmte Storage-Strategie inkl. Lifecycle-Regeln.
- **Kein konkreter Bedrohungsfall offen:** Die Audit-Suite belegt, dass die
  drei Verteidigungslinien greifen. Ein zus&auml;tzlicher Pfad-Pr&auml;fix bringt erst
  dann echten Mehrwert, wenn **direkter** Storage-Zugriff (z. B. via
  S3-Bucket-Policies, per-Tenant-Pre-signed-URLs eines Cloud-Anbieters,
  separate Disks pro Mandant) eingef&uuml;hrt werden soll.

### F&uuml;r eine sp&auml;tere Migration spricht:

- **Zus&auml;tzliche Defense-in-Depth** f&uuml;r den hypothetischen Fall eines Bugs in
  Scope/Policy/Signatur.
- Vorbereitung f&uuml;r **Per-Tenant-Storage-Buckets** (S3, MinIO), die Pfadpr&auml;fixe
  als IAM-Boundary nutzen.
- **Operations-Vereinfachung** (Backup/Restore eines einzelnen Mandanten,
  Quoten je Mandant).

Sobald einer dieser Punkte konkret wird, &ouml;ffnen wir ein Folge-Issue und ziehen
die Migration nach. Das Issue muss enthalten:

- Backfill-Job f&uuml;r bestehende Datei-Pfade (mit Resume-F&auml;higkeit).
- Pfadschema-Definition (`orgs/{org_id}/{collection}/{yyyy}/{mm}/{uuid}.{ext}`).
- Migrationsstrategie f&uuml;r lange laufende signierte URLs (Rewrite-Layer w&auml;hrend
  &Uuml;bergang).
- Erweiterung der `AttachmentTenantTest`-Suite um Pfad-Assertion.

## Konsequenzen

- `attachments.path` bleibt unver&auml;ndert; keine Schema-Migration n&ouml;tig.
- Neue Anh&auml;nge folgen weiterhin der bestehenden Verzeichnislogik (Date-Buckets).
- Die Audit-Suite (`tests/Feature/Tenant/AttachmentTenantTest.php`) bleibt die
  Pflicht-Regression. Bei jedem Refactoring an Attachment-Download-Pfaden ist
  die Suite gr&uuml;n zu halten.
- F&uuml;r externe Audits/Pen-Tests wird in der Dokumentation klar ausgewiesen,
  dass die Mandantentrennung **ausschlie&szlig;lich auf Anwendungsebene** stattfindet.
- Folge-Issue f&uuml;r Pfad-Migration wird im Backlog gef&uuml;hrt, ohne MVP-Bezug.
