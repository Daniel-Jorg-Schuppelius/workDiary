{{--
  Created on   : Sun Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : edit.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Prozedurvorlagen-Designer (Feature 026): Stammdaten + Versions-Metadaten
  + Schritt-Editor (dynamische Zeilen über die Alpine-`repeater`-Komponente,
  Muster: FormTemplate-Designer). Schritte sind nur in einer Draft-Version
  editierbar; veröffentlichte Versionen werden read-only dargestellt.
--}}
@extends('layouts.app')

@section('title', $template->name . ' — ' . __('procedure.title.designer'))
@section('nav-title', __('procedure.title.designer'))

@php
    /** @var \App\Models\ProcedureTemplate $template */
    /** @var \App\Models\ProcedureTemplateVersion|null $draft */
    $applicability = $draft?->applicability ?? [];
    $entryTypesVal = old('applicability_entry_types', implode(', ', (array) ($applicability['diary_entry_type'] ?? [])));
    $tagsVal = old('applicability_tags', implode(', ', (array) ($applicability['tags_any'] ?? [])));

    $stepTemplate = [
        'code' => '',
        'step_type' => \App\Enums\Procedure\ProcedureStepType::Confirm->value,
        'label' => '',
        'description' => '',
        'required' => true,
        'blocking' => true,
        'requires_second_person' => false,
        'requires_proof_type' => '',
        'required_role' => '',
        'required_qualification_code' => '',
        'condition_step' => '',
        'condition_equals' => '',
    ];

    $stepItems = old('steps', $draft
        ? $draft->steps->map(fn($s) => [
            'code' => $s->code,
            'step_type' => $s->step_type->value,
            'label' => $s->label,
            'description' => (string) ($s->description ?? ''),
            'required' => (bool) $s->required,
            'blocking' => (bool) $s->blocking,
            'requires_second_person' => (bool) $s->requires_second_person,
            'requires_proof_type' => $s->requires_proof_type?->value ?? '',
            'required_role' => (string) ($s->required_role ?? ''),
            'required_qualification_code' => (string) ($s->required_qualification_code ?? ''),
            'condition_step' => (string) (data_get($s->config, 'depends_on.step_code') ?? ''),
            'condition_equals' => (string) (data_get($s->config, 'depends_on.equals') ?? ''),
        ])->values()->all()
        : []);
@endphp

