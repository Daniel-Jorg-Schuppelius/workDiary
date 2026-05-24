@php
    $action = route('assets.store');
@endphp

<x-modal
    :title="__('Asset anlegen')"
    :eyebrow="__('Objekte & Assets')"
    icon="precision_manufacturing"
    tone="primary"
    :action="$action"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    @include('assets._form_body', [
        'asset' => $asset,
        'classOptions' => $classOptions,
        'statusOptions' => $statusOptions,
        'customers' => $customers,
    ])
</x-modal>
