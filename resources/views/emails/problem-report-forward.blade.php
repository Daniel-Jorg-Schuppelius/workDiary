{{--
  Created on   : Thu Jul 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : problem-report-forward.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-mail::message>
# {{ __('problemreport.mail.heading', ['reference' => $report->reference_no]) }}

**{{ __('problemreport.field.summary') }}:** {{ $report->summary }}

**{{ __('problemreport.field.severity') }}:** {{ $report->severity->label() }}

{{ $report->description }}

@if ($report->contact_ok && $report->reporter)
{{ __('problemreport.mail.contact_ok', ['name' => $report->reporter->name]) }}
@endif

{{ __('problemreport.mail.attachment_hint') }}

{{ config('app.name') }}
</x-mail::message>
