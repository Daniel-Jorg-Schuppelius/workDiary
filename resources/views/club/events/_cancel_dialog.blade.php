{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _cancel_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Termin absagen (in #entry-modal geladen). Variablen: $event, $inSeries
--}}
<x-modal
    :title="__('club.events.action.cancel_event')"
    :eyebrow="$event->title"
    icon="event_busy"
    tone="warning"
    :action="route('club.events.cancel', $event)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    submit-class="btn-warning"
    :submit-label="__('club.events.action.cancel_event')">

    <x-form-group :legend="__('club.events.field.cancel_reason')" icon="event_busy" tone="warning" cols="1" :description="__('club.events.hint.cancel')">
        <x-textarea-field name="cancel_reason" :label="__('club.events.field.cancel_reason')" rows="2" maxlength="500" :value="old('cancel_reason')" />
        @if ($inSeries)
            <x-select-field name="scope" :label="__('club.events.field.scope')">
                <option value="this" @selected(old('scope', 'this') === 'this')>{{ __('club.events.scope.this') }}</option>
                <option value="future" @selected(old('scope') === 'future')>{{ __('club.events.scope.future') }}</option>
            </x-select-field>
        @endif
    </x-form-group>
</x-modal>
