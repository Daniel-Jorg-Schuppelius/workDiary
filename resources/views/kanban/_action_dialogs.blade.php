{{--
    Geteilte Auftrags-Dialoge fürs Kanban-Drag-and-Drop. Ein Dialog je Aktion
    mit Pflichtangaben (complete/cancel/markInvoiced); kanban.js setzt vor dem
    Öffnen die form.action auf die diary.lifecycle-URL der gezogenen Karte.
    Felder/Labels spiegeln diary/_lifecycle_dialogs.blade.php.
--}}
<x-modal id="kanban-complete-dialog" :embedded="false" tone="success" icon="task_alt"
    :eyebrow="__('Auftrag')" :title="__('Auftrag abschließen')"
    action="#" form-id="kanban-complete-form"
    :submit-label="__('Abschließen')" submit-class="btn-success">
    <x-form-group :legend="__('Abschluss')" icon="description" tone="success">
        <div class="fieldset">
            <label class="fieldset-label" for="completion-summary-kanban">{{ __('Abschlusszusammenfassung') }} *</label>
            <textarea id="completion-summary-kanban" name="summary" required rows="4" maxlength="5000"
                class="textarea textarea-sm textarea-bordered w-full"></textarea>
        </div>
    </x-form-group>
</x-modal>

<x-modal id="kanban-invoice-dialog" :embedded="false" tone="success" icon="receipt_long"
    :eyebrow="__('Auftrag')" :title="__('Auftrag als berechnet markieren')"
    action="#" form-id="kanban-invoice-form"
    :submit-label="__('Als berechnet markieren')" submit-class="btn-success">
    <x-form-group :legend="__('Abrechnung')" icon="payments" tone="success">
        <div class="fieldset">
            <label class="fieldset-label" for="invoice-reference-kanban">{{ __('Rechnungsreferenz') }} *</label>
            <input id="invoice-reference-kanban" name="reference" required maxlength="120"
                class="input input-sm input-bordered w-full">
        </div>
    </x-form-group>
</x-modal>

<x-modal id="kanban-cancel-dialog" :embedded="false" tone="error" icon="cancel"
    :eyebrow="__('Auftrag')" :title="__('Auftrag stornieren')"
    action="#" form-id="kanban-cancel-form"
    :submit-label="__('Stornieren')" submit-class="btn-error">
    <x-form-group :legend="__('Stornierung')" icon="warning" tone="error">
        <div class="fieldset">
            <label class="fieldset-label" for="cancellation-reason-kanban">{{ __('Begründung') }} *</label>
            <textarea id="cancellation-reason-kanban" name="reason" required rows="4" maxlength="2000"
                class="textarea textarea-sm textarea-bordered w-full"></textarea>
        </div>
    </x-form-group>
</x-modal>
