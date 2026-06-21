{{-- Dialog: Benachrichtigungsregel pro Ereignistyp bearbeiten (MVP-018) --}}
@php
    /** @var \App\Models\Notification\NotificationRule $rule */
    /** @var \App\Enums\Notification\NotificationEvent $event */
    $channels = (array) old('channels', $rule->channels ?? []);
    $selectedRoles = (array) old('recipient_roles', $rule->recipient_roles ?? []);
    $selectedUserSqids = collect((array) ($rule->recipient_user_ids ?? []))
        ->map(fn($id) => \App\Support\Sqid::encode(\App\Models\User::class, (int) $id))
        ->all();
    $selectedUserSqids = (array) old('recipient_users', $selectedUserSqids);
@endphp
<x-modal
    :title="__('notification.title.edit_rule')"
    :eyebrow="$event->label()"
    icon="notifications_active"
    tone="primary"
    :action="route('admin.notification-rules.update', ['event' => $event->value])"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('notification.action.save')"
>
    <x-form-group :legend="__('notification.field.enabled')" icon="toggle_on" tone="primary" cols="2">
        <div class="fieldset">
            <label class="label cursor-pointer justify-start gap-3">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" class="toggle toggle-primary"
                       @checked(old('enabled', $rule->enabled))>
                <span class="label-text">{{ __('notification.field.rule_enabled') }}</span>
            </label>
            @error('enabled')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <span class="fieldset-label">{{ __('notification.field.channels') }}</span>
            <div class="flex flex-wrap gap-4">
                @foreach (\App\Enums\Notification\NotificationChannel::cases() as $channel)
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" name="channels[]" value="{{ $channel->value }}" class="checkbox checkbox-sm"
                               @checked(in_array($channel->value, $channels, true))>
                        <span class="label-text text-sm">{{ $channel->label() }}</span>
                    </label>
                @endforeach
            </div>
            @error('channels')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            @error('channels.*')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    <x-form-group :legend="__('notification.field.recipients')" icon="group" tone="info" cols="2">
        <div class="fieldset md:col-span-2">
            <label class="label cursor-pointer justify-start gap-3">
                <input type="hidden" name="notify_affected" value="0">
                <input type="checkbox" name="notify_affected" value="1" class="checkbox checkbox-sm"
                       @checked(old('notify_affected', $rule->notify_affected))>
                <span class="label-text text-sm">{{ __('notification.field.notify_affected_help') }}</span>
            </label>
            @error('notify_affected')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <x-select-field name="recipient_roles[]" :label="__('notification.field.recipient_roles')" multiple size="5" class="h-auto" error="recipient_roles.*">
            @foreach ($roleOptions as $value => $label)
                <option value="{{ $value }}" @selected(in_array($value, $selectedRoles, true))>{{ $label }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="recipient_users[]" :label="__('notification.field.recipient_users')" multiple size="5" class="h-auto" error="recipient_users.*">
            @foreach ($userOptions as $sqid => $name)
                <option value="{{ $sqid }}" @selected(in_array($sqid, $selectedUserSqids, true))>{{ $name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('notification.field.escalation')" icon="priority_high" tone="warning" cols="2"
                  :description="$event->supportsEscalation() ? __('notification.field.escalation_help') : __('notification.field.escalation_unsupported')">
        <div class="fieldset md:col-span-2">
            <label class="label cursor-pointer justify-start gap-3">
                <input type="hidden" name="escalation_enabled" value="0">
                <input type="checkbox" name="escalation_enabled" value="1" class="toggle toggle-warning"
                       @checked(old('escalation_enabled', $rule->escalation_enabled))
                       :disabled="! $event->supportsEscalation()">
                <span class="label-text">{{ __('notification.field.escalation_enabled') }}</span>
            </label>
            @error('escalation_enabled')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <x-input-field type="number" name="escalate_after_hours" :label="__('notification.field.escalate_after_hours')" min="1" max="720"
                       :value="old('escalate_after_hours', $rule->escalate_after_hours)"
                       :disabled="! $event->supportsEscalation()" />

        <x-select-field name="escalation_role" :label="__('notification.field.escalation_role')" :disabled="! $event->supportsEscalation()">
            <option value="">–</option>
            @foreach ($roleOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('escalation_role', $rule->escalation_role) === $value)>{{ $label }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>
</x-modal>
