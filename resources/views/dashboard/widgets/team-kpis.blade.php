{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : team-kpis.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Team-Kennzahlen" (nur Admins) — Daten: TeamKpisWidget.
--}}
<div class="grid grid-cols-2 gap-3 md:grid-cols-4">
    <x-kpi-tile :label="__('Offen (Team)')" :value="$kpi['open_entries'] ?? 0" tone="info" />
    <x-kpi-tile :label="__('In Bearbeitung (Team)')" :value="$kpi['progress_entries'] ?? 0" tone="info" />
    <x-kpi-tile :label="__('Heute archiviert')" :value="$kpi['archived_today'] ?? 0" tone="info" />
    <x-kpi-tile :label="__('Mitarbeitende')" :value="$kpi['user_count'] ?? 0" tone="info" />
</div>
