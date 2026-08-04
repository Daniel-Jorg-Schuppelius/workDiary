{{--
  Created on   : Mon Aug 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('textcorrections.title.index') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('textcorrections.title.index'))

@section('content')
<x-index-page :subtitle="__('textcorrections.title.subtitle')">
    <x-slot:actions>
        <form method="GET" action="{{ route('admin.text-corrections.index') }}" class="flex items-center gap-2">
            <input type="search" name="q" value="{{ $q }}" maxlength="190"
                   class="input input-bordered input-sm"
                   placeholder="{{ __('textcorrections.search_placeholder') }}"
                   aria-label="{{ __('textcorrections.search_placeholder') }}">
        </form>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('admin.text-corrections.create')"
                        show-label>{{ __('textcorrections.action.new') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    {{-- Transparenz-Hinweis: wirkt automatisch auf generierte Positionstexte,
         nie auf die erfassten Zeiteinträge selbst. --}}
    <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
        <x-icon name="spellcheck" />
        <span>{{ __('textcorrections.notice') }}</span>
    </div>

    <x-table :caption="__('textcorrections.title.index')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('textcorrections.field.wrong') }}</x-table.th>
                <x-table.th>{{ __('textcorrections.field.correct') }}</x-table.th>
                <x-table.th>{{ __('textcorrections.field.origin') }}</x-table.th>
                <x-table.th>{{ __('textcorrections.field.usage') }}</x-table.th>
                <x-table.th>{{ __('textcorrections.field.active') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($corrections as $correction)
            <tr class="{{ $correction->active ? '' : 'opacity-50' }}">
                <td class="font-mono text-xs">{{ $correction->wrong }}</td>
                <td class="font-mono text-xs">{{ $correction->correct }}</td>
                <td>
                    <x-status-badge :tone="$correction->origin === \App\Models\TextCorrection::ORIGIN_LEARNED ? 'warning' : 'ghost'" size="sm">
                        {{ __('textcorrections.field.origin_' . $correction->origin) }}
                    </x-status-badge>
                    @if ($correction->creator !== null)
                        <div class="text-xs text-base-content/60">{{ $correction->creator->name }}</div>
                    @endif
                </td>
                <td class="text-sm">{{ $correction->usage_count }}</td>
                <td>{{ $correction->active ? __('textcorrections.field.enabled_yes') : __('textcorrections.field.enabled_no') }}</td>
                <td class="text-right whitespace-nowrap">
                    @if ($canManage)
                        <x-icon-btn icon="edit" size="xs"
                                    data-entry-modal-trigger
                                    :href="route('admin.text-corrections.edit', $correction)"
                                    :title="__('textcorrections.action.edit')" />
                        <x-action-form :action="route('admin.text-corrections.toggle', $correction)">
                            <x-icon-btn :icon="$correction->active ? 'toggle_on' : 'toggle_off'" size="xs" type="submit"
                                        :title="$correction->active ? __('textcorrections.action.deactivate') : __('textcorrections.action.activate')" />
                        </x-action-form>
                        <x-action-form :action="route('admin.text-corrections.destroy', $correction)" method="DELETE"
                                       :confirm="__('textcorrections.delete_confirm')" confirm-tone="error">
                            <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :title="__('textcorrections.action.delete')" />
                        </x-action-form>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="6" :title="__('textcorrections.empty')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$corrections" standing />
</x-index-page>
@endsection
