{{-- Dialog: neuer Druckauftrag (MVP-459) — legt Fertigungsauftrag + Fachakte an. --}}
<x-modal
    :title="__('print.orders.action.create')"
    :eyebrow="__('print.orders.title')"
    icon="print"
    tone="primary"
    :action="route('print-orders.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('print.orders.action.create_submit')"
>
    <x-form-group :legend="__('print.section.order')" icon="print" tone="primary" cols="2">
        <x-select-field name="article_id" :label="__('print.field.article')" required span="2">
            <option value="">…</option>
            @foreach ($articles as $article)
                <option value="{{ $article->sqid }}" @selected((string) old('article_id') === $article->sqid)>{{ $article->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="target_qty" type="number" step="1" min="1" :label="__('print.field.quantity')" :value="old('target_qty', 100)" required />
        <x-input-field name="unit" :label="__('print.field.unit')" :value="old('unit', 'Stk')" required />
        <x-select-field name="customer_id" :label="__('print.field.customer_optional')" span="2">
            <option value="">{{ __('print.field.walk_in') }}</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->sqid }}" @selected((string) old('customer_id') === $customer->sqid)>{{ $customer->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="due_at" type="date" :label="__('print.field.due_at')" :value="old('due_at')" />
        <x-select-field name="output_kind" :label="__('print.field.output_kind')" required>
            @foreach (\App\Enums\Print\PrintOutputKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected(old('output_kind', \App\Enums\Print\PrintOutputKind::Pickup->value) === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="files_retain_until" type="date" :label="__('print.field.files_retain_until')" :value="old('files_retain_until')" span="2" :hint="__('print.hint.retention')" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
