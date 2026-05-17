{{-- Variablen: $date, $users, $vehicles, $statuses --}}
@php
    $action = route('tours.store');
@endphp

<x-modal
    :title="__('Neue Tour')"
    :eyebrow="__('Touren')"
    icon="map"
    tone="primary"
    :action="$action"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    @include('tours._form_body')
</x-modal>
