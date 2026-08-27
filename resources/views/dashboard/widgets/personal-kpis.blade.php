{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : personal-kpis.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Persönliche Kennzahlen" — Daten: App\Dashboard\Widgets\PersonalKpisWidget.
--}}
<div class="grid grid-cols-2 gap-3 md:grid-cols-4">
    <x-kpi-tile :label="__('Meine offenen Einträge')" :value="$kpi['open_entries'] ?? 0" />
    <x-kpi-tile :label="__('In Bearbeitung')" :value="$kpi['progress_entries'] ?? 0" />
    <x-kpi-tile :label="__('Anstehende Schichten')" :value="$kpi['upcoming_shifts'] ?? 0" />
    <x-kpi-tile :label="__('Anstehende Notdienste')" :value="$kpi['upcoming_emergencies'] ?? 0" />
</div>
