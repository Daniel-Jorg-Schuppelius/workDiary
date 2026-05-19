{{-- Compliance-Settings für eine Organisation. --}}
@php
    /** @var \App\Models\Organization|null $organization */
    $current = $organization?->complianceSettings() ?? \App\Models\Organization::COMPLIANCE_DEFAULTS;
    $ruleLabels = [
        'overlap'             => __('Überlappende Schichten'),
        'rest_period'         => __('Mindestruhezeit'),
        'max_daily_hours'     => __('Tagesarbeitszeit'),
        'max_weekly_hours'    => __('Wochenarbeitszeit'),
        'consecutive_days'    => __('Aufeinanderfolgende Tage'),
        'vacation_conflict'   => __('Urlaubskonflikt'),
        'qualification_match' => __('Qualifikations-Match'),
        'holiday_double_book' => __('Feiertagsbuchung'),
    ];
@endphp

<x-form-group :legend="__('Compliance-Modus')" icon="policy" tone="warning" cols="1"
              :description="__('Steuert, wie streng die ArbZG-Prüfungen beim Speichern reagieren.')">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Modus') }}</label>
        <select name="compliance[mode]" class="select select-bordered w-full">
            @foreach (\App\Models\Organization::$complianceModes as $mode)
                <option value="{{ $mode }}" @selected(old('compliance.mode', $current['mode']) === $mode)>
                    @switch($mode)
                        @case('off') {{ __('Aus') }} @break
                        @case('warn') {{ __('Warnen') }} @break
                        @case('block') {{ __('Blockieren') }} @break
                    @endswitch
                </option>
            @endforeach
        </select>
    </div>
</x-form-group>

<x-form-group :legend="__('Arbeitszeit-Grenzwerte')" icon="schedule" tone="info" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Max. Stunden/Tag') }}</label>
        <input type="number" min="1" max="24" name="compliance[max_hours_day]"
               class="input input-bordered w-full"
               value="{{ old('compliance.max_hours_day', $current['max_hours_day']) }}">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Min. Ruhezeit (h)') }}</label>
        <input type="number" min="1" max="24" name="compliance[min_rest_hours]"
               class="input input-bordered w-full"
               value="{{ old('compliance.min_rest_hours', $current['min_rest_hours']) }}">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Max. Stunden/Woche') }}</label>
        <input type="number" min="1" max="168" name="compliance[max_hours_week]"
               class="input input-bordered w-full"
               value="{{ old('compliance.max_hours_week', $current['max_hours_week']) }}">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Max. Tage am Stück') }}</label>
        <input type="number" min="1" max="14" name="compliance[max_consecutive_days]"
               class="input input-bordered w-full"
               value="{{ old('compliance.max_consecutive_days', $current['max_consecutive_days']) }}">
    </div>
</x-form-group>

<x-form-group :legend="__('Aktive Regeln')" icon="rule" tone="ghost" cols="2">
    @foreach ($ruleLabels as $key => $label)
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="compliance[rules][{{ $key }}]" value="0">
            <input type="checkbox" name="compliance[rules][{{ $key }}]" value="1" class="checkbox checkbox-sm"
                   @checked(old('compliance.rules.' . $key, (bool) ($current['rules'][$key] ?? true)))>
            <span class="label-text">{{ $label }}</span>
        </label>
    @endforeach
</x-form-group>
