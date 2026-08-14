{{--
  Created on   : Thu Jul 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : maintenance-window-banner.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Geplante Wartungsfenster (MVP-055): Vorlauf-Banner ab announce_from
     für ALLE Nutzer des betroffenen Scopes; aktives Nur-Lesen-Fenster
     zeigt den Einschränkungshinweis (Vollsperre rendert ohnehin 503). --}}
@php
    $orgId = app()->bound('currentOrganization') && app('currentOrganization') instanceof \App\Models\Organization
        ? (int) app('currentOrganization')->id
        : null;
    $effective = \App\Models\MaintenanceWindow::effectiveFor($orgId);
    $upcoming = $effective === null ? \App\Models\MaintenanceWindow::upcomingFor($orgId) : null;
@endphp
@if ($effective !== null && $effective->read_only)
    <div role="alert" class="alert alert-warning rounded-none py-2 text-sm">
        <x-icon name="engineering" />
        <span>
            {{ __('maintenance.window.banner.read_only', ['to' => $effective->ends_at->format('d.m.Y H:i')]) }}
            @if ($effective->message) — {{ $effective->message }} @endif
        </span>
    </div>
@elseif ($upcoming !== null)
    <div role="alert" class="alert alert-info rounded-none py-2 text-sm">
        <x-icon name="engineering" />
        <span>
            {{ __('maintenance.window.banner.upcoming', [
                'from' => $upcoming->starts_at->format('d.m.Y H:i'),
                'to' => $upcoming->ends_at->format('d.m.Y H:i'),
            ]) }}
            @if ($upcoming->message) — {{ $upcoming->message }} @endif
        </span>
    </div>
@endif
