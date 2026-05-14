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

<div class="divider">{{ __('Compliance-Prüfung') }}</div>

<div class="form-control">
    <label class="label"><span class="label-text">{{ __('Modus') }}</span></label>
    <select name="compliance[mode]" class="select select-bordered">
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

<div class="grid grid-cols-2 gap-4">
    <div class="form-control">
        <label class="label"><span class="label-text">{{ __('Max. Stunden/Tag') }}</span></label>
        <input type="number" min="1" max="24" name="compliance[max_hours_day]" class="input input-bordered"
               value="{{ old('compliance.max_hours_day', $current['max_hours_day']) }}">
    </div>
    <div class="form-control">
        <label class="label"><span class="label-text">{{ __('Min. Ruhezeit (h)') }}</span></label>
        <input type="number" min="1" max="24" name="compliance[min_rest_hours]" class="input input-bordered"
               value="{{ old('compliance.min_rest_hours', $current['min_rest_hours']) }}">
    </div>
    <div class="form-control">
        <label class="label"><span class="label-text">{{ __('Max. Stunden/Woche') }}</span></label>
        <input type="number" min="1" max="168" name="compliance[max_hours_week]" class="input input-bordered"
               value="{{ old('compliance.max_hours_week', $current['max_hours_week']) }}">
    </div>
    <div class="form-control">
        <label class="label"><span class="label-text">{{ __('Max. Tage am Stück') }}</span></label>
        <input type="number" min="1" max="14" name="compliance[max_consecutive_days]" class="input input-bordered"
               value="{{ old('compliance.max_consecutive_days', $current['max_consecutive_days']) }}">
    </div>
</div>

<fieldset class="form-control gap-2">
    <legend class="label-text font-medium">{{ __('Aktive Regeln') }}</legend>
    {{-- Hidden 0 als Default vor jeder Checkbox, damit unchecked = false ankommt --}}
    @foreach ($ruleLabels as $key => $label)
        <label class="cursor-pointer label justify-start gap-3">
            <input type="hidden" name="compliance[rules][{{ $key }}]" value="0">
            <input type="checkbox" name="compliance[rules][{{ $key }}]" value="1" class="checkbox checkbox-sm"
                   @checked(old('compliance.rules.' . $key, (bool) ($current['rules'][$key] ?? true)))>
            <span class="label-text">{{ $label }}</span>
        </label>
    @endforeach
</fieldset>
