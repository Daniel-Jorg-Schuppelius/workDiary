{{-- Attendance-Panel — erwartet: $current (App\Models\Attendance|null) --}}
<div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs" data-attendance-panel>
    <div class="flex items-center justify-between gap-2">
        <h2 class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Stempeluhr') }}</h2>
        @if ($current)
            <span class="badge badge-success badge-sm">{{ __('Offen') }}</span>
        @else
            <span class="badge badge-ghost badge-sm">{{ __('Geschlossen') }}</span>
        @endif
    </div>

    @if ($current)
        <div class="mt-2" x-data="{ s: 0 }" x-init="s = Math.max(0, Math.floor((Date.now() - new Date('{{ $current->started_at?->toIso8601String() }}').getTime())/1000)); setInterval(() => s++, 1000);">
            <div class="font-['Space_Grotesk'] text-2xl font-bold tabular-nums text-success"
                 x-text="String(Math.floor(s/3600)).padStart(2,'0') + ':' + String(Math.floor((s%3600)/60)).padStart(2,'0') + ':' + String(s%60).padStart(2,'0')">00:00:00</div>
            <p class="mt-1 text-xs text-base-content/60">
                {{ __('Eingestempelt seit :time', ['time' => $current->started_at?->format('H:i')]) }}
                @if ($current->break_minutes_total > 0)
                    · {{ __(':min Min. Pause', ['min' => $current->break_minutes_total]) }}
                @endif
            </p>

            <form method="POST" action="{{ route('attendance.clock-out') }}" class="mt-3 flex flex-wrap items-end gap-2">
                @csrf
                <div class="form-control">
                    <label class="label py-0"><span class="label-text text-xs">{{ __('Pause (Min.)') }}</span></label>
                    <input type="number" name="break_minutes" min="0" max="600" value="0" class="input input-bordered input-sm w-24">
                </div>
                <button type="submit" class="btn btn-sm btn-warning gap-1">
                    <x-icon name="logout" /> {{ __('Ausstempeln') }}
                </button>
            </form>

            <form method="POST" action="{{ route('attendance.cancel') }}" class="mt-1">
                @csrf
                <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Stempelung verwerfen') }}</button>
            </form>
        </div>
    @else
        <p class="mt-2 text-sm text-base-content/60">{{ __('Nicht eingestempelt.') }}</p>
        <form method="POST" action="{{ route('attendance.clock-in') }}" class="mt-3">
            @csrf
            <button type="submit" class="btn btn-sm btn-success gap-1">
                <x-icon name="login" /> {{ __('Einstempeln') }}
            </button>
        </form>
    @endif
</div>
