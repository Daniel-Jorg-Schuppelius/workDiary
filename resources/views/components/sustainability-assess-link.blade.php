{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : sustainability-assess-link.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Einstieg „Nachhaltigkeit bewerten“ an Asset, Lieferant und Investition (MVP-1607): öffnet den
     Bewertungsdialog mit dem Objekt als Bezug. Sichtbar nur mit aktivem Modul und Anlagerecht. --}}
@props(['subject'])
@if (app(\App\Services\Navigation\NavGate::class)->allows('sustainability.assessments.create'))
    @can('create', \App\Models\Sustainability\SustainabilityAssessment::class)
        @php($assessValue = app(\App\Services\Sustainability\SustainabilitySubjects::class)->value($subject))
        @if ($assessValue !== null)
            <x-icon-btn icon="eco" tone="outline" size="sm" data-entry-modal-trigger
                        :href="route('sustainability.assessments.create', ['subject' => $assessValue])" show-label {{ $attributes }}>{{ __('sustainability.subject.assess_action') }}</x-icon-btn>
        @endif
    @endcan
@endif
