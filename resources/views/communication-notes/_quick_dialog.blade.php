{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _quick_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Schnellerfassung ohne geöffnete Akte (Feature 154, MVP-777), in #entry-modal geladen.
  Variablen: $customers, $customerSqid (string|null), $users, $canManageConfidential
--}}
@php
    $tz = \App\Support\Tz::current();
    $occurredDefault = now($tz)->format('Y-m-d\TH:i');
    $storageDefault = $customerSqid !== null ? 'customer' : 'internal';
    // Arten ohne Außenkontakt brauchen keine Richtung — der Server setzt „Intern“.
    $internalTypes = array_map(
        static fn (\App\Enums\Communication\CommunicationNoteType $type): string => "'" . $type->value . "'",
        array_filter(\App\Enums\Communication\CommunicationNoteType::cases(), static fn (\App\Enums\Communication\CommunicationNoteType $type): bool => $type->isInternalByNature()),
    );
    $directionVisible = 'isNone(' . implode(', ', $internalTypes) . ')';
@endphp

<x-modal
    :title="__('communication.action.create')"
    :eyebrow="__('communication.title.notes')"
    icon="sticky_note_2"
    tone="primary"
    size="lg"
    :action="route('communication-notes.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('communication.action.create')">

    <x-form-group :legend="__('communication.field.storage')" icon="inventory_2" tone="primary" cols="2">
        <div class="space-y-3 sm:col-span-2" x-data="reveal(@js($storageDefault))">
            <fieldset class="flex flex-wrap gap-4">
                <legend class="sr-only">{{ __('communication.field.storage') }}</legend>
                <label class="flex items-center gap-2">
                    <input type="radio" name="storage" value="internal" class="radio radio-sm" x-model="value" @checked($storageDefault === 'internal')>
                    {{ __('communication.storage.internal') }}
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="storage" value="customer" class="radio radio-sm" x-model="value" @checked($storageDefault === 'customer')>
                    {{ __('communication.storage.customer') }}
                </label>
            </fieldset>
            <div x-show="is('customer')" x-cloak>
                <x-select-field id="quick-note-customer" name="customer_id" :label="__('communication.field.customer')" :hint="__('communication.hint.customer_not_published')">
                    <option value="">—</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->sqid }}" @selected($customerSqid === $customer->sqid)>{{ $customer->name }}</option>
                    @endforeach
                </x-select-field>
            </div>
        </div>
    </x-form-group>

    <x-form-group :legend="__('communication.title.note')" icon="edit_note" tone="primary" cols="2">
        <div class="grid grid-cols-1 gap-4 sm:col-span-2 sm:grid-cols-2" x-data="reveal('general')">
            <x-select-field id="quick-note-type" name="type" :label="__('communication.field.type')" required x-model="value">
                @foreach (\App\Enums\Communication\CommunicationNoteType::quickCaptureOrder() as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </x-select-field>
            <div x-show="{{ $directionVisible }}" x-cloak>
                <x-select-field id="quick-note-direction" name="direction" :label="__('communication.field.direction')">
                    <option value="">{{ __('communication.field.direction_choose') }}</option>
                    @foreach (\App\Enums\Communication\CommunicationDirection::cases() as $direction)
                        <option value="{{ $direction->value }}">{{ $direction->label() }}</option>
                    @endforeach
                </x-select-field>
            </div>
        </div>
        <x-input-field name="occurred_at" type="datetime-local" :label="__('communication.field.occurred_at')" required :value="$occurredDefault" />
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('communication.field.subject') }} *</span>
            <input type="text" name="subject" required minlength="3" maxlength="180" class="input input-bordered w-full">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('communication.field.body') }} *</span>
            <textarea name="body" rows="4" required maxlength="8000" class="textarea textarea-bordered w-full"></textarea>
        </label>
    </x-form-group>

    <details class="collapse collapse-arrow rounded-box border border-base-300 bg-base-100">
        <summary class="collapse-title text-sm font-semibold">{{ __('communication.section.more') }}</summary>
        <div class="collapse-content grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="form-control sm:col-span-2">
                <span class="label-text">{{ __('communication.field.result') }}</span>
                <textarea name="result" rows="2" maxlength="8000" class="textarea textarea-bordered w-full"></textarea>
            </label>
            <label class="form-control sm:col-span-2">
                <span class="label-text">{{ __('communication.field.next_action') }}</span>
                <input type="text" name="next_action" maxlength="180" class="input input-bordered w-full">
            </label>
            <x-input-field name="next_action_due_at" type="datetime-local" :label="__('communication.field.next_action_due_at')" />
            <x-select-field id="quick-note-user" name="next_action_user_id" :label="__('communication.field.next_action_user')">
                <option value="">—</option>
                @foreach ($users as $assignee)
                    <option value="{{ $assignee->sqid }}">{{ $assignee->name }}</option>
                @endforeach
            </x-select-field>
            @if ($canManageConfidential)
                <label class="flex items-center gap-2 sm:col-span-2">
                    <input type="hidden" name="confidential" value="0">
                    <input type="checkbox" name="confidential" value="1" class="checkbox">
                    {{ __('communication.field.confidential') }}
                </label>
            @endif
        </div>
    </details>
</x-modal>
