{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : claim-open-link.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Einstieg „Reklamation eröffnen“ an Rechnung, Auftrag, Asset und Ticket (MVP-1577).
     :params nennt das Ausgangsobjekt als Sqid (invoice|order|asset|ticket); der Anlagedialog
     belegt Objekt, Kunde und Seriennummer vor. Sichtbar nur mit aktivem Modul und Anlagerecht. --}}
@props(['params' => []])
@if (app(\App\Services\Navigation\NavGate::class)->allows('claims.create'))
    @can('create', \App\Models\Claims\ClaimCase::class)
        <x-icon-btn icon="assignment_return" tone="outline" size="sm" data-entry-modal-trigger
                    :href="route('claims.create', $params)" show-label {{ $attributes }}>{{ __('claims.open.action') }}</x-icon-btn>
    @endcan
@endif
