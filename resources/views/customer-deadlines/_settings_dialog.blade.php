{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _settings_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Einstellungen der Kundenfristen (Feature 197). Variablen: $available (Schlüssel → Bezeichnung), $enabled, $warnDays, $byRuleSet. --}}
<x-modal
    :title="__('customer_deadline.settings.title')"
    :eyebrow="__('customer_deadline.title')"
    icon="tune"
    tone="primary"
    :action="route('customer-deadlines.settings.update')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('customer_deadline.settings.submit')">

    <input type="hidden" name="_dialog_url" value="{{ route('customer-deadlines.settings.edit') . '?dialog=1' }}">

    <x-form-group :legend="__('customer_deadline.settings.rule_sets')" icon="rule" tone="primary" cols="2">
        @forelse ($available as $key => $label)
            <x-checkbox-field name="rule_sets[]" id="cds-rule-set-{{ $key }}" :value="$key" :label="$label" :checked="in_array($key, old('rule_sets', $enabled), true)" :with-hidden="false" />
        @empty
            <x-empty-state compact icon="rule" :title="__('customer_deadline.settings.none_available')" class="sm:col-span-2" />
        @endforelse
        <p class="text-xs text-muted sm:col-span-2">{{ __('customer_deadline.settings.rule_sets_hint') }}</p>
    </x-form-group>

    <x-form-group :legend="__('customer_deadline.settings.warning')" icon="notifications_active" tone="primary" cols="2">
        <x-input-field type="number" id="cds-warn-days" name="warn_days" :label="__('customer_deadline.settings.warn_days')" :value="old('warn_days', $warnDays)" min="0" max="90" required />
        @foreach ($available as $key => $label)
            <x-input-field type="number" id="cds-warn-{{ $key }}" name="warn_days_by_rule_set[{{ $key }}]" :label="__('customer_deadline.settings.warn_days_for', ['rule_set' => $label])"
                           :value="old('warn_days_by_rule_set.' . $key, $byRuleSet[$key] ?? null)" min="0" max="90" :hint="__('customer_deadline.settings.warn_days_for_hint')" />
        @endforeach
    </x-form-group>

    <x-validation-errors />
</x-modal>
