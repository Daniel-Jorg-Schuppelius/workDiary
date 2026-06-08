{{-- Attendance-Panel — erwartet: $current (App\Models\Attendance|null) --}}
<div class="rounded-box border border-base-300 bg-base-100 px-4 py-3 shadow-xs" data-attendance-panel>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ __('Stempeluhr') }}</h2>
        <div class="flex flex-wrap items-center justify-end gap-1.5">
            @if ($current)
                <x-status-badge tone="success" size="sm">{{ __('Offen') }}</x-status-badge>
                <x-status-badge size="sm" outline>
                    {{ __('Eingestempelt seit :time', ['time' => $current->started_at?->ftime()]) }}
                </x-status-badge>
                @if ($current->break_minutes_total > 0)
                    <x-status-badge tone="ghost" size="sm">
                        {{ __(':min Min. Pause', ['min' => $current->break_minutes_total]) }}
                    </x-status-badge>
                @endif
            @else
                <x-status-badge tone="ghost" size="sm">{{ __('Geschlossen') }}</x-status-badge>
                <x-status-badge size="sm" outline>{{ __('Nicht eingestempelt.') }}</x-status-badge>
            @endif
        </div>
    </div>

    @if ($current)
        <div class="mt-2 flex flex-wrap items-center justify-between gap-1.5" x-data="stopwatch('{{ $current->started_at?->toIso8601String() }}')">
            <div class="font-['Space_Grotesk'] text-lg font-bold tabular-nums text-success" x-text="display">00:00:00</div>

            <div class="ml-auto flex flex-wrap items-center justify-end gap-1.5">
                <form method="POST" action="{{ route('attendance.clock-out') }}" class="flex flex-wrap items-center justify-end gap-1.5">
                    @csrf
                    <div class="join">
                        <span class="join-item flex h-7 items-center border border-base-300 bg-base-200 px-2 text-xs text-base-content/60">{{ __('Pause') }}</span>
                        <input type="number" name="break_minutes" min="0" max="600" value="0" class="input input-bordered input-xs join-item h-7 min-h-7 w-16 px-2 text-right tabular-nums" aria-label="{{ __('Pause (Min.)') }}">
                    </div>
                    <button type="submit" class="btn btn-xs btn-warning h-7 min-h-7 gap-1 px-2">
                        <x-icon name="logout" /> {{ __('Ausstempeln') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('attendance.cancel') }}" class="leading-none">
                    @csrf
                    <button type="submit" class="btn btn-xs btn-ghost btn-square h-7 min-h-7 text-error" title="{{ __('Stempelung verwerfen') }}" aria-label="{{ __('Stempelung verwerfen') }}">
                        <x-icon name="delete" class="text-[0.95rem]" />
                    </button>
                </form>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('attendance.clock-in') }}" class="mt-2 flex justify-end leading-none">
            @csrf
            <button type="submit" class="btn btn-xs btn-success h-7 min-h-7 gap-1 px-2" title="{{ __('Einstempeln') }}">
                <x-icon name="login" class="text-[0.95rem]" /> {{ __('Einstempeln') }}
            </button>
        </form>
    @endif
</div>
