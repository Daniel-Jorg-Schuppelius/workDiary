{{--
  Created on   : Wed Jun 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _entry_card.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Auftragsbuch-Eintragskarte — gemeinsame Darstellung für /diary und
    /duties?tab=diary. Erwartet:
      - $entry   : DiaryEntry
      - $filters : array (für Such-Highlight, optional)
--}}
@php($needle = trim((string) ($filters['q'] ?? '')))
<x-card as="article" class="grid gap-4 transition hover:border-primary/30 md:grid-cols-[minmax(0,1fr)_auto]">
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-3 mb-3">
            <x-status-badge :tone="$entry->status->badgeTone()">{{ $entry->statusLabel() }}</x-status-badge>
            @php($dispatchStatus = app(\App\Services\Dispatch\DispatchStatusResolver::class)->resolve($entry))
            @if ($dispatchStatus !== \App\Enums\Diary\DispatchStatus::Unplanned)
                <x-status-badge :tone="$dispatchStatus->badgeTone()" outline>{{ $dispatchStatus->label() }}</x-status-badge>
            @endif
            @if ($entry->mode && $entry->mode !== \App\Enums\Diary\Mode::Fixed)
                <x-status-badge tone="ghost" outline>{{ $entry->modeLabel() }}</x-status-badge>
            @endif
            @if ($entry->location_mode === \App\Enums\Diary\LocationMode::Remote)
                <x-status-badge tone="ghost" outline>{{ __('Remote') }}</x-status-badge>
            @elseif ($entry->location_mode === \App\Enums\Diary\LocationMode::Hybrid)
                <x-status-badge tone="ghost" outline>{{ __('Hybrid') }}</x-status-badge>
            @endif
            @if ($entry->is_archived)
                <x-status-badge tone="neutral">{{ __('Archiviert') }}</x-status-badge>
            @endif
            <span class="text-sm text-base-content/70">{{ $entry->user?->name ?? '—' }}</span>
        </div>
        <p class="text-base leading-relaxed text-base-content wrap-break-word">
            @php($snippet = \CommonToolkit\Helper\Data\StringHelper::truncate($entry->content, 240))
            @if ($needle !== '')
                {!! preg_replace('/(' . preg_quote($needle, '/') . ')/i', '<mark class="bg-warning/40 px-0.5 rounded">$1</mark>', e($snippet)) !!}
            @else
                {{ $snippet }}
            @endif
        </p>
        @if ($entry->tags->isNotEmpty())
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach ($entry->tags as $tag)
                    <x-status-badge tone="plain" outline :style="$tag->color ? 'border-color: '.$tag->color.'; color: '.$tag->color.';' : null">#{{ $tag->displayName() }}</x-status-badge>
                @endforeach
            </div>
        @endif
        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-base-content/65">
            @switch($entry->mode)
                @case(\App\Enums\Diary\Mode::Deadline)
                    @if ($entry->due_date)<span>{{ __('Fällig bis') }} {{ $entry->due_date->fdate() }}</span>@endif
                    @break
                @case(\App\Enums\Diary\Mode::Window)
                    @if ($entry->window_start_date)<span>{{ __('Fenster') }} {{ $entry->window_start_date->fdate() }}@if ($entry->window_end_date) – {{ $entry->window_end_date->fdate() }}@endif</span>@endif
                    @break
                @case(\App\Enums\Diary\Mode::Backlog)
                    <span>{{ __('Backlog — kein Datum') }}</span>
                    @break
                @default
                    @if ($entry->start_at)<span class="{{ $entry->start_at->isSunday() ? 'text-error font-semibold' : '' }}">{{ __('Von') }} {{ $entry->start_at->fdatetime() }}</span>@endif
                    @if ($entry->end_at)<span class="{{ $entry->end_at->isSunday() ? 'text-error font-semibold' : '' }}">{{ __('Bis') }} {{ $entry->end_at->fdatetime() }}</span>@endif
            @endswitch
            <span>{{ __('Erstellt') }} {{ $entry->created_at->diffForHumans() }}</span>
        </div>
        <x-custom-field-summary class="mt-2" :columns="$customColumns ?? []" :model="$entry" />
    </div>
    <div class="flex flex-col gap-2 md:items-end md:justify-between">
        {{-- Team-Sicht: fremde Aufträge stehen in der Liste, öffnen darf sie nur, wer sie sehen darf. --}}
        @can('view', $entry)
            <x-icon-btn icon="visibility" tone="outline" size="sm"
                        data-entry-modal-trigger
                        :href="route('diary.show', $entry)"
                        class="btn-primary"
                        show-label>{{ __('Details') }}</x-icon-btn>
        @endcan
        @can('update', $entry)
            <x-icon-btn icon="edit" size="sm"
                        data-entry-modal-trigger
                        :href="route('diary.edit', $entry)"
                        show-label>{{ __('Bearbeiten') }}</x-icon-btn>
        @endcan
    </div>
</x-card>
