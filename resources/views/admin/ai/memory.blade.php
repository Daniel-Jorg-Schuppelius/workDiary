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
        <x-icon-btn icon="smart_toy" size="sm" :href="route('admin.ai.index')" show-label>{{ __('ai.title.connections') }}</x-icon-btn>
    </x-slot:actions>

    @if (session('success'))
        <div role="alert" class="alert alert-success"><span>{{ session('success') }}</span></div>
    @endif
    @if (session('error'))
        <div role="alert" class="alert alert-error"><span>{{ session('error') }}</span></div>
    @endif

    {{-- Transparenz-Hinweis: kein Fine-Tuning, nur Prompt-Kontext (Feature 025). --}}
    <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
        <x-icon name="psychology" />
        <span>{{ __('ai.memory.notice') }}</span>
    </div>

    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('ai.field.scope') }}</th>
                    <th>{{ __('ai.field.type') }}</th>
                    <th>{{ __('ai.field.term') }}</th>
                    <th>{{ __('ai.field.content') }}</th>
                    <th>{{ __('ai.field.origin') }}</th>
                    <th>{{ __('ai.field.active') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr class="{{ $entry->active ? '' : 'opacity-50' }}">
                        <td>
                            @if ($entry->customer_id !== null)
                                <span class="badge badge-info badge-sm">{{ __('ai.field.scope_customer') }}</span>
                                <div class="text-xs text-base-content/60">{{ $entry->customer?->name }}</div>
                            @elseif ($entry->capability !== null)
                                <span class="badge badge-ghost badge-sm">{{ __('ai.field.scope_capability') }}</span>
                                <div class="text-xs font-mono text-base-content/60">{{ $entry->capability }}</div>
                            @else
                                <span class="badge badge-neutral badge-sm">{{ __('ai.field.scope_organization') }}</span>
                            @endif
                        </td>
                        <td>{{ $entry->entry_type->label() }}</td>
                        <td class="font-mono text-xs">{{ $entry->term ?? '—' }}</td>
                        <td class="max-w-md">
                            @if ($entry->source_text !== null)
                                <div class="text-xs text-base-content/60 line-clamp-1">{{ $entry->source_text }}</div>
                                <div class="text-xs">→ {{ \Illuminate\Support\Str::limit($entry->content, 120) }}</div>
                            @else
                                <div class="text-xs">{{ \Illuminate\Support\Str::limit($entry->content, 160) }}</div>
                            @endif
                            @if (! empty($entry->translations))
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($entry->translations as $lang => $translation)
                                        <span class="badge badge-outline badge-xs font-mono" title="{{ $translation }}">{{ $lang }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-{{ $entry->origin === \App\Models\Ai\AiMemoryEntry::ORIGIN_LEARNED ? 'warning' : 'ghost' }} badge-sm">
                                {{ __('ai.field.origin_' . $entry->origin) }}
                            </span>
                        </td>
                        <td>{{ $entry->active ? __('ai.field.enabled_yes') : __('ai.field.enabled_no') }}</td>
                        <td class="text-right whitespace-nowrap">
                            @if ($canManage ?? false)
                                <form method="POST" action="{{ route('admin.ai.memory.toggle', $entry) }}" class="inline">
                                    @csrf
                                    <x-icon-btn :icon="$entry->active ? 'toggle_on' : 'toggle_off'" size="xs" type="submit"
                                                :title="$entry->active ? __('ai.action.deactivate') : __('ai.action.activate')" />
                                </form>
                                <form method="POST" action="{{ route('admin.ai.memory.destroy', $entry) }}" class="inline"
                                      onsubmit="return confirm('{{ __('ai.memory.delete_confirm') }}')">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :title="__('ai.action.delete')" />
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-base-content/60 py-8">{{ __('ai.empty.memory') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-index-page>
@endsection
