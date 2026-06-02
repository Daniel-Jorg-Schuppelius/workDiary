@php
    /** @var \App\Models\Asset $asset */
    $isEdit = $asset->exists;
    $action = $isEdit ? route('assets.update', $asset) : route('assets.store');
    $method = $isEdit ? 'PUT' : 'POST';
    $title = $isEdit ? __('Asset bearbeiten') : __('Asset anlegen');
@endphp

<x-modal
    :title="$title"
    :eyebrow="__('Objekte & Assets')"
    icon="precision_manufacturing"
    tone="primary"
    size="lg"
    :action="$action"
    :method="$method"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    @include('assets._form_body', [
        'asset' => $asset,
        'classOptions' => $classOptions,
        'statusOptions' => $statusOptions,
        'customers' => $customers,
        'foreignCustomers' => $foreignCustomers ?? collect(),
        'categoryOptions' => $categoryOptions,
        'prefill' => $prefill,
        'sites' => $sites ?? collect(),
        'buildings' => $buildings ?? collect(),
        'floors' => $floors ?? collect(),
        'rooms' => $rooms ?? collect(),
    ])
</x-modal>

