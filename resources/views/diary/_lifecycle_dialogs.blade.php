@can('pause', $diary)
    <x-modal id="order-pause-{{ $dialogSuffix }}" :embedded="false" tone="warning" icon="pause"
        :eyebrow="__('Auftrag')" :title="__('Auftrag pausieren')"
        :action="route('diary.lifecycle', [$diary, 'action' => 'pause'])"
        :submit-label="__('Pausieren')" submit-class="btn-warning">
        <p class="mb-3 text-sm text-base-content/70">
            {{ __('Ein pausierter Auftrag gilt als wartend und erscheint in der Auftragsübersicht unter „Probleme", bis er fortgesetzt wird.') }}
        </p>
        <x-form-group :legend="__('Pausengrund')" icon="schedule" tone="warning">
            <div class="fieldset">
                <label class="fieldset-label" for="pause-reason-{{ $dialogSuffix }}">{{ __('Grund') }} *</label>
                <select id="pause-reason-{{ $dialogSuffix }}" name="reason" required class="select select-sm select-bordered w-full">
                    <option value="customer">{{ __('Problem / Wartet auf Kundenrückmeldung') }}</option>
                    <option value="material">{{ __('Wartet auf Material') }}</option>
                </select>
            </div>
            <div class="fieldset">
                <label class="fieldset-label" for="pause-note-{{ $dialogSuffix }}">{{ __('Notiz') }}</label>
                <textarea id="pause-note-{{ $dialogSuffix }}" name="note" rows="3" maxlength="2000"
                    class="textarea textarea-sm textarea-bordered w-full"></textarea>
            </div>
        </x-form-group>
    </x-modal>
@endcan

@can('complete', $diary)
    <x-modal id="order-complete-{{ $dialogSuffix }}" :embedded="false" tone="success" icon="task_alt"
        :eyebrow="__('Auftrag')" :title="__('Auftrag abschließen')"
        :action="route('diary.lifecycle', [$diary, 'action' => 'complete'])"
        :submit-label="__('Abschließen')" submit-class="btn-success">
        <x-form-group :legend="__('Abschluss')" icon="description" tone="success">
            <div class="fieldset">
                <label class="fieldset-label" for="completion-summary-{{ $dialogSuffix }}">{{ __('Abschlusszusammenfassung') }} *</label>
                <textarea id="completion-summary-{{ $dialogSuffix }}" name="summary" required rows="4" maxlength="5000"
                    class="textarea textarea-sm textarea-bordered w-full"></textarea>
            </div>
        </x-form-group>
    </x-modal>
@endcan

@can('markInvoiced', $diary)
    <x-modal id="order-invoice-{{ $dialogSuffix }}" :embedded="false" tone="success" icon="receipt_long"
        :eyebrow="__('Auftrag')" :title="__('Auftrag als berechnet markieren')"
        :action="route('diary.lifecycle', [$diary, 'action' => 'markInvoiced'])"
        :submit-label="__('Als berechnet markieren')" submit-class="btn-success">
        <x-form-group :legend="__('Abrechnung')" icon="payments" tone="success">
            <div class="fieldset">
                <label class="fieldset-label" for="invoice-reference-{{ $dialogSuffix }}">{{ __('Rechnungsreferenz') }} *</label>
                <input id="invoice-reference-{{ $dialogSuffix }}" name="reference" required maxlength="120"
                    class="input input-sm input-bordered w-full">
            </div>
        </x-form-group>
    </x-modal>
@endcan

@can('cancel', $diary)
    <x-modal id="order-cancel-{{ $dialogSuffix }}" :embedded="false" tone="error" icon="cancel"
        :eyebrow="__('Auftrag')" :title="__('Auftrag stornieren')"
        :action="route('diary.lifecycle', [$diary, 'action' => 'cancel'])"
        :submit-label="__('Stornieren')" submit-class="btn-error">
        <x-form-group :legend="__('Stornierung')" icon="warning" tone="error">
            <div class="fieldset">
                <label class="fieldset-label" for="cancellation-reason-{{ $dialogSuffix }}">{{ __('Begründung') }} *</label>
                <textarea id="cancellation-reason-{{ $dialogSuffix }}" name="reason" required rows="4" maxlength="2000"
                    class="textarea textarea-sm textarea-bordered w-full"></textarea>
            </div>
        </x-form-group>
    </x-modal>
@endcan
