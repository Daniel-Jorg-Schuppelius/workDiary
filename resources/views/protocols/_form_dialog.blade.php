{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Protokoll am Bezug anlegen (MVP-883) oder seine Stammdaten bearbeiten. --}}
@php
    $protocol ??= null;
    $subject ??= $protocol?->subject;
    $templates ??= collect();
@endphp
<x-modal
    :title="$protocol ? __('protocol.title.edit') : __('protocol.title.create')"
    :eyebrow="$subject ? \App\Support\EntityType::label($subject->getMorphClass()) . ': ' . ($subject->title ?? $subject->name ?? '') : null"
    icon="description"
    tone="primary"
    :action="$protocol ? route('protocols.update', $protocol) : route('protocols.store')"
    :method="$protocol ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$protocol ? __('Speichern') : __('protocol.action.create')">
    @unless ($protocol)
        <input type="hidden" name="subject_kind" value="{{ $subjectKind }}">
        <input type="hidden" name="subject_id" value="{{ $subject->sqid }}">
    @endunless
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
            <option value="{{ $type->value }}" @selected(old('type', $protocol?->type->value ?? \App\Enums\Protocol\ProtocolType::Service->value) === $type->value)>{{ $type->label() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="title" :label="__('protocol.field.title')" :value="old('title', $protocol?->title)" required maxlength="180" />
    <x-input-field name="occurred_at" type="datetime-local" :label="__('protocol.field.occurred_at')" :value="old('occurred_at', ($protocol?->occurred_at ?? now())->format('Y-m-d\TH:i'))" />
    <x-select-field name="visibility" :label="__('protocol.field.visibility')">
        @foreach (\App\Enums\Protocol\ProtocolVisibility::cases() as $visibility)
            <option value="{{ $visibility->value }}" @selected(old('visibility', $protocol?->visibility?->value ?? \App\Enums\Protocol\ProtocolVisibility::Internal->value) === $visibility->value)>{{ $visibility->label() }}</option>
        @endforeach
    </x-select-field>
    <div class="flex justify-end"><x-dictation-button target="#protocol-description" context="protocol" /></div>
    <x-textarea-field name="description" id="protocol-description" :label="__('protocol.field.description')" :value="old('description', $protocol?->description)" rows="3" />
    <x-textarea-field name="state_initial" :label="__('protocol.field.state_initial')" :value="old('state_initial', $protocol?->state_initial)" rows="2" />
    @if ($protocol)
        <x-textarea-field name="state_final" :label="__('protocol.field.state_final')" :value="old('state_final', $protocol->state_final)" rows="2" />
    @endif
</x-modal>
