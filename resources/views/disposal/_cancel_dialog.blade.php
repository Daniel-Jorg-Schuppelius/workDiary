{{-- Dialog: Entsorgungsakte stornieren (Feature 100) — Pflicht-Begründung,
     landet als payload.reason in der Nachweiskette. Erwartet: $job. --}}
<x-modal
    id="disposal-cancel-dialog"
    :embedded="false"
    :title="__('disposal.cancel.title')"
    :eyebrow="__('disposal.eyebrow')"
    icon="cancel"
    tone="error"
    :action="route('disposal.cancel', $job)"
    method="POST"
    :submit-label="__('Stornieren')"
    submit-class="btn-error"
>
    <p class="text-sm text-base-content/70">{{ __('disposal.cancel.intro') }}</p>

    <x-textarea-field name="reason" :label="__('disposal.cancel.reason')" rows="3" required maxlength="255" />
</x-modal>
