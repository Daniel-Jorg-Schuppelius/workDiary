{{--
  Created on   : Tue Oct 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _bulk_assign_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Sammelzuordnung des Halters aus der Abo-Liste (Feature 152, Nachbesserung
  2026-10-27): ein Halter für alle gewählten Abos — auch für eines (Zeilen-
  aktion). Domains bekommen ihn auch in der Domainverwaltung; Firmen laut
  Anbieter werden auf Wunsch für den nächsten Import gemerkt.
--}}
@php
    $mode = (string) old('mode', 'customer');
@endphp
<x-modal
    :title="trans_choice('resale.bulk.assign_title', $subscriptions->count(), ['count' => $subscriptions->count()])"
    icon="person_add"
    tone="primary"
    size="md"
    :action="route('finance.resale.subscriptions.bulk.assign')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.bulk.assign_submit')"
>
    @foreach ($subscriptions as $subscription)
        <input type="hidden" name="ids[]" value="{{ $subscription->sqid }}">
    @endforeach
    <div class="text-sm text-base-content/70">{{ __('resale.bulk.assign_hint') }}</div>
    <ul class="text-sm max-h-48 overflow-y-auto space-y-0.5 border border-base-300 rounded-box px-3 py-2">
        @foreach ($subscriptions as $subscription)
            <li class="flex flex-wrap justify-between gap-2">
                <span>{{ $subscription->label }}<span class="text-xs text-muted"> · {{ $subscription->provider->label() }}@if (trim((string) $subscription->company_name) !== '') · {{ $subscription->company_name }}@endif</span></span>
                <span class="text-xs text-muted">{{ $subscription->hasHolder() ? $subscription->holderLabel() : __('resale.holder.unassigned') }}</span>
            </li>
        @endforeach
    </ul>
    @if ($suggestions !== [])
        <div class="text-xs">
            <span class="text-muted">{{ __('resale.bulk.suggestion') }}:</span>
            @foreach ($suggestions as $suggestion)
                <x-status-badge tone="plain" outline class="mr-1">{{ $suggestion['customer']->name }} · {{ __('domain.mapping.reason_' . $suggestion['reason']) }}</x-status-badge>
            @endforeach
        </div>
    @endif

    {{-- Partner-Modus nur mit Firma laut Anbieter (wie der Inbox-Dialog). --}}
    @include('finance.resale._holder_picker', [
        'mode' => $mode,
        'modes' => $withCompany > 0 ? ['customer', 'partner', 'foreign', 'own'] : ['customer', 'foreign', 'own'],
        'modeLabel' => __('resale.inbox.mode'),
        'modeHints' => $withCompany > 0 ? ['partner' => __('resale.bulk.partner_hint')] : [],
        'customerDefault' => $suggestions[0]['customer']->sqid ?? '',
    ])

    @if ($withCompany > 0)
        <x-checkbox-field name="remember" :label="__('resale.bulk.remember')" :checked="(bool) old('remember', true)" :hint="__('resale.bulk.remember_hint')" />
    @endif
</x-modal>
