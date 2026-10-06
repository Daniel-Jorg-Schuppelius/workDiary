{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@include('partials.page-fill')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Gespeicherte Filter pro Ansicht.')">


        @if ($presets->total() === 0)
            <x-empty-state framed
                icon="filter_alt"
                :title="__('Noch keine Filter-Presets gespeichert.')" />
        @else
            <x-table :zebra="true" scroll="flex" :pinRows="true" table-sort="server"
                     :route="route('filter-presets.index')"
                     :current-sort="$sort"
                     :current-dir="$dir"
                     :sort-params="request()->except(['sort', 'dir', 'page'])">
                <x-slot:head>
                    <tr>
                        <x-table.th sort="scope" default>{{ __('Bereich') }}</x-table.th>
                        <x-table.th sort="name">{{ __('Name') }}</x-table.th>
                        <x-table.th sort="is_default">{{ __('Standard') }}</x-table.th>
                        <th class="text-right">{{ __('Aktionen') }}</th>
                    </tr>
                </x-slot:head>
                    @foreach ($presets as $preset)
                        <tr class="hover">
                            <td><x-status-badge size="md" outline>{{ $preset->scope }}</x-status-badge></td>
                            <td>{{ $preset->name }}</td>
                            <td>
                                @if ($preset->is_default)
                                    <x-icon name="check_circle" class="text-success" />
                                @endif
                            </td>
                            <td class="text-right">
                                <x-icon-btn icon="edit" tone="ghost"
                                            data-entry-modal-trigger
                                            :href="route('filter-presets.edit', $preset)"
                                            :label="__('Bearbeiten')" />
                                <form method="POST" action="{{ route('filter-presets.destroy', $preset) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" tone="ghost" size="xs" data-confirm-dialog data-confirm-message="{{ __('Wirklich löschen?') }}" icon="delete">{{ __('Löschen') }}</x-button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
            </x-table>
        @endif

        <x-pagination :paginator="$presets" standing />
    </x-index-page>
@endsection
