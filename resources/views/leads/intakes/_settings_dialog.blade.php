{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _settings_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Einstellungen der Aufnahmen (Feature 200, MVP-1641/1643). Variablen: $available (Schlüssel → Bezeichnung), $enabled, $refreshMonths (Risikostufe → Monate), $secondReview. --}}
<x-modal
    :title="__('lead_intake.settings.title')"
    :eyebrow="__('lead_intake.index_title')"
    icon="tune"
    tone="primary"
    :action="route('leads.intakes.settings.update')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('lead_intake.settings.submit')">

    <input type="hidden" name="_dialog_url" value="{{ route('leads.intakes.settings.edit') . '?dialog=1' }}">

    <x-form-group :legend="__('lead_intake.settings.packages')" icon="inventory_2" tone="primary" cols="2">
        @forelse ($available as $key => $label)
            <x-checkbox-field name="packages[]" id="lis-package-{{ $key }}" :value="$key" :label="$label" :checked="in_array($key, old('packages', $enabled), true)" :with-hidden="false" />
        @empty
            <x-empty-state compact icon="inventory_2" :title="__('lead_intake.settings.none_available')" class="sm:col-span-2" />
        @endforelse
        <p class="text-xs text-muted sm:col-span-2">{{ __('lead_intake.settings.packages_hint') }}</p>
    </x-form-group>

    <x-form-group :legend="__('lead_intake.settings.refresh')" icon="history" tone="primary" cols="3">
        @foreach (\App\Enums\Sales\IntakeRiskLevel::cases() as $level)
            <x-input-field type="number" id="lis-refresh-{{ $level->value }}" name="refresh_months[{{ $level->value }}]"
                           :label="__('lead_intake.settings.refresh_for', ['level' => $level->label()])"
                           :value="old('refresh_months.' . $level->value, $refreshMonths[$level->value] ?? 12)" min="1" max="120" required />
        @endforeach
        <p class="text-xs text-muted sm:col-span-3">{{ __('lead_intake.settings.refresh_hint') }}</p>
    </x-form-group>

    <x-form-group :legend="__('lead_intake.settings.second_review')" icon="verified" tone="primary">
        <x-checkbox-field name="second_review" id="lis-second-review" value="1" :label="__('lead_intake.settings.second_review_label')" :checked="(bool) old('second_review', $secondReview)" />
        <p class="text-xs text-muted">{{ __('lead_intake.settings.second_review_hint') }}</p>
    </x-form-group>

    <x-validation-errors />
</x-modal>
