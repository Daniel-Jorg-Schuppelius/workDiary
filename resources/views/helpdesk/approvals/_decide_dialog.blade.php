{{-- Variablen: $approval (mit approvable geladen), $orgUsers --}}
@php
    /** @var \App\Models\Approval $approval */
    $approvable = $approval->approvable;
    $ticket = $approvable instanceof \App\Models\ServiceRequest ? $approvable->ticket : null;
    $subject = $ticket?->title ?? $approvable?->title ?? $approvable?->name ?? '—';
@endphp

<x-modal
    :title="__('Schritt :n entscheiden', ['n' => $approval->step])"
    :eyebrow="__('Genehmigungen')"
    icon="rule"
    tone="primary"
    size="md"
    :action="route('servicedesk.approvals.decide', $approval)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Entscheiden')">

    <div class="alert alert-info text-sm">
        <div>
            <div class="font-semibold">{{ \App\Support\EntityType::label($approval->approvable_type) }}: {{ $subject }}</div>
            @if ($ticket !== null)
                <div class="text-base-content/70 font-mono">{{ $ticket->ticket_no }}</div>
            @endif
            @if ($approval->decision === 'question' && $approval->reason)
                <div class="mt-1">{{ __('Letzte Rückfrage') }}: {{ $approval->reason }}</div>
            @endif
        </div>
    </div>

    <div class="mt-3 space-y-2"
         x-data="{ decision: '{{ old('decision', 'approved') }}' }">
        <div class="fieldset">
            <span class="fieldset-label">{{ __('Entscheidung') }}</span>
            <div class="grid grid-cols-2 gap-2">
                <label class="flex cursor-pointer items-center gap-2 rounded-box border border-base-300 p-2">
                    <input type="radio" name="decision" value="approved" class="radio radio-sm radio-success" x-model="decision">
                    <span>{{ __('Genehmigen') }}</span>
                </label>
                <label class="flex cursor-pointer items-center gap-2 rounded-box border border-base-300 p-2">
                    <input type="radio" name="decision" value="rejected" class="radio radio-sm radio-error" x-model="decision">
                    <span>{{ __('Ablehnen') }}</span>
                </label>
                <label class="flex cursor-pointer items-center gap-2 rounded-box border border-base-300 p-2">
                    <input type="radio" name="decision" value="question" class="radio radio-sm radio-warning" x-model="decision">
                    <span>{{ __('Rückfrage') }}</span>
                </label>
                <label class="flex cursor-pointer items-center gap-2 rounded-box border border-base-300 p-2">
                    <input type="radio" name="decision" value="delegated" class="radio radio-sm radio-info" x-model="decision">
                    <span>{{ __('Delegieren') }}</span>
                </label>
            </div>
            @error('decision')
                <p class="mt-1 text-xs text-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="fieldset" x-show="decision === 'delegated'" x-cloak>
            <label class="fieldset-label" for="delegate">{{ __('Delegieren an') }}</label>
            <x-user-select name="delegate" :users="$orgUsers" value-key="sqid"
                           :placeholder="__('Benutzer auswählen…')" />
            <p class="mt-1 text-xs text-base-content/60">{{ __('Der Delegat übernimmt den Schritt; die Selbstfreigabe-Sperre gilt auch für ihn.') }}</p>
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="reason">
                {{ __('Begründung') }}
                <span class="text-base-content/60" x-show="decision === 'rejected' || decision === 'delegated'">({{ __('Pflicht') }})</span>
            </label>
            <textarea id="reason" name="reason" rows="3" maxlength="500"
                      class="textarea textarea-bordered w-full @error('reason') textarea-error @enderror"
                      :required="decision === 'rejected' || decision === 'delegated'"
                      placeholder="{{ __('z. B. Budget fehlt, andere Zuständigkeit, Rückfrage zum Umfang …') }}">{{ old('reason') }}</textarea>
            @error('reason')
                <p class="mt-1 text-xs text-error">{{ $message }}</p>
            @enderror
        </div>
    </div>
</x-modal>
