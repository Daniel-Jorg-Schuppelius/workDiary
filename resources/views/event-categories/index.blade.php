{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Veranstaltungs-Kategorien'))
@section('nav-title', __('Veranstaltungs-Kategorien'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $categories */
@endphp

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Kategorien für Veranstaltungen und Termine pflegen.')">
        <x-slot:actions>
            @can('create', App\Models\EventCategory::class)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('event-categories.create').'?dialog=1'"
                            show-label>{{ __('Neue Kategorie') }}</x-icon-btn>
            @endcan
        </x-slot:actions>

        <x-table table-sort="server"
                 :route="route('event-categories.index')"
                 :current-sort="$sort"
                 :current-dir="$dir"
                 scroll="flex" :pinRows="true" :zebra="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort="name" default>{{ __('Name') }}</x-table.th>
                    <th>{{ __('Beschreibung') }}</th>
                    <x-table.th sort="requires_certificate">{{ __('Zertifikat') }}</x-table.th>
                    <x-table.th sort="certificate_valid_months" align="right">{{ __('Gültig (Monate)') }}</x-table.th>
                    <th>{{ __('Reminder (Min.)') }}</th>
                    <x-table.th sort="is_active">{{ __('Status') }}</x-table.th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($categories as $cat)
                    <tr class="hover">
                        <td>
                            <div class="flex items-center gap-2 font-semibold">
                                <span class="inline-block w-3 h-3 rounded" style="background:{{ $cat->color ?? '#999' }}"></span>
                                {{ $cat->name }}
                            </div>
                        </td>
                        <td class="max-w-md truncate text-base-content/70 text-xs">{{ $cat->description }}</td>
                        <td>
                            @if ($cat->requires_certificate)
                                <span class="inline-flex items-center gap-1 text-success">
                                    <x-icon name="check_circle" /> {{ __('Erforderlich') }}
                                </span>
                            @else
                                <span class="opacity-50">—</span>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $cat->certificate_valid_months ?? '—' }}</td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @foreach (($cat->reminder_offsets ?? []) as $off)
                                    <x-status-badge tone="ghost" size="sm" class="font-mono">{{ $off }}</x-status-badge>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            @if ($cat->is_active)
                                <x-status-badge tone="success" size="sm">{{ __('Aktiv') }}</x-status-badge>
                            @else
                                <x-status-badge tone="ghost" size="sm">{{ __('Inaktiv') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @can('update', $cat)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('event-categories.edit', $cat).'?dialog=1'"
                                            :label="__('Bearbeiten')" />
                            @endcan
                            @can('delete', $cat)
                                <x-action-form :action="route('event-categories.destroy', $cat)" method="DELETE"
                                      :confirm="__('Kategorie wirklich löschen?')"
                                      :confirm-label="__('Löschen')">
                                    <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                </x-action-form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="7"
                        icon="category"
                        :title="__('Noch keine Kategorien angelegt')" compact />
                @endforelse
        </x-table>

        <x-pagination :paginator="$categories" standing />
    </x-index-page>
@endsection
