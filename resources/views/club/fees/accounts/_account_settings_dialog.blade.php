{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _account_settings_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Mandat und Portalzugang (in #entry-modal geladen). Variablen: $account, $mandates, $users --}}
<x-modal
    :title="__('club.fees.action.account_settings')"
    :eyebrow="$account->name"
    icon="settings"
    tone="primary"
    :action="route('club.fees.accounts.settings', $account)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.fees.card.collection')" icon="account_balance" tone="primary" cols="1" :description="__('club.fees.hint.mandate')">
        <x-select-field name="sepa_mandate_id" :label="__('club.fees.field.mandate')">
            <option value="">{{ __('club.fees.label.mandate_auto') }}</option>
            @foreach ($mandates as $mandate)
                <option value="{{ $mandate->sqid }}" @selected(old('sepa_mandate_id', $account->sepa_mandate_id ? \App\Support\Sqid::encode(\App\Models\Finance\SepaMandate::class, $account->sepa_mandate_id) : '') === $mandate->sqid)>{{ $mandate->reference }} · {{ $mandate->status->label() }}</option>
            @endforeach
        </x-select-field>
        <p class="text-xs text-muted"><a href="{{ route('finance.mandates.index') }}" class="link">{{ __('club.fees.action.manage_mandates') }}</a></p>
    </x-form-group>

    <x-form-group :legend="__('club.fees.card.portal')" icon="person" tone="primary" cols="1" :description="__('club.fees.hint.portal_user')">
        <x-select-field name="user_id" :label="__('club.fees.field.portal_user')">
            <option value="">–</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected(old('user_id', $account->user_id ? \App\Support\Sqid::encode(\App\Models\User::class, $account->user_id) : '') === $user->sqid)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>
</x-modal>
