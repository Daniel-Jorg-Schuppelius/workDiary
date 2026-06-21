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
<x-page-shell>

    @if (! empty($newToken))
        <div class="rounded-box border border-success/30 bg-success/10 p-4">
            <p class="font-semibold">{{ __('Neuer Token erstellt') }} ({{ $newTokenName }})</p>
            <p class="text-sm">{{ __('Dieser Token wird nur einmalig angezeigt — bitte sicher speichern:') }}</p>
            <code class="mt-2 block break-all rounded bg-base-200 p-2 text-sm">{{ $newToken }}</code>
        </div>
    @endif

    <form method="POST" action="{{ route('profile.api-tokens.store') }}" class="card border border-base-300 bg-base-100 p-4">
        @csrf
        <div class="form-control">
            <label class="label" for="name">{{ __('Token-Name') }}</label>
            <input id="name" name="name" type="text" class="input input-bordered" maxlength="64" required>
        </div>
        @error('name')<div class="text-error text-sm">{{ $message }}</div>@enderror
        <div class="card-actions justify-end mt-3">
            <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Erstellen') }}</x-icon-btn>
        </div>
    </form>

    <x-table table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string">{{ __('Name') }}</x-table.th>
                <x-table.th sort type="date">{{ __('Erstellt') }}</x-table.th>
                <x-table.th sort type="date">{{ __('Zuletzt benutzt') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>

        @forelse ($tokens as $token)
            <tr>
                <td>{{ $token->name }}</td>
                <td data-sort-value="{{ optional($token->created_at)->format('Y-m-d H:i:s') }}">{{ optional($token->created_at)->fdatetime() }}</td>
                <td data-sort-value="{{ optional($token->last_used_at)->format('Y-m-d H:i:s') }}">{{ $token->last_used_at ? $token->last_used_at->diffForHumans() : '—' }}</td>
                <td class="text-right">
                    <x-action-form :action="route('profile.api-tokens.destroy', $token->id)" method="DELETE"
                          :confirm="__('Token wirklich widerrufen?')"
                          :confirm-label="__('Widerrufen')">
                        <x-icon-btn icon="block" tone="error" type="submit" :label="__('Widerrufen')" />
                    </x-action-form>
                </td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">key</span>' :colspan="4" :title="__('Keine API-Token vorhanden')" compact />
        @endforelse
    </x-table>
</x-page-shell>
@endsection
