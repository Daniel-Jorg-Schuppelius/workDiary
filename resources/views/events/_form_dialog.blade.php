{{--
  Created on   : Thu May 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $event, $isEdit, $isDialog, $categories, $rooms, $users, $customers,
                 $types, $statuses, $visibilities, $roles, $prefillStart, $prefillEnd --}}
@php
    /** @var \App\Models\Event|null $event */
    /** @var bool $isEdit */
    /** @var bool $isDialog */
    /** @var \Illuminate\Support\Collection $categories */
    /** @var \Illuminate\Support\Collection $rooms */
    /** @var \Illuminate\Support\Collection $users */
    /** @var \Illuminate\Support\Collection $customers */
    /** @var array $types */
    /** @var array $statuses */
    /** @var array $visibilities */
    /** @var array $roles */
    /** @var ?string $prefillStart */
    /** @var ?string $prefillEnd */

    $isDialog ??= true;
    $action    = $isEdit ? route('events.update', $event) : route('events.store');
    $method    = $isEdit ? 'PUT' : 'POST';
    $title     = $isEdit ? __('Veranstaltung bearbeiten') : __('Neue Veranstaltung');
    $dialogUrl = ($isEdit ? route('events.edit', $event) : route('events.create')).'?dialog=1';

    $startVal = old('started_at', $event?->started_at?->format('Y-m-d\TH:i') ?? $prefillStart);
    $endVal   = old('ended_at',   $event?->ended_at?->format('Y-m-d\TH:i')   ?? $prefillEnd);

    $roomItems = old('rooms', $event?->rooms->map(fn ($r) => [
        'room_id' => $r->sqid,
        'started_at' => optional($r->pivot->started_at)->format('Y-m-d\TH:i'),
        'ended_at'   => optional($r->pivot->ended_at)->format('Y-m-d\TH:i'),
        'setup_minutes_before'   => $r->pivot->setup_minutes_before,
        'teardown_minutes_after' => $r->pivot->teardown_minutes_after,
    ])->all() ?? []);

    $participantItems = old('participants', $event?->participants->map(fn ($p) => [
        'user_id' => $p->sqid,
        'role'    => $p->pivot->role,
    ])->all() ?? []);

    $roomTemplate = ['room_id' => '', 'started_at' => '', 'ended_at' => '', 'setup_minutes_before' => 0, 'teardown_minutes_after' => 0];
    $participantTemplate = ['user_id' => '', 'role' => 'attendee'];
@endphp

