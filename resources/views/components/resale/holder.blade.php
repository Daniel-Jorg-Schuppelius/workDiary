{{--
  Created on   : Sun Nov 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : holder.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Halter eines Abos. Ohne Halter der Hinweis und — mit Firmenname laut
  Anbieter — „Halter zuordnen“ (Pilot 2026-11-01: der Weg war nur über den
  Posteingang zu finden). Der Dialog gilt für alle Abos dieser Firma ohne Halter.
--}}
@props(['subscription'])
@if ($subscription->hasHolder())
    {{ $subscription->holderLabel() }}
@else
    <span class="inline-flex flex-wrap items-center gap-1 align-middle">
        <x-status-badge size="xs" tone="warning" :label="__('resale.holder.unassigned')" />
        @if (trim((string) $subscription->company_name) !== '')
            @can(\App\Enums\User\Permission::ResellingManage->value)
                <a href="{{ route('finance.resale.inbox.assign', ['company' => $subscription->company_name]) }}" data-entry-modal-trigger class="link link-primary text-xs whitespace-nowrap">{{ __('resale.holder.assign') }}</a>
            @endcan
        @endif
    </span>
@endif
