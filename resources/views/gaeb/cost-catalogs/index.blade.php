{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Baukostenkataloge') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Baukostenkataloge'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Kennwerte als Nachschlagewerk — was ein Bauteil üblicherweise kostet.')">
    @if ($canManage)
        <x-card :title="__('Katalog einlesen')">
            <form method="POST" action="{{ route('cost-catalogs.store') }}" enctype="multipart/form-data"
                  class="flex flex-wrap items-end gap-2">
                @csrf
                <label class="fieldset flex-1 min-w-64">
                    <span class="label-text text-xs">{{ __('GAEB X50') }}</span>
                    <input type="file" name="file" required class="file-input file-input-sm file-input-bordered w-full">
                </label>
                <label class="fieldset">
                    <span class="label-text text-xs">{{ __('Bezeichnung') }}</span>
                    <input name="name" maxlength="200" class="input input-sm input-bordered w-64"
                           placeholder="{{ __('aus der Datei übernehmen') }}">
                </label>
                <x-icon-btn icon="upload_file" tone="primary" size="sm" type="submit" show-label>{{ __('Einlesen') }}</x-icon-btn>
            </form>
        </x-card>
    @endif

    @if ($catalogs->isEmpty())
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">price_change</span>'
                       :title="__('Noch kein Baukostenkatalog vorhanden.')"
                       :message="__('Ein Baukostenkatalog (GAEB X50) liefert Kennwerte für die frühen Kostenstufen — Kostenschätzung und -berechnung, für die aus dem eigenen Bestand keine Zahlen vorliegen.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Bezeichnung') }}</th>
                    <th>{{ __('Stand') }}</th>
                    <th>{{ __('Nummernform') }}</th>
                    <th class="text-right">{{ __('Elemente') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @foreach ($catalogs as $catalog)
                <tr class="hover">
                    <td class="font-medium">
                        <a class="link" href="{{ route('cost-catalogs.show', $catalog) }}">{{ $catalog->name }}</a>
                        @unless ($catalog->active)<span class="wd-badge badge-outline">{{ __('Inaktiv') }}</span>@endunless
                    </td>
                    <td class="text-base-content/70 tabular-nums">{{ $catalog->valid_on?->format('d.m.Y') ?? '—' }}</td>
                    {{-- X50.2 nummeriert vollständig, X50.1 in Teilen — der Export
                         muss dieselbe Form wählen. --}}
                    <td class="text-xs text-base-content/70">{{ $catalog->full_element_numbers ? __('vollständig (X50.2)') : __('in Teilen (X50.1)') }}</td>
                    <td class="text-right tabular-nums">{{ $catalog->elements_count }}</td>
                    <td class="text-right">
                        <div class="flex items-center justify-end gap-1">
                            <x-icon-btn icon="download" size="sm" :href="route('cost-catalogs.export', $catalog)"
                                        :title="__('Als GAEB X50 ausgeben')" />
                            @if ($canManage)
                                <x-action-form :action="route('cost-catalogs.destroy', $catalog)" method="DELETE"
                                               :confirm="__('Baukostenkatalog löschen? Die Kennwerte gehen verloren.')"
                                               :confirm-label="__('Löschen')" confirm-tone="error">
                                    <x-icon-btn icon="delete" size="sm" tone="error" type="submit" :title="__('Löschen')" />
                                </x-action-form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>
@endsection
