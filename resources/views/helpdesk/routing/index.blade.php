{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Routing-Regeln (Feature 065, MVP-153): deterministische Reihenfolge,
     JSON-Bedingungen/-Aktionen (Admin-Werkzeug), Dry-Run-Testmodus. --}}

@extends('layouts.app')
@section('title', __('Ticket-Routing'))
@section('nav-title', __('Ticket-Routing'))

@section('content')
    <x-index-page :subtitle="__('Regeln in Positions-Reihenfolge; je Aktionstyp gewinnt die erste zutreffende Regel. Jede Anwendung wird protokolliert.')">
        <x-card :title="__('Dry-Run (Regel-Test)')">
            <form method="POST" action="{{ route('helpdesk.routing.dry-run') }}" class="flex items-end gap-2">
                @csrf
                <div class="fieldset grow">
                    <label class="fieldset-label">{{ __('Ticket-Nummer') }}</label>
                    <input name="ticket_no" required maxlength="40" class="input input-sm input-bordered w-full" placeholder="ST-2026-0001">
                </div>
                <x-icon-btn icon="science" tone="outline" size="sm" type="submit" show-label>{{ __('Testen (ohne Änderung)') }}</x-icon-btn>
            </form>
        </x-card>

        <x-card :title="__('Neue Regel')">
            <form method="POST" action="{{ route('helpdesk.routing.store') }}" class="grid gap-2 md:grid-cols-2">
                @csrf
                <x-input-field name="name" :label="__('Name')" required maxlength="120" />
                <x-input-field name="position" type="number" :label="__('Position')" required min="1" max="999" :value="old('position', 1)" />
                <div class="fieldset">
                    <label class="fieldset-label">{{ __('Bedingungen (JSON)') }}</label>
                    <textarea name="conditions" rows="3" required class="textarea textarea-bordered w-full font-mono text-xs">{{ old('conditions', '{"kind": "incident"}') }}</textarea>
                </div>
                <div class="fieldset">
                    <label class="fieldset-label">{{ __('Aktionen (JSON)') }}</label>
                    <textarea name="actions" rows="3" required class="textarea textarea-bordered w-full font-mono text-xs">{{ old('actions', '{"set_priority": "high"}') }}</textarea>
                </div>
                <div class="md:col-span-2">
                    <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Anlegen') }}</x-icon-btn>
                </div>
            </form>
        </x-card>

        <x-table :zebra="true">
            <x-slot:head>
                <tr>
                    <th class="w-16 text-right">{{ __('Pos.') }}</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Bedingungen') }}</th>
                    <th>{{ __('Aktionen') }}</th>
                    <th class="w-20 text-right">{{ __('Version') }}</th>
                    <th class="w-24 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            <tbody>
                @forelse ($rules as $rule)
                    <tr class="hover {{ $rule->active ? '' : 'opacity-50' }}">
                        <td class="text-right tabular-nums">{{ $rule->position }}</td>
                        <td class="font-semibold">{{ $rule->name }}</td>
                        <td class="font-mono text-xs">{{ json_encode($rule->conditions, JSON_UNESCAPED_UNICODE) }}</td>
                        <td class="font-mono text-xs">{{ json_encode($rule->actions, JSON_UNESCAPED_UNICODE) }}</td>
                        <td class="text-right tabular-nums">v{{ $rule->version }}</td>
                        <td class="text-right">
                            <x-action-form :action="route('helpdesk.routing.destroy', $rule)"
                                  method="DELETE"
                                  data-confirm-title="{{ __('Regel löschen') }}"
                                  :confirm="__('Die Regel wird entfernt (Protokolle bleiben).')"
                                  :confirm-label="__('Löschen')">
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </x-action-form>
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="6" icon="alt_route" :title="__('Noch keine Routing-Regeln')" compact />
                @endforelse
            </tbody>
        </x-table>
    </x-index-page>
@endsection
