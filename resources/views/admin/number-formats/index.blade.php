{{--
  Created on   : Wed May 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Nummernkreise'))
@section('nav-title', __('Nummernkreise'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Format pro Nummernkreis (Präfix, Jahr, Padding, Reset) für :org festlegen.', ['org' => $organization->name])">
    <x-slot:actions>
        <x-icon-btn icon="save" tone="primary" size="sm" type="submit" form="number-formats-form"
                    show-label>{{ __('Speichern') }}</x-icon-btn>
    </x-slot:actions>

    {{-- Ein Formular für alle Zeilen (MVP-1147); die Felder hängen über form="…" daran. --}}
    <form id="number-formats-form" method="POST" action="{{ route('admin.number-formats.update') }}" class="hidden">
        @csrf
        @method('PUT')
    </form>

    <div class="flex-none space-y-1">
        <p class="text-muted text-xs">
            {{ __('Hinweis: „Start bei" wird nur beim ersten Mal je Periode (Jahr) übernommen. Bestehende Sequenzen werden weitergezählt.') }}
        </p>
        <p class="text-muted text-xs">{{ __('numbering.source.page_hint') }}</p>
    </div>

    <div class="flex-none flex flex-wrap items-end gap-3">
        <label class="flex flex-col gap-1 text-xs">
            <span>{{ __('numbering.separators.prefix') }}</span>
            <input type="text" name="separators[prefix]" value="{{ old('separators.prefix', $separators['prefix']) }}"
                   maxlength="4" form="number-formats-form"
                   class="input input-sm input-bordered w-16 font-mono @error('separators.prefix') input-error @enderror" />
        </label>
        <label class="flex flex-col gap-1 text-xs">
            <span>{{ __('numbering.separators.year') }}</span>
            <input type="text" name="separators[year]" value="{{ old('separators.year', $separators['year']) }}"
                   maxlength="4" form="number-formats-form"
                   class="input input-sm input-bordered w-16 font-mono @error('separators.year') input-error @enderror" />
        </label>
        <x-button type="submit" tone="outline" size="sm" icon="done_all" form="number-formats-form"
                  name="apply_separators" value="1">{{ __('numbering.separators.apply') }}</x-button>
        <p class="text-muted text-xs basis-full">{{ __('numbering.separators.hint') }}</p>
    </div>

    <x-table scroll="flex" :pinRows="true" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string">{{ __('Nummernkreis') }}</x-table.th>
                <th>{{ __('numbering.source.column') }}</th>
                <th>{{ __('Präfix') }}</th>
                <th class="text-center">{{ __('Jahr') }}</th>
                <th class="text-center">{{ __('Padding') }}</th>
                <th class="text-center">{{ __('Jahres-Reset') }}</th>
                <th class="text-right">{{ __('Start bei') }}</th>
                <th>{{ __('Nächste Nummer') }}</th>
            </tr>
        </x-slot:head>
        @foreach ($rows as $row)
            @php
                $key = 'formats.' . $row['scope']->value;
                $name = 'formats[' . $row['scope']->value . ']';
                $label = $row['scope']->label();
                $locked = $row['lockedBy'] !== null;
                $lockHint = $locked ? __('numbering.preview.locked_hint', ['program' => $row['lockedBy']]) : null;
                $includeYear = filter_var(old($key . '.include_year', $row['format']->include_year), FILTER_VALIDATE_BOOL);
                $resetPerYear = filter_var(old($key . '.reset_per_year', $row['format']->reset_per_year), FILTER_VALIDATE_BOOL);
            @endphp
            <tr class="hover align-top">
                <td class="font-medium" data-sort-value="{{ $label }}">
                    {{ $label }}
                    @unless ($row['persisted'])
                        <x-status-badge tone="ghost" size="sm" class="ml-1">{{ __('Default') }}</x-status-badge>
                    @endunless
                </td>
                <td>
                    @if ($row['sourceOptions'] !== null)
                        <select name="{{ $name }}[source]" form="number-formats-form"
                                class="select select-sm select-bordered w-40 @error($key . '.source') select-error @enderror"
                                aria-label="{{ __('numbering.field.row', ['field' => __('numbering.source.column'), 'scope' => $label]) }}">
                            @foreach ($row['sourceOptions'] as $value => $optionLabel)
                                <option value="{{ $value }}" @selected(old($key . '.source', $row['source']) === $value)>{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                    @else
                        <span class="text-sm">{{ $row['issuer'] }}</span>
                    @endif
                    @foreach ($row['issuerNotes'] as $note)
                        <span class="block text-muted text-xs">{{ $note }}</span>
                    @endforeach
                </td>
                <td>
                    <div class="flex gap-1">
                        <input type="text" name="{{ $name }}[prefix]" value="{{ old($key . '.prefix', $row['format']->prefix) }}"
                               maxlength="16" form="number-formats-form" @disabled($locked)
                               title="{{ $lockHint ?? __('Präfix') }}"
                               aria-label="{{ __('numbering.field.row', ['field' => __('Präfix'), 'scope' => $label]) }}"
                               class="input input-sm input-bordered w-20 font-mono @error($key . '.prefix') input-error @enderror" />
                        <input type="text" name="{{ $name }}[prefix_separator]" value="{{ old($key . '.prefix_separator', $row['format']->prefix_separator) }}"
                               maxlength="4" form="number-formats-form" @disabled($locked)
                               title="{{ $lockHint ?? __('Trenner nach Präfix') }}"
                               aria-label="{{ __('numbering.field.row', ['field' => __('Trenner nach Präfix'), 'scope' => $label]) }}"
                               class="input input-sm input-bordered w-12 font-mono @error($key . '.prefix_separator') input-error @enderror" />
                    </div>
                </td>
                <td class="text-center">
                    <div class="flex items-center justify-center gap-1">
                        <input type="hidden" name="{{ $name }}[include_year]" value="0" form="number-formats-form" @disabled($locked) />
                        <input type="checkbox" name="{{ $name }}[include_year]" value="1" form="number-formats-form" @disabled($locked)
                               class="checkbox checkbox-sm" @checked($includeYear)
                               title="{{ $lockHint ?? __('Jahr') }}"
                               aria-label="{{ __('numbering.field.row', ['field' => __('Jahr'), 'scope' => $label]) }}" />
                        <input type="text" name="{{ $name }}[year_separator]" value="{{ old($key . '.year_separator', $row['format']->year_separator) }}"
                               maxlength="4" form="number-formats-form" @disabled($locked)
                               title="{{ $lockHint ?? __('Trenner nach Jahr') }}"
                               aria-label="{{ __('numbering.field.row', ['field' => __('Trenner nach Jahr'), 'scope' => $label]) }}"
                               class="input input-sm input-bordered w-12 font-mono @error($key . '.year_separator') input-error @enderror" />
                    </div>
                </td>
                <td class="text-center">
                    <input type="number" name="{{ $name }}[padding]" value="{{ old($key . '.padding', $row['format']->padding) }}"
                           min="1" max="10" form="number-formats-form" @disabled($locked)
                           title="{{ $lockHint ?? __('Padding') }}"
                           aria-label="{{ __('numbering.field.row', ['field' => __('Padding'), 'scope' => $label]) }}"
                           class="input input-sm input-bordered w-16 @error($key . '.padding') input-error @enderror" />
                </td>
                <td class="text-center">
                    <input type="hidden" name="{{ $name }}[reset_per_year]" value="0" form="number-formats-form" @disabled($locked) />
                    <input type="checkbox" name="{{ $name }}[reset_per_year]" value="1" form="number-formats-form" @disabled($locked)
                           class="checkbox checkbox-sm" @checked($resetPerYear)
                           title="{{ $lockHint ?? __('Jahres-Reset') }}"
                           aria-label="{{ __('numbering.field.row', ['field' => __('Jahres-Reset'), 'scope' => $label]) }}" />
                </td>
                <td class="text-right">
                    <input type="number" name="{{ $name }}[starts_at]" value="{{ old($key . '.starts_at', $row['format']->starts_at) }}"
                           min="0" form="number-formats-form" @disabled($locked)
                           title="{{ $lockHint ?? __('Start bei') }}"
                           aria-label="{{ __('numbering.field.row', ['field' => __('Start bei'), 'scope' => $label]) }}"
                           class="input input-sm input-bordered w-24 text-right @error($key . '.starts_at') input-error @enderror" />
                </td>
                <td class="text-xs">
                    @if ($row['preview'] !== null)
                        <span class="font-mono">{{ $row['preview'] }}</span>
                    @endif
                    @foreach ($row['details'] as $detail)
                        <span class="block text-muted">{{ $detail }}</span>
                    @endforeach
                </td>
            </tr>
        @endforeach
    </x-table>
</x-index-page>
@endsection
