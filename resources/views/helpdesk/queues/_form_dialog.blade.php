{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $queue, $isEdit, $teams, $slaContracts --}}
@php
    /** @var \App\Models\ServiceTicket\ServiceQueue $queue */
    /** @var bool $isEdit */
    $isEdit ??= $queue->exists;
    $action = $isEdit ? route('helpdesk.queues.update', $queue) : route('helpdesk.queues.store');
    $method = $isEdit ? 'PATCH' : 'POST';
    $title = $isEdit ? __('Queue bearbeiten') : __('Neue Queue');
@endphp

<x-modal
    :title="$title"
    :eyebrow="__('Helpdesk')"
    icon="inbox"
    tone="primary"
    size="md"
    :action="$action"
    :method="$method"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-form-group :legend="__('Queue')" icon="inbox" tone="primary" cols="2">
        <x-input-field name="name" :label="__('Name')" required maxlength="120" span="2" :value="old('name', $queue->name)" />
        <x-input-field name="purpose" :label="__('Zweck')" maxlength="500" span="2" :value="old('purpose', $queue->purpose)" />

        <x-select-field name="team_id" :label="__('Team')">
            <option value="">{{ __('— Kein Team —') }}</option>
            @foreach ($teams as $team)
                <option value="{{ $team->sqid }}" @selected((string) old('team_id', \App\Support\Sqid::encode(\App\Models\Platform\Team::class, $queue->team_id)) === $team->sqid)>{{ $team->name }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="default_sla_contract_id" :label="__('Standard-SLA')">
            <option value="">{{ __('— Kein SLA —') }}</option>
            @foreach ($slaContracts as $contract)
                <option value="{{ $contract->sqid }}" @selected((string) old('default_sla_contract_id', \App\Support\Sqid::encode(\App\Models\ServiceTicket\SlaContract::class, $queue->default_sla_contract_id)) === $contract->sqid)>{{ $contract->label }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="visibility" :label="__('Sichtbarkeit')" required>
            <option value="internal" @selected(old('visibility', $queue->visibility ?? 'internal') === 'internal')>{{ __('Intern') }}</option>
            <option value="portal" @selected(old('visibility', $queue->visibility) === 'portal')>{{ __('Kundenportal') }}</option>
        </x-select-field>

        <x-checkbox-field name="is_default" :label="__('Standard-Queue')" :checked="(bool) old('is_default', $queue->is_default)"
                          :hint="__('Neue Tickets ohne Zuordnung landen hier (genau eine je Organisation).')" />
    </x-form-group>

    <x-form-group :legend="__('helpdesk.queue.intake')" icon="alt_route" tone="ghost" cols="2">
        <div class="fieldset md:col-span-2">
            <span class="fieldset-label">{{ __('helpdesk.queue.kinds') }}</span>
            <div class="flex flex-wrap gap-x-4 gap-y-1">
                @foreach (\App\Enums\ServiceTicket\ServiceTicketKind::cases() as $kind)
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" name="supported_kinds[]" value="{{ $kind->value }}" class="checkbox checkbox-sm"
                               @checked(in_array($kind->value, (array) old('supported_kinds', $queue->supported_kinds ?? []), true))>
                        {{ $kind->label() }}
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-muted mt-1">{{ __('helpdesk.queue.kindsHint') }}</p>
            @error('supported_kinds.*')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <x-select-field name="email_connection_id" :label="__('helpdesk.queue.mailbox')" :hint="__('helpdesk.queue.mailboxHint')" span="2">
            <option value="">{{ __('helpdesk.queue.mailboxNone') }}</option>
            @foreach ($mailboxes as $mailbox)
                <option value="{{ $mailbox->sqid }}" @selected((string) old('email_connection_id', \App\Support\Sqid::encode(\App\Models\Mail\EmailConnection::class, $queue->email_connection_id)) === $mailbox->sqid)>{{ $mailbox->name }}@if ($mailbox->mailbox_address) · {{ $mailbox->mailbox_address }}@endif</option>
            @endforeach
        </x-select-field>

        <x-input-field name="sender_name" :label="__('helpdesk.queue.senderName')" maxlength="120"
                       :value="old('sender_name', $queue->sender_identity['name'] ?? null)" />
        <x-input-field name="sender_email" type="email" :label="__('helpdesk.queue.senderEmail')" maxlength="190"
                       :hint="__('helpdesk.queue.senderHint')"
                       :value="old('sender_email', $queue->sender_identity['email'] ?? null)" />
    </x-form-group>

    <x-form-group :legend="__('helpdesk.queue.calendar')" icon="schedule" tone="ghost" cols="1">
        <x-select-field name="holiday_region" :label="__('Feiertags-Region')">
            <option value="">{{ __('Feiertagsregelung der Organisation') }}</option>
            @foreach (\App\Support\HolidayRegions::grouped() as $group => $providers)
                <optgroup label="{{ $group }}">
                    @foreach ($providers as $provider => $label)
                        <option value="{{ $provider }}" @selected(old('holiday_region', $queue->holiday_region) === $provider)>{{ $label }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </x-select-field>

        @php
            $hoursByDay = collect((array) ($queue->business_hours ?? []))->keyBy(fn ($row, $key) => (int) ($row['weekday'] ?? $key));
            $monday = now()->startOfWeek(\Carbon\CarbonInterface::MONDAY);
        @endphp
        <div class="fieldset">
            <span class="fieldset-label">{{ __('helpdesk.queue.hours') }}</span>
            <div class="grid gap-x-3 gap-y-1 grid-cols-[auto_1fr_1fr] items-center">
                @foreach (range(1, 7) as $weekday)
                    @php
                        $dayName = $monday->copy()->addDays($weekday - 1)->translatedFormat('l');
                    @endphp
                    <span class="text-sm">{{ $dayName }}</span>
                    <input type="time" name="business_hours[{{ $weekday }}][from]" class="input input-sm input-bordered w-full"
                           aria-label="{{ __('helpdesk.queue.hoursFrom', ['day' => $dayName]) }}"
                           value="{{ old('business_hours.' . $weekday . '.from', $hoursByDay[$weekday]['from'] ?? '') }}">
                    <input type="time" name="business_hours[{{ $weekday }}][to]" class="input input-sm input-bordered w-full"
                           aria-label="{{ __('helpdesk.queue.hoursTo', ['day' => $dayName]) }}"
                           value="{{ old('business_hours.' . $weekday . '.to', $hoursByDay[$weekday]['to'] ?? '') }}">
                @endforeach
            </div>
            <p class="text-xs text-muted mt-1">{{ __('helpdesk.queue.hoursHint') }}</p>
            @error('business_hours')<p class="text-error text-sm">{{ $message }}</p>@enderror
            @error('business_hours.*.from')<p class="text-error text-sm">{{ $message }}</p>@enderror
            @error('business_hours.*.to')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    @if ($isEdit)
        <p class="text-sm {{ $queue->isExternallyLed() ? 'text-warning' : 'text-muted' }}" data-queue-ownership>
            <x-icon name="{{ $queue->isExternallyLed() ? 'cloud_sync' : 'home_storage' }}" class="align-middle text-[16px]" />
            {{ $queue->isExternallyLed() ? __('helpdesk.queue.ownershipExternalHint') : __('helpdesk.queue.ownershipNativeHint') }}
        </p>
    @endif
</x-modal>