@section('content')
    <x-page-shell>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h1 class="text-lg font-semibold">{{ $template->name }}</h1>
                <p class="text-sm text-muted"><code>{{ $template->code }}</code></p>
            </div>
            <div class="flex items-center gap-2">
                <x-help-button topic="procedures.designer" :label="__('procedure.help.designer')" />
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" show-label
                            :href="route('procedures.index')">{{ __('procedure.action.backToList') }}</x-icon-btn>
            </div>
        </div>

        {{-- Versionsübersicht --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-muted">{{ __('procedure.title.versions') }}</h2>
            <div class="flex flex-wrap gap-2">
                @foreach ($versions as $v)
                    <div class="flex items-center gap-2 rounded-box border border-base-300 px-3 py-1.5 text-sm">
                        <span class="font-medium">v{{ $v->version }}</span>
                        @if ($v->published_at)
                            <x-status-badge tone="success">{{ __('procedure.status.published') }}</x-status-badge>
                            <span class="text-xs text-muted">{{ optional($v->valid_from)->format('Y-m-d') }}{{ $v->valid_to ? ' – ' . $v->valid_to->format('Y-m-d') : '' }}</span>
                        @else
                            <x-status-badge tone="warning">{{ __('procedure.status.draft') }}</x-status-badge>
                            @if ($canPublish)
                                <x-action-form :action="route('procedures.versions.publish', [$template, $v])"
                                      data-confirm-title="{{ __('procedure.action.publish') }}"
                                      :confirm="__('procedure.confirm.publish')"
                                      confirm-icon="publish"
                                      confirm-tone="primary"
                                      :confirm-label="__('procedure.action.publish')">
                                    <x-icon-btn icon="publish" tone="primary" size="xs" type="submit"
                                                :label="__('procedure.action.publish')" />
                                </x-action-form>
                            @endif
                        @endif
                    </div>
                @endforeach

                @if ($draft === null)
                    <form method="POST" action="{{ route('procedures.versions.store', $template) }}">
                        @csrf
                        <x-icon-btn icon="add" tone="outline" size="sm" type="submit" show-label>{{ __('procedure.action.newVersion') }}</x-icon-btn>
                    </form>
                @endif
            </div>
        </div>

        @if ($draft === null)
            <div class="rounded-box border border-warning/40 bg-warning/10 p-4 text-sm">
                {{ __('procedure.notice.noDraft') }}
            </div>
        @endif

        <form method="POST" action="{{ route('procedures.update', $template) }}">
            @csrf
            @method('PUT')

            {{-- Stammdaten --}}
            <x-form-group :legend="__('procedure.title.template')" icon="rule" tone="primary" cols="2">
                <x-input-field name="name" :label="__('procedure.field.name')" required minlength="3" maxlength="180" span="2" :value="old('name', $template->name)" />
                <x-input-field name="domain" :label="__('procedure.field.domain')" maxlength="40" :value="old('domain', $template->domain)" />
                <x-select-field name="risk_level" :label="__('procedure.field.riskLevel')" :disabled="$draft === null">
                    @foreach ($riskLevels as $level)
                        <option value="{{ $level->value }}" @selected(old('risk_level', $draft?->risk_level?->value) === $level->value)>{{ $level->label() }}</option>
                    @endforeach
                </x-select-field>
                <x-textarea-field name="description" :label="__('procedure.field.description')" rows="2" maxlength="2000" span="2" :value="old('description', $template->description)" />
            </x-form-group>

            {{-- Anwendbarkeit (automatische Zuordnung) --}}
            <x-form-group :legend="__('procedure.title.applicability')" icon="filter_alt" tone="info" cols="2">
                <p class="text-xs text-muted sm:col-span-2">{{ __('procedure.hint.applicability') }}</p>
                <x-input-field name="applicability_entry_types" :label="__('procedure.field.applicabilityEntryTypes')" maxlength="2000" placeholder="{{ __('procedure.hint.commaList') }}" :value="$entryTypesVal" :disabled="$draft === null" />
                <x-input-field name="applicability_tags" :label="__('procedure.field.applicabilityTags')" maxlength="2000" placeholder="{{ __('procedure.hint.commaList') }}" :value="$tagsVal" :disabled="$draft === null" />
                <x-input-field name="change_note" :label="__('procedure.field.changeNote')" maxlength="2000" span="2" :value="old('change_note', $draft?->change_note)" :disabled="$draft === null" />
            </x-form-group>

            {{-- Schritte --}}
            <x-form-group :legend="__('procedure.title.steps')" icon="checklist" tone="success">
                @error('steps')
                    <p class="text-error text-sm sm:col-span-2">{{ $message }}</p>
                @enderror

                @if ($draft === null)
                    <div class="space-y-2 sm:col-span-2">
                        @foreach (($versions->firstWhere('published_at', '!=', null)?->steps ?? collect()) as $s)
                            <div class="rounded-box border border-base-300 bg-base-200/40 p-3 text-sm">
                                <span class="font-medium">{{ $s->sort_order }}. {{ $s->label }}</span>
                                <span class="ml-2 text-xs text-muted">{{ $s->step_type->label() }}</span>
                                @if ($s->required)<x-status-badge tone="error" class="ml-1">{{ __('procedure.field.required') }}</x-status-badge>@endif
                                @if ($s->requires_second_person)<x-status-badge tone="warning" class="ml-1">{{ __('procedure.field.secondPerson') }}</x-status-badge>@endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div x-data="repeater"
                         data-prefix="steps"
                         data-items="{{ json_encode($stepItems) }}"
                         data-template="{{ json_encode($stepTemplate) }}"
                         class="space-y-3 sm:col-span-2">
                        <template x-for="(it, i) in items" :key="i">
                            <div class="space-y-2 rounded-box border border-base-300 bg-base-200/40 p-3">
                                <div class="grid grid-cols-1 items-end gap-2 sm:grid-cols-[auto_1fr_1fr_auto]">
                                    <div class="fieldset">
                                        <label :for="fieldName(i, 'code')" class="fieldset-label">{{ __('procedure.field.code') }}</label>
                                        <input type="text" maxlength="60"
                                               :id="fieldName(i, 'code')" :name="fieldName(i, 'code')" x-model="it.code"
                                               class="input input-sm input-bordered w-28 font-mono">
                                    </div>
                                    <div class="fieldset">
                                        <label :for="fieldName(i, 'label')" class="fieldset-label">{{ __('procedure.field.stepLabel') }}</label>
                                        <input type="text" maxlength="180"
                                               :id="fieldName(i, 'label')" :name="fieldName(i, 'label')" x-model="it.label"
                                               class="input input-sm input-bordered w-full">
                                    </div>
                                    <div class="fieldset">
                                        <label :for="fieldName(i, 'step_type')" class="fieldset-label">{{ __('procedure.field.stepType') }}</label>
                                        <select :id="fieldName(i, 'step_type')" :name="fieldName(i, 'step_type')" x-model="it.step_type"
                                                class="select select-sm select-bordered">
                                            @foreach ($stepTypes as $type)
                                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <x-icon-btn icon="close" tone="error" size="xs" type="button"
                                                :label="__('procedure.action.removeStep')" @click="remove(i)" />
                                </div>

                                <div class="fieldset">
                                    <label :for="fieldName(i, 'description')" class="fieldset-label">{{ __('procedure.field.stepDescription') }}</label>
                                    <input type="text" maxlength="2000"
                                           :id="fieldName(i, 'description')" :name="fieldName(i, 'description')" x-model="it.description"
                                           class="input input-sm input-bordered w-full">
                                </div>

                                <div class="flex flex-wrap items-center gap-4">
                                    <label class="label cursor-pointer gap-2">
                                        <input type="checkbox" class="checkbox checkbox-sm"
                                               :name="fieldName(i, 'required')" x-model="it.required">
                                        <span class="label-text text-xs">{{ __('procedure.field.required') }}</span>
                                    </label>
                                    <label class="label cursor-pointer gap-2">
                                        <input type="checkbox" class="checkbox checkbox-sm"
                                               :name="fieldName(i, 'blocking')" x-model="it.blocking">
                                        <span class="label-text text-xs">{{ __('procedure.field.blocking') }}</span>
                                    </label>
                                    <label class="label cursor-pointer gap-2">
                                        <input type="checkbox" class="checkbox checkbox-sm"
                                               :name="fieldName(i, 'requires_second_person')" x-model="it.requires_second_person">
                                        <span class="label-text text-xs">{{ __('procedure.field.secondPerson') }}</span>
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                    <div class="fieldset">
                                        <label :for="fieldName(i, 'requires_proof_type')" class="fieldset-label">{{ __('procedure.field.proofType') }}</label>
                                        <select :id="fieldName(i, 'requires_proof_type')" :name="fieldName(i, 'requires_proof_type')" x-model="it.requires_proof_type"
                                                class="select select-sm select-bordered">
                                            <option value="">{{ __('procedure.field.none') }}</option>
                                            @foreach ($proofTypes as $proof)
                                                <option value="{{ $proof->value }}">{{ $proof->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="fieldset">
                                        <label :for="fieldName(i, 'required_role')" class="fieldset-label">{{ __('procedure.field.requiredRole') }}</label>
                                        <input type="text" maxlength="40"
                                               :id="fieldName(i, 'required_role')" :name="fieldName(i, 'required_role')" x-model="it.required_role"
                                               class="input input-sm input-bordered w-full">
                                    </div>
                                    <div class="fieldset">
                                        <label :for="fieldName(i, 'required_qualification_code')" class="fieldset-label">{{ __('procedure.field.requiredQualification') }}</label>
                                        <input type="text" maxlength="60"
                                               :id="fieldName(i, 'required_qualification_code')" :name="fieldName(i, 'required_qualification_code')" x-model="it.required_qualification_code"
                                               class="input input-sm input-bordered w-full">
                                    </div>
                                </div>

                                {{-- Bedingter Schritt (wenn-dann), additiv in config.depends_on --}}
                                <div class="grid grid-cols-1 gap-2 rounded-box border border-dashed border-base-300 p-2 sm:grid-cols-2">
                                    <p class="text-xs text-muted sm:col-span-2">{{ __('procedure.hint.condition') }}</p>
                                    <div class="fieldset">
                                        <label :for="fieldName(i, 'condition_step')" class="fieldset-label">{{ __('procedure.field.conditionStep') }}</label>
                                        <input type="text" maxlength="60"
                                               placeholder="{{ __('procedure.hint.conditionStep') }}"
                                               :id="fieldName(i, 'condition_step')" :name="fieldName(i, 'condition_step')" x-model="it.condition_step"
                                               class="input input-sm input-bordered w-full font-mono">
                                    </div>
                                    <div class="fieldset">
                                        <label :for="fieldName(i, 'condition_equals')" class="fieldset-label">{{ __('procedure.field.conditionEquals') }}</label>
                                        <input type="text" maxlength="120"
                                               placeholder="{{ __('procedure.hint.conditionEquals') }}"
                                               :id="fieldName(i, 'condition_equals')" :name="fieldName(i, 'condition_equals')" x-model="it.condition_equals"
                                               class="input input-sm input-bordered w-full">
                                    </div>
                                </div>
                            </div>
                        </template>

                        <x-icon-btn icon="add" tone="ghost" size="sm" type="button" show-label @click="add()">
                            {{ __('procedure.action.addStep') }}
                        </x-icon-btn>
                    </div>
                @endif
            </x-form-group>

            @if ($draft !== null)
                <div class="flex justify-end gap-2">
                    <x-icon-btn icon="save" tone="primary" size="sm" type="submit" show-label>{{ __('procedure.action.save') }}</x-icon-btn>
                </div>
            @endif
        </form>

        {{-- Rezeptur / Materialbedarf (MVP-455) — eigenständige Formulare. --}}
        @include('procedures.templates._recipe')
    </x-page-shell>
@endsection
