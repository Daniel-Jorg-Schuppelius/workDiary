{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _settings_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Einstellung des Arbeitsvorrats (Feature 179, MVP-1670). Variablen: $dormantDays. --}}
<x-modal
    :title="__('work-queue.settings.title')"
    :eyebrow="__('work-queue.title')"
    icon="tune"
    tone="primary"
    :action="route('work-queue.settings.update')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('work-queue.settings.submit')">

    <input type="hidden" name="_dialog_url" value="{{ route('work-queue.settings.edit') . '?dialog=1' }}">

    <x-form-group :legend="__('work-queue.settings.dormant')" icon="hourglass_bottom" tone="primary">
        <x-input-field type="number" id="wq-dormant-days" name="dormant_days" :label="__('work-queue.settings.dormant_days')"
                       :value="old('dormant_days', $dormantDays)" min="1" max="{{ \App\Services\WorkQueue\WorkQueue::MAX_DORMANT_DAYS }}" required
                       :hint="__('work-queue.settings.dormant_hint', ['default' => \App\Services\WorkQueue\WorkQueue::DEFAULT_DORMANT_DAYS])" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
