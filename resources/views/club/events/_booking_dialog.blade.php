{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _booking_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Ressource belegen (in #entry-modal geladen). Variablen: $event, $resources, $members, $formTz, $start, $end. --}}
<x-modal
    :title="__('club.resources.action.book')"
    :eyebrow="$event->title"
    icon="stadium"
    tone="primary"
    :action="route('club.events.resources.store', $event)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.resources.action.book')">
    <x-form-group :legend="__('club.resources.card.event')" icon="stadium" tone="primary" cols="2" :description="__('club.resources.hint.book')">
        <x-select-field name="club_resource_id" :label="__('club.resources.field.resource')" required span="2">
            <option value="">–</option>
            @foreach ($resources as $resource)
                <option value="{{ $resource->sqid }}" @selected(old('club_resource_id') === $resource->sqid)>{{ $resource->parent ? $resource->parent->name . ' › ' : '' }}{{ $resource->name }} ({{ $resource->kind->label() }}@if ($resource->capacity > 1), {{ __('club.resources.label.capacity_of', ['count' => $resource->capacity]) }}@endif)</option>
            @endforeach
        </x-select-field>
        <x-input-field name="quantity" type="number" min="1" max="999" :label="__('club.resources.field.quantity')" :value="old('quantity', 1)" :hint="__('club.resources.hint.quantity')" />
        <x-select-field name="club_member_id" :label="__('club.field.member')" :hint="__('club.resources.hint.booking_member')">
            <option value="">–</option>
            @foreach ($members as $member)
                <option value="{{ $member->sqid }}" @selected(old('club_member_id') === $member->sqid)>{{ $member->last_name }}, {{ $member->first_name }} ({{ $member->member_no }})</option>
            @endforeach
        </x-select-field>
        <x-date-range class="md:col-span-2" layout="split" form-control type="datetime-local" from-name="starts_at" to-name="ends_at"
                      :from-label="__('club.events.field.starts')" :to-label="__('club.events.field.ends')"
                      :from="old('starts_at', $start)" :to="old('ends_at', $end)" />
        <input type="hidden" name="timezone" value="{{ old('timezone', $formTz) }}">
        <x-input-field name="setup_minutes" type="number" min="0" max="600" :label="__('club.resources.field.setup_minutes')" :value="old('setup_minutes')" :hint="__('club.resources.hint.buffer_default')" />
        <x-input-field name="teardown_minutes" type="number" min="0" max="600" :label="__('club.resources.field.teardown_minutes')" :value="old('teardown_minutes')" />
        <x-input-field name="note" :label="__('club.field.notes')" maxlength="255" span="2" :value="old('note')" />
    </x-form-group>
</x-modal>
