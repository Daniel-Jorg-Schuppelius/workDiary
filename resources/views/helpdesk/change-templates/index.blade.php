{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Standard-Change-Vorlagen (Feature 065, MVP-157): versioniert;
     Standard-Changes entstehen NUR aus freigegebenen Vorlagen — jede
     inhaltliche Änderung zieht die Freigabe zurück. --}}

@extends('layouts.app')
@section('title', __('Change-Vorlagen'))
@section('nav-title', __('Change-Vorlagen'))

@section('content')
    <x-index-page :subtitle="__('Vorlagen für Standard-Changes — nur freigegebene Vorlagen sind nutzbar; Änderungen erhöhen die Version und ziehen die Freigabe zurück.')">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" size="sm" :href="route('servicedesk.changes.index')"
                        show-label>{{ __('Changes') }}</x-icon-btn>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('servicedesk.change-templates.create')"
                        show-label>{{ __('Neue Vorlage') }}</x-icon-btn>
        </x-slot:actions>

        <x-table :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th class="text-right">{{ __('Version') }}</th>
                    <th>{{ __('Freigabe') }}</th>
                    <th class="text-right">{{ __('Changes') }}</th>
                    <th class="w-40 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            <tbody>
                @forelse ($templates as $template)
                    <tr class="hover">
                        <td class="font-medium">{{ $template->name }}</td>
                        <td class="text-right tabular-nums">{{ $template->version }}</td>
                        <td>
                            @if ($template->approved)
                                <x-status-badge tone="success" size="sm">{{ __('Freigegeben') }}</x-status-badge>
                            @else
                                <x-status-badge tone="warning" size="sm" outline>{{ __('Nicht freigegeben') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $template->changes_count }}</td>
                        <td class="text-right whitespace-nowrap">
                            @unless ($template->approved)
                                <x-action-form :action="route('servicedesk.change-templates.approve', $template)"
                                               method="POST" class="inline">
                                    <x-icon-btn icon="verified" tone="success" size="sm" type="submit" :label="__('Freigeben')" />
                                </x-action-form>
                            @endunless
                            <x-icon-btn icon="edit" size="sm"
                                        data-entry-modal-trigger
                                        :href="route('servicedesk.change-templates.edit', $template)"
                                        :label="__('Bearbeiten')" />
                            <x-action-form :action="route('servicedesk.change-templates.destroy', $template)"
                                           method="DELETE" class="inline"
                                           :confirm="__('Vorlage wirklich löschen?')">
                                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" :label="__('Löschen')" />
                            </x-action-form>
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5" icon="library_books" :title="__('Noch keine Vorlagen erfasst')" compact />
                @endforelse
            </tbody>
        </x-table>

        <x-pagination :paginator="$templates" standing />
    </x-index-page>
@endsection
