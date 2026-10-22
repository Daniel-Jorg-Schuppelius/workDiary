{{-- SLA-Vertrag bearbeiten (Vollscan 2026-10-21). --}}
<x-modal
    :title="__('SLA-Vertrag bearbeiten')"
    :eyebrow="$contract->code"
    icon="gavel"
    tone="primary"
    :action="route('sla-contracts.update', $contract)"
    method="PATCH"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    <div class="grid gap-2 md:grid-cols-2">
        @include('sla-contracts._fields', ['contract' => $contract])
    </div>
</x-modal>
