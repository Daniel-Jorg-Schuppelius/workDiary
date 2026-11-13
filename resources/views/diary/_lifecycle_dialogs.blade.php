{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _lifecycle_dialogs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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

@can('reject', $diary)
    <x-modal id="order-reject-{{ $dialogSuffix }}" :embedded="false" tone="warning" icon="undo"
        :eyebrow="__('Auftrag')" :title="__('diary.lifecycle.reject.title')"
        :action="route('diary.lifecycle', [$diary, 'action' => 'reject'])"
        :submit-label="__('diary.lifecycle.action.reject')" submit-class="btn-warning">
        <p class="mb-3 text-sm text-base-content/70">{{ __('diary.lifecycle.reject.hint') }}</p>
        <x-form-group :legend="__('diary.lifecycle.reject.legend')" icon="undo" tone="warning">
            <div class="fieldset">
                <label class="fieldset-label" for="reject-reason-{{ $dialogSuffix }}">{{ __('Begründung') }} *</label>
                <textarea id="reject-reason-{{ $dialogSuffix }}" name="reason" required rows="3" maxlength="2000"
                    class="textarea textarea-sm textarea-bordered w-full"></textarea>
            </div>
        </x-form-group>
    </x-modal>
@endcan

@if (($signedProtocol ?? null) !== null && ($handoverSigner ?? null) === null)
    @can('handover', $diary)
        <x-modal id="order-handover-{{ $dialogSuffix }}" :embedded="false" tone="primary" icon="handshake"
            :eyebrow="__('Auftrag')" :title="__('Abnahme starten')"
            :action="route('diary.lifecycle', [$diary, 'action' => 'handover'])"
            :submit-label="__('Abnahme starten')">
            <input type="hidden" name="protocol_id" value="{{ $signedProtocol->sqid }}">
            <p class="mb-3 text-sm text-base-content/70">{{ __('diary.lifecycle.handover.hint') }}</p>
            <x-form-group :legend="__('diary.lifecycle.handover.legend')" icon="draw" tone="primary">
                <div class="fieldset">
                    <label class="fieldset-label" for="handover-signer-{{ $dialogSuffix }}">{{ __('diary.lifecycle.handover.accepted_by') }} *</label>
                    <input id="handover-signer-{{ $dialogSuffix }}" name="accepted_by" required maxlength="120"
                        class="input input-sm input-bordered w-full">
                </div>
            </x-form-group>
        </x-modal>
    @endcan
@endif

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
        @include('diary._completion_classifications', ['dialogSuffix' => $dialogSuffix . '-c'])
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

{{-- Interne Prüfung und Folgeperiode (Feature 198). --}}
@if (in_array('queryReview', $diary->lifecycleActions(), true))
    @can('queryReview', $diary)
        <x-modal id="order-query-{{ $dialogSuffix }}" :embedded="false" tone="warning" icon="contact_support"
            :eyebrow="__('Auftrag')" :title="__('diary.review.query.title')"
            :action="route('diary.lifecycle', [$diary, 'action' => 'queryReview'])"
            :submit-label="__('diary.lifecycle.action.query_review')" submit-class="btn-warning">
            <p class="mb-3 text-sm text-base-content/70">{{ __('diary.review.query.hint') }}</p>
            <x-form-group :legend="__('diary.review.query.legend')" icon="contact_support" tone="warning">
                <div class="fieldset">
                    <label class="fieldset-label" for="review-question-{{ $dialogSuffix }}">{{ __('diary.review.query.question') }} *</label>
                    <textarea id="review-question-{{ $dialogSuffix }}" name="question" required rows="4" maxlength="2000"
                        class="textarea textarea-sm textarea-bordered w-full"></textarea>
                </div>
            </x-form-group>
        </x-modal>
    @endcan
@endif

@if (in_array('approveAndComplete', $diary->lifecycleActions(), true))
    @can('approveAndComplete', $diary)
        <x-modal id="order-approve-complete-{{ $dialogSuffix }}" :embedded="false" tone="success" icon="task_alt"
            :eyebrow="__('Auftrag')" :title="__('diary.lifecycle.action.approve_and_complete')"
            :action="route('diary.lifecycle', [$diary, 'action' => 'approveAndComplete'])"
            :submit-label="__('diary.lifecycle.action.approve_and_complete')" submit-class="btn-success">
            <p class="mb-3 text-sm text-base-content/70">{{ __('diary.review.approve_complete_hint') }}</p>
            <x-form-group :legend="__('Abschluss')" icon="description" tone="success">
                <div class="fieldset">
                    <label class="fieldset-label" for="approve-summary-{{ $dialogSuffix }}">{{ __('Abschlusszusammenfassung') }} *</label>
                    <textarea id="approve-summary-{{ $dialogSuffix }}" name="summary" required rows="4" maxlength="5000"
                        class="textarea textarea-sm textarea-bordered w-full"></textarea>
                </div>
            </x-form-group>
            @include('diary._completion_classifications', ['dialogSuffix' => $dialogSuffix . '-ac'])
        </x-modal>
    @endcan
@endif

@can('waiveReview', $diary)
    <x-modal id="order-review-requirement-{{ $dialogSuffix }}" :embedded="false" tone="warning" icon="fact_check"
        :eyebrow="__('Auftrag')" :title="__($diary->requires_review ? 'diary.review.waive_action' : 'diary.review.require_action')"
        :action="route('diary.review-requirement', $diary)" method="PATCH"
        :submit-label="__($diary->requires_review ? 'diary.review.waive_action' : 'diary.review.require_action')" submit-class="btn-warning">
        <input type="hidden" name="requires_review" value="{{ $diary->requires_review ? '0' : '1' }}">
        <p class="mb-3 text-sm text-base-content/70">{{ __($diary->requires_review ? 'diary.review.waive_hint' : 'diary.review.require_hint') }}</p>
        <x-form-group :legend="__('Begründung')" icon="edit_note" tone="warning">
            <div class="fieldset">
                <label class="fieldset-label" for="review-requirement-reason-{{ $dialogSuffix }}">{{ __('Begründung') }}</label>
                <textarea id="review-requirement-reason-{{ $dialogSuffix }}" name="reason" rows="3" maxlength="2000"
                    class="textarea textarea-sm textarea-bordered w-full"></textarea>
            </div>
        </x-form-group>
    </x-modal>
@endcan

@if ($diary->period_key && $diary->customer_id && $diary->entry_type_id)
    @can('createFollowUp', $diary)
        @php($followUpService = app(\App\Services\Diary\FollowUpPeriodService::class))
        @php($followUpTarget = $followUpService->targetPeriod($diary))
        @php($followUpExisting = $followUpTarget !== null ? $followUpService->existingFor($diary, $followUpTarget) : null)
        <x-modal id="order-follow-up-{{ $dialogSuffix }}" :embedded="false" tone="primary" icon="event_repeat"
            :eyebrow="__('Auftrag')" :title="__('diary.follow_up.title', ['period' => $followUpTarget?->label() ?? '—'])"
            :action="route('diary.follow-up.store', $diary)"
            :submit-label="__('diary.follow_up.submit')" :hide-footer="$followUpExisting !== null">
            @if ($followUpExisting)
                <div class="alert alert-warning mb-3 text-sm" role="status">
                    <x-icon name="content_copy" />
                    <span>{{ __('diary.follow_up.exists_hint', ['period' => $followUpTarget?->label() ?? '—']) }}
                        <x-order-link :entry="$followUpExisting" link-class="link">#{{ $followUpExisting->id }}</x-order-link></span>
                </div>
            @endif
            <p class="text-sm text-base-content/70">{{ __('diary.follow_up.copied') }}</p>
            <p class="mt-2 text-sm text-base-content/70">{{ __('diary.follow_up.not_copied') }}</p>
        </x-modal>
    @endcan
@endif
