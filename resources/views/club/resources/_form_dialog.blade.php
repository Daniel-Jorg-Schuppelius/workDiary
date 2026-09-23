{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Ressource anlegen/bearbeiten (in #entry-modal geladen). Variablen: $resource|null, $parents, $rooms, $assets, $kindHints. --}}
@php
    $isEdit = $resource !== null;
    $selectedParent = old('parent_id', $resource?->parent_id ? \App\Support\Sqid::encode(\App\Models\Club\ClubResource::class, $resource->parent_id) : '');
    $selectedRoom = old('room_id', $resource?->room_id ? \App\Support\Sqid::encode(\App\Models\Room::class, $resource->room_id) : '');
    $selectedAsset = old('asset_id', $resource?->asset_id ? \App\Support\Sqid::encode(\App\Models\Asset::class, $resource->asset_id) : '');
@endphp
<x-modal
    :title="$isEdit ? __('club.action.edit') : __('club.resources.action.create')"
    :eyebrow="__('club.resources.title.index')"
    icon="stadium"
    tone="primary"
    size="wide"
    :action="$isEdit ? route('club.resources.update', $resource) : route('club.resources.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">
    <x-form-group :legend="__('club.card.master_data')" icon="stadium" tone="primary" cols="2" :description="__('club.resources.hint.form')">
        <x-input-field name="name" :label="__('club.field.name')" required maxlength="120" :value="old('name', $resource?->name)" />
        <x-select-field name="kind" :label="__('club.resources.field.kind')" required>
            @foreach (\App\Enums\Club\ClubResourceKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind', $resource?->kind->value ?? 'hall') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="parent_id" :label="__('club.resources.field.parent')" :hint="__('club.resources.hint.parent')">
            <option value="">–</option>
            @foreach ($parents as $parent)
                <option value="{{ $parent->sqid }}" @selected((string) $selectedParent === $parent->sqid)>{{ $parent->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="capacity" type="number" min="1" max="999" :label="__('club.resources.field.capacity')" :value="old('capacity', $resource?->capacity ?? 1)" :hint="__('club.resources.hint.capacity')" />
        <x-select-field name="room_id" :label="__('club.resources.field.room')" :hint="__('club.resources.hint.room')">
            <option value="">–</option>
            @foreach ($rooms as $room)
                <option value="{{ $room->sqid }}" @selected((string) $selectedRoom === $room->sqid)>{{ $room->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="asset_id" :label="__('club.resources.field.asset')" :hint="__('club.resources.hint.asset')">
            <option value="">–</option>
            @foreach ($assets as $asset)
                <option value="{{ $asset->sqid }}" @selected((string) $selectedAsset === $asset->sqid)>{{ $asset->name }} ({{ $asset->asset_no }})</option>
            @endforeach
        </x-select-field>
        <x-input-field name="setup_minutes" type="number" min="0" max="600" :label="__('club.resources.field.setup_minutes')" :value="old('setup_minutes', $resource?->setup_minutes ?? 0)" />
        <x-input-field name="teardown_minutes" type="number" min="0" max="600" :label="__('club.resources.field.teardown_minutes')" :value="old('teardown_minutes', $resource?->teardown_minutes ?? 0)" />
        <x-checkbox-field name="requires_clearance" :label="__('club.resources.field.requires_clearance')" :checked="(bool) old('requires_clearance', $resource?->requires_clearance ?? false)" />
        <x-checkbox-field name="is_active" :label="__('club.field.is_active')" :checked="(bool) old('is_active', $resource?->is_active ?? true)" />
        <x-input-field name="sort_order" type="number" min="0" max="9999" :label="__('club.field.sort_order')" :value="old('sort_order', $resource?->sort_order ?? 0)" />
        @if ($kindHints->isNotEmpty())
            <p class="text-xs text-muted md:col-span-1 self-end">{{ __('club.resources.hint.kind_hints', ['types' => $kindHints->implode(', ')]) }}</p>
        @endif
        <x-textarea-field name="notes" :label="__('club.field.notes')" rows="2" maxlength="2000" span="2" :value="old('notes', $resource?->notes)" />
    </x-form-group>
</x-modal>
