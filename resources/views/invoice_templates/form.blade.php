{{--
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : form.blade.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('content')
@php $isEdit = (bool) $template->exists; @endphp
<x-page-shell gap="6">
    <x-page-toolbar :title="$isEdit ? __('Vorlage bearbeiten') : __('Neue Vorlage')">
        <x-slot:actions>
            <a href="{{ route('invoice-templates.index') }}" class="btn btn-secondary">{{ __('Zurück') }}</a>
        </x-slot:actions>
    </x-page-toolbar>

    <x-card>
        <form method="POST" action="{{ $isEdit ? route('invoice-templates.update', $template) : route('invoice-templates.store') }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <x-form-group :label="__('Name')" name="name" required>
                <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $template->name) }}" required maxlength="120">
            </x-form-group>

            <x-form-group :label="__('Slug')" name="slug" required :help="__('Nur a–z, 0–9, Bindestriche und Unterstriche.')">
                <input type="text" name="slug" id="slug" class="form-control" value="{{ old('slug', $template->slug) }}" required maxlength="64" pattern="[a-z0-9_-]+">
            </x-form-group>

            <x-form-group :label="__('Akzentfarbe')" name="accent_color" :help="__('Hex-Code, z. B. #2563eb.')">
                <input type="text" name="accent_color" id="accent_color" class="form-control" value="{{ old('accent_color', $template->accent_color) }}" maxlength="16">
            </x-form-group>

            <x-form-group :label="__('Kopftext')" name="header_text">
                <textarea name="header_text" id="header_text" class="form-control" rows="4" maxlength="2000">{{ old('header_text', $template->header_text) }}</textarea>
            </x-form-group>

            <x-form-group :label="__('Fußtext')" name="footer_text">
                <textarea name="footer_text" id="footer_text" class="form-control" rows="4" maxlength="2000">{{ old('footer_text', $template->footer_text) }}</textarea>
            </x-form-group>

            <x-form-group name="is_default">
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1" {{ old('is_default', $template->is_default) ? 'checked' : '' }}>
                    <span>{{ __('Als Standard-Vorlage verwenden') }}</span>
                </label>
            </x-form-group>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('Speichern') }}</button>
            </div>
        </form>
    </x-card>
</x-page-shell>
@endsection
