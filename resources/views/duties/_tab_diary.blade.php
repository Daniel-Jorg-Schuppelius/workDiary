{{-- Aufträge (Diary): Karten-Ansicht --}}
<div class="min-h-0 flex-1 overflow-y-auto space-y-3 pr-1">
    @forelse ($entries as $entry)
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
                    <span class="text-sm text-base-content/70">{{ $entry->user?->name ?? '—' }}</span>
                </div>
                <p class="text-base leading-relaxed text-base-content">
                    @php
                        $snippet = truncate($entry->content, 240);
                        $needle  = trim((string) ($filters['q'] ?? ''));
                    @endphp
                    @if ($needle !== '')
                        {!! preg_replace('/(' . preg_quote($needle, '/') . ')/i', '<mark class="bg-warning/40 px-0.5 rounded">$1</mark>', e($snippet)) !!}
                    @else
                        {{ $snippet }}
                    @endif
                </p>
                @if ($entry->tags->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach ($entry->tags as $tag)
                            <span class="badge badge-outline badge-sm"
                                  @if ($tag->color) style="border-color:{{ $tag->color }};color:{{ $tag->color }};" @endif>#{{ $tag->name }}</span>
                        @endforeach
                    </div>
                @endif
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-base-content/65">
                    @if ($entry->start_at)<span class="{{ $entry->start_at->isSunday() ? 'text-error font-semibold' : '' }}">{{ __('Von') }} {{ $entry->start_at->format('d.m.Y H:i') }}</span>@endif
                    @if ($entry->end_at)<span class="{{ $entry->end_at->isSunday() ? 'text-error font-semibold' : '' }}">{{ __('Bis') }} {{ $entry->end_at->format('d.m.Y H:i') }}</span>@endif
                    <span>{{ $entry->created_at->diffForHumans() }}</span>
                </div>
            </div>
            <div class="flex flex-col gap-2 md:items-end md:justify-between">
                <a href="{{ route('diary.show', $entry) }}" data-entry-modal-trigger class="btn btn-outline btn-primary btn-sm">{{ __('Details') }}</a>
                @can('update', $entry)
                    <a href="{{ route('diary.edit', $entry) }}" data-entry-modal-trigger class="btn btn-ghost btn-sm">{{ __('Bearbeiten') }}</a>
                @endcan
            </div>
        </article>
    @empty
        <x-card>
            <x-empty-state :title="__('Keine Einträge gefunden')">
                @if (! empty($tabFilters))
                    <x-slot:action>
                        <a href="{{ route('duties.index', ['tab' => 'diary']) }}" class="btn btn-sm btn-ghost">{{ __('Filter zurücksetzen') }}</a>
                    </x-slot:action>
                @endif
            </x-empty-state>
        </x-card>
    @endforelse
</div>
@if ($entries->total() > 0)
    @include('duties._pagination', ['paginator' => $entries])
@endif
