{{--
    Auftragsbuch-Filterfelder — gemeinsam für /diary und /duties?tab=diary.
    Erwartet:
      - $filters    : array (aktuelle Filterwerte)
      - $allTags    : Collection (optional)
      - $entryTypes : Collection (optional)
      - $idPrefix   : string (eindeutige Feld-IDs, Default "diary")
    Hinweis: gehört in eine <x-filter-bar>. Reset/Action/Export setzt die
    jeweilige Seite, da diese sich unterscheiden.
--}}
@php($idPrefix = $idPrefix ?? 'diary')
<x-filter-field :label="__('Suche')" for="{{ $idPrefix }}-q" class="flex-1 min-w-52">
    <input id="{{ $idPrefix }}-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Inhalt oder Antwort …') }}" class="input input-bordered input-sm w-full">
</x-filter-field>
<x-filter-field :label="__('Status')" for="{{ $idPrefix }}-status" class="min-w-40">
    <select id="{{ $idPrefix }}-status" name="status" class="select select-bordered select-sm w-full">
        <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>{{ __('Alle') }}</option>
        <option value="2" @selected(($filters['status'] ?? '') === '2')>{{ __('Offen') }}</option>
        <option value="3" @selected(($filters['status'] ?? '') === '3')>{{ __('Problem') }}</option>
        <option value="1" @selected(($filters['status'] ?? '') === '1')>{{ __('Bestätigt') }}</option>
        <option value="-1" @selected(($filters['status'] ?? '') === '-1')>{{ __('Erledigt') }}</option>
    </select>
</x-filter-field>
@if (($allTags ?? collect())->isNotEmpty())
    <x-filter-field :label="__('Tag')" for="{{ $idPrefix }}-tag" class="min-w-36">
        <select id="{{ $idPrefix }}-tag" name="tag" class="select select-bordered select-sm">
            <option value="">—</option>
            @foreach ($allTags as $tag)
                <option value="{{ $tag->sqid }}" @selected((string) ($filters['tag'] ?? '') === $tag->sqid)>{{ $tag->name }}</option>
            @endforeach
        </select>
    </x-filter-field>
@endif
@if (($entryTypes ?? collect())->isNotEmpty())
    <x-filter-field :label="__('Typ')" for="{{ $idPrefix }}-entry-type" class="min-w-40">
        <select id="{{ $idPrefix }}-entry-type" name="entry_type" class="select select-bordered select-sm w-full">
            <option value="">{{ __('Alle Typen') }}</option>
            @foreach ($entryTypes as $type)
                <option value="{{ $type->sqid }}" @selected((string) ($filters['entry_type'] ?? '') === $type->sqid)>{{ $type->label }}</option>
            @endforeach
        </select>
    </x-filter-field>
@endif
<x-filter-field :label="__('Modus')" for="{{ $idPrefix }}-mode" class="min-w-40">
    <select id="{{ $idPrefix }}-mode" name="mode" class="select select-bordered select-sm w-full">
        <option value="">{{ __('Alle Modi') }}</option>
        <option value="{{ \App\Enums\Diary\Mode::Fixed->value }}" @selected(($filters['mode'] ?? '') === \App\Enums\Diary\Mode::Fixed->value)>{{ __('Terminiert') }}</option>
        <option value="{{ \App\Enums\Diary\Mode::Deadline->value }}" @selected(($filters['mode'] ?? '') === \App\Enums\Diary\Mode::Deadline->value)>{{ __('Deadline') }}</option>
        <option value="{{ \App\Enums\Diary\Mode::Window->value }}" @selected(($filters['mode'] ?? '') === \App\Enums\Diary\Mode::Window->value)>{{ __('Zeitfenster') }}</option>
        <option value="{{ \App\Enums\Diary\Mode::Recurring->value }}" @selected(($filters['mode'] ?? '') === \App\Enums\Diary\Mode::Recurring->value)>{{ __('Wiederkehrend') }}</option>
        <option value="{{ \App\Enums\Diary\Mode::Backlog->value }}" @selected(($filters['mode'] ?? '') === \App\Enums\Diary\Mode::Backlog->value)>{{ __('Backlog') }}</option>
    </select>
</x-filter-field>
<x-filter-field :label="__('Standort')" for="{{ $idPrefix }}-location" class="min-w-36">
    <select id="{{ $idPrefix }}-location" name="location" class="select select-bordered select-sm w-full">
        <option value="">{{ __('Alle Standorte') }}</option>
        @foreach (\App\Enums\Diary\LocationMode::cases() as $lm)
            <option value="{{ $lm->value }}" @selected(($filters['location'] ?? '') === $lm->value)>{{ $lm->label() }}</option>
        @endforeach
    </select>
</x-filter-field>
<label class="flex shrink-0 items-center gap-2 cursor-pointer">
    <input type="checkbox" id="{{ $idPrefix }}-mine" name="mine" value="1" @checked(!empty($filters['mine'])) class="toggle toggle-primary toggle-sm">
    <span class="text-sm text-base-content/75">{{ __('Nur meine') }}</span>
</label>
{{-- Drilldown-Filter (Projekt/Kunde) ohne eigenes Feld als Hidden erhalten. --}}
@if (! empty($filters['project']))<input type="hidden" name="project" value="{{ $filters['project'] }}">@endif
@if (! empty($filters['customer']))<input type="hidden" name="customer" value="{{ $filters['customer'] }}">@endif
