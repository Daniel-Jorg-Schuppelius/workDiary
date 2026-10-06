{{--
  Created on   : Tue Jun 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : mappings.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('OpenProject – Zuordnungen'))
@section('nav-title', __('OpenProject'))

@php
    use App\Plugins\OpenProject\Services\OpenProjectStructureSync;

    $typeLabels = [
        OpenProjectStructureSync::EXT_TYPE_PROJECT => __('Projekt'),
        OpenProjectStructureSync::EXT_TYPE_WORK_PACKAGE => __('Work Package'),
        OpenProjectStructureSync::EXT_TYPE_USER => __('Benutzer'),
    ];
    $optionsByType = [
        OpenProjectStructureSync::EXT_TYPE_PROJECT => $projects,
        OpenProjectStructureSync::EXT_TYPE_WORK_PACKAGE => $tasks,
        OpenProjectStructureSync::EXT_TYPE_USER => $users,
    ];
@endphp

@section('content')
<x-index-page :title="__('OpenProject-Zuordnungen')" :subtitle="__('Gemerkte Zuordnungen zwischen OpenProject (Projekt, Work Package, Benutzer) und workDiary. Hier lassen sie sich auf ein anderes Ziel umlegen oder entfernen.')" back-route="admin.openproject.index" :back-label="__('Zurück')">
    <x-card>
        <x-validation-errors first class="mb-3" />

        @if ($mappings->isEmpty())
            <x-empty-state icon="link_off" :title="__('Noch keine Zuordnungen. Starten Sie einen Struktur-Abgleich.')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Typ') }}</th>
                        <th>{{ __('OpenProject-ID') }}</th>
                        <th>{{ __('Zugeordnet zu') }}</th>
                        <th class="text-right">{{ __('Aktion') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($mappings as $mapping)
                    @php $options = $optionsByType[$mapping->external_type] ?? []; @endphp
                    <tr>
                        <td>{{ $typeLabels[$mapping->external_type] ?? $mapping->external_type }}</td>
                        <td class="font-mono text-xs">
                            {{ data_get($mapping->payload, 'name', data_get($mapping->payload, 'subject', $mapping->external_id)) }}
                            <span class="text-muted">#{{ $mapping->external_id }}</span>
                        </td>
                        <td>{{ optional($mapping->referenceable)->name ?? optional($mapping->referenceable)->title ?? '—' }}</td>
                        <td>
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" action="{{ route('admin.openproject.mappings.update', $mapping->sqid) }}" class="flex items-center gap-1">
                                    @csrf
                                    <select name="target_id" class="select select-xs select-bordered">
                                        <option value="">{{ __('— Ziel wählen —') }}</option>
                                        @foreach ($options as $option)
                                            <option value="{{ $option['sqid'] }}">{{ $option['name'] }}</option>
                                        @endforeach
                                    </select>
                                    <x-button type="submit" tone="plain" size="xs">{{ __('Umlegen') }}</x-button>
                                </form>
                                <form method="POST" action="{{ route('admin.openproject.mappings.delete', $mapping->sqid) }}"
                                      data-confirm-dialog data-confirm-message="{{ __('Zuordnung wirklich entfernen?') }}">
                                    @csrf
                                    <x-button type="submit" tone="ghost" size="xs" class="text-error">{{ __('Entfernen') }}</x-button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-index-page>
@endsection
