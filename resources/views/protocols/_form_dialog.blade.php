{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Protokoll am Bezug anlegen (MVP-883). --}}
<x-modal
    :title="__('protocol.title.create')"
    :eyebrow="\App\Support\EntityType::label($subject->getMorphClass()) . ': ' . ($subject->title ?? $subject->name ?? '')"
    icon="description"
    tone="primary"
    :action="route('protocols.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('protocol.action.create')">
    <input type="hidden" name="subject_kind" value="{{ $subjectKind }}">
    <input type="hidden" name="subject_id" value="{{ $subject->sqid }}">
    @if ($templates->isNotEmpty())
        <x-select-field name="template_id" :label="__('protocol.template.choose')" :hint="__('protocol.template.choose_hint')">
            <option value="">{{ __('protocol.template.none') }}</option>
            @foreach ($templates as $template)
                <option value="{{ $template->sqid }}" @selected(old('template_id') === $template->sqid)>{{ $template->name }} ({{ $template->kind->label() }})</option>
            @endforeach
        </x-select-field>
    @endif
    <x-select-field name="type" :label="__('protocol.field.type')" required>
        @foreach (\App\Enums\Protocol\ProtocolType::cases() as $type)
            <option value="{{ $type->value }}" @selected(old('type', \App\Enums\Protocol\ProtocolType::Service->value) === $type->value)>{{ $type->label() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="title" :label="__('protocol.field.title')" :value="old('title')" required maxlength="180" />
    <x-input-field name="occurred_at" type="datetime-local" :label="__('protocol.field.occurred_at')" :value="old('occurred_at', now()->format('Y-m-d\TH:i'))" />
    <x-select-field name="visibility" :label="__('protocol.field.visibility')">
        @foreach (\App\Enums\Protocol\ProtocolVisibility::cases() as $visibility)
            <option value="{{ $visibility->value }}" @selected(old('visibility', \App\Enums\Protocol\ProtocolVisibility::Internal->value) === $visibility->value)>{{ $visibility->label() }}</option>
        @endforeach
    </x-select-field>
    <x-textarea-field name="description" :label="__('protocol.field.description')" :value="old('description')" rows="3" />
    <x-textarea-field name="state_initial" :label="__('protocol.field.state_initial')" :value="old('state_initial')" rows="2" />
</x-modal>
