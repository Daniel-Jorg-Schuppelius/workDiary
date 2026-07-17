{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anlage-Dialog Faktura-Übergabe (Feature 045, Teil B): Kunde, Kanal,
  Zeitraum (x-date-range). Ziel wird aus dem effektiven billing_mode
  vorbelegt (lexoffice ⇒ lexoffice, datev ⇒ Datei-Paket mit Hinweis
  „Desktop-API folgt", workdiary ⇒ nur Datei). Der Server prüft die
  Ziel-Zulässigkeit beim Speichern erneut.
--}}

<x-modal
    :title="__('finance.action.create_draft')"
    :eyebrow="__('finance.title.transfers')"
    icon="outbox"
    tone="primary"
    :action="route('finance.transfers.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('finance.action.create_draft')">

    <x-form-group :legend="__('finance.title.transfer')" icon="outbox" tone="primary" cols="2">
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('finance.field.customer') }} *</label>
            <select name="customer_id" required class="select select-bordered w-full">
                <option value="">{{ __('-- bitte wählen --') }}</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->sqid }}"
                            @selected(old('customer_id', $selectedCustomer?->sqid) === $c->sqid)>{{ $c->name }}</option>
                @endforeach
            </select>
            @if ($selectedMode !== null)
                <p class="mt-1 text-xs text-base-content/60">
                    {{ __('finance.field.billing_mode') }}: {{ $selectedMode->label() }}
                </p>
            @endif
        </div>

        <x-select-field name="channel" :label="__('finance.field.channel')" required :hint="__('finance.hint.channels_separate')">
            @foreach ($allowedChannels as $channel)
                <option value="{{ $channel->value }}" @selected(old('channel') === $channel->value)>{{ $channel->label() }}</option>
            @endforeach
        </x-select-field>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('finance.field.target') }} *</label>
            <select name="target" required class="select select-bordered w-full">
                @foreach ($allowedTargets as $target)
                    <option value="{{ $target->value }}" @selected(old('target', $defaultTarget->value) === $target->value)>{{ $target->label() }}</option>
                @endforeach
            </select>
            @if ($showDatevHint)
                <p class="mt-1 text-xs text-warning">{{ __('finance.hint.datev_desktop_api') }}</p>
            @else
                <p class="mt-1 text-xs text-base-content/60">{{ __('finance.hint.target_by_mode') }}</p>
            @endif
        </div>

        <div class="fieldset md:col-span-2">
            <x-date-range :label="__('finance.field.period')"
                          form-control
                          from-name="from" to-name="to"
                          :from="old('from')" :to="old('to')" />
            <p class="mt-1 text-xs text-base-content/60">{{ __('finance.hint.period_sources') }}</p>
        </div>
    </x-form-group>

    <x-validation-errors />
</x-modal>
