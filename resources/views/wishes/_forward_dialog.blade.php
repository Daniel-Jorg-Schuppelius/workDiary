{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _forward_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Weitergabe an den Plattformbetrieb ohne Personenbezug (Feature 206, MVP-1659). Variablen: $wish, $votes. --}}
<x-modal
    :title="__('wishes.dialog.forward_title')"
    :eyebrow="__('wishes.title')"
    icon="outbox"
    tone="primary"
    :action="route('wishes.forward.store', $wish)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('wishes.dialog.forward_submit')">

    <input type="hidden" name="_dialog_url" value="{{ route('wishes.forward.create', $wish) . '?dialog=1' }}">

    <div role="note" class="alert alert-warning mb-3 text-sm">
        <x-icon name="privacy_tip" />
        <span>{{ __('wishes.dialog.forward_hint') }}</span>
    </div>

    <x-form-group :legend="__('wishes.dialog.forward_legend')" icon="outbox" tone="primary">
        <x-textarea-field name="forwarded_body" id="wish-forwarded-body" :label="__('wishes.field.forwarded_body')" rows="6" required
                          :value="old('forwarded_body', $wish->body)" :hint="__('wishes.dialog.forward_body_hint')" />
        <x-detail-grid layout="cells" :cols="1" small-labels>
            <x-detail-grid.row :label="__('wishes.field.kind')">{{ $wish->kind->label() }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('wishes.field.page')"><span class="font-mono text-xs">{{ $wish->route_name ?? '—' }}</span></x-detail-grid.row>
            <x-detail-grid.row :label="__('wishes.field.votes')">{{ $votes }}</x-detail-grid.row>
        </x-detail-grid>
        <p class="text-xs text-muted">{{ __('wishes.dialog.forward_not_shared') }}</p>
        <x-checkbox-field name="confirm_clean" id="wish-confirm-clean" :label="__('wishes.dialog.forward_confirm')" :with-hidden="false" :toggle="false" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
