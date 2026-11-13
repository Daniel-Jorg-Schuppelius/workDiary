{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : versions.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Fassungen einer Wissensseite mit Zeilenvergleich und Zurückholen
  (Feature 191, MVP-1664). Variablen: $page, $versions, $from, $to,
  $titleChanged, $diff (list<array{op, text}>).
--}}
@extends('layouts.app')

@section('title', __('knowledge_pages.versions.title') . ' — ' . $page->title)
@section('nav-title', __('knowledge_pages.versions.title'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
    <x-page-toolbar :subtitle="$page->title" :back="route('knowledge.pages.show', $page)" />
    </x-slot:toolbar>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[22rem_1fr]">
        <x-card :title="__('knowledge_pages.versions.title')" icon="history" :count="$versions->count()" padding="p-3">
            <ol class="space-y-2 text-sm" role="list">
                @foreach ($versions as $row)
                    <li class="rounded-box border border-base-300 p-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium">{{ __('knowledge_pages.version.label', ['version' => $row->version]) }}</span>
                            @if ($row->approved_at)
                                <x-status-badge tone="success" size="xs" icon="verified">{{ __('knowledge_pages.versions.approved') }}</x-status-badge>
                            @endif
                            @if ($row->version === $page->version)
                                <x-status-badge tone="info" size="xs">{{ __('knowledge_pages.versions.current') }}</x-status-badge>
                            @endif
                        </div>
                        <p class="text-xs text-muted">{{ $row->created_at?->fdatetime() }} · {{ $row->creator?->name ?? '—' }}</p>
                        @if ($row->note)
                            <p class="text-xs">{{ $row->note }}</p>
                        @endif
                        <div class="mt-1 flex flex-wrap gap-1">
                            <x-icon-btn icon="visibility" size="xs" tone="outline" :href="route('knowledge.pages.show', ['page' => $page, 'version' => $row->version])" :label="__('knowledge_pages.versions.view')" />
                            @if ($row->version > 1)
                                <x-icon-btn icon="compare_arrows" size="xs" tone="outline" :href="route('knowledge.pages.versions', ['page' => $page, 'from' => $row->version - 1, 'to' => $row->version])" :label="__('knowledge_pages.versions.compare_previous')" />
                            @endif
                            @if ($row->version !== $page->version)
                                <x-action-form :action="route('knowledge.pages.versions.restore', [$page, $row])"
                                               :confirm="__('knowledge_pages.confirm.restore_version', ['version' => $row->version])" confirm-icon="restore" confirm-tone="warning"
                                               :confirm-label="__('knowledge_pages.versions.restore')">
                                    <x-icon-btn icon="restore" size="xs" tone="outline" type="submit" :label="__('knowledge_pages.versions.restore')" />
                                </x-action-form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-card>

        <x-card :title="$from !== null && $to !== null ? __('knowledge_pages.versions.compare', ['from' => $from->version, 'to' => $to->version]) : __('knowledge_pages.versions.compare_title')" icon="compare_arrows">
            <form method="GET" action="{{ route('knowledge.pages.versions', $page) }}" class="mb-3 flex flex-wrap items-end gap-2">
                <label for="kp-from" class="text-xs text-muted">{{ __('knowledge_pages.versions.from') }}</label>
                <select id="kp-from" name="from" class="select select-sm select-bordered">
                    @foreach ($versions as $row)
                        <option value="{{ $row->version }}" @selected($from?->version === $row->version)>{{ $row->version }}</option>
                    @endforeach
                </select>
                <label for="kp-to" class="text-xs text-muted">{{ __('knowledge_pages.versions.to') }}</label>
                <select id="kp-to" name="to" class="select select-sm select-bordered">
                    @foreach ($versions as $row)
                        <option value="{{ $row->version }}" @selected($to?->version === $row->version)>{{ $row->version }}</option>
                    @endforeach
                </select>
                <x-icon-btn icon="compare_arrows" size="sm" tone="primary" type="submit" show-label>{{ __('knowledge_pages.versions.show_compare') }}</x-icon-btn>
            </form>
            @if ($from === null || $to === null)
                <x-empty-state compact icon="compare_arrows" :title="__('knowledge_pages.versions.no_compare')" />
            @else
                @if ($titleChanged)
                    <p class="mb-2 text-sm">{{ __('knowledge_pages.versions.title_changed', ['from' => $from->title, 'to' => $to->title]) }}</p>
                @endif
                <div class="overflow-x-auto rounded-box border border-base-300 font-mono text-xs" data-version-diff>
                    @foreach ($diff as $line)
                        <div @class([
                            'whitespace-pre-wrap px-2 py-0.5',
                            'bg-success/15' => $line['op'] === 'add',
                            'bg-error/15 line-through' => $line['op'] === 'del',
                        ])><span class="select-none text-muted">{{ $line['op'] === 'add' ? '+' : ($line['op'] === 'del' ? '−' : ' ') }}</span> {{ $line['text'] }}</div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>
</x-page-shell>
@endsection
