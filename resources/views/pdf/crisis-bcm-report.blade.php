{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : crisis-bcm-report.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- BCM-Auswertung als PDF (MVP-944). Erwartet: $report, $organization --}}
<x-pdf-layout pdf-type="report" :pdf-title="__('crisis.bcm_report.title')">
    <h1>{{ __('crisis.bcm_report.title') }}</h1>
    <div class="meta">{{ $organization?->name }} · {{ now()->format('d.m.Y') }}</div>
    @include('crisis._bcm_report_rows')
    <p style="margin-top: 12pt; font-size: 8pt;">{{ __('crisis.bcm_report.disclaimer') }}</p>
</x-pdf-layout>
