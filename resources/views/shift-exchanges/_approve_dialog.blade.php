{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _approve_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Freigabe eines Schichttauschs mit Regelverstoß (MVP-1553): Verstöße
  zeigen, Übersteuerung mit Begründung. Variablen: $exchange, $violations.
--}}
<x-modal
    :title="__('schedule.exchange.approve_title')"
    :eyebrow="__('schedule.exchange.title')"
    icon="approval"
    tone="warning"
    size="md"
    :action="route('schedule.exchanges.approve', $exchange)"
    method="PATCH"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Freigeben')">
    <input type="hidden" name="_dialog_url" value="{{ route('schedule.exchanges.approve-form', $exchange) }}?dialog=1">

    <div class="mb-4 rounded-box border border-base-300 bg-base-200/60 p-3 text-sm">
        <p class="font-semibold">{{ $exchange->requester?->name }} → {{ $exchange->targetUser?->name ?? __('schedule.exchange.open_target') }}</p>
        <p class="text-base-content/70">{{ $exchange->scheduledShift?->date?->fdate() }} · {{ $exchange->scheduledShift?->shiftType?->name ?? '—' }}</p>
        @if ($exchange->isSwap())
            <p class="text-base-content/70">{{ __('schedule.exchange.offered_for') }} {{ $exchange->offeredShift?->date?->fdate() }} · {{ $exchange->offeredShift?->shiftType?->name ?? '—' }}</p>
        @endif
    </div>

    @if ($violations === [])
        <p class="text-sm text-muted">{{ __('schedule.exchange.approve_clean') }}</p>
    @else
        <div role="alert" class="alert alert-warning mb-3 items-start">
            <x-icon name="warning" />
            <div>
                <p class="font-semibold">{{ __('schedule.exchange.error_compliance') }}</p>
                <ul class="mt-1 list-disc pl-5 text-sm">
                    @foreach ($violations as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        <x-form-group :legend="__('schedule.exchange.override_legend')" icon="gavel" tone="warning">
            <x-checkbox-field name="override_compliance" tone="warning" required
                              :label="__('schedule.exchange.override_label')"
                              :checked="(bool) old('override_compliance')" />
            <x-textarea-field name="override_reason" id="exchange-override-reason" rows="3" required
                              minlength="{{ \App\Services\Schedule\ShiftExchangeService::OVERRIDE_REASON_MIN_LENGTH }}" maxlength="500"
                              :label="__('schedule.exchange.override_reason')"
                              :hint="__('schedule.exchange.override_hint')"
                              :value="old('override_reason')" />
        </x-form-group>
    @endif
</x-modal>
