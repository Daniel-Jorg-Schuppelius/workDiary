# Security-Release-Gate — Prüfprotokoll und ASVS-Kontrollmatrix

Feature 051 (`MVP-097`–`MVP-101`). Dieses Dokument bündelt Angriffsflächen-
Inventar, ASVS-5.0-Kontrollmatrix, den automatisierten Gate-Lauf und den
Behebungs-/Freigabeprozess für den MVP-Release-Kandidaten.

> Status: **In Bearbeitung.** Der automatisierte Teil (MVP-098) ist eingerichtet
> und grün; das Inventar und die Kontrollmatrix (MVP-097) sind angelegt. Die
> manuelle Whitebox-/dynamische Prüfung (MVP-099), die 2FA-Vertiefung (MVP-100)
> und der **unabhängige Penetrationstest (MVP-101)** stehen aus und sind
> Voraussetzung für die formale Produktivfreigabe.

## 1. Eingaben / Regression-Baseline

- [Sicherheitsaudit 2026-06](./audit-2026-06.md)
- [Sicherheitshärtung 2026-06](./hardening-2026-06.md)
- [Rollen-Matrix](./rollen-matrix.md), [Tenant-Audit 2026](./tenant-audit-2026.md)
- [Kundenportal-Guard](./customer-portal-guard.md), [Supportzugriff-Grundsätze](./supportzugriff-grundsaetze.md)
- ADRs: [Anhang-Pfade](./adr-attachment-paths.md), [Export-Autorisierung](./adr-export-authorization.md)

## 2. Angriffsflächen-Inventar (MVP-097)

Stand des Release-Kandidaten (Commit beim Gate-Lauf eintragen):

| Fläche | Umfang (Stand 2026-06-28) | Quelle |
| --- | --- | --- |
| Web-/API-Routen | ~1037 Routen | `routes/web.php` |
| Controller | ~287 | `app/Http/Controllers/**` |
| Policies | ~96 | `app/Policies/**` |
| Form-Requests (Validierung) | ~65 | `app/Http/Requests/**` |
| Middleware | ~15 | `app/Http/Middleware/**` |
| Interaktive Guards | `web`, `customer` (+ Session-Provider) | `config/auth.php` |
| Queue-Jobs / Commands | 2 / 38 | `app/Jobs`, `app/Console/Commands` |
| Mandanten-Scope | `OrganizationScope` + `BelongsToOrganization` global | `app/Models/Scopes`, `app/Models/Concerns` |
| Datei-Upload/Parse | Bank (CAMT/MT940), Katalog (CSV/BMEcat/Datanorm/Shopinfo), **GAEB DA XML** | Finance/Procurement/Gaeb |
| Export | DATEV/Lexoffice/PDF/CSV, Bestellungen (XBestellung/Order-X), **GAEB-Export** | diverse `*ExportService` |
| Verschlüsselung at-rest | PII/2FA encrypted (APP_KEY), Crypto-Shredding (Hinweisgeber/Datenschutz) | `project_encryption_at_rest` |
| Audit | Hash-Ketten, append-only (`audit_logs`, org-audit) | `Auditable`, `audit:verify` |

Neue oder im Inventar fehlende Flächen blockieren den Abschluss, bis sie
klassifiziert und geprüft sind. **Neu seit letztem Audit:** GAEB-Import/-Export
(Feature 049) — XML-Upload/Parse + Datei-Export; siehe Kontrollmatrix-Zeile
„Datei/XML".

## 3. ASVS 5.0 Kontrollmatrix (Level 2, risikobasiert L3)

Ergebnis je Anforderung: ✅ erfüllt · ⏳ manuell zu verifizieren · n/a (mit
Begründung).

