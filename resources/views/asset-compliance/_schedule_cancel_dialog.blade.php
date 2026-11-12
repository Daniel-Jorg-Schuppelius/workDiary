{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _schedule_cancel_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@php
    /** @var \App\Models\AssetCompliance\AssetInspectionSchedule $schedule */
@endphp

<x-modal
    :title="__('asset_compliance.schedule.cancel_title')"
    :eyebrow="($schedule->asset->name ?? '—') . ' — ' . $schedule->due_on->fdate()"
    icon="event_busy"
    tone="error"
    size="sm"
    :action="route('asset-compliance.schedules.cancel', $schedule)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('asset_compliance.schedule.cancel_action')">

    <p class="mb-2 text-sm text-base-content/70">{{ __('asset_compliance.schedule.cancel_hint') }}</p>

    <x-textarea-field name="cancel_reason" :label="__('Begründung')" required rows="3" maxlength="500" :value="old('cancel_reason')" />
</x-modal>
