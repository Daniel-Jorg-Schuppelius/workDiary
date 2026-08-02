{{-- Dialog: Entsorger-Übergabe erfassen (Feature 100, MVP-470).
     Erwartet: $job, $disposers (ExternalContact id+name). Lokaler ad-hoc-Dialog.
     Datei-Upload: enctype am x-modal (Modal rendert das <form> selbst). --}}
<x-modal
    id="disposal-handover-create"
    :embedded="false"
    :title="__('disposal.handover.title_create')"
    :eyebrow="__('disposal.eyebrow')"
    icon="handshake"
    tone="primary"
    :action="route('disposal.handovers.store', $job)"
    method="POST"
    enctype="multipart/form-data"
    :submit-label="__('Erfassen')"
>
    @if ($disposers->isEmpty())
        <div class="alert alert-warning text-sm">
            {{ __('disposal.handover.no_disposers') }}
            <a class="link" href="{{ route('external-contacts.index') }}">{{ __('disposal.handover.create_disposer') }}</a>
        </div>
    @endif

    <x-form-group :legend="__('disposal.handover.group_proof')" icon="handshake" tone="primary" cols="2">
        <x-select-field name="external_contact_id" :label="__('disposal.handover.disposer')" required>
            <option value="">{{ __('disposal.treatment.please_select') }}</option>
            @foreach ($disposers as $disposer)
                <option value="{{ $disposer->id }}" @selected((string) old('external_contact_id') === (string) $disposer->id)>{{ $disposer->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="proof_type" :label="__('disposal.handover.proof_type')" required>
            <option value="">{{ __('disposal.treatment.please_select') }}</option>
            @foreach (\App\Enums\Disposal\DisposalProofType::options() as $value => $label)
                <option value="{{ $value }}" @selected(old('proof_type') === $value)>{{ $label }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="document_number" :label="__('disposal.handover.document_number')" required maxlength="80"
                       :value="old('document_number')" />
        <x-input-field name="handed_over_on" type="date" :label="__('disposal.handover.handed_over_on')" required
                       :value="old('handed_over_on', now()->format('Y-m-d'))" />
        <x-input-field name="certificate_reference" :label="__('disposal.handover.certificate_reference')" maxlength="180" span="2"
                       :value="old('certificate_reference')" />
    </x-form-group>

    <x-form-group :legend="__('disposal.handover.group_attachment')" icon="attach_file" tone="ghost" cols="1">
        <x-input-field name="proof_file" type="file" :label="__('disposal.handover.proof_file')"
                       accept=".pdf,.jpg,.jpeg,.png"
                       :hint="__('disposal.handover.proof_file_hint')" />
        <x-textarea-field name="note" :label="__('disposal.handover.note')" rows="2">{{ old('note') }}</x-textarea-field>
    </x-form-group>
</x-modal>
