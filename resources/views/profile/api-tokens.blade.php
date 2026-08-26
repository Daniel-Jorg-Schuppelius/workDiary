{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : api-tokens.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('API-Tokens'))
@section('nav-title', __('API-Tokens'))

@section('content')
    <x-index-page :subtitle="__('Persönliche Zugriffstoken für den API-Zugang verwalten.')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('profile.api-tokens.create')"
                        show-label>{{ __('Neuer Token') }}</x-icon-btn>
        </x-slot:actions>

        @if (! empty($newToken))
            <div class="rounded-box border border-success/30 bg-success/10 p-4">
                <p class="font-semibold">{{ __('Neuer Token erstellt') }} ({{ $newTokenName }})</p>
                <p class="text-sm">{{ __('Dieser Token wird nur einmalig angezeigt — bitte sicher speichern:') }}</p>
                <code class="mt-2 block break-all rounded bg-base-200 p-2 text-sm">{{ $newToken }}</code>
            </div>
        @endif

        <x-table :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('Name') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Fähigkeiten') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('Erstellt') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('Zuletzt benutzt') }}</x-table.th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            <tbody>
                @forelse ($tokens as $token)
                    <tr class="hover">
                        <td class="font-semibold">{{ $token->name }}</td>
                        <td class="text-xs">
                            @if (in_array('*', (array) $token->abilities, true))
                                <span class="badge badge-warning badge-sm" title="{{ __('Für eingeschränkten Zugriff neu ausstellen.') }}">{{ __('Voller Zugriff') }}</span>
                            @else
                                {{ implode(', ', (array) $token->abilities) }}
                            @endif
                        </td>
                        <td data-sort-value="{{ optional($token->created_at)->format('Y-m-d H:i:s') }}">{{ optional($token->created_at)->fdatetime() }}</td>
                        <td data-sort-value="{{ optional($token->last_used_at)->format('Y-m-d H:i:s') }}">{{ $token->last_used_at ? $token->last_used_at->diffForHumans() : '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            <x-action-form :action="route('profile.api-tokens.destroy', \App\Support\Sqid::encode(\Laravel\Sanctum\PersonalAccessToken::class, $token->id))" method="DELETE"
                                  data-confirm-title="{{ __('Token widerrufen') }}"
                                  :confirm="__('Token wirklich widerrufen?')"
                                  :confirm-label="__('Widerrufen')">
                                <x-icon-btn icon="block" tone="error" type="submit" :label="__('Widerrufen')" />
                            </x-action-form>
                        </td>
                    </tr>
                @empty
                    <x-table.empty icon="key" :colspan="5" :title="__('Keine API-Token vorhanden')" compact />
                @endforelse
            </tbody>
        </x-table>
    </x-index-page>
@endsection
