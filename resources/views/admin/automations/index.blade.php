{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Automatisierungen'))
@section('nav-title', __('Automatisierungen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Regeln werten bei festgelegten Ereignissen Bedingungen aus und führen Aktionen automatisch aus (z. B. Kleinbetragsspesen genehmigen). Jede Auswertung wird im Audit-Log protokolliert.')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('admin.automations.create')"
                    show-label>{{ __('Neue Regel anlegen (JSON)') }}</x-icon-btn>
    </x-slot:actions>

    <x-table scroll="flex" :pinRows="true" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="number" class="w-16" default="asc">{{ __('Prio') }}</x-table.th>
                <x-table.th sort type="string">{{ __('Name') }}</x-table.th>
                <x-table.th sort type="string">{{ __('Trigger') }}</x-table.th>
                <th>{{ __('Aktion(en)') }}</th>
                <x-table.th sort type="string" align="center">{{ __('Aktiv') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($rules as $rule)
            <tr>
                <td class="tabular-nums">{{ $rule->priority }}</td>
                <td class="font-medium">
                    <a href="{{ route('admin.automations.show', $rule) }}" class="link link-hover">{{ $rule->name }}</a>
                </td>
                <td><code class="text-xs">{{ $rule->trigger_event }}</code></td>
                <td class="text-xs">
                    @foreach ((array) $rule->actions as $a)
                        <x-status-badge tone="ghost" size="sm">{{ $a['type'] ?? '?' }}</x-status-badge>
                    @endforeach
                </td>
                <td class="text-center">
                    @if ($rule->is_active)
                        <x-status-badge tone="success" size="sm">{{ __('Ja') }}</x-status-badge>
                    @else
                        <x-status-badge tone="ghost" size="sm">{{ __('Nein') }}</x-status-badge>
                    @endif
                </td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-action-form :action="route('admin.automations.toggle', $rule)">
                            <x-icon-btn icon="{{ $rule->is_active ? 'pause' : 'play_arrow' }}" type="submit"
                                        :label="$rule->is_active ? __('Deaktivieren') : __('Aktivieren')" />
                        </x-action-form>
                        <x-action-form :action="route('admin.automations.destroy', $rule)" method="DELETE"
                              :confirm="__('Regel wirklich löschen?')"
                              :confirm-label="__('Löschen')">
                            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                        </x-action-form>
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="6"
                icon='<span class="material-symbols-outlined" aria-hidden="true">smart_toy</span>'
                :title="__('Keine Regeln definiert.')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
