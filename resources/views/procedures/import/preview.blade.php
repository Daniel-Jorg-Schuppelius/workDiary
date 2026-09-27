{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : preview.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Vorschau des Imports (MVP-913): Schritte prüfen, anpassen, auswählen. --}}
@extends('layouts.app')

@section('title', __('procedure.import.title'))
@section('nav-title', __('procedure.import.title'))

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="__('procedure.import.title')" :subtitle="$source ?? __('procedure.import.from_text')">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('procedures.index')" :label="__('procedure.title.templates')" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <form method="POST" action="{{ route('procedures.import.store') }}" class="space-y-4">
        @csrf
        <x-card>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-input-field name="name" :label="__('procedure.import.name')" :value="old('name', $name)" required maxlength="180" />
                <x-input-field name="code" :label="__('procedure.import.code')" :value="old('code', $code)" required maxlength="60" />
            </div>
        </x-card>
        <x-card :title="__('procedure.import.steps')" :count="count($steps)">
            <p class="mb-2 text-xs text-muted">{{ __('procedure.import.check') }}</p>
            <div class="space-y-3">
                @foreach ($steps as $i => $step)
                    <div class="grid gap-2 rounded-box border border-base-300 p-3 sm:grid-cols-[auto_1fr_12rem]">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="hidden" name="steps[{{ $i }}][include]" value="0">
                            <input type="checkbox" name="steps[{{ $i }}][include]" value="1" class="checkbox checkbox-sm" @checked(old("steps.$i.include", '1') === '1') aria-label="{{ __('procedure.import.include') }}">
                            <span class="tabular-nums text-muted">{{ $i + 1 }}</span>
                        </label>
                        <div class="space-y-1">
                            <input type="text" name="steps[{{ $i }}][label]" value="{{ old("steps.$i.label", $step['label']) }}" maxlength="180" required class="input input-sm input-bordered w-full" aria-label="{{ __('procedure.import.label') }}">
                            <textarea name="steps[{{ $i }}][description]" rows="2" class="textarea textarea-sm textarea-bordered w-full" aria-label="{{ __('procedure.import.description_label') }}">{{ old("steps.$i.description", $step['description']) }}</textarea>
                        </div>
                        <select name="steps[{{ $i }}][step_type]" class="select select-sm select-bordered" aria-label="{{ __('procedure.library.type') }}">
                            @foreach (\App\Enums\Procedure\ProcedureStepType::cases() as $type)
                                <option value="{{ $type->value }}" @selected(old("steps.$i.step_type", $step['step_type']) === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </x-card>
        <x-validation-errors />
        <x-button type="submit" tone="primary">{{ __('procedure.import.create') }}</x-button>
    </form>
</x-page-shell>
@endsection
