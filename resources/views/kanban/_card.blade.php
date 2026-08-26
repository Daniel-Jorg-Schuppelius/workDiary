{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _card.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    // Autorisiert der Nutzer eine Auftragsaktion, wird sie als Aktion→URL an
    // kanban.js gereicht (Zeigergeste UND „Verschieben nach"-Menü). Abnahme
    // (handover) bewusst nie per Zug: sie braucht ein signiertes Protokoll und
    // läuft im Auftrag.
    $lifecycleActions = collect($entry->status->allowedActions())
        ->reject(fn (string $action) => $action === 'handover')
        ->filter(fn (string $action) => \Illuminate\Support\Facades\Gate::allows($action, $entry))
        ->mapWithKeys(fn (string $action) => [$action => route('diary.lifecycle', [$entry, 'action' => $action])]);
@endphp
{{--
    Hülle statt Link als Karte: der Zug läuft über Pointer Events (Maus/Touch/
    Stift) und braucht einen Container, der neben dem Eintrags-Link auch den
    Menü-Button trägt. `touch-pan-y` überlässt das vertikale Scrollen der
    Spalte dem Browser; `draggable="false"` schaltet das native Link-Dragging
    ab, das sonst die Zeigergeste abwürgt.
--}}
<div class="group relative touch-pan-y"
     data-kanban-card
     data-id="{{ $entry->id }}"
     data-status="{{ $entry->status->value }}"
     data-actions="{{ $lifecycleActions->toJson(JSON_UNESCAPED_SLASHES) }}">
    <a href="{{ route('diary.show', $entry) }}"
       data-entry-modal-trigger
       draggable="false"
       class="block cursor-grab rounded-lg border border-base-300 bg-base-100 p-2 pe-8 text-sm shadow-xs transition hover:shadow-md active:cursor-grabbing">
        <div class="flex items-center justify-between gap-2 text-[0.65rem] uppercase tracking-wider text-muted">
            <span>{{ $entry->start_at?->format('d.m. H:i') }}</span>
            @if ($entry->user)
                <span class="font-medium">{{ $entry->user->name }}</span>
            @endif
        </div>
        <p class="mt-1 line-clamp-3 text-sm text-base-content">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($entry->content, 140) }}</p>
        @if ($entry->tags->isNotEmpty())
            <div class="mt-1 flex flex-wrap gap-1">
                @foreach ($entry->tags as $tag)
                    <span class="badge badge-xs" style="background: {{ $tag->color }}; color: #fff; border-color: {{ $tag->color }}">{{ $tag->name }}</span>
                @endforeach
            </div>
        @endif
    </a>
    <x-icon-btn icon="drag_indicator"
                :label="__('Verschieben nach')"
                size="xs"
                data-kanban-move
                aria-keyshortcuts="m"
                class="absolute end-1 top-1 opacity-60 transition group-hover:opacity-100 focus-visible:opacity-100" />
</div>