| ASVS-Bereich | Projekt-Mechanismus | Ergebnis |
| --- | --- | --- |
| V1 Architektur / Vertrauensgrenzen | Org-Scope, Guards (web/customer), Plugin-Sandbox | ⏳ Bedrohungsmodell (MVP-099) |
| V2 Authentifizierung & 2FA (L3) | TOTP + Mail-OTP + WebAuthn (`two_factor_credentials`), org-weite Pflicht | ⏳ Vertiefung (MVP-100) |
| V3 Session-Management | Laravel-Session, Re-Auth für sensible Aktionen, Token-Widerruf | ⏳ Negativtests (MVP-100) |
| V4 Zugriffskontrolle / IDOR (L3) | Policies (~96), `ExistsInCurrentOrganization`, `abort_unless org_id` | ⏳ Pro Rolle/Guard (MVP-099) |
| V5 Validierung / Injection / XSS | Form-Requests, Eloquent-Bindings, Blade-Escaping | ⏳ + ✅ Form-Requests |
| V5.x XML-/Datei-Verarbeitung | **GAEB-Parser: DOCTYPE-Reject + LIBXML_NONET (kein XXE)**, Import-Preflight | ✅ XXE gehärtet + Regressionstest |
| V6 Kryptografie / Secrets (L3) | encrypted Casts, APP_KEY, Crypto-Shredding, Secret-Scan | ⏳ Secret-Scan (MVP-098/099) |
| V7 Fehler / Logging | Audit-Hash-Kette, keine Secrets im Log | ⏳ Informationsabfluss (MVP-099) |
| V8 Datenschutz / Export (L3) | Export-Autorisierung-ADR, Mandanten-Scope | ⏳ Massenexport-Missbrauch (MVP-099) |
| V10 Konfiguration / Header | CSP-Nonce-Flag, Härtungs-Doku | ⏳ produktionsnahe Konfig (MVP-099) |
| V13 Abhängigkeiten | composer/npm audit, SBOM | ✅ automatisiert (MVP-098) |

## 4. Automatisiertes Gate (MVP-098)

Reproduzierbar über `composer security:gate` (`scripts/security-gate.sh`) bzw.
den CI-Job **Security gate**:

| Prüfung | Befehl | Stand 2026-06-28 |
| --- | --- | --- |
| Composer-Advisories | `composer audit` | ✅ keine Advisories |
| NPM-Advisories (prod) | `npm audit --omit=dev` | ✅ 0 vulnerabilities |
| Code-Stil | `composer format` (pint) | ✅ |
| Statische Analyse | `composer lint` (PHPStan L8) | ✅ |
| SBOM (CycloneDX) | `composer sbom` | ✅ 307 Komponenten (190 Composer + 117 npm) |
| Testsuite | `composer test` | im `tests`-CI-Job |

### SBOM (CycloneDX 1.5)

`composer sbom` (→ `scripts/generate-sbom.php`) erzeugt eine kombinierte
CycloneDX-1.5-SBOM aus `composer.lock` und `package-lock.json`. Sie ist
selbsttragend (keine zusätzliche Abhängigkeit, kein Netzwerk) und
**deterministisch** (nach PURL sortiert, ohne Zeitstempel/Seriennummer) — gleicher
Lock-Stand ⇒ gleicher Inhalt. Standardziel `storage/app/sbom.cdx.json`
(gitignoriert); im CI wird sie als Artefakt `sbom-cyclonedx` hochgeladen und kann
gegen Advisories (z. B. OSV/Dependency-Track) abgeglichen werden.

Secret-Historie-Scan (z. B. `gitleaks`) bleibt als nächster
Automatisierungsschritt offen.

## 5. Befund- und Behebungsprozess

Pro bestätigtem Befund: Ursache beheben → gleichartige Stellen prüfen →
Regressionstest → Auswirkungsbewertung → Vier-Augen-Review + Re-Test.
Kritische/hohe/mittlere Befunde sperren die Freigabe ausnahmslos.

Bisher behoben:

- **GAEB-XML XXE-Härtung** (Feature 049/051): DOCTYPE-Ablehnung + `LIBXML_NONET`,
  keine Entity-Substitution. Regressionstest
  `tests/Unit/Gaeb/GaebDaXmlParserTest::test_rejects_doctype_to_prevent_xxe`.

## 6. Definition of Done — Restpunkte

- [ ] MVP-099 Manuelle Whitebox-/dynamische Prüfung der gesamten Anwendung
- [ ] MVP-100 2FA-/Session-/Recovery-Vertiefung beider Guards inkl. Umgehung
- [ ] MVP-101 Unabhängiger Penetrationstest + formale Freigabe
- [ ] Secret-Historie-Scan automatisiert (gitleaks)
- [x] SBOM (CycloneDX) automatisiert + als CI-Artefakt
- [x] MVP-097 Inventar + ASVS-Kontrollmatrix angelegt
- [x] MVP-098 Automatisierte Abhängigkeits-/Stil-/Analyseprüfungen in CI + Gate-Skript
