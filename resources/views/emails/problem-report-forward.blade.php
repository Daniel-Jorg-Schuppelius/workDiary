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
