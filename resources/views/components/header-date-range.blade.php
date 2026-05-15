@php
    /** @var array{from: \Carbon\CarbonImmutable, to: \Carbon\CarbonImmutable, preset: string, label: string}|null $globalDateRange */
    $globalDateRange = $globalDateRange ?? null;
    if (! $globalDateRange) {
        return;
    }
    $presets = [
        'today' => __('Heute'),
        'this_week' => __('Diese Woche'),
        'this_month' => __('Dieser Monat'),
        'last_month' => __('Letzter Monat'),
        'this_year' => __('Dieses Jahr'),
    ];
    $current = $globalDateRange['preset'];
    $fromIso = $globalDateRange['from']->toDateString();
    $toIso = $globalDateRange['to']->toDateString();
@endphp

<div class="dropdown dropdown-end">
    <label tabindex="0"
           class="btn btn-sm btn-ghost gap-1.5 rounded-box border border-base-300 bg-base-200/70 shadow-xs"
           title="{{ __('Zeitraum auswählen') }}">
        <x-icon name="calendar_month" class="text-[1.1rem] text-primary" />
        <span class="hidden font-['Space_Grotesk'] text-sm font-medium tabular-nums sm:inline">
            {{ $globalDateRange['label'] }}
        </span>
        <x-icon name="expand_more" class="hidden text-[1rem] opacity-70 sm:inline" />
    </label>

    <div tabindex="0"
         class="dropdown-content z-[60] mt-2 w-72 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
        <div class="mb-2 px-2 pt-1 text-[0.7rem] uppercase tracking-[0.2em] text-base-content/60">
            {{ __('Schnellauswahl') }}
        </div>
        <ul class="menu menu-sm gap-0.5 p-0">
            @foreach ($presets as $key => $label)
                <li>
                    <form method="POST" action="{{ route('ui.date-range.update') }}" class="m-0 p-0">
                        @csrf
                        <input type="hidden" name="preset" value="{{ $key }}">
                        <button type="submit"
                                class="flex w-full items-center justify-between gap-2 rounded-md px-3 py-1.5 text-left {{ $current === $key ? 'bg-primary/10 text-primary font-medium' : 'hover:bg-base-200' }}">
                            <span>{{ $label }}</span>
                            @if ($current === $key)
                                <x-icon name="check" class="text-[1rem] text-primary" />
                            @endif
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>

        <div class="divider my-1.5 text-[0.7rem] uppercase tracking-[0.2em] text-base-content/50">
            {{ __('Eigener Zeitraum') }}
        </div>

        <form method="POST" action="{{ route('ui.date-range.update') }}" class="space-y-2 px-2 pb-1">
            @csrf
            <input type="hidden" name="preset" value="custom">
            <label class="block">
                <span class="text-[0.7rem] uppercase tracking-wider text-base-content/60">{{ __('Von') }}</span>
                <input type="date"
                       name="from"
                       value="{{ $fromIso }}"
                       class="input input-sm input-bordered mt-1 w-full">
            </label>
            <label class="block">
                <span class="text-[0.7rem] uppercase tracking-wider text-base-content/60">{{ __('Bis') }}</span>
                <input type="date"
                       name="to"
                       value="{{ $toIso }}"
                       class="input input-sm input-bordered mt-1 w-full">
            </label>
            <button type="submit" class="btn btn-sm btn-primary w-full gap-1">
                <x-icon name="check" class="text-[1rem]" />
                {{ __('Übernehmen') }}
            </button>
        </form>
    </div>
</div>
