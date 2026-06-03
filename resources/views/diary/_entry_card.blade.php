{{--
    Auftragsbuch-Eintragskarte — gemeinsame Darstellung für /diary und
    /duties?tab=diary. Erwartet:
      - $entry   : DiaryEntry
      - $filters : array (für Such-Highlight, optional)
--}}
@php($needle = trim((string) ($filters['q'] ?? '')))
<article class="grid gap-4 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs transition hover:border-primary/30 md:grid-cols-[1fr_auto]">
    <div>
        <div class="flex flex-wrap items-center gap-3 mb-3">
            <span @class([
                'badge badge-sm',
                'badge-success' => $entry->statusTone() === 'done',
                'badge-info'    => $entry->statusTone() === 'progress',
                'badge-warning' => $entry->statusTone() === 'open',
                'badge-error'   => $entry->statusTone() === 'alert',
                'badge-ghost'   => $entry->statusTone() === 'neutral',
            ])>{{ $entry->statusLabel() }}</span>
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
        <p class="text-base leading-relaxed text-base-content">
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
                    <span class="badge badge-outline badge-sm" @if ($tag->color) style="border-color: {{ $tag->color }}; color: {{ $tag->color }};" @endif>#{{ $tag->name }}</span>
                @endforeach
            </div>
        @endif
        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-base-content/65">
            @switch($entry->mode)
                @case(\App\Enums\Diary\Mode::Deadline)
                    @if ($entry->due_date)<span>{{ __('Fällig bis') }} {{ $entry->due_date->format('d.m.Y') }}</span>@endif
                    @break
                @case(\App\Enums\Diary\Mode::Window)
                    @if ($entry->window_start_date)<span>{{ __('Fenster') }} {{ $entry->window_start_date->format('d.m.Y') }}@if ($entry->window_end_date) – {{ $entry->window_end_date->format('d.m.Y') }}@endif</span>@endif
                    @break
                @case(\App\Enums\Diary\Mode::Backlog)
                    <span>{{ __('Backlog — kein Datum') }}</span>
                    @break
                @default
                    @if ($entry->start_at)<span class="{{ $entry->start_at->isSunday() ? 'text-error font-semibold' : '' }}">{{ __('Von') }} {{ $entry->start_at->format('d.m.Y H:i') }}</span>@endif
                    @if ($entry->end_at)<span class="{{ $entry->end_at->isSunday() ? 'text-error font-semibold' : '' }}">{{ __('Bis') }} {{ $entry->end_at->format('d.m.Y H:i') }}</span>@endif
            @endswitch
            <span>{{ __('Erstellt') }} {{ $entry->created_at->diffForHumans() }}</span>
        </div>
    </div>
    <div class="flex flex-col gap-2 md:items-end md:justify-between">
        <x-icon-btn icon="visibility" tone="outline" size="sm"
                    data-entry-modal-trigger
                    :href="route('diary.show', $entry)"
                    class="btn-primary"
                    show-label>{{ __('Details') }}</x-icon-btn>
        @can('update', $entry)
            <x-icon-btn icon="edit" size="sm"
                        data-entry-modal-trigger
                        :href="route('diary.edit', $entry)"
                        show-label>{{ __('Bearbeiten') }}</x-icon-btn>
        @endcan
    </div>
</article>
