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
    <span class="material-symbols-outlined text-sm" aria-hidden="true">date_range</span>
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
            <option value="">{{ __('Alle Projekte') }}</option>
            @foreach ($filterProjects ?? [] as $option)
                <option value="{{ $option->sqid }}" @selected($standardFilters->projectId === $option->id)>{{ $option->name }}</option>
            @endforeach
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
    <label class="flex shrink-0 cursor-pointer items-center gap-2" for="{{ $idPrefix }}-include-excluded"
           title="{{ __('Kunden mit „In Auswertungen ausblenden“ mit anzeigen.') }}">
        <input type="checkbox" id="{{ $idPrefix }}-include-excluded" name="include_excluded" value="1"
               @checked($standardFilters->includeExcludedCustomers) class="toggle toggle-primary toggle-sm" data-autosubmit>
        <span class="text-sm text-base-content/75">{{ __('Ausgeblendete Kunden einbeziehen') }}</span>
    </label>
@endif
