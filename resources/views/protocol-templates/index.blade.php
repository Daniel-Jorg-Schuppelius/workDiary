{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Protokollvorlagen (MVP-901): Liste mit Zuordnung, Version und Aktivierung.
     Neue Vorlagen entstehen am Protokoll („Als Vorlage speichern“). --}}

@extends('layouts.app')

@section('title', __('protocol.template.title'))
@section('nav-title', __('protocol.template.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('protocol.template.subtitle')">
        <x-filter-bar :action="route('protocol-templates.index')" :reset="$q !== '' ? route('protocol-templates.index') : null">
            <x-filter-field :label="__('protocol.template.search')" for="protocol-tpl-q" class="flex-1 min-w-60">
                <input id="protocol-tpl-q" type="search" name="q" value="{{ $q }}" class="input input-sm input-bordered w-full">
            </x-filter-field>
        </x-filter-bar>

        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('protocol.template.name') }}</th>
                    <th>{{ __('protocol.field.type') }}</th>
                    <th>{{ __('protocol.template.target') }}</th>
                    <th>{{ __('protocol.template.items') }}</th>
                    <th>{{ __('protocol.template.version') }}</th>
                    <th>{{ __('protocol.template.active') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($templates as $template)
                <tr class="hover">
                    <td>
                        <span class="font-medium">{{ $template->name }}</span>
                        @if ($template->description)
                            <span class="block max-w-md truncate text-xs text-muted">{{ $template->description }}</span>
                        @endif
                    </td>
                    <td>{{ $template->kind->label() }}</td>
                    <td class="text-base-content/70">{{ collect([$template->entryType?->label, $template->customer?->name])->filter()->implode(' · ') ?: __('protocol.template.everywhere') }}</td>
                    <td class="text-base-content/70 tabular-nums">{{ count($template->items ?? []) }}</td>
                    <td class="tabular-nums">{{ $template->version }}</td>
                    <td>
                        <x-status-badge :tone="$template->is_active ? 'success' : 'ghost'" size="sm">{{ $template->is_active ? __('Ja') : __('Nein') }}</x-status-badge>
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="edit" tone="outline" size="xs" data-entry-modal-trigger :href="route('protocol-templates.edit', $template)" :label="__('protocol.template.edit')" />
                            <x-action-form :action="route('protocol-templates.destroy', $template)" method="DELETE"
                                           :confirm="__('protocol.template.confirm_delete', ['name' => $template->name])"
                                           confirm-icon="delete" confirm-tone="error">
                                <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('protocol.template.delete')" />
                            </x-action-form>
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7" :title="__('protocol.template.empty_title')" :message="__('protocol.template.empty')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$templates" standing />
    </x-index-page>
@endsection