<x-modal
    :title="$title"
    :eyebrow="__('Veranstaltungen')"
    icon="event"
    tone="primary"
    size="xl"
    :action="$action"
    :method="$method"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    {{-- Basis -------------------------------------------------------------- --}}
    <x-form-group :legend="__('Basis')" icon="event" tone="primary" cols="2">
        <x-input-field name="title" :label="__('Titel')" required span="2" :value="old('title', $event?->title)" />

        <x-select-field name="event_type" :label="__('Typ')" required>
            @foreach ($types as $val => $label)
                <option value="{{ $val }}" @selected(old('event_type', $event?->event_type?->value) === $val)>{{ $label }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="category_id" :label="__('Kategorie')">
            <option value="">—</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->sqid }}" @selected((string) old('category_id', \App\Support\Sqid::encode(\App\Models\EventCategory::class, $event?->category_id)) === $cat->sqid)>{{ $cat->name }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="status" :label="__('Status')">
            @foreach ($statuses as $val => $label)
                <option value="{{ $val }}" @selected(old('status', $event?->status?->value) === $val)>{{ $label }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="visibility" :label="__('Sichtbarkeit')">
            @foreach ($visibilities as $val => $label)
                <option value="{{ $val }}" @selected(old('visibility', $event?->visibility?->value) === $val)>{{ $label }}</option>
            @endforeach
        </x-select-field>

        <x-input-field name="topic" :label="__('Thema')" span="2" :value="old('topic', $event?->topic)" />

        <x-textarea-field name="description" :label="__('Beschreibung')" rows="3" span="2" :value="old('description', $event?->description)" />
    </x-form-group>

    {{-- Termin ------------------------------------------------------------- --}}
    <x-form-group :legend="__('Termin')" icon="schedule" tone="info" cols="2">
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('Zeitraum') }} *</label>
            <x-date-range
                type="datetime-local"
                fromName="started_at"
                toName="ended_at"
                :from="$startVal"
                :to="$endVal"
                :fromLabel="__('Beginn')"
                :toLabel="__('Ende')"
                :label="false"
                required
                class="w-full"
            />
            @error('started_at')<p class="text-error text-sm">{{ $message }}</p>@enderror
            @error('ended_at')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label cursor-pointer justify-start gap-2">
                <input type="hidden" name="is_all_day" value="0">
                <input type="checkbox" name="is_all_day" value="1"
                       class="toggle toggle-primary"
                       @checked(old('is_all_day', $event?->is_all_day))>
                <span>{{ __('Ganztägig') }}</span>
            </label>
        </div>

        <x-input-field name="timezone" :label="__('Zeitzone')" class="font-mono" :value="old('timezone', $event?->timezone ?? config('app.timezone'))" />

        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="ev-rrule">{{ __('Wiederholungsregel (iCal RRULE)') }}</label>
            <input id="ev-rrule" type="text" name="recurrence_rule"
                   class="input input-bordered w-full font-mono text-xs"
                   placeholder="FREQ=WEEKLY;COUNT=8;BYDAY=MO"
                   value="{{ old('recurrence_rule', $event?->recurrence_rule) }}">
            <p class="text-xs opacity-60">{{ __('Optional. iCal RRULE-Format.') }}</p>
        </div>

        <x-input-field name="series_until" type="date" :label="__('Serie bis')" :value="old('series_until', $event?->series_until?->format('Y-m-d'))" />
    </x-form-group>

    {{-- Verantwortung ----------------------------------------------------- --}}
    <x-form-group :legend="__('Verantwortung')" icon="person" tone="success" cols="2">
        <x-select-field name="responsible_user_id" :label="__('Verantwortlich')" required>
            <option value="">—</option>
            @foreach ($users as $u)
                <option value="{{ $u->sqid }}" @selected((string) old('responsible_user_id', \App\Support\Sqid::encode(\App\Models\User::class, $event?->responsible_user_id ?? auth()->id())) === $u->sqid)>{{ $u->name }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="customer_id" :label="__('Externer Anbieter')">
            <option value="">—</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected((string) old('customer_id', \App\Support\Sqid::encode(\App\Models\Customer::class, $event?->customer_id)) === $c->sqid)>{{ $c->name }}</option>
            @endforeach
        </x-select-field>

        <x-input-field name="external_contact_note" :label="__('Externer Kontakt (Notiz)')" span="2" :value="old('external_contact_note', $event?->external_contact_note)" />
    </x-form-group>

    {{-- Pflicht & Zertifikat --------------------------------------------- --}}
    <x-form-group :legend="__('Pflicht & Zertifikat')" icon="workspace_premium" tone="warning" cols="3">
        <div class="fieldset">
            <label class="fieldset-label cursor-pointer justify-start gap-2">
                <input type="hidden" name="is_mandatory" value="0">
                <input type="checkbox" name="is_mandatory" value="1"
                       class="toggle toggle-warning"
                       @checked(old('is_mandatory', $event?->is_mandatory))>
                <span>{{ __('Pflichtschulung') }}</span>
            </label>
        </div>

        <x-input-field name="max_participants" type="number" :label="__('Max. Teilnehmer')" min="1" :value="old('max_participants', $event?->max_participants)" />

        <x-input-field name="certificate_valid_months" type="number" :label="__('Zertifikat gültig (Monate)')" min="1" :value="old('certificate_valid_months', $event?->certificate_valid_months)" />
    </x-form-group>

    {{-- Räume ------------------------------------------------------------- --}}
    <x-form-group :legend="__('Räume')" icon="meeting_room" tone="ghost">
        <div x-data="repeater"
             data-prefix="rooms"
             data-items="{{ json_encode($roomItems) }}"
             data-template="{{ json_encode($roomTemplate) }}"
             class="space-y-2">
            <template x-for="(it, i) in items" :key="i">
                <div class="rounded-box border border-base-300 bg-base-200/40 p-3 space-y-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 items-end">
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('Raum') }}</label>
                            <select :name="fieldName(i, 'room_id')" x-model="it.room_id"
                                    class="select select-sm select-bordered w-full" required>
                                <option value="">—</option>
                                @foreach ($rooms as $room)
                                    <option value="{{ $room->sqid }}">{{ $room->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('Belegung (Von – Bis)') }}</label>
                            <div class="join w-full">
                                <input type="datetime-local"
                                       :name="fieldName(i, 'started_at')" x-model="it.started_at"
                                       class="join-item input input-sm input-bordered flex-1 min-w-0"
                                       :title="'{{ __('Beginn') }}'" :aria-label="'{{ __('Beginn') }}'">
                                <input type="datetime-local"
                                       :name="fieldName(i, 'ended_at')" x-model="it.ended_at"
                                       class="join-item input input-sm input-bordered flex-1 min-w-0"
                                       :title="'{{ __('Ende') }}'" :aria-label="'{{ __('Ende') }}'">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 items-end">
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('Aufbau (Min)') }}</label>
                            <input type="number" min="0"
                                   :name="fieldName(i, 'setup_minutes_before')"
                                   x-model.number="it.setup_minutes_before"
                                   class="input input-sm input-bordered w-full">
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('Abbau (Min)') }}</label>
                            <input type="number" min="0"
                                   :name="fieldName(i, 'teardown_minutes_after')"
                                   x-model.number="it.teardown_minutes_after"
                                   class="input input-sm input-bordered w-full">
                        </div>
                        <div class="flex justify-end">
                            <x-icon-btn icon="close" tone="error" type="button"
                                        :label="__('Raum entfernen')" @click="remove(i)" />
                        </div>
                    </div>
                </div>
            </template>

            <x-icon-btn icon="add" tone="ghost" size="sm" type="button" show-label @click="add()">
                {{ __('Raum hinzufügen') }}
            </x-icon-btn>
        </div>
    </x-form-group>

    {{-- Plugin-Erweiterungen (View-Slot, z. B. M365-Free/Busy, Feature 102 C2) --}}
    {!! app(\App\Plugins\PluginManager::class)->renderSlot('event-form.aside', $event) !!}

    {{-- Teilnehmer -------------------------------------------------------- --}}
    <x-form-group :legend="__('Teilnehmer')" icon="group" tone="info">
        <div x-data="repeater"
             data-prefix="participants"
             data-items="{{ json_encode($participantItems) }}"
             data-template="{{ json_encode($participantTemplate) }}"
             class="space-y-2">
            <template x-for="(it, i) in items" :key="i">
                <div class="rounded-box border border-base-300 bg-base-200/40 p-3">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-2 items-end">
                        <div class="fieldset md:col-span-3">
                            <label class="fieldset-label">{{ __('Benutzer') }}</label>
                            <select :name="fieldName(i, 'user_id')" x-model="it.user_id"
                                    class="select select-sm select-bordered w-full" required>
                                <option value="">—</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->sqid }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('Rolle') }}</label>
                            <select :name="fieldName(i, 'role')" x-model="it.role"
                                    class="select select-sm select-bordered w-full">
                                @foreach ($roles as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex justify-end">
                            <x-icon-btn icon="close" tone="error" type="button"
                                        :label="__('Teilnehmer entfernen')" @click="remove(i)" />
                        </div>
                    </div>
                </div>
            </template>

            <x-icon-btn icon="add" tone="ghost" size="sm" type="button" show-label @click="add()">
                {{ __('Teilnehmer hinzufügen') }}
            </x-icon-btn>
        </div>
    </x-form-group>

    <x-validation-errors />
</x-modal>
