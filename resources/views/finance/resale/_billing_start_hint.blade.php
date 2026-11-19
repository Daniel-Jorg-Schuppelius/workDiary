{{--
  Created on   : Thu Nov 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _billing_start_hint.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Portalkonto jünger als die Domains (MVP-1746): die Lizenzjahre davor hat der
  Reseller nie berechnet — Hinweis je Konto mit Weg zur Sammelaktion des Kunden.
  Erwartet: $billingStart (ProviderBillingStart::candidates), $customer, $canManage.
  Arbeitsmappe und Lizenzkonto.
--}}
@foreach ($billingStart as $start)
    <div role="status" class="alert alert-info mb-4 text-sm">
        <div>
            <span class="font-medium">{{ __('resale.billing_start.title', ['account' => $start['account'], 'date' => $start['since']->fdate()]) }}</span>
            <span class="block text-xs">{{ trans_choice('resale.billing_start.hint', count($start['periods']), ['count' => count($start['periods'])]) }}</span>
        </div>
        @if ($canManage && $loop->first)
            <x-icon-btn icon="event_available" tone="primary" size="sm" data-entry-modal-trigger :href="route('finance.resale.reconcile.billing-start.create', $customer)" show-label>{{ __('resale.billing_start.action') }}</x-icon-btn>
        @endif
    </div>
@endforeach
