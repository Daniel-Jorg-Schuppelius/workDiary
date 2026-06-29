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
        'this_quarter' => __('Dieses Quartal'),
        'last_quarter' => __('Letztes Quartal'),
        'last_90_days' => __('Letzte 90 Tage'),
        'this_year' => __('Dieses Jahr'),
    ];
    $current = $globalDateRange['effectivePreset'] ?? $globalDateRange['preset'];
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
            <span data-header-daterange-label
                  class="font-['Space_Grotesk'] text-sm font-medium tabular-nums">
                {{ $globalDateRange['label'] }}
            </span>
            <span data-header-daterange-kw
                  class="rounded bg-primary/10 px-1.5 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wider text-primary {{ $kw ? '' : 'hidden' }}">
                {{ $kw ?? '' }}
            </span>
            <x-icon name="expand_more" class="text-[1rem] opacity-70" />
        </label>

        <div tabindex="0"
             class="dropdown-content z-60 mt-2 w-[min(18rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
            <div class="mb-2 px-2 pt-1 text-[0.7rem] uppercase tracking-[0.2em] text-base-content/60">
                {{ __('Schnellauswahl') }}
            </div>
            <ul class="flex flex-col gap-0.5 p-0 m-0 list-none">
                @foreach ($presets as $key => $label)
                    <li class="w-full">
                        <form method="POST" action="{{ route('ui.date-range.update') }}" class="m-0 p-0 w-full block">
                            @csrf
                            <input type="hidden" name="preset" value="{{ $key }}">
                            <button type="submit"
                                    data-header-daterange-preset="{{ $key }}"
                                    class="grid w-full grid-cols-[1fr_1rem] items-center gap-2 rounded-md px-3 py-1.5 text-left {{ $current === $key ? 'bg-primary/10 text-primary font-medium' : 'hover:bg-base-200' }}">
                                <span class="truncate">{{ $label }}</span>
                                <span
                                    data-header-daterange-check
                                    class="material-symbols-outlined leading-none align-middle shrink-0 select-none justify-self-end text-[1rem] text-primary"
                                    style="font-variation-settings: 'FILL' 0, 'wght' 400; visibility: {{ $current === $key ? 'visible' : 'hidden' }};"
                                    aria-hidden="true">check</span>
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
          class="hidden items-center gap-1 md:flex"
          data-header-daterange>
        @csrf
        <input type="hidden" name="preset" value="custom">
        <x-date-range
            :from="$fromIso"
            :to="$toIso"
            :label="false"
            size="sm"
            fromId="hdr-dr-from"
            toId="hdr-dr-to"
            inputClass="bg-base-200/70 font-['Space_Grotesk'] tabular-nums shadow-xs"
            class="w-60" />
        <button type="submit"
                class="btn btn-sm btn-ghost btn-square rounded-box border border-base-300 bg-base-200/70 shadow-xs"
                title="{{ __('Übernehmen') }}" aria-label="{{ __('Übernehmen') }}">
            <x-icon name="check" class="text-[1rem] text-primary" />
        </button>
    </form>

    @once
        @push('scripts')
            <script @cspNonce>
                (function () {
                    var PRESET_LABELS = {
                        today: @json(__('Heute')),
                        this_week: @json(__('Diese Woche')),
                        this_month: @json(__('Dieser Monat')),
                        last_month: @json(__('Letzter Monat')),
                        this_quarter: @json(__('Dieses Quartal')),
                        last_quarter: @json(__('Letztes Quartal')),
                        this_year: @json(__('Dieses Jahr')),
                        last_7_days: @json(__('Letzte 7 Tage')),
                        last_30_days: @json(__('Letzte 30 Tage')),
                        last_90_days: @json(__('Letzte 90 Tage')),
                    };
                    var SINGLE_DAY_LABELS = {
                        yesterday: @json(__('Gestern')),
                        today: @json(__('Heute')),
                        tomorrow: @json(__('Morgen')),
                    };

                    function formatDate(iso) {
                        if (!iso) return '';
                        var m = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                        if (!m) return iso;
                        return m[3] + '.' + m[2] + '.' + m[1];
                    }
                    // ISO-Woche nach ISO 8601 (Donnerstag-Regel).
                    function isoWeek(iso) {
                        var m = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                        if (!m) return null;
                        var d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
                        var day = d.getUTCDay() || 7;
                        d.setUTCDate(d.getUTCDate() + 4 - day);
                        var yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
                        var week = Math.ceil(((d - yearStart) / 86400000 + 1) / 7);
                        return { week: week, year: d.getUTCFullYear() };
                    }
                    function toIso(d) {
                        return d.getFullYear() + '-'
                            + String(d.getMonth() + 1).padStart(2, '0') + '-'
                            + String(d.getDate()).padStart(2, '0');
                    }
                    function localMonday(d) {
                        var r = new Date(d.getFullYear(), d.getMonth(), d.getDate());
                        var day = r.getDay() || 7;
                        r.setDate(r.getDate() - (day - 1));
                        return r;
                    }
                    function localSunday(d) {
                        var r = new Date(d.getFullYear(), d.getMonth(), d.getDate());
                        var day = r.getDay() || 7;
                        r.setDate(r.getDate() + (7 - day));
                        return r;
                    }
                    function isoMondayStr(iso) {
                        var m = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                        if (!m) return null;
                        return toIso(localMonday(new Date(+m[1], +m[2] - 1, +m[3])));
                    }
                    function isoSundayStr(iso) {
                        var m = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                        if (!m) return null;
                        return toIso(localSunday(new Date(+m[1], +m[2] - 1, +m[3])));
                    }

                    function presetRanges() {
                        var now = new Date();
                        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                        var monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
                        var monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                        var lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                        var lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
                        var quarterStartMonth = Math.floor(today.getMonth() / 3) * 3;
                        var quarterStart = new Date(today.getFullYear(), quarterStartMonth, 1);
                        var quarterEnd = new Date(today.getFullYear(), quarterStartMonth + 3, 0);
                        var lastQuarterStart = new Date(today.getFullYear(), quarterStartMonth - 3, 1);
                        var lastQuarterEnd = new Date(today.getFullYear(), quarterStartMonth, 0);
                        var yearStart = new Date(today.getFullYear(), 0, 1);
                        var yearEnd = new Date(today.getFullYear(), 11, 31);
                        function minusDays(n) {
                            var d = new Date(today);
                            d.setDate(d.getDate() - n);
                            return d;
                        }
                        return {
                            today: [today, today],
                            this_week: [localMonday(today), localSunday(today)],
                            this_month: [monthStart, monthEnd],
                            last_month: [lastMonthStart, lastMonthEnd],
                            this_quarter: [quarterStart, quarterEnd],
                            last_quarter: [lastQuarterStart, lastQuarterEnd],
                            this_year: [yearStart, yearEnd],
                            last_7_days: [minusDays(6), today],
                            last_30_days: [minusDays(29), today],
                            last_90_days: [minusDays(89), today],
                        };
                    }

                    function detectPreset(from, to) {
                        var ranges = presetRanges();
                        for (var key in ranges) {
                            if (!Object.prototype.hasOwnProperty.call(ranges, key)) continue;
                            if (toIso(ranges[key][0]) === from && toIso(ranges[key][1]) === to) {
                                return key;
                            }
                        }
                        return null;
                    }

                    function updateLabel(from, to) {
                        var label = document.querySelector('[data-header-daterange-label]');
                        var kw = document.querySelector('[data-header-daterange-kw]');
                        if (label) {
                            var matched = (from && to) ? detectPreset(from, to) : null;
                            if (matched && PRESET_LABELS[matched]) {
                                label.textContent = PRESET_LABELS[matched];
                            } else if (from && to && from === to) {
                                var now = new Date();
                                var today = toIso(new Date(now.getFullYear(), now.getMonth(), now.getDate()));
                                var ymd = function (offset) {
                                    var d = new Date(now.getFullYear(), now.getMonth(), now.getDate() + offset);
                                    return toIso(d);
                                };
                                if (from === today) label.textContent = SINGLE_DAY_LABELS.today;
                                else if (from === ymd(-1)) label.textContent = SINGLE_DAY_LABELS.yesterday;
                                else if (from === ymd(1)) label.textContent = SINGLE_DAY_LABELS.tomorrow;
                                else label.textContent = formatDate(from);
                            } else {
                                label.textContent = formatDate(from) + ' – ' + formatDate(to);
                            }
                        }
                        if (kw) {
                            if (from && to && isoMondayStr(from) === from && isoSundayStr(from) === to) {
                                var w = isoWeek(from);
                                if (w) {
                                    kw.textContent = 'KW ' + String(w.week).padStart(2, '0') + '/' + w.year;
                                    kw.classList.remove('hidden');
                                    return;
                                }
                            }
                            kw.classList.add('hidden');
                            kw.textContent = '';
                        }

                        // Aktiven Preset-Eintrag in der Dropdown-Liste live spiegeln.
                        var matchedPreset = (from && to) ? detectPreset(from, to) : null;
                        document.querySelectorAll('[data-header-daterange-preset]').forEach(function (btn) {
                            var key = btn.getAttribute('data-header-daterange-preset');
                            var active = (key === matchedPreset);
                            btn.classList.toggle('bg-primary/10', active);
                            btn.classList.toggle('text-primary', active);
                            btn.classList.toggle('font-medium', active);
                            btn.classList.toggle('hover:bg-base-200', !active);
                            var check = btn.querySelector('[data-header-daterange-check]');
                            if (check) check.style.visibility = active ? 'visible' : 'hidden';
                        });
                    }

                    function wire(form) {
                        var from = form.querySelector('#hdr-dr-from');
                        var to = form.querySelector('#hdr-dr-to');
                        if (!from || !to) return;

                        function nativeSync() {
                            if (from.value) {
                                to.min = from.value;
                                if (to.value && to.value < from.value) {
                                    to.value = from.value;
                                }
                            } else {
                                to.removeAttribute('min');
                            }
                            if (to.value) {
                                from.max = to.value;
                            } else {
                                from.removeAttribute('max');
                            }
                        }

                        function fpSync() {
                            var fpFrom = from._flatpickr;
                            var fpTo = to._flatpickr;
                            if (!fpFrom || !fpTo) return false;
                            fpTo.set('minDate', from.value || null);
                            fpFrom.set('maxDate', to.value || null);
                            if (from.value && to.value && to.value < from.value) {
                                fpTo.setDate(from.value, true);
                            }
                            return true;
                        }

                        var submitTimer = null;
                        function scheduleSubmit() {
                            if (submitTimer) clearTimeout(submitTimer);
                            submitTimer = setTimeout(function () {
                                if (from.value && to.value && to.value >= from.value) {
                                    form.submit();
                                }
                            }, 350);
                        }

                        function sync() {
                            fpSync();
                            nativeSync();
                            updateLabel(from.value, to.value);
                            scheduleSubmit();
                        }

                        from.addEventListener('change', sync);
                        from.addEventListener('input', sync);
                        to.addEventListener('change', sync);
                        to.addEventListener('input', sync);

                        // flatpickr wird per Vite-Modul-Script (defer) initialisiert.
                        // Wir warten DOMContentLoaded ab, retryen aber kurz falls
                        // die Instanz noch nicht angehängt ist.
                        var tries = 0;
                        function init() {
                            if (fpSync()) return;
                            if (++tries < 20) setTimeout(init, 50);
                            else nativeSync();
                        }
                        init();
                    }

                    function boot() {
                        document.querySelectorAll('form[data-header-daterange]').forEach(wire);
                    }
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', boot);
                    } else {
                        boot();
                    }
                })();
            </script>
        @endpush
    @endonce

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
