{{-- Stoppuhr-Panel — erwartet: $current (TimeEntry|null) --}}
<div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
    <h2 class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Stoppuhr') }}</h2>
    @if($current)
        <div class="mt-2">
            <div class="text-2xl font-bold tabular-nums" x-data="stopwatch('{{ $current->started_at?->toIso8601String() }}')">
                <span x-text="display"></span>
            </div>
            <div class="mt-1 text-xs text-base-content/60">{{ $current->description ?: __('Läuft…') }}</div>
            <form method="POST" action="{{ route('stopwatch.stop') }}" class="mt-2">
                @csrf
                <button class="btn btn-sm btn-error">{{ __('Stoppen') }}</button>
            </form>
        </div>
    @else
        <div class="mt-2 text-sm text-base-content/60">{{ __('Keine laufende Erfassung.') }}</div>
    @endif
</div>
