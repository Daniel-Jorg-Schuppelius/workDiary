{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _assign_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Terminanfrage einem Kunden oder Lead zuordnen (MVP-1610), in #entry-modal geladen. Variablen: $request, $customers, $leads (null ohne Vertrieb) --}}
<x-modal
    :title="__('appointment.assign.title')"
    :eyebrow="($request->start_at?->orgTz()->fdatetime() ?? '—') . ' · ' . ($request->invitee_name ?? $request->invitee_email ?? '—')"
    icon="person_search"
    tone="primary"
    :action="route('appointments.assign', $request)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('appointment.assign.submit')">

    @if (filled($request->invitee_email))
        <p class="text-sm text-muted">{{ __('appointment.assign.invitee', ['name' => $request->invitee_name ?? '—', 'email' => $request->invitee_email]) }}</p>
    @endif

    <x-select-field name="customer" :label="__('Kunde')" :hint="__('appointment.assign.customer_hint')">
        <option value="">{{ __('— keiner —') }}</option>
        @foreach ($customers as $customer)
            <option value="{{ $customer->sqid }}" @selected(old('customer', $request->customer?->sqid) === $customer->sqid)>{{ $customer->name }}</option>
        @endforeach
    </x-select-field>

    @if ($leads !== null)
        <x-select-field name="lead" :label="__('appointment.assign.lead')" :hint="__('appointment.assign.lead_hint')">
            <option value="">{{ __('— keiner —') }}</option>
            @foreach ($leads as $lead)
                <option value="{{ $lead->sqid }}" @selected(old('lead', $request->lead?->sqid) === $lead->sqid)>{{ $lead->displayName() }}</option>
            @endforeach
        </x-select-field>
    @endif
</x-modal>
