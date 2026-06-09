# Wo gehören Einstellungen hin?

> Entscheidungsregel für die Ablage von Einstellungen/Konfiguration. Ziel: **eine**
> klare Heimat je Art von Wert, keine Fragmentierung über mehrere parallele Mechanismen.

## Die drei Ebenen

### 1. Per-User-Präferenzen → `users.preferences`

JSON-Bag pro Benutzer, gemerged mit Defaults aus `config/personalization.php` über
`User::preferences()`.

- **Lesen:** `$user->getPreference('key', $default)` (inkl. Default-Merge)
- **Schreiben:** `$user->setPreference('key', $value)`
- **Default definieren:** `config/personalization.php` → `defaults`
- **Beispiele:** `theme`, `locale`, `date_format`, `time_format`, `startpage`, `timezone`,
  `work_mode`, Dashboard-Anpassung

Vorteil: wird mit dem User-Datensatz geladen (kein Extra-Query), typisch für UI-Präferenzen.

### 2. Global / pro-Organisation → `App\Support\Setting`

Mandantenbewusster Resolver. Auflösungsreihenfolge:

1. `organizations.settings[<group>][<rest>]` — Org-Override (über `Organization::Auditable`
   **auditiert**)
2. `config('<group>.<rest>')` — Datei-Default, env-überschreibbar
3. harter `$default`

- **Lesen:** `App\Support\Setting::get('pagination.customers', 25)`
- **Org-Override schreiben:** in `organizations.settings` (JSON) ablegen
- **Global-Default:** in `config/<group>.php` pflegen
- **Beispiele:** `pagination.*`, `validation.*`, `invoicing.*`, `numbering.*`,
  `personalization.*` (als Org-Default)

### 3. Typisiert / abfragbar / relational → eigene Spalte oder Tabelle

Wenn der Wert getypt, gefiltert/joinbar oder mengenhaft ist.

- **Beispiele:** `UserFilterPreset`, `UserBookmark`, getypte Spalten wie
  `organizations.locale` / `organizations.timezone`

## Sonderfall: Plugin-Secrets

`App\Models\PluginSetting` — Settings/Secrets **pro Plugin und Organisation**, das `settings`-JSON
ist `encrypted:array` (API-Keys landen nicht im Klartext in DB-Dumps). Nicht mit
`App\Support\Setting` (Resolver) verwechseln.

## Entscheidungsbaum

```
Gehört der Wert genau einem Benutzer (UI/Präferenz)?      → users.preferences (Ebene 1)
Plugin-Secret pro Organisation?                           → PluginSetting (verschlüsselt)
Global oder pro-Organisation tunbar (Limits, Defaults)?   → App\Support\Setting (Ebene 2)
Getypt / abfragbar / relational?                          → Spalte oder eigene Tabelle (Ebene 3)
```

## Bewusst NICHT

- **Keine** generische `settings`-Schlüssel-Wert-Tabelle als vierter Mechanismus — Ebene 2
  deckt global/Org bereits über Datei-Default + JSON-Override ab (eleganter als Schema-pro-Key,
  und Org-Änderungen sind bereits auditiert).
- **Keine** eigene Spalte für reine UI-Präferenzen (z. B. war `users.preferred_work_mode` eine
  Einzelspalte → konsolidiert nach `users.preferences['work_mode']`).
