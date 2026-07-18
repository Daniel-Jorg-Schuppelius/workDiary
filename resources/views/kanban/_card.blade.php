@php
    // Autorisiert der Nutzer eine Auftragsaktion, wird sie als Aktion→URL an
    // das Drag-and-Drop (kanban.js) gereicht. Abnahme (handover) bewusst nie
    // per Drag: sie braucht ein signiertes Protokoll und läuft im Auftrag.
    $lifecycleActions = collect($entry->status->allowedActions())
        ->reject(fn (string $action) => $action === 'handover')
        ->filter(fn (string $action) => \Illuminate\Support\Facades\Gate::allows($action, $entry))
        ->mapWithKeys(fn (string $action) => [$action => route('diary.lifecycle', [$entry, 'action' => $action])]);
@endphp
<a href="{{ route('diary.show', $entry) }}"
   data-entry-modal-trigger
   data-kanban-card
   data-id="{{ $entry->id }}"
   data-status="{{ $entry->status->value }}"
   data-actions="{{ $lifecycleActions->toJson(JSON_UNESCAPED_SLASHES) }}"
   draggable="true"
   class="block cursor-grab rounded-lg border border-base-300 bg-base-100 p-2 text-sm shadow-xs transition hover:shadow-md active:cursor-grabbing">
    <div class="flex items-center justify-between gap-2 text-[0.65rem] uppercase tracking-wider text-base-content/60">
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
