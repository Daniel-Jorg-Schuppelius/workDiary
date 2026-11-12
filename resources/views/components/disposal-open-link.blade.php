{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : disposal-open-link.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Einstieg „Entsorgen“ an der Asset-Akte (MVP-1578): öffnet die Entsorgungsakte mit dem Asset
     als vorbereiteter Geräteposition. Sichtbar nur für Assets in Betrieb, mit aktivem Modul und Anlagerecht. --}}
@props(['asset'])
@if ($asset->isInOperation() && app(\App\Services\Navigation\NavGate::class)->allows('disposal.create'))
    @can('create', \App\Models\Disposal\DisposalJob::class)
        <x-icon-btn icon="recycling" tone="outline" size="sm" data-entry-modal-trigger
                    :href="route('disposal.create', ['asset' => $asset->sqid])" show-label {{ $attributes }}>{{ __('disposal.asset.action') }}</x-icon-btn>
    @endcan
@endif
