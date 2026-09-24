{{--
  Created on   : Thu Sep 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Eigene Felder je Träger (MVP-868): Übersicht der fünf Träger mit Schema,
  Version, Nutzung und Status; Bearbeiten im Dialog.
--}}
@extends('layouts.app')
@section('title', __('fields.custom.title'))
@section('nav-title', __('fields.custom.title'))
@section('content')
    <x-page-toolbar :title="__('fields.custom.title')" icon="dashboard_customize" :subtitle="__('fields.custom.intro')" />

    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('fields.custom.subject') }}</th>
                    <th>{{ __('fields.custom.fields') }}</th>
                    <th>{{ __('fields.custom.status') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($rows as $row)
                @php $definition = $row['definition']; @endphp
                <tr>
                    <td class="font-medium">{{ $row['label'] }}</td>
                    <td class="text-sm">
                        @if ($definition === null || $definition->schema->isEmpty())
                            <span class="text-muted">{{ __('fields.custom.none') }}</span>
                        @else
                            {{ $definition->schema->all() === [] ? '' : collect($definition->schema->all())->map(fn ($f) => $f->label)->implode(', ') }}
                            <span class="block text-xs text-muted">{{ __('fields.custom.version', ['version' => $definition->version]) }} · {{ __('fields.custom.values_count', ['count' => $row['usage']]) }}</span>
                        @endif
                    </td>
                    <td>
                        @if ($definition !== null)
                            <x-status-badge size="md" :tone="$definition->is_active ? 'success' : 'neutral'" outline>{{ $definition->is_active ? __('fields.custom.active') : __('fields.custom.inactive') }}</x-status-badge>
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="edit" size="sm" tone="primary" :href="route('admin.custom-fields.edit', $row['alias'])" data-modal-url :title="__('fields.custom.edit')" />
                            @if ($definition !== null)
                                <x-action-form :action="route('admin.custom-fields.toggle', $row['alias'])" method="PATCH">
                                    <x-icon-btn :icon="$definition->is_active ? 'visibility_off' : 'visibility'" size="sm" tone="ghost" type="submit"
                                                :title="$definition->is_active ? __('fields.custom.deactivate') : __('fields.custom.activate')" />
                                </x-action-form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>
    </x-card>
@endsection
