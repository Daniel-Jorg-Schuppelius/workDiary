{{--
  Quick-Buchung offener Zeitblöcke (MVP-015, Rang 37).

  Je offenem Block ein eigenständiges Formular → funktioniert OHNE JS
  (Projekt wählen, „Buchen" → Server-Redirect). Mit JS zusätzlich: Block per
  Drag auf ein Projekt-Ziel ziehen bzw. Ctrl/Cmd+Enter = buchen + weiter
  (resources/js/quick-book.js). Erwartet: $openBlocks, $quickBookProjects, $fmt.
--}}
@if (! empty($openBlocks) && $quickBookProjects->isNotEmpty())
    <x-card as="section" data-qb-panel data-qb-url="{{ route('today.quick-book') }}">
        <header class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Quick-Buchung') }}</h2>
            <span class="text-xs text-base-content/60">{{ __('Offenen Block auf ein Projekt ziehen oder unten wählen.') }}</span>
        </header>

        {{-- Drag-Ziele: aktive Projekte (zuletzt genutzte zuerst). --}}
        <div class="mb-3 flex flex-wrap gap-2">
            @foreach ($quickBookProjects as $p)
                <span data-qb-target data-project="{{ $p->sqid }}"
                      class="qb-target inline-flex items-center gap-1 rounded-box border border-dashed border-base-300 bg-base-200/60 px-3 py-1 text-xs">
                    <span class="material-symbols-outlined text-sm" aria-hidden="true">folder</span>{{ $p->name }}
                </span>
            @endforeach
        </div>

        <ul class="space-y-2">
            @foreach ($openBlocks as $block)
                <li data-qb-block draggable="true"
                    data-started-at="{{ $block['started_at']->toIso8601String() }}"
                    data-ended-at="{{ $block['ended_at']->toIso8601String() }}"
                    data-minutes="{{ $block['minutes'] }}"
                    class="flex flex-wrap items-center gap-2 rounded-box bg-base-200/70 px-3 py-2">
                    <span class="material-symbols-outlined cursor-grab text-base-content/50" aria-hidden="true">drag_indicator</span>
                    <span class="tabular-nums text-sm font-medium">
                        {{ $block['started_at']->format('H:i') }}–{{ $block['ended_at']->format('H:i') }}
                        <span class="text-base-content/60">({{ $fmt($block['minutes']) }})</span>
                    </span>
                    <form method="POST" action="{{ route('today.quick-book') }}" class="qb-form ml-auto flex items-center gap-2">
                        @csrf
                        <input type="hidden" name="started_at" value="{{ $block['started_at']->toIso8601String() }}">
                        <input type="hidden" name="ended_at" value="{{ $block['ended_at']->toIso8601String() }}">
                        <label class="sr-only" for="qb-project-{{ $loop->index }}">{{ __('Projekt') }}</label>
                        <select id="qb-project-{{ $loop->index }}" name="project" required
                                class="select select-sm select-bordered">
                            <option value="">{{ __('— Projekt —') }}</option>
                            @foreach ($quickBookProjects as $p)
                                <option value="{{ $p->sqid }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Buchen') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </x-card>
@endif
