{{--
  Created on   : Thu May 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _compliance.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
        'plausibility_missing_checkout' => __('Vergessene Geht-Stempelung'),
        'plausibility_free_day'         => __('Stempelung an freiem Tag'),
        'plausibility_absence_conflict' => __('Stempelung trotz Abwesenheit'),
        'plausibility_frame_time'       => __('Rahmenzeit (Stempelzeiten)'),
    ];
@endphp

<x-form-group :legend="__('Compliance-Modus')" icon="policy" tone="warning" cols="1"
              :description="__('Steuert, wie streng die ArbZG-Prüfungen beim Speichern reagieren.')">
    <x-select-field name="compliance[mode]" :label="__('Modus')">
        @foreach (\App\Models\Organization::$complianceModes as $mode)
            <option value="{{ $mode }}" @selected(old('compliance.mode', $current['mode']) === $mode)>
                @switch($mode)
                    @case('off') {{ __('Aus') }} @break
                    @case('warn') {{ __('Warnen') }} @break
                    @case('block') {{ __('Blockieren') }} @break
                @endswitch
            </option>
        @endforeach
    </x-select-field>
</x-form-group>

<x-form-group :legend="__('Arbeitszeit-Modell')" icon="tune" tone="primary" cols="1"
              :description="__('Standard-Arbeitszeit-Typ für neue Mitarbeiter (pro Person überschreibbar).')">
    <div class="fieldset">
        <label class="fieldset-label" for="settings-timesheet-default_schedule_type">{{ __('Standard-Arbeitszeit-Typ') }}</label>
        @php $defaultType = old('settings.timesheet.default_schedule_type', $organization?->defaultScheduleType() ?? 'flextime'); @endphp
        <select id="settings-timesheet-default_schedule_type" name="settings[timesheet][default_schedule_type]" class="select select-bordered w-full">
            @foreach (\App\Enums\WorkSchedule\ScheduleType::options() as $val => $lbl)
                <option value="{{ $val }}" @selected($defaultType === $val)>{{ $lbl }}</option>
            @endforeach
        </select>
    </div>
</x-form-group>

<x-form-group :legend="__('Arbeitszeit-Grenzwerte')" icon="schedule" tone="info" cols="2">
    <x-input-field name="compliance[max_hours_day]"
                   :label="__('Max. Stunden/Tag')"
                   type="number"
                   value="{{ old('compliance.max_hours_day', $current['max_hours_day']) }}"
                   min="1"
                   max="24" />
    <x-input-field name="compliance[min_rest_hours]"
                   :label="__('Min. Ruhezeit (h)')"
                   type="number"
                   value="{{ old('compliance.min_rest_hours', $current['min_rest_hours']) }}"
                   min="1"
                   max="24" />
    <x-input-field name="compliance[max_hours_week]"
                   :label="__('Max. Stunden/Woche')"
                   type="number"
                   value="{{ old('compliance.max_hours_week', $current['max_hours_week']) }}"
                   min="1"
                   max="168" />
    <x-input-field name="compliance[max_consecutive_days]"
                   :label="__('Max. Tage am Stück')"
                   type="number"
                   value="{{ old('compliance.max_consecutive_days', $current['max_consecutive_days']) }}"
                   min="1"
                   max="14" />
    <x-input-field name="compliance[frame_tolerance_minutes]"
                   :label="__('Bagatellgrenze Rahmenzeit (Min.)')"
                   type="number"
                   value="{{ old('compliance.frame_tolerance_minutes', $current['frame_tolerance_minutes'] ?? 15) }}"
                   min="0"
                   max="240" />
    @php
        $flexSettings = (array) data_get($organization?->settings, 'flex', []);
    @endphp
    <x-input-field name="settings[flex][warn_minutes]"
                   :label="__('Gleitzeit-Ampel: Gelb ab (Min.)')"
                   type="number"
                   value="{{ old('settings.flex.warn_minutes', $flexSettings['warn_minutes'] ?? \App\Services\Flextime\FlexTrafficLight::DEFAULT_WARN_MINUTES) }}"
                   min="0"
                   max="100000" />
    <x-input-field name="settings[flex][critical_minutes]"
                   :label="__('Gleitzeit-Ampel: Rot ab (Min.)')"
                   type="number"
                   value="{{ old('settings.flex.critical_minutes', $flexSettings['critical_minutes'] ?? \App\Services\Flextime\FlexTrafficLight::DEFAULT_CRITICAL_MINUTES) }}"
                   min="0"
                   max="100000" />
</x-form-group>

<x-form-group :legend="__('Genehmigungen')" icon="how_to_reg" tone="ghost" cols="1"
              :description="__('Genehmigungsstufen je Antragstyp: einstufig oder zweistufig (Vier-Augen-Prinzip).')">
    @php $stages = (int) old('settings.vacation.approval_stages', data_get($organization?->settings, 'vacation.approval_stages', 1)); @endphp
    <x-select-field name="settings[vacation][approval_stages]" :label="__('Urlaubs-Genehmigungsstufen')">
        <option value="1" @selected($stages === 1)>{{ __('Einstufig (eine Freigabe)') }}</option>
        <option value="2" @selected($stages === 2)>{{ __('Zweistufig (Vier-Augen-Prinzip)') }}</option>
    </x-select-field>
    {{-- MVP-531: generisches Antragsverfahren — weitere Antragstypen. --}}
    @php $otStages = (int) old('settings.approvals.overtime_stages', data_get($organization?->settings, 'approvals.overtime_stages', 1)); @endphp
    <x-select-field name="settings[approvals][overtime_stages]" :label="__('Überstunden-Genehmigungsstufen')">
        <option value="1" @selected($otStages === 1)>{{ __('Einstufig (eine Freigabe)') }}</option>
        <option value="2" @selected($otStages === 2)>{{ __('Zweistufig (Vier-Augen-Prinzip)') }}</option>
    </x-select-field>
    @php $tcStages = (int) old('settings.approvals.time_correction_stages', data_get($organization?->settings, 'approvals.time_correction_stages', 1)); @endphp
    <x-select-field name="settings[approvals][time_correction_stages]" :label="__('Zeitkorrektur-Genehmigungsstufen')">
        <option value="1" @selected($tcStages === 1)>{{ __('Einstufig (eine Freigabe)') }}</option>
        <option value="2" @selected($tcStages === 2)>{{ __('Zweistufig (Vier-Augen-Prinzip)') }}</option>
    </x-select-field>
    {{-- MVP-536: Vorbehalts-Eintragung beantragter Fehlzeiten (Q1 S. 43). --}}
    @php $provisional = (string) old('settings.vacation.provisional_booking', data_get($organization?->settings, 'vacation.provisional_booking', '0')); @endphp
    <label class="label cursor-pointer justify-start gap-3">
        <input type="hidden" name="settings[vacation][provisional_booking]" value="0">
        <input type="checkbox" name="settings[vacation][provisional_booking]" value="1" class="checkbox checkbox-sm"
               @checked($provisional === '1' || $provisional === 1)>
        <span class="label-text">{{ __('Beantragte Fehlzeiten sofort unter Vorbehalt eintragen (Ablehnung nimmt sie zurück)') }}</span>
    </label>
    @php $boardEnabled = (string) old('settings.presence.board_enabled', data_get($organization?->settings, 'presence.board_enabled', '0')); @endphp
    <label class="label cursor-pointer justify-start gap-3">
        <input type="hidden" name="settings[presence][board_enabled]" value="0">
        <input type="checkbox" name="settings[presence][board_enabled]" value="1" class="checkbox checkbox-sm"
               @checked($boardEnabled === '1' || $boardEnabled === 1)>
        <span class="label-text">{{ __('Anwesenheits-Board (Aktuelle Belegung) aktivieren') }}</span>
    </label>
    @php $oofEnabled = (string) old('settings.msgraph.oof_enabled', data_get($organization?->settings, 'msgraph.oof_enabled', '0')); @endphp
    <label class="label cursor-pointer justify-start gap-3">
        <input type="hidden" name="settings[msgraph][oof_enabled]" value="0">
        <input type="checkbox" name="settings[msgraph][oof_enabled]" value="1" class="checkbox checkbox-sm"
               @checked($oofEnabled === '1' || $oofEnabled === 1)>
        <span class="label-text">{{ __('Outlook-Abwesenheitsnotiz bei genehmigtem Urlaub setzen (M365)') }}</span>
    </label>
</x-form-group>

<x-form-group :legend="__('Lenk- und Ruhezeiten')" icon="local_shipping" tone="warning" cols="1"
              :description="__('Prüft Fahrten mit entsprechend markierten Fahrzeugen gegen die Grenzwerte der VO (EG) 561/2006 bzw. FPersV (Tages-/Wochenlenkzeit, Fahrtunterbrechung, tägliche und wöchentliche Ruhezeit). Keine Rechtsberatung — welche Vorschriften im Einzelfall gelten, klärt der Betrieb.')">
    {{-- Feature 144: Opt-in je Org; zweiter Schalter ist das Fahrzeug-Flag „Lenk- und Ruhezeitregeln anwenden". --}}
    @php $drivingRules = (string) old('compliance.driving_time_rules', $current['driving_time_rules'] ? '1' : '0'); @endphp
    <label class="label cursor-pointer justify-start gap-3">
        <input type="hidden" name="compliance[driving_time_rules]" value="0">
        <input type="checkbox" name="compliance[driving_time_rules]" value="1" class="checkbox checkbox-sm"
               @checked($drivingRules === '1')>
        <span class="label-text">{{ __('Lenkzeitregeln anwenden (Fahrzeuge mit Flag „Lenk- und Ruhezeitregeln anwenden")') }}</span>
    </label>
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
