{{-- Tab: Tagebuch — erwartet: $project, $entries --}}
<div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
    <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
        <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Tagebucheinträge') }}</span>
        <a href="{{ route('diary.index', ['project' => $project->id]) }}"
           class="btn btn-sm btn-ghost">{{ __('Im Tagebuch öffnen') }}</a>
    </header>
    <ul class="divide-y divide-base-300">
        @forelse ($entries as $entry)
            <li class="flex flex-wrap items-start justify-between gap-2 px-4 py-3">
                <a href="{{ route('diary.show', $entry) }}" data-entry-modal-trigger class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-base-content/60">
                        <span>{{ optional($entry->start_at)->format('d.m.Y H:i') }}</span>
                        <span>· {{ $entry->user->name ?? '—' }}</span>
                        @foreach ($entry->tags as $tag)
                            <span class="badge badge-xs" style="background:{{ $tag->color ?? '#94a3b8' }};color:#fff">{{ $tag->name }}</span>
                        @endforeach
                    </div>
                    <div class="line-clamp-2 text-sm">{{ truncate($entry->content, 200) }}</div>
                </a>
            </li>
        @empty
            <li class="px-4 py-8 text-center text-sm text-base-content/60">{{ __('Keine Einträge zugeordnet.') }}</li>
        @endforelse
    </ul>
</div>
