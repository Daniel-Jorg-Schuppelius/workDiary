{{-- Dialog wrapper for Invoice create --}}
<x-modal
    :title="__('Rechnung aus Zeiteinträgen erstellen')"
    :eyebrow="__('Neue Rechnung')"
    icon="receipt_long"
    tone="primary"
    :action="route('invoices.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Entwurf erstellen')"
>
    @include('invoices._form_body')
</x-modal>
