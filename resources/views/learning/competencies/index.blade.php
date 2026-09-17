{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kompetenzmatrix (Feature 149 — Oberfläche MVP-798, Befund C3-03).
  Personen × Kompetenzen mit erreichter Stufe; Lücken gegen die Soll-Stufe der
  Rolle. Die Kompetenz sperrt nichts — Sperren bleiben bei der Qualifikation.
  Variablen: $users (Paginator), $people, $matrix, $gaps, $catalog, $requirements
--}}
@extends('layouts.app')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('title', __('learning.title.competencies'))
@section('nav-title', __('learning.title.competencies'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('learning.subtitle.competencies')">
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm" data-open-dialog="competency-create"
                            show-label>{{ __('learning.action.create_competency') }}</x-icon-btn>
                @if ($catalog->isNotEmpty())
                    <x-icon-btn icon="grading" tone="ghost" size="sm" data-open-dialog="competency-assess"
                                show-label>{{ __('learning.action.assess_competency') }}</x-icon-btn>
                    <x-icon-btn icon="flag" tone="ghost" size="sm" data-open-dialog="competency-requirement"
                                show-label>{{ __('learning.action.set_required_level') }}</x-icon-btn>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Vor der Matrix: nach einer Voll-Höhe-Tabelle läge sichtbarer Inhalt unter dem Fold (R5). --}}
    <div class="grid flex-none gap-4 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-3 text-sm font-semibold">{{ __('learning.title.competency_catalog') }}</h3>
            <div class="max-h-48 overflow-y-auto">
                <x-table :bare="true" size="xs">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('learning.field.short_code') }}</th>
                            <th>{{ __('learning.field.name') }}</th>
                            <th>{{ __('learning.field.category') }}</th>
                            <th class="text-right">{{ __('learning.field.max_level') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($catalog as $competency)
                        <tr>
                            <td class="font-mono text-xs">{{ $competency->code }}</td>
                            <td>{{ $competency->name }}</td>
                            <td>{{ $competency->category ?? '—' }}</td>
                            <td class="text-right">{{ $competency->max_level }}</td>
                        </tr>
                    @empty
                        <x-table.empty icon="psychology" :message="__('learning.empty.competencies')" :colspan="4" compact />
                    @endforelse
                </x-table>
            </div>
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold">{{ __('learning.title.competency_requirements') }}</h3>
            <div class="max-h-48 overflow-y-auto">
                <x-table :bare="true" size="xs">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('learning.field.competency') }}</th>
                            <th>{{ __('learning.field.role') }}</th>
                            <th class="text-right">{{ __('learning.field.required_level') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($requirements as $requirement)
                        <tr>
                            <td>{{ $requirement->competency?->name ?? '—' }}</td>
                            <td>{{ \App\Enums\User\UserRole::tryFrom($requirement->subject_key)?->label() ?? $requirement->subject_key }}</td>
                            <td class="text-right">{{ $requirement->required_level }}</td>
                        </tr>
                    @empty
                        <x-table.empty icon="flag" :message="__('learning.empty.competency_requirements')" :colspan="3" compact />
                    @endforelse
                </x-table>
            </div>
        </x-card>
    </div>

    <x-table scroll="flex" hover :caption="__('learning.title.competencies')">
        <x-slot:head>
            <tr>
                <th>{{ __('learning.field.person') }}</th>
                @foreach ($matrix['competencies'] as $competency)
                    <th class="text-center" title="{{ $competency->description }}">
                        {{ $competency->name }}
                        <span class="block text-xs font-normal text-muted">{{ __('learning.field.max_level_short', ['max' => $competency->max_level]) }}</span>
                    </th>
                @endforeach
                <th class="text-right">{{ __('learning.field.gaps') }}</th>
            </tr>
        </x-slot:head>

        @if ($matrix['competencies'] === [])
            <x-table.empty icon="psychology" :message="__('learning.empty.competencies')" :colspan="2" compact />
        @else
            @forelse ($matrix['rows'] as $row)
                @php $rowGaps = $gaps[$row['user']->id] ?? []; @endphp
                <tr>
                    <td>{{ $row['user']->name }}</td>
                    @foreach ($matrix['competencies'] as $competency)
                        @php $gap = $rowGaps[$competency->id] ?? null; @endphp
                        <td class="text-center">
                            @if ($gap !== null)
                                <span class="badge badge-warning badge-sm" title="{{ __('learning.help.competency_gap', ['actual' => $gap['actual'], 'required' => $gap['required']]) }}">{{ $gap['actual'] }} / {{ $gap['required'] }}</span>
                            @else
                                {{ $row['levels'][$competency->id] ?? '—' }}
                            @endif
                        </td>
                    @endforeach
                    <td class="text-right">{{ count($rowGaps) }}</td>
                </tr>
            @empty
                <x-table.empty icon="group" :message="__('learning.empty.competency_people')" :colspan="count($matrix['competencies']) + 2" compact />
            @endforelse
        @endif
    </x-table>

    <x-pagination :paginator="$users" standing />

</x-page-shell>

<x-modal id="competency-create" :embedded="false" tone="primary" icon="psychology"
    :eyebrow="__('learning.title.competencies')" :title="__('learning.action.create_competency')"
    :action="route('learning.competencies.store')"
    :submit-label="__('learning.action.create_competency')">
    <x-form-group :legend="__('learning.field.competency')" icon="psychology" tone="primary" cols="2">
        <x-input-field name="code" :label="__('learning.field.short_code')" required maxlength="60" :value="old('code')" />
        <x-input-field name="name" :label="__('learning.field.name')" required minlength="2" maxlength="180" :value="old('name')" />
        <x-input-field name="category" :label="__('learning.field.category')" maxlength="60" :value="old('category')" />
        <x-input-field name="max_level" type="number" min="1" max="10" required :label="__('learning.field.max_level')"
                       :hint="__('learning.help.max_level')" :value="old('max_level', 4)" />
        <x-textarea-field name="description" :label="__('learning.field.description')" rows="2" span="2" maxlength="2000" :value="old('description')" />
    </x-form-group>
</x-modal>

@if ($catalog->isNotEmpty())
    <x-modal id="competency-assess" :embedded="false" tone="primary" icon="grading"
        :eyebrow="__('learning.title.competencies')" :title="__('learning.action.assess_competency')"
        :action="route('learning.competencies.assess')"
        :submit-label="__('learning.action.save')">
        <x-form-group :legend="__('learning.action.assess_competency')" :description="__('learning.help.assessment')" icon="grading" tone="primary" cols="2">
            <x-select-field name="user_id" :label="__('learning.field.person')" required>
                <option value="">—</option>
                @foreach ($people as $person)
                    <option value="{{ $person->sqid }}">{{ $person->name }}</option>
                @endforeach
            </x-select-field>
            <x-select-field name="competency_id" id="competency-assess-competency" :label="__('learning.field.competency')" required>
                <option value="">—</option>
                @foreach ($catalog as $competency)
                    <option value="{{ $competency->sqid }}">{{ $competency->name }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="level" type="number" min="1" max="10" required :label="__('learning.field.level')" />
            <x-input-field name="note" :label="__('learning.field.note')" maxlength="500" />
        </x-form-group>
    </x-modal>

    <x-modal id="competency-requirement" :embedded="false" tone="primary" icon="flag"
        :eyebrow="__('learning.title.competencies')" :title="__('learning.action.set_required_level')"
        :action="route('learning.competencies.requirements.store')"
        :submit-label="__('learning.action.save')">
        <x-form-group :legend="__('learning.action.set_required_level')" :description="__('learning.help.required_level')" icon="flag" tone="primary" cols="2">
            <x-select-field name="competency_id" id="competency-requirement-competency" :label="__('learning.field.competency')" required span="2">
                <option value="">—</option>
                @foreach ($catalog as $competency)
                    <option value="{{ $competency->sqid }}">{{ $competency->name }}</option>
                @endforeach
            </x-select-field>
            <x-select-field name="role" :label="__('learning.field.role')" required>
                @foreach (\App\Enums\User\UserRole::cases() as $role)
                    <option value="{{ $role->value }}">{{ $role->label() }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="required_level" type="number" min="1" max="10" required :label="__('learning.field.required_level')" />
        </x-form-group>
    </x-modal>
@endif
@endsection
