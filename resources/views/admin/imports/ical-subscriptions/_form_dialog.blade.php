{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Kalender-Abo anlegen/bearbeiten (MVP-1516) --}}
@php
    /** @var \App\Models\Time\IcalSubscription $subscription */
    /** @var list<\App\Enums\Import\ImportEntity> $entities */
    $isEdit = $subscription->exists;
    $selectedEntity = old('import_entity', $subscription->import_entity?->value);
    $selectedUser = (string) old('user_id', $subscription->user_id !== null ? \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $subscription->user_id) : '');
@endphp
<x-modal
    :title="$isEdit ? __('import.ical_subscription.edit_title') : __('import.ical_subscription.create_title')"
    :eyebrow="$isEdit ? $subscription->name : null"
    icon="event_repeat"
    tone="primary"
    :action="$isEdit ? route('admin.imports.ical-subscriptions.update', $subscription) : route('admin.imports.ical-subscriptions.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('import.ical_subscription.save')"
>
    <x-form-group :legend="__('import.ical_subscription.title')" icon="event_repeat" tone="primary" cols="2">
        <x-input-field name="name" :label="__('import.ical_subscription.field_name')" required maxlength="120"
                       :value="old('name', $subscription->name)"
                       :placeholder="__('import.ical_subscription.field_name_placeholder')" />
        <x-select-field name="import_entity" :label="__('import.ical_subscription.field_entity')" required>
            @foreach ($entities as $entity)
                <option value="{{ $entity->value }}" @selected($selectedEntity === $entity->value)>{{ $entity->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="url" type="url" span="2" maxlength="2048" autocomplete="off" class="font-mono"
                       :required="! $isEdit"
                       :label="__('import.ical_subscription.field_url')"
                       :hint="$isEdit ? __('import.ical_subscription.field_url_keep') : __('import.ical_subscription.field_url_help')"
                       :value="old('url')"
                       placeholder="https://calendar.example.org/feed.ics" />
        <x-select-field name="user_id" span="2" :label="__('import.ical_subscription.field_user')" :hint="__('import.ical_subscription.field_user_help')">
            <option value="">{{ __('import.ical_subscription.field_user_any') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected($selectedUser === $user->sqid)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="category_allowlist" span="2" maxlength="600"
                       :label="__('import.ical_subscription.field_categories')"
                       :hint="__('import.ical_subscription.field_categories_help')"
                       :value="old('category_allowlist', implode(', ', (array) ($subscription->category_allowlist ?? [])))" />
        <x-checkbox-field name="allow_private_network" span="2" tone="warning"
                          :label="__('import.ical_subscription.field_private')"
                          :hint="__('import.ical_subscription.field_private_help')"
                          :checked="(bool) old('allow_private_network', $subscription->allow_private_network)" />
        <x-checkbox-field name="is_active" span="2"
                          :label="__('import.ical_subscription.field_active')"
                          :checked="(bool) old('is_active', $subscription->is_active ?? true)" />
    </x-form-group>

    @if ($isEdit)
        <x-slot:footerExtra>
            <x-action-form :action="route('admin.imports.ical-subscriptions.destroy', $subscription)"
                  method="DELETE"
                  :confirm="__('import.ical_subscription.delete_confirm', ['name' => $subscription->name])"
                  :confirm-label="__('import.ical_subscription.delete')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('import.ical_subscription.delete') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
