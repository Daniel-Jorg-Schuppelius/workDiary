{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog Kommunikationsnotiz (in #entry-modal geladen).
  Variablen: $note (CommunicationNote|null), $notableKind, $notableId,
             $users, $canPublishToCustomer, $canManageConfidential
--}}
@php
    $isEdit = $note !== null;
    $tz = \App\Support\Tz::current();
    $occurredDefault = old('occurred_at', $isEdit
        ? $note->occurred_at->copy()->timezone($tz)->format('Y-m-d\TH:i')
        : now($tz)->format('Y-m-d\TH:i'));
    $dueDefault = old('next_action_due_at', $isEdit && $note->next_action_due_at
        ? $note->next_action_due_at->copy()->timezone($tz)->format('Y-m-d\TH:i')
        : '');
    $participantTemplate = ['name' => '', 'role' => '', 'party' => \App\Enums\Communication\ParticipantParty::Customer->value, 'user_id' => ''];
    $participantItems = old('participants', $isEdit
        ? $note->participants->map(fn($p) => [
            'name' => $p->name,
            'role' => (string) $p->role,
            'party' => $p->party->value,
            'user_id' => $p->user_id !== null ? (string) $p->user_id : '',
        ])->values()->all()
        : []);
@endphp

<x-modal
    :title="$isEdit ? __('communication.action.edit') : __('communication.action.create')"
    :eyebrow="__('communication.title.index')"
    icon="forum"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('communication-notes.update', $note) : route('communication-notes.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('communication.action.save') : __('communication.action.create')">

    @unless ($isEdit)
        <input type="hidden" name="notable_kind" value="{{ $notableKind }}">
        <input type="hidden" name="notable_id" value="{{ $notableId }}">
    @endunless

    <x-form-group :legend="__('communication.title.index')" icon="forum" tone="primary" cols="2">
        <label class="form-control">
            <span class="label-text">{{ __('communication.field.type') }} *</span>
            <select name="type" required class="select select-bordered w-full">
                @foreach (\App\Enums\Communication\CommunicationNoteType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $note?->type?->value ?? 'call') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('communication.field.direction') }} *</span>
            <select name="direction" required class="select select-bordered w-full">
                @foreach (\App\Enums\Communication\CommunicationDirection::cases() as $direction)
                    <option value="{{ $direction->value }}" @selected(old('direction', $note?->direction?->value ?? 'outbound') === $direction->value)>{{ $direction->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('communication.field.occurred_at') }} *</span>
            <input type="datetime-local" name="occurred_at" required
                   class="input input-bordered w-full" value="{{ $occurredDefault }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('communication.field.subject') }} *</span>
            <input type="text" name="subject" required minlength="3" maxlength="180"
                   class="input input-bordered w-full" value="{{ old('subject', $note?->subject) }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('communication.field.body') }} *</span>
            <textarea name="body" rows="4" required maxlength="8000"
                      class="textarea textarea-bordered w-full">{{ old('body', $note?->body) }}</textarea>
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('communication.field.result') }}</span>
            <textarea name="result" rows="2" maxlength="8000"
                      class="textarea textarea-bordered w-full">{{ old('result', $note?->result) }}</textarea>
        </label>
    </x-form-group>

    <x-form-group :legend="__('communication.field.participants')" icon="group" tone="info">
        {{-- Leerer Marker VOR den Zeilen: erlaubt das Entfernen aller Beteiligten beim Bearbeiten. --}}
        <input type="hidden" name="participants" value="">
        <div x-data="repeater"
             data-prefix="participants"
             data-items="{{ json_encode($participantItems) }}"
             data-template="{{ json_encode($participantTemplate) }}"
             class="space-y-2 sm:col-span-2">
            <template x-for="(it, i) in items" :key="i">
                <div class="grid grid-cols-1 items-end gap-2 rounded-box border border-base-300 bg-base-200/40 p-3 sm:grid-cols-[1fr_1fr_auto]">
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('communication.field.participant_name') }}</label>
                        <input type="text" maxlength="120"
                               :name="fieldName(i, 'name')" x-model="it.name"
                               class="input input-sm input-bordered w-full">
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('communication.field.participant_role') }}</label>
                        <input type="text" maxlength="40"
                               :name="fieldName(i, 'role')" x-model="it.role"
                               class="input input-sm input-bordered w-full">
                    </div>
                    <div class="flex items-end gap-2">
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('communication.field.participant_party') }}</label>
                            <select :name="fieldName(i, 'party')" x-model="it.party"
                                    class="select select-sm select-bordered">
                                @foreach (\App\Enums\Communication\ParticipantParty::cases() as $party)
                                    <option value="{{ $party->value }}">{{ $party->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-icon-btn icon="close" tone="error" size="xs" type="button"
                                    :label="__('communication.action.remove_participant')" @click="remove(i)" />
                    </div>
                </div>
            </template>

            <x-icon-btn icon="add" tone="ghost" size="sm" type="button" show-label @click="add()">
                {{ __('communication.action.add_participant') }}
            </x-icon-btn>
        </div>
    </x-form-group>

    <x-form-group :legend="__('communication.field.next_action')" icon="pending_actions" tone="warning" cols="2">
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('communication.field.next_action') }}</span>
            <input type="text" name="next_action" maxlength="180"
                   class="input input-bordered w-full" value="{{ old('next_action', $note?->next_action) }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('communication.field.next_action_due_at') }}</span>
            <input type="datetime-local" name="next_action_due_at"
                   class="input input-bordered w-full" value="{{ $dueDefault }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('communication.field.next_action_user') }}</span>
            <select name="next_action_user_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((string) old('next_action_user_id', $note?->next_action_user_id) === (string) $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </label>
    </x-form-group>

    @if ($canPublishToCustomer || $canManageConfidential)
        <x-form-group :legend="__('communication.field.visibility')" icon="visibility" tone="ghost" cols="2">
            @if ($canPublishToCustomer)
                <label class="flex items-center gap-2">
                    <input type="hidden" name="visibility" value="internal">
                    <input type="checkbox" name="visibility" value="customer" class="checkbox"
                           @checked(old('visibility', $note?->visibility?->value) === 'customer')>
                    {{ __('communication.field.customer_visible') }}
                </label>
            @endif
            @if ($canManageConfidential)
                <label class="flex items-center gap-2">
                    <input type="hidden" name="confidential" value="0">
                    <input type="checkbox" name="confidential" value="1" class="checkbox"
                           @checked(old('confidential', $note?->confidential))>
                    {{ __('communication.field.confidential') }}
                </label>
            @endif
        </x-form-group>
    @endif
</x-modal>
