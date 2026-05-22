# Branchenprofil IT-Service

Status: Aktiv (MVP-033, Issue #33) • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).
• Aufbauend auf:
[Kernklassifikationen](kernklassifikationen.md) (MVP-030),
[Kategorien pro Org](kategorien-org.md) (MVP-031),
[Pflichtklassifikationen](pflichtklassifikationen.md) (MVP-032),
[Prozedurvorlagen](prozedurvorlagen.md) (MVP-025).

## 1. Zweck

Erstes Referenzprofil: stellt für neue Organisationen eine sofort
nutzbare Konfiguration für IT-Service / Managed Services bereit
(Auftragstypen, Klassifikationen, Prozedurvorlagen, Protokoll-
Vorlagen, Pflichtregeln, Asset-Kategorien, Beispiel-Tags).

## 2. Profilbaustein-Struktur

Ein Profil ist deklarativ in `database/data/branchprofiles/it.php`
als Array hinterlegt und wird per Seeder /
`BranchProfileInstaller::install(org, "it")` in eine Organisation
ausgerollt.

```php
return [
    'code' => 'it',
    'label' => 'IT-Service / Managed Services',
    'version' => 1,
    'classifications' => [...],       // §3
    'classification_requirements' => [...], // §4
    'procedure_templates' => [...],   // §5
    'protocol_templates' => [...],    // §6
    'asset_categories' => [...],      // §7
    'tags_seed' => [...],
];
```

Installer ist **idempotent**: existierende Codes werden nicht
überschrieben (außer mit explizitem `--force`-Flag), neue werden
ergänzt. Installations-Audit:
`branch_profile.installed` mit `profile_code`, `version`, Anzahl
neuer Objekte pro Domäne.

## 3. Klassifikationen (Auszug)

| Domain          | Codes                                                                                     |
| --------------- | ----------------------------------------------------------------------------------------- |
| `entry_type`    | incident, request, change, problem, maintenance, advice                                   |
| `activity`      | analysis, configure, deploy, patch, backup, restore, monitor                              |
| `defect_type`   | hardware, software, network, security, user, integration                                  |
| `root_cause`    | bug, misconfiguration, capacity, hardwareFailure, dependency, externalProvider            |
| `result`        | resolved, workaround, knownIssue, deferred, escalated                                     |
| `product_group` | router, switch, firewall, accessPoint, server, workstation, printer, virtualization, saas |

## 4. Pflichtklassifikationen

- `entry_type=change`: Pflicht `defect_type` (wenn Anlass-bezogen
  via `only_if_json`) und `priority` `onCreate`; Pflicht
  `result` und `root_cause` `beforeComplete`.
- `entry_type=incident`: Pflicht `priority` `onCreate`; Pflicht
  `result`, `defect_type` `beforeComplete`.
- `entry_type=maintenance`: Pflicht `product_group` `onCreate`;
  Pflicht `result` `beforeComplete`.

Alle als `severity = hard`, außer `defect_type` bei `incident` →
`soft` (manchmal vor Diagnose unklar).

## 5. Prozedurvorlagen

Mit Code-Präfix `IT_`:

| Code                     | Anwendung                                  |
| ------------------------ | ------------------------------------------ |
| `IT_FW_UPDATE`           | Firmware-Update mit Backup + Funktionstest |
| `IT_PATCH_DEPLOY`        | Patch-Rollout (Pilot → Prod)               |
| `IT_INCIDENT_TRIAGE`     | Incident-Aufnahme, Klassifizierung, ETA    |
| `IT_NEW_CLIENT_ONBOARD`  | Onboarding eines neuen Endgeräts           |
| `IT_OFFBOARD_USER`       | Offboarding (Account, Geräte, Daten)       |
| `IT_BACKUP_RESTORE_TEST` | Quartalsweiser Restore-Test                |
| `IT_NETWORK_CHANGE`      | Netzwerk-Änderung mit Vier-Augen           |

Jede Vorlage enthält:

- `backup`-Schritt (sofern Change-Charakter),
- ggf. Vier-Augen-Schritt (MVP-028),
- Funktionstest mit `link_test`-Schritt,
- Dokumentations-Schritt mit Pflichtfeld `root_cause` oder `result`.

## 6. Protokollvorlagen

Mit Code-Präfix `IT_`:

| Code                   | Zweck                                       |
| ---------------------- | ------------------------------------------- |
| `IT_CHANGE_PROTOCOL`   | Change-Protokoll inkl. Rollback-Vermerk     |
| `IT_INCIDENT_REPORT`   | Incident-Bericht mit Zeitleiste             |
| `IT_HANDOVER_DEVICE`   | Geräte-Übergabe (Foto, Seriennr., Signatur) |
| `IT_MAINTENANCE_LOG`   | Wartungslog                                 |
| `IT_SECURITY_INCIDENT` | Security-Incident mit Eskalationsstufen     |

## 7. Asset-Kategorien (Vorbereitung für MVP-035)

`router`, `switch`, `firewall`, `accessPoint`, `server`,
`workstation`, `notebook`, `printer`, `vm`, `saasAccount`,
`monitor`, `phone`.

## 8. Tags-Seed

`#after-hours`, `#oncall`, `#critical-customer`, `#prod`, `#dev`,
`#test`, `#emergency-change`, `#planned-change`.

## 9. Installation

- Beim Onboarding (siehe MVP-048) wählt Org-Admin ein Profil.
- Profile sind kombinierbar: zuerst `it`, später z. B. `handwerk`
  zusätzlich (Konflikt = identischer Code → Skip, Audit-Note).
- Nach Installation Hinweis-Karte „Profil 'IT-Service' installiert,
  X neue Objekte. [Konfiguration prüfen]".

## 10. Permissions

| Permission                  | Wer                                                                    |
| --------------------------- | ---------------------------------------------------------------------- |
| `branchProfile.install`     | Org-Admin                                                              |
| `branchProfile.viewCatalog` | Org-Admin                                                              |
| `branchProfile.uninstall`   | Plattform-Admin (mit Vorsicht — nur, wenn keine Referenzen existieren) |

## 11. Akzeptanzkriterien

1. `database/data/branchprofiles/it.php` enthält alle Inhalte aus
   §3–§8 vollständig.
2. `BranchProfileInstaller::install(org, "it")` ist idempotent,
   meldet Anzahl neuer Objekte pro Domäne.
3. Tests: zweimal install → keine Duplikate, Audit korrekt.
4. Nach Install sind im Org-Kontext die Klassifikationen, Pflicht-
   regeln, Vorlagen und Asset-Kategorien aktiv.
5. Onboarding-Wizard (MVP-048) listet das Profil mit Beschreibung.

## 12. Out-of-scope (MVP-033)

- Profil-Updates (Versions-Migration) — Folge.
- Profil-Marketplace — Folge.
- Demo-Daten-Generierung (siehe MVP-050).

## 13. Folge

- MVP-034 Branchenprofil Handwerk/Service.
