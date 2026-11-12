{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Meldefristen je Organisation (MVP-1589). Erwartet: $templates, $defaults, $ownCategories, $overridden, $canManage --}}
@extends('layouts.app')

@section('title', __('crisis.deadlines.title'))
@section('nav-title', __('crisis.deadlines.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('crisis.deadlines.subtitle')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" size="sm" tone="primary" data-entry-modal-trigger :href="route('crisis.deadlines.create')" show-label>{{ __('crisis.deadlines.create') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @include('crisis._tabs')

    {{-- Vorgaben vor der Voll-Höhe-Tabelle und eingeklappt: darunter lägen sie unter dem Fold. --}}
    <x-card as="details" padding="p-0">
        <summary class="cursor-pointer px-4 py-3 text-sm font-medium">{{ __('crisis.deadlines.defaults') }} ({{ $defaults->count() }})</summary>
        <div class="px-4 pb-4">
            <ul class="flex flex-col gap-1 text-sm">
                @forelse ($defaults as $default)
                    <li class="flex flex-wrap items-center gap-2 {{ in_array($default->category, $overridden, true) ? 'opacity-60' : '' }}">
                        <span class="font-medium">{{ __('values.' . $default->category) }}</span>
                        <span>{{ $default->label }}</span>
                        <span class="text-muted">{{ $default->offset_hours !== null ? $default->offset_hours . ' h' : __('crisis.deadlines.immediate') }}@if ($default->source) · {{ $default->source }}@endif</span>
                        @if (in_array($default->category, $overridden, true))
                            <span class="wd-badge badge-ghost">{{ __('crisis.deadlines.replaced') }}</span>
                        @endif
                        {{-- Übernehmen einmal je Kategorie, solange die Organisation dort keine eigene Frist führt. --}}
                        @if ($canManage && ! in_array($default->category, $ownCategories, true) && ($loop->first || $defaults[$loop->index - 1]->category !== $default->category))
                            <form method="POST" action="{{ route('crisis.deadlines.adopt') }}" class="ml-auto inline">
                                @csrf
                                <input type="hidden" name="category" value="{{ $default->category }}">
                                <x-icon-btn type="submit" size="xs" icon="content_copy" show-label>{{ __('crisis.deadlines.adopt') }}</x-icon-btn>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="text-muted">{{ __('crisis.deadlines.no_defaults') }}</li>
                @endforelse
            </ul>
        </div>
    </x-card>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('crisis.deadlines.field.category') }}</th>
                <th>{{ __('crisis.deadlines.field.label') }}</th>
                <th class="text-right">{{ __('crisis.deadlines.field.offset_hours') }}</th>
                <th>{{ __('crisis.deadlines.field.source') }}</th>
                <th class="text-right">{{ __('Aktionen') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($templates as $template)
            <tr class="hover">
                <td>{{ __('values.' . $template->category) }}</td>
                <td>{{ $template->label }}@unless ($template->active) <span class="wd-badge badge-ghost">{{ __('crisis.deadlines.inactive') }}</span>@endunless</td>
                <td class="text-right tabular-nums">{{ $template->offset_hours ?? __('crisis.deadlines.immediate') }}</td>
                <td>{{ $template->source ?? '—' }}</td>
                <td>
                    <div class="flex justify-end gap-1">
                        @if ($canManage)
                            <x-icon-btn icon="edit" size="xs" data-entry-modal-trigger :href="route('crisis.deadlines.edit', $template)" :label="__('crisis.deadlines.edit')" />
                            <form method="POST" action="{{ route('crisis.deadlines.toggle', $template) }}" class="inline">
                                @csrf
                                <x-icon-btn type="submit" size="xs" :icon="$template->active ? 'block' : 'check_circle'"
                                            :label="$template->active ? __('crisis.deadlines.deactivate') : __('crisis.deadlines.activate')" />
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="timer" :colspan="5" :title="__('crisis.deadlines.empty')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$templates" standing />
</x-index-page>
@endsection
