{{-- Dialog wrapper for ExpenseCategory create/edit --}}
@php
    /** @var \App\Models\ExpenseCategory $category */
    $isEdit = $category?->exists ?? false;
@endphp
<x-modal
    :title="$isEdit ? __('Spesenkategorie bearbeiten') : __('Spesenkategorie anlegen')"
    :eyebrow="$isEdit ? $category->label : null"
    icon="receipt_long"
    tone="primary"
    :action="$isEdit ? route('admin.expense-categories.update', $category) : route('admin.expense-categories.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')"
>
    <x-slot:headerActions>
        <x-dialog-status-controls
            :active="$category?->is_active ?? true" />
    </x-slot:headerActions>

    @include('admin.expense-categories._form_body', ['skipStatusControls' => true])

    @if ($isEdit)
        <x-slot:footerExtra>
            <x-action-form :action="route('admin.expense-categories.destroy', $category)" method="DELETE"
                  :confirm="__('Kategorie wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
