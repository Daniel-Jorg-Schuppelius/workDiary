{{--
  Created on   : Sat Jul 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $isDialog, optional $map (bearbeiten) --}}
@php
    $isDialog = $isDialog ?? false;
    $editing = isset($map);
@endphp

<x-modal
    :title="$editing ? __('ideas.action.edit') : __('ideas.action.create')"
    :eyebrow="__('ideas.title.index')"
    icon="emoji_objects"
    tone="primary"
    :action="$editing ? route('ideas.update', $map) : route('ideas.store')"
    :method="$editing ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$editing ? __('Speichern') : __('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url"
               value="{{ ($editing ? route('ideas.edit', $map) : route('ideas.create')) . '?dialog=1' }}">
    @endif

    <x-form-group :legend="__('ideas.legend.map')" icon="emoji_objects" tone="primary" cols="1">
        <x-input-field name="title" :label="__('ideas.col.title')" required maxlength="191"
                       :value="old('title', $editing ? $map->title : '')" />
        <x-textarea-field name="description" :label="__('ideas.col.description')" rows="3" maxlength="2000"
                          :value="old('description', $editing ? $map->description : '')" />
    </x-form-group>

    <x-form-group :legend="__('ideas.legend.context')" icon="link" tone="primary" cols="2">
        <x-select-field name="customer" :label="__('ideas.context.customer')">
            <option value="">—</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->sqid }}" @selected($editing && $map->customer_id === $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="project" :label="__('ideas.context.project')">
            <option value="">—</option>
            @foreach ($projects as $project)
                <option value="{{ $project->sqid }}" @selected($editing && $map->project_id === $project->id)>{{ $project->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>
    <p class="text-xs opacity-60">{{ __('ideas.privacy_hint') }}</p>
</x-modal>
