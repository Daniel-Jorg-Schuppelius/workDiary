{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Externe Kontaktprofile — Stammdatenpflege (Feature 033, Rang 30).
  Variablen: $contacts (Paginator), $parties
--}}
@extends('layouts.app')
@section('title', __('external.contact.title'))
@section('nav-title', __('external.contact.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-page-shell overflow="clip">
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:subtitle>{{ __('external.contact.intro') }}</x-slot:subtitle>
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                            :href="route('external-contacts.create')" show-label>{{ __('external.contact.new') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('external.field.name') }}</th>
                    <th>{{ __('external.field.party') }}</th>
                    <th>{{ __('external.field.role') }}</th>
                    <th>{{ __('external.field.email') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($contacts as $contact)
                <tr class="hover">
                    <td class="font-medium">{{ $contact->name }}</td>
                    <td><span class="badge badge-sm badge-ghost">{{ $contact->party->label() }}</span></td>
                    <td>{{ $contact->role }}</td>
                    <td class="text-base-content/70">{{ $contact->email }}</td>
                    <td class="text-right">
                        <div class="flex items-center justify-end gap-1">
                            <x-icon-btn icon="edit" size="xs" tone="ghost" data-entry-modal-trigger
                                        :href="route('external-contacts.edit', $contact)" />
                            <form method="POST" action="{{ route('external-contacts.destroy', $contact) }}"
                                  data-confirm-dialog data-confirm-message="{{ __('external.contact.confirm_delete') }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('external.contact.delete') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('external.contact.empty')" compact />
            @endforelse
    </x-table>

    <x-pagination :paginator="$contacts" standing />
</x-page-shell>
@endsection
