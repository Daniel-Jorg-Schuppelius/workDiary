{{--
  Created on   : Fri Jul 31 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _standard_filters.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Standard-Filterset der Auswertungen (Feature 002) — gehört in die
    <x-filter-bar> der jeweiligen Report-Seite. Rendert nur die in
    $filterFields aktivierten Felder; Seiten behalten ihre Spezialfelder
    (z. B. Bereich mine/team) daneben.

    Erwartet:
      - $standardFilters : \App\Services\Reporting\ReportFilters
      - $filterFields    : list<string> (customer|project|user|team|entry_type|status)
      - $filterCustomers/$filterProjects/$filterUsers/$filterTeams/$filterEntryTypes
                         : Collections aus ResolvesStandardReportFilters::standardFilterOptions()
      - $statusOptions   : array<string, string> (Wert => Label; nur mit Feld status)
      - $statusLabel     : ?string (Feldbeschriftung, Default „Status")
      - $idPrefix        : string (eindeutige Feld-IDs, Default "rep")

    Der Zeitraum kommt global aus dem Header-Widget (DateRangeContext) und
    wird hier nur als Chip angezeigt — keine zweite Quelle der Wahrheit.
--}}
@php
    /** @var \App\Services\Reporting\ReportFilters $standardFilters */
    $idPrefix = $idPrefix ?? 'rep';
    $filterFields = $filterFields ?? [];
    $range = app(\App\Services\UI\DateRangeContext::class)->current();
@endphp

<span class="inline-flex shrink-0 items-center gap-1 rounded-full border border-base-300 bg-base-200/60 px-3 py-1 text-xs text-base-content/70"
      title="{{ __('Zeitraum — über die Zeitraumwahl im Seitenkopf ändern.') }}">
    <x-icon name="date_range" class="text-sm" />
    {{ $range['label'] }}
</span>

@if (in_array('customer', $filterFields, true))
    <x-filter-field :label="__('Kunde')" for="{{ $idPrefix }}-customer" class="min-w-44">
        <select id="{{ $idPrefix }}-customer" name="customer" class="select select-sm select-bordered w-full" data-autosubmit>
            <option value="">{{ __('Alle Kunden') }}</option>
            @foreach ($filterCustomers ?? [] as $option)
                <option value="{{ $option->sqid }}" @selected($standardFilters->customerId === $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    </x-filter-field>
@endif

@if (in_array('project', $filterFields, true))
    <x-filter-field :label="__('Projekt')" for="{{ $idPrefix }}-project" class="min-w-44">
        <select id="{{ $idPrefix }}-project" name="project" class="select select-sm select-bordered w-full" data-autosubmit>
            {{-- Reports, die genau EIN Projekt zeigen (z. B. Projekt-Details),
                 setzen projectRequired=true → keine sinnlose „Alle"-Option. --}}
            @unless ($projectRequired ?? false)
                <option value="">{{ __('Alle Projekte') }}</option>
            @endunless
            {{-- Gruppierung nur ohne Kundenfilter (sonst ist der Kunde schon gewählt). --}}
            <x-project-options :projects="$filterProjects ?? collect()"
                :group="$standardFilters->customerId === null"
                :selected="\App\Support\Sqid::encode(\App\Models\Project::class, $standardFilters->projectId)" />
        </select>
    </x-filter-field>
@endif

@if (in_array('user', $filterFields, true))
    <x-filter-field :label="__('Mitarbeiter')" for="{{ $idPrefix }}-user" class="min-w-44">
        <select id="{{ $idPrefix }}-user" name="user" class="select select-sm select-bordered w-full" data-autosubmit>
            <option value="">{{ __('Alle Mitarbeitenden') }}</option>
            @foreach ($filterUsers ?? [] as $option)
                <option value="{{ $option->sqid }}" @selected($standardFilters->userId === $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    </x-filter-field>
@endif

@if (in_array('team', $filterFields, true))
    <x-filter-field :label="__('Team')" for="{{ $idPrefix }}-team" class="min-w-40">
        <select id="{{ $idPrefix }}-team" name="team" class="select select-sm select-bordered w-full" data-autosubmit>
            <option value="">{{ __('Alle Teams') }}</option>
            @foreach ($filterTeams ?? [] as $option)
                <option value="{{ $option->sqid }}" @selected($standardFilters->teamId === $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    </x-filter-field>
@endif

@if (in_array('entry_type', $filterFields, true))
    <x-filter-field :label="__('Auftragstyp')" for="{{ $idPrefix }}-entry-type" class="min-w-40">
        <select id="{{ $idPrefix }}-entry-type" name="entry_type" class="select select-sm select-bordered w-full" data-autosubmit>
            <option value="">{{ __('Alle Typen') }}</option>
            @foreach ($filterEntryTypes ?? [] as $option)
                <option value="{{ $option->sqid }}" @selected($standardFilters->entryTypeId === $option->id)>{{ $option->label }}</option>
            @endforeach
        </select>
    </x-filter-field>
@endif

@if (in_array('status', $filterFields, true))
    <x-filter-field :label="$statusLabel ?? __('Status')" for="{{ $idPrefix }}-status" class="min-w-36">
        <select id="{{ $idPrefix }}-status" name="status" class="select select-sm select-bordered w-full" data-autosubmit>
            <option value="">{{ __('Alle') }}</option>
            @foreach ($statusOptions ?? [] as $value => $label)
                <option value="{{ $value }}" @selected($standardFilters->status === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-filter-field>
@endif

{{-- Feature 002: org-weit ausgeblendete Kunden (customers.exclude_from_reports)
     temporär einbeziehen — Toggle erscheint nur, wenn es solche Kunden gibt. --}}
@if (in_array('include_excluded', $filterFields, true) && ($hasExcludedCustomers ?? false))
    <x-filter-toggle name="include_excluded" :id="$idPrefix . '-include-excluded'"
                     :label="__('Ausgeblendete Kunden einbeziehen')"
                     :title="__('Kunden mit „In Auswertungen ausblenden“ mit anzeigen.')"
                     :checked="$standardFilters->includeExcludedCustomers" data-autosubmit />
@endif
