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
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Format pro Nummernkreis (Präfix, Jahr, Padding, Reset) für :org festlegen.', ['org' => $organization->name])">
    <p class="text-muted text-xs flex-none">
        {{ __('Hinweis: „Start bei" wird nur beim ersten Mal je Periode (Jahr) übernommen. Bestehende Sequenzen werden weitergezählt.') }}
    </p>
    <x-table scroll="flex" :pinRows="true" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string">{{ __('Nummernkreis') }}</x-table.th>
                <th>{{ __('Präfix') }}</th>
                <th class="text-center">{{ __('Jahr') }}</th>
                <th class="text-center">{{ __('Padding') }}</th>
                <th class="text-center">{{ __('Jahres-Reset') }}</th>
                <th class="text-right">{{ __('Start bei') }}</th>
                <th>{{ __('Nächste Nummer') }}</th>
                <th class="text-right">{{ __('Aktion') }}</th>
            </tr>
        </x-slot:head>
        @foreach ($rows as $row)
            <tr class="hover align-top">
                <form method="POST" action="{{ route('admin.number-formats.update') }}" class="contents">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="scope" value="{{ $row['scope']->value }}" />
                    <td class="font-medium" data-sort-value="{{ $row['scope']->label() }}">
                        {{ $row['scope']->label() }}
                        @unless ($row['persisted'])
                            <x-status-badge tone="ghost" size="sm" class="ml-1">{{ __('Default') }}</x-status-badge>
                        @endunless
                    </td>
                    <td class="flex gap-1">
                        <input type="text" name="prefix" value="{{ $row['format']->prefix }}"
                               maxlength="16"
                               class="input input-sm input-bordered w-20 font-mono" />
                        <input type="text" name="prefix_separator" value="{{ $row['format']->prefix_separator }}"
                               maxlength="4"
                               class="input input-sm input-bordered w-12 font-mono"
                               title="{{ __('Trenner nach Präfix') }}" />
                    </td>
                    <td class="text-center">
                        <input type="hidden" name="include_year" value="0" />
                        <input type="checkbox" name="include_year" value="1"
                               class="checkbox checkbox-sm"
                               @checked($row['format']->include_year) />
                        <input type="text" name="year_separator" value="{{ $row['format']->year_separator }}"
                               maxlength="4"
                               class="input input-sm input-bordered w-12 font-mono ml-1"
                               title="{{ __('Trenner nach Jahr') }}" />
                    </td>
                    <td class="text-center">
                        <input type="number" name="padding" value="{{ $row['format']->padding }}"
                               min="1" max="10"
                               class="input input-sm input-bordered w-16" />
                    </td>
                    <td class="text-center">
                        <input type="hidden" name="reset_per_year" value="0" />
                        <input type="checkbox" name="reset_per_year" value="1"
                               class="checkbox checkbox-sm"
                               @checked($row['format']->reset_per_year) />
                    </td>
                    <td class="text-right">
                        <input type="number" name="starts_at" value="{{ $row['format']->starts_at }}"
                               min="0"
                               class="input input-sm input-bordered w-24 text-right" />
                    </td>
                    <td class="font-mono text-xs">{{ $row['preview'] }}</td>
                    <td class="text-right">
                        <x-icon-btn icon="save" tone="primary" size="sm" type="submit"
                                    show-label>{{ __('Speichern') }}</x-icon-btn>
                    </td>
                </form>
            </tr>
        @endforeach
    </x-table>
</x-index-page>
@endsection
