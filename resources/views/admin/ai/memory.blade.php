{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : memory.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('ai.title.memory') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('ai.title.memory'))

@section('content')
<x-index-page :subtitle="__('ai.title.memory_subtitle')">
    <x-slot:actions>
        @if ($canManage ?? false)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('admin.ai.memory.create')"
                        show-label>{{ __('ai.memory.new') }}</x-icon-btn>
        @endif
        {{-- Vollaudit 2026-07 (M9): DSGVO-Export, respektiert den Kundenfilter. --}}
        <x-icon-btn icon="download" size="sm"
                    :href="route('admin.ai.memory.export', array_filter(['kunde' => request()->query('kunde')]))"
                    show-label>{{ __('Export (JSON)') }}</x-icon-btn>
        <x-icon-btn icon="smart_toy" size="sm" :href="route('admin.ai.index')" show-label>{{ __('ai.title.connections') }}</x-icon-btn>
    </x-slot:actions>

    {{-- Transparenz-Hinweis: kein Fine-Tuning, nur Prompt-Kontext (Feature 025). --}}
    <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
        <x-icon name="psychology" />
        <span>{{ __('ai.memory.notice') }}</span>
    </div>

    <x-table :caption="__('ai.title.memory')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('ai.field.scope') }}</x-table.th>
                <x-table.th>{{ __('ai.field.type') }}</x-table.th>
                <x-table.th>{{ __('ai.field.term') }}</x-table.th>
                <x-table.th>{{ __('ai.field.content') }}</x-table.th>
                <x-table.th>{{ __('ai.field.origin') }}</x-table.th>
                <x-table.th>{{ __('ai.field.active') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($entries as $entry)
            <tr class="{{ $entry->active ? '' : 'opacity-50' }}">
                <td>
                    @if ($entry->customer_id !== null)
                        <x-status-badge tone="info" size="sm">{{ __('ai.field.scope_customer') }}</x-status-badge>
                        <div class="text-xs text-muted">{{ $entry->customer?->name }}</div>
                    @elseif ($entry->capability !== null)
                        <x-status-badge tone="ghost" size="sm">{{ __('ai.field.scope_capability') }}</x-status-badge>
                        <div class="text-xs text-muted">{{ \App\Services\Ai\Dto\AiCapability::labelFor($entry->capability) }}</div>
                    @else
                        <x-status-badge tone="neutral" size="sm">{{ __('ai.field.scope_organization') }}</x-status-badge>
                    @endif
                </td>
                <td>{{ $entry->entry_type->label() }}</td>
                <td class="font-mono text-xs">{{ $entry->term ?? '—' }}</td>
                <td class="max-w-md">
                    @if ($entry->source_text !== null)
                        <div class="text-xs text-muted line-clamp-1">{{ $entry->source_text }}</div>
                        <div class="text-xs">→ {{ \Illuminate\Support\Str::limit($entry->content, 120) }}</div>
                    @else
                        <div class="text-xs">{{ \Illuminate\Support\Str::limit($entry->content, 160) }}</div>
                    @endif
                    @if (! empty($entry->translations))
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach ($entry->translations as $lang => $translation)
                                <x-status-badge tone="ghost" size="xs" outline class="font-mono" :title="$translation">{{ $lang }}</x-status-badge>
                            @endforeach
                        </div>
                    @endif
                </td>
                <td>
                    <x-status-badge :tone="$entry->origin === \App\Models\Ai\AiMemoryEntry::ORIGIN_LEARNED ? 'warning' : 'ghost'" size="sm">
                        {{ __('ai.field.origin_' . $entry->origin) }}
                    </x-status-badge>
                </td>
                <td>{{ $entry->active ? __('ai.field.enabled_yes') : __('ai.field.enabled_no') }}</td>
                <td class="text-right whitespace-nowrap">
                    @if ($canManage ?? false)
                        <x-action-form :action="route('admin.ai.memory.toggle', $entry)">
                            <x-icon-btn :icon="$entry->active ? 'toggle_on' : 'toggle_off'" size="xs" type="submit"
                                        :title="$entry->active ? __('ai.action.deactivate') : __('ai.action.activate')" />
                        </x-action-form>
                        <x-action-form :action="route('admin.ai.memory.destroy', $entry)" method="DELETE"
                                       :confirm="__('ai.memory.delete_confirm')" confirm-tone="error">
                            <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :title="__('ai.action.delete')" />
                        </x-action-form>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="7" :title="__('ai.empty.memory')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
