{{--
  Created on   : Sun Aug 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : sovereignty-note.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Hoheits-Hinweis des Hauptbuch-Arbeitsplatzes (Feature 125): erklärt auf den
  Ledger-Seiten, WARUM Inbox/Journal/OPOS leer bleiben, solange die Buchungs-
  hoheit heute nicht lokal liegt (Vorstufe oder Fachanwendung). Rendert bei
  lokaler Hoheit nichts.
--}}

@php
    $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
    $period = $organization instanceof \App\Models\Organization
        ? app(\App\Services\Accounting\AccountingSovereigntyResolver::class)->periodAt($organization)
        : null;
    $sovereignty = $organization instanceof \App\Models\Organization
        ? ($period?->sovereignty ?? \App\Enums\Finance\AccountingSovereignty::Preaccounting)
        : null;
@endphp

@if ($sovereignty !== null && ! $sovereignty->allowsLocalPosting())
    <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
        <x-icon name="sync_alt" />
        <span>
            {{ $sovereignty->isExternal() && filled($period?->external_provider)
                ? __('accounting.ledger.sovereignty_note.external_named', ['provider' => $period->external_provider])
                : __('accounting.ledger.sovereignty_note.' . $sovereignty->value) }}
            <a href="{{ route('finance.accounting.setup') }}" class="link">{{ __('accounting.ledger.sovereignty_note.setup_link') }}</a>
        </span>
    </div>
@endif
