@props(['align' => 'end'])
@php
    /** @var array{from: \Carbon\CarbonImmutable, to: \Carbon\CarbonImmutable, preset: string, label: string, unit: string, isoWeekLabel: ?string}|null $globalDateRange */
    $globalDateRange = $globalDateRange ?? null;
    if (! $globalDateRange) {
        return;
    }
    $presets = [
        'today' => __('Heute'),
        'last_7_days' => __('Letzte 7 Tage'),
        'this_week' => __('Diese Woche'),
        'last_30_days' => __('Letzte 30 Tage'),
        'this_month' => __('Dieser Monat'),
        'last_month' => __('Letzter Monat'),
        'last_90_days' => __('Letzte 90 Tage'),
        'this_year' => __('Dieses Jahr'),
    ];
    $current = $globalDateRange['preset'];
    $fromIso = $globalDateRange['from']->toDateString();
    $toIso = $globalDateRange['to']->toDateString();
    $kw = $globalDateRange['isoWeekLabel'] ?? null;
    $dropdownAlignClass = $align === 'center' ? 'dropdown-center' : 'dropdown-end';
@endphp

<div class="flex items-center gap-1">
    <form method="POST" action="{{ route('ui.date-range.shift') }}" class="m-0 p-0">
        @csrf
        <input type="hidden" name="direction" value="prev">
        <button type="submit"
                class="btn btn-sm btn-ghost btn-square rounded-box border border-base-300 bg-base-200/70 shadow-xs"
                title="{{ __('Vorige Periode') }}" aria-label="{{ __('Vorige Periode') }}">
            <x-icon name="chevron_left" class="text-[1.1rem]" />
        </button>
    </form>

    <div class="dropdown {{ $dropdownAlignClass }}">
        <label tabindex="0"
               class="btn btn-sm btn-ghost gap-1.5 rounded-box border border-base-300 bg-base-200/70 shadow-xs"
               title="{{ __('Zeitraum auswählen') }}">
            <x-icon name="calendar_month" class="text-[1.1rem] text-primary" />
            <span class="font-['Space_Grotesk'] text-sm font-medium tabular-nums">
                {{ $globalDateRange['label'] }}
            </span>
            @if ($kw)
                <span class="rounded bg-primary/10 px-1.5 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wider text-primary">
                    {{ $kw }}
                </span>
            @endif
            <x-icon name="expand_more" class="text-[1rem] opacity-70" />
        </label>

        <div tabindex="0"
             class="dropdown-content z-60 mt-2 w-[min(18rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
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
            <p class="px-3 pb-2 text-[0.7rem] text-base-content/60">
                {{ __('Nutze die Von/Bis-Felder direkt im Header.') }}
            </p>
        </div>
    </div>

    {{-- Eigener Zeitraum: direkt im Header, immer erreichbar --}}
    <form method="POST" action="{{ route('ui.date-range.update') }}"
          class="hidden items-center gap-1 md:flex">
        @csrf
        <input type="hidden" name="preset" value="custom">
        <x-date-range
            :from="$fromIso"
            :to="$toIso"
            :label="false"
            size="sm"
            inputClass="bg-base-200/70 font-['Space_Grotesk'] tabular-nums shadow-xs"
            class="w-60" />
        <button type="submit"
                class="btn btn-sm btn-ghost btn-square rounded-box border border-base-300 bg-base-200/70 shadow-xs"
                title="{{ __('Übernehmen') }}" aria-label="{{ __('Übernehmen') }}">
            <x-icon name="check" class="text-[1rem] text-primary" />
        </button>
    </form>

    <form method="POST" action="{{ route('ui.date-range.shift') }}" class="m-0 p-0">
        @csrf
        <input type="hidden" name="direction" value="next">
        <button type="submit"
                class="btn btn-sm btn-ghost btn-square rounded-box border border-base-300 bg-base-200/70 shadow-xs"
                title="{{ __('Nächste Periode') }}" aria-label="{{ __('Nächste Periode') }}">
            <x-icon name="chevron_right" class="text-[1.1rem]" />
        </button>
    </form>
</div>
