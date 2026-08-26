{{--
  Created on   : Wed Apr 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Mitarbeiter') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Mitarbeiter'))

@section('content')
    <?php $legacyUsers = collect($users ?? []); ?>
    <x-page-shell overflow="clip">
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('Legacy-Mitarbeiterliste aus dem Altsystem.')">
                <span class="text-sm text-muted">{{ trans_choice(':n Mitarbeiter|:n Mitarbeiter', $legacyUsers->count(), ['n' => $legacyUsers->count()]) }}</span>
                <x-slot:actions>
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('legacy.users.create')"
                                show-label>{{ __('Neuer Mitarbeiter') }}</x-icon-btn>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>
        <div class="flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
        <x-table table-sort="client" bare scroll="none" size="xs" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string" default="asc">{{ __('Name') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('E-Mail') }}</x-table.th>
                    <th class="w-24 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($legacyUsers as $legacyUser)
                <tr class="hover">
                    <td>{{ $legacyUser->uname }}</td>
                    <td>{{ $legacyUser->email ?: '–' }}</td>
                    <td class="whitespace-nowrap text-right">
                        <x-icon-btn icon="edit"
                                    data-entry-modal-trigger
                                    :href="route('legacy.users.edit', $legacyUser)"
                                    :label="__('Bearbeiten')" />
                        <form method="POST" action="{{ route('legacy.users.destroy', $legacyUser) }}" class="inline"
                              data-confirm-dialog
                              data-confirm-message="{{ __('Mitarbeiter wirklich löschen?') }}"
                              data-confirm-label="{{ __('Löschen') }}">
                            @csrf
                            @method('DELETE')
                            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                        </form>
                    </td>
                </tr>
            @empty
                <x-table.empty icon="group" :colspan="3" :title="__('Keine Mitarbeiter gefunden')" compact />
            @endforelse
        </x-table>
        </div>
    </x-page-shell>
@endsection
