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
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('Bezeichnung') }} *</label>
            <input type="text" name="name" maxlength="160" required
                   value="{{ old('name', $rule->name) }}"
                   placeholder="{{ __('z.B. DATEV-Update, Wartung Server, monatlicher Check') }}"
                   class="input input-bordered w-full">
            @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Titel-Template') }}</label>
            <input type="text" name="title_template" maxlength="200"
                   value="{{ old('title_template', $rule->title_template) }}"
                   placeholder="{{ __('Wartung KW {week}') }}"
                   class="input input-bordered w-full">
            <p class="text-xs text-base-content/50 mt-1">{{ __('Platzhalter: {date}, {iso_date}, {year}, {month}, {week}') }}</p>
            @error('title_template')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Eintragstyp') }}</label>
            <select name="entry_type_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($entryTypes as $t)
                    <option value="{{ $t->sqid }}" @selected((string) old('entry_type_id', \App\Support\Sqid::encode(\App\Models\EntryType::class, $rule->entry_type_id)) === $t->sqid)>{{ $t->label }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('Inhalts-Template') }} *</label>
            <textarea name="content_template" rows="3" required class="textarea textarea-bordered w-full">{{ old('content_template', $rule->content_template) }}</textarea>
            @error('content_template')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    <x-form-group :legend="__('Standard-Eigenschaften')" icon="settings" tone="info" cols="3">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Servicedauer (Min.)') }}</label>
            <input type="number" name="default_service_minutes" min="0" max="10080"
                   value="{{ old('default_service_minutes', $rule->default_service_minutes) }}"
                   class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Priorität') }}</label>
            <select name="default_priority" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach (\App\Enums\Diary\Priority::cases() as $p)
                    <option value="{{ $p->value }}" @selected(old('default_priority', $rule->default_priority?->value) === $p->value)>{{ $p->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Standort') }} *</label>
            <select name="default_location_mode" required class="select select-bordered w-full">
                @php($currentLocation = old('default_location_mode', $rule->default_location_mode?->value ?? 'onsite'))
                @foreach (\App\Enums\Diary\LocationMode::cases() as $lm)
                    <option value="{{ $lm->value }}" @selected($currentLocation === $lm->value)>{{ $lm->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Kunde (optional)') }}</label>
            <select name="customer_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->sqid }}" @selected((string) old('customer_id', \App\Support\Sqid::encode(\App\Models\Customer::class, $rule->customer_id)) === $c->sqid)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('Zuständig (optional)') }}</label>
            <select name="assigned_user_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($users as $u)
                    <option value="{{ $u->sqid }}" @selected((string) old('assigned_user_id', \App\Support\Sqid::encode(\App\Models\User::class, $rule->assigned_user_id)) === $u->sqid)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
    </x-form-group>

    <x-form-group :legend="__('Wiederholung')" icon="event_repeat" tone="accent" cols="3">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Häufigkeit') }} *</label>
            <select name="frequency" required class="select select-bordered w-full">
                @php($currentFrequency = old('frequency', $rule->frequency?->value ?? 'weekly'))
                @foreach (\App\Enums\Recurrence\RecurrenceFrequency::cases() as $freq)
                    <option value="{{ $freq->value }}" @selected($currentFrequency === $freq->value)>{{ $freq->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Intervall') }} *</label>
            <input type="number" name="interval" min="1" max="365" required
                   value="{{ old('interval', $rule->interval ?: 1) }}"
                   class="input input-bordered w-full">
            <p class="text-xs text-base-content/50 mt-1">{{ __('z.B. 2 = alle 2 Wochen/Monate') }}</p>
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Wochentage (nur wöchentlich)') }}</label>
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
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Tag im Monat (1–31)') }}</label>
            <input type="number" name="bymonthday" min="1" max="31"
                   value="{{ old('bymonthday', $rule->bymonthday) }}"
                   class="input input-bordered w-full">
            <p class="text-xs text-base-content/50 mt-1">{{ __('Nur bei monatlich/jährlich; klemmt am Monatsende.') }}</p>
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Monat (1–12, nur jährlich)') }}</label>
            <input type="number" name="bymonth" min="1" max="12"
                   value="{{ old('bymonth', $rule->bymonth) }}"
                   class="input input-bordered w-full">
        </div>
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
