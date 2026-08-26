{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : packages.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Vergabeunterlagen') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Vergabeunterlagen'))

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $proposals */
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <div class="text-sm text-base-content/70">
                {{ __('Ein Paket enthält meist mehr als das Leistungsverzeichnis — erkannte GAEB-Dateien bleiben als Vorschlag liegen, bis sie jemand prüft.') }}
            </div>
            <x-slot:actions>
                <x-icon-btn icon="list_alt" size="sm" :href="route('bill-of-quantities.index')" show-label>{{ __('Leistungsverzeichnisse') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($canImport)
        <x-card :title="__('Paket einlesen')">
            <form method="POST" action="{{ route('bill-of-quantities.packages.store') }}" enctype="multipart/form-data"
                  class="flex flex-wrap items-end gap-2">
                @csrf
                <label class="fieldset flex-1 min-w-64">
                    <span class="label-text text-xs">{{ __('ZIP-Paket oder GAEB-Datei') }}</span>
                    <input type="file" name="file" required class="file-input file-input-sm file-input-bordered w-full">
                </label>
                <label class="fieldset">
                    <span class="label-text text-xs">{{ __('Vergabevorgang') }}</span>
                    <select name="opportunity" class="select select-sm select-bordered w-64">
                        <option value="">{{ __('— ohne Zuordnung —') }}</option>
                        @foreach ($opportunities as $opportunity)
                            <option value="{{ $opportunity->sqid }}">{{ $opportunity->title }}</option>
                        @endforeach
                    </select>
                </label>
                <x-icon-btn icon="unarchive" tone="primary" size="sm" type="submit" show-label>{{ __('Paket einlesen') }}</x-icon-btn>
            </form>
            <p class="mt-2 text-xs text-muted">
                {{ __('Ohne zugeordneten Vergabevorgang werden nur GAEB-Dateien übernommen — Restdokumente hätten keine Akte, an die sie gehören.') }}
            </p>
        </x-card>
    @endif

    <x-card :title="__('Erkannte GAEB-Dateien')">
        @if ($proposals->total() === 0)
            <x-empty-state icon="folder_zip" compact
                           :title="__('Keine offenen Vorschläge.')"
                           :message="__('Aus eingelesenen Paketen erkannte Leistungsverzeichnisse erscheinen hier zur Prüfung.')" />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Datei') }}</th>
                        <th>{{ __('Paket') }}</th>
                        <th>{{ __('Format') }}</th>
                        <th>{{ __('Phase') }}</th>
                        <th>{{ __('Vergabevorgang') }}</th>
                        <th class="text-right"></th>
                    </tr>
                </x-slot:head>
                @foreach ($proposals as $proposal)
                    <tr>
                        <td class="font-medium">{{ $proposal->filename }}</td>
                        <td class="text-base-content/70">{{ $proposal->package_name ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ $proposal->source_format ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ $proposal->phase?->value ?? '—' }}</td>
                        <td class="text-base-content/70">
                            @if ($proposal->opportunity)
                                <a class="link" href="{{ route('tenders.show', $proposal->opportunity) }}">{{ $proposal->opportunity->title }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                @if ($canImport)
                                    <form method="POST" action="{{ route('bill-of-quantities.packages.accept', $proposal) }}"
                                          class="flex items-center gap-1">
                                        @csrf
                                        <select name="project" class="select select-xs select-bordered w-40"
                                                aria-label="{{ __('Projekt') }}">
                                            <option value="">{{ __('— ohne Projekt —') }}</option>
                                            <x-project-options :projects="$projects" />
                                        </select>
                                        <x-icon-btn icon="download_done" tone="primary" size="xs" type="submit" show-label>{{ __('Importieren') }}</x-icon-btn>
                                    </form>
                                    <x-action-form :action="route('bill-of-quantities.packages.discard', $proposal)" method="DELETE"
                                                   :confirm="__('Vorschlag verwerfen? Die abgelegte Datei wird gelöscht.')"
                                                   :confirm-label="__('Verwerfen')" confirm-tone="error">
                                        <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('Verwerfen')" />
                                    </x-action-form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>

            <x-pagination :paginator="$proposals" standing />
        @endif
    </x-card>
</x-page-shell>
@endsection
