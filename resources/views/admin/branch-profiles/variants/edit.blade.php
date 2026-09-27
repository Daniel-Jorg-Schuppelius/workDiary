{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : edit.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Profilvariante bearbeiten (MVP-933). Erwartet: $variant, $options, $additions, $installed --}}
@extends('layouts.app')

@section('title', $variant->label)
@section('nav-title', __('branch_profile.variant.title'))

@section('content')
<x-index-page :subtitle="__('branch_profile.variant.subtitle', ['base' => $variant->base_code, 'version' => $variant->version])">
    <x-slot:actions>
        <x-icon-btn icon="download" size="sm" :href="route('admin.branch-profile-variants.export', $variant)" show-label>{{ __('branch_profile.variant.export') }}</x-icon-btn>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('admin.branch-profiles.index')" show-label>{{ __('Branchenprofile') }}</x-icon-btn>
    </x-slot:actions>

    <x-card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm">
                @if ($installed)
                    {{ __('branch_profile.variant.installed', ['code' => $installed['code'] ?? '', 'version' => $installed['version'] ?? '']) }}
                @else
                    {{ __('branch_profile.variant.not_installed') }}
                @endif
            </p>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('admin.branch-profile-variants.install', $variant) }}" class="flex items-center gap-2">
                    @csrf
                    <x-checkbox-field name="force" :label="__('branch_profile.variant.force')" />
                    <x-button type="submit" size="sm">{{ __('branch_profile.variant.install') }}</x-button>
                </form>
                <form method="POST" action="{{ route('admin.branch-profile-variants.destroy', $variant) }}" data-confirm-dialog data-confirm-message="{{ __('branch_profile.variant.confirm_delete') }}" data-confirm-tone="error">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('branch_profile.variant.delete') }}</button>
                </form>
            </div>
        </div>
    </x-card>

    <form method="POST" action="{{ route('admin.branch-profile-variants.update', $variant) }}" class="mt-4 flex flex-col gap-4">
        @csrf
        @method('PUT')
        <x-card>
            <x-form-group :legend="__('branch_profile.variant.title')" icon="tune" tone="primary" cols="2">
                <x-input-field name="label" :label="__('branch_profile.variant.field.label')" :value="old('label', $variant->label)" required />
                <x-input-field name="code" :label="__('branch_profile.variant.field.code')" :value="$variant->code" disabled />
                <x-textarea-field name="description" :label="__('branch_profile.variant.field.description')" rows="2" span="2">{{ old('description', $variant->description) }}</x-textarea-field>
            </x-form-group>
        </x-card>

        <x-card :title="__('branch_profile.variant.removals')">
            <p class="mb-3 text-sm opacity-70">{{ __('branch_profile.variant.hint.removals') }}</p>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($options as $section => $rows)
                    <fieldset class="rounded-box border border-base-300 p-3">
                        <legend class="px-1 text-sm font-semibold">{{ __('branch_profile.variant.section.' . $section) }}</legend>
                        @foreach ($rows as $row)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" class="checkbox checkbox-sm" name="removals[{{ $section }}][]" value="{{ $row['key'] }}" @checked(in_array($row['key'], (array) ($variant->removals[$section] ?? []), true))>
                                <span>{{ $row['label'] }}</span>
                            </label>
                        @endforeach
                    </fieldset>
                @endforeach
            </div>
        </x-card>

        <x-card :title="__('branch_profile.variant.additions')">
            <x-textarea-field name="additions" :label="__('branch_profile.variant.field.additions')" rows="10" class="font-mono" :hint="__('branch_profile.variant.hint.additions')">{{ old('additions', $additions) }}</x-textarea-field>
        </x-card>

        <div class="flex justify-end"><x-button type="submit">{{ __('branch_profile.variant.save') }}</x-button></div>
    </form>
</x-index-page>
@endsection
