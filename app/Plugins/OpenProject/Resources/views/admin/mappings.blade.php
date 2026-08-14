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
<x-page-shell>
    <div class="space-y-4">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('OpenProject-Zuordnungen') }}</h1>
                <a href="{{ route('admin.openproject.index') }}" class="btn btn-ghost btn-sm">{{ __('Zurück') }}</a>
            </div>
            <p class="mb-4 text-sm text-base-content/60">
                {{ __('Gemerkte Zuordnungen zwischen OpenProject (Projekt, Work Package, Benutzer) und workDiary. Hier lassen sie sich auf ein anderes Ziel umlegen oder entfernen.') }}
            </p>

            @if (session('status'))
                <div class="alert alert-success mb-3 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error mb-3 text-sm">{{ $errors->first() }}</div>
            @endif

            @if ($mappings->isEmpty())
                <p class="rounded-box border border-base-300 p-6 text-center text-sm text-base-content/60">
                    {{ __('Noch keine Zuordnungen. Starte einen Struktur-Abgleich.') }}
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Typ') }}</th>
                                <th>{{ __('OpenProject-ID') }}</th>
                                <th>{{ __('Zugeordnet zu') }}</th>
                                <th class="text-right">{{ __('Aktion') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mappings as $mapping)
                                @php $options = $optionsByType[$mapping->external_type] ?? []; @endphp
                                <tr>
                                    <td>{{ $typeLabels[$mapping->external_type] ?? $mapping->external_type }}</td>
                                    <td class="font-mono text-xs">
                                        {{ data_get($mapping->payload, 'name', data_get($mapping->payload, 'subject', $mapping->external_id)) }}
                                        <span class="text-base-content/50">#{{ $mapping->external_id }}</span>
                                    </td>
                                    <td>{{ optional($mapping->referenceable)->name ?? optional($mapping->referenceable)->title ?? '—' }}</td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="POST" action="{{ route('admin.openproject.mappings.update', $mapping->id) }}" class="flex items-center gap-1">
                                                @csrf
                                                <select name="target_id" class="select select-xs select-bordered">
                                                    <option value="">{{ __('— Ziel wählen —') }}</option>
                                                    @foreach ($options as $option)
                                                        <option value="{{ $option['sqid'] }}">{{ $option['name'] }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn btn-xs">{{ __('Umlegen') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.openproject.mappings.delete', $mapping->id) }}"
                                                  data-confirm-dialog data-confirm-message="{{ __('Zuordnung wirklich entfernen?') }}">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Entfernen') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
