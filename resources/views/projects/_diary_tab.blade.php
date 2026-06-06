{{-- Tab: Aufträge — erwartet: $project, $entries
     $entries enthält Initial-Aufträge (project_id=p) UND Aufträge, die nur
     über TimeEntries auf diesem Projekt buchen. --}}
<div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
    <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
        <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Aufträge') }}</span>
        <a href="{{ route('diary.index', ['project' => \App\Support\Sqid::encode(\App\Models\Project::class, $project->id)]) }}"
           class="btn btn-sm btn-ghost">{{ __('In der Arbeitsliste öffnen') }}</a>
    </header>
    <ul class="divide-y divide-base-300">
        @forelse ($entries as $entry)
            @php
                $primary = (int) $entry->project_id === (int) $project->id;
                $dateLabel = match ($entry->mode) {
                    \App\Enums\Diary\Mode::Deadline => $entry->due_date?->fdate(),
                    \App\Enums\Diary\Mode::Window => $entry->window_start_date?->fdate(),
                    \App\Enums\Diary\Mode::Backlog => __('Backlog'),
                    default => $entry->start_at?->fdatetime(),
                };
            @endphp
            <li class="flex flex-wrap items-start justify-between gap-2 px-4 py-3">
                <a href="{{ route('diary.show', $entry) }}" data-entry-modal-trigger class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-base-content/60">
                        @if ($dateLabel)
                            <span>{{ $dateLabel }}</span>
                            <span>·</span>
                        @endif
                        <span>{{ $entry->user->name ?? '—' }}</span>
                        @if ($entry->mode && $entry->mode !== \App\Enums\Diary\Mode::Fixed)
                            <x-status-badge size="xs" outline>{{ $entry->modeLabel() }}</x-status-badge>
                        @endif
                        @if (! $primary)
                            <x-status-badge tone="ghost" size="xs" title="{{ __('Auftrag bucht nur Stunden auf dieses Projekt') }}">
                                {{ __('via Zeiteinträge') }}
                            </x-status-badge>
                        @endif
                        @foreach ($entry->tags as $tag)
                            <span class="badge badge-xs" style="background:{{ $tag->color ?? '#94a3b8' }};color:#fff">{{ $tag->name }}</span>
                        @endforeach
                    </div>
                    <div class="line-clamp-2 text-sm">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($entry->content, 200) }}</div>
                </a>
            </li>
        @empty
            <li class="p-4">
                <x-empty-state compact
                    icon='<span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>'
                    :title="__('Keine Aufträge auf dieses Projekt gebucht.')" />
            </li>
        @endforelse
    </ul>
</div>
