{{--
  Created on   : Tue May 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _recurrence_rule_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $project, $rule, $entryTypes, $customers, $users --}}
@php
    /**
     * @var \App\Models\Project $project
     * @var \App\Models\RecurrenceRule $rule
     * @var \Illuminate\Support\Collection<int, \App\Models\EntryType> $entryTypes
     * @var \Illuminate\Support\Collection<int, \App\Models\Customer> $customers
     * @var \Illuminate\Support\Collection<int, \App\Models\User> $users
     */
    $isEdit = $rule->exists;
    $action = $isEdit
        ? route('projects.recurrence-rules.update', [$project, $rule])
        : route('projects.recurrence-rules.store', $project);
    $weekdays = $isEdit ? $rule->weekdays() : [];
@endphp
<x-modal
    :title="$isEdit ? __('Wiederkehr-Regel bearbeiten') : __('Neue Wiederkehr-Regel')"
    :eyebrow="$project->name"
    icon="autorenew"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Regel speichern')"
>
    <x-form-group :legend="__('Beschreibung')" icon="autorenew" tone="primary" cols="2">
        <x-input-field span="2" name="name"
                       :label="__('Bezeichnung')"
                       type="text"
                       value="{{ old('name', $rule->name) }}"
                       required
                       maxlength="160"
                       placeholder="{{ __('z.B. DATEV-Update, Wartung Server, monatlicher Check') }}" />
        <x-input-field name="title_template"
                       :label="__('Titel-Template')"
                       type="text"
                       value="{{ old('title_template', $rule->title_template) }}"
                       :hint="__('Platzhalter: {date}, {iso_date}, {year}, {month}, {week}')"
                       maxlength="200"
                       placeholder="{{ __('Wartung KW {week}') }}" />
        <x-select-field name="entry_type_id" :label="__('Eintragstyp')">
            <option value="">—</option>
            @foreach ($entryTypes as $t)
                <option value="{{ $t->sqid }}" @selected((string) old('entry_type_id', \App\Support\Sqid::encode(\App\Models\EntryType::class, $rule->entry_type_id)) === $t->sqid)>{{ $t->label }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field span="2" name="content_template" :label="__('Inhalts-Template')" rows="3" required>{{ old('content_template', $rule->content_template) }}</x-textarea-field>
    </x-form-group>

    <x-form-group :legend="__('Standard-Eigenschaften')" icon="settings" tone="info" cols="3">
        <x-input-field name="default_service_minutes"
                       :label="__('Servicedauer (Min.)')"
                       type="number"
                       value="{{ old('default_service_minutes', $rule->default_service_minutes) }}"
                       min="0"
                       max="10080" />
        <x-select-field name="default_priority" :label="__('Priorität')">
            <option value="">—</option>
            @foreach (\App\Enums\Diary\Priority::cases() as $p)
                <option value="{{ $p->value }}" @selected(old('default_priority', $rule->default_priority?->value) === $p->value)>{{ $p->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="default_location_mode" :label="__('Standort')" required>
            @php($currentLocation = old('default_location_mode', $rule->default_location_mode?->value ?? 'onsite'))
            @foreach (\App\Enums\Diary\LocationMode::cases() as $lm)
                <option value="{{ $lm->value }}" @selected($currentLocation === $lm->value)>{{ $lm->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="customer_id" :label="__('Kunde (optional)')">
            <option value="">—</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected((string) old('customer_id', \App\Support\Sqid::encode(\App\Models\Customer::class, $rule->customer_id)) === $c->sqid)>{{ $c->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field span="2" name="assigned_user_id" :label="__('Zuständig (optional)')">
            <option value="">—</option>
            @foreach ($users as $u)
                <option value="{{ $u->sqid }}" @selected((string) old('assigned_user_id', \App\Support\Sqid::encode(\App\Models\User::class, $rule->assigned_user_id)) === $u->sqid)>{{ $u->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('Wiederholung')" icon="event_repeat" tone="accent" cols="3">
        <x-select-field name="frequency" :label="__('Häufigkeit')" required>
            @php($currentFrequency = old('frequency', $rule->frequency?->value ?? 'weekly'))
            @foreach (\App\Enums\Recurrence\RecurrenceFrequency::cases() as $freq)
                <option value="{{ $freq->value }}" @selected($currentFrequency === $freq->value)>{{ $freq->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="interval"
                       :label="__('Intervall')"
                       type="number"
                       value="{{ old('interval', $rule->interval ?: 1) }}"
                       required
                       :hint="__('z.B. 2 = alle 2 Wochen/Monate')"
                       min="1"
                       max="365" />
        <div class="fieldset">
            <span class="fieldset-label">{{ __('Wochentage (nur wöchentlich)') }}</span>
            <div class="flex flex-wrap gap-2">
                @foreach (\App\Models\RecurrenceRule::WEEKDAY_CODES as $code)
                    <label class="cursor-pointer">
                        <input type="checkbox" name="byweekday[]" value="{{ $code }}"
                               @checked(in_array($code, (array) old('byweekday', $weekdays), true))
                               class="peer sr-only">
                        <span class="badge badge-outline transition-colors peer-checked:bg-primary peer-checked:border-primary peer-checked:text-primary-content">
                            {{ __($code) }}
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
        <x-input-field name="bymonthday"
                       :label="__('Tag im Monat (1–31)')"
                       type="number"
                       value="{{ old('bymonthday', $rule->bymonthday) }}"
                       :hint="__('Nur bei monatlich/jährlich; klemmt am Monatsende.')"
                       min="1"
                       max="31" />
        <x-input-field name="bymonth"
                       :label="__('Monat (1–12, nur jährlich)')"
                       type="number"
                       value="{{ old('bymonth', $rule->bymonth) }}"
                       min="1"
                       max="12" />
        <div class="fieldset flex items-center gap-2 pt-7">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" id="rule_is_active" value="1"
                   @checked(old('is_active', $rule->exists ? $rule->is_active : true))
                   class="checkbox checkbox-primary">
            <label for="rule_is_active" class="text-sm">{{ __('Aktiv') }}</label>
        </div>
        <x-date-range class="md:col-span-2" layout="split" form-control size="md"
                      from-name="starts_on" to-name="ends_on" from-required
                      :from="old('starts_on', $rule->starts_on?->format('Y-m-d') ?? now()->format('Y-m-d'))"
                      :to="old('ends_on', $rule->ends_on?->format('Y-m-d'))"
                      :from-label="__('Beginnt am') . ' *'"
                      :to-label="__('Endet am (optional, für befristete Generierung)')"
                      :from-error="$errors->first('starts_on')"
                      :to-error="$errors->first('ends_on')" />
    </x-form-group>
</x-modal>
