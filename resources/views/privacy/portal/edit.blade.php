{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : edit.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('dsar.admin.title'))
@section('nav-title', __('dsar.admin.title'))

@section('content')
    <x-index-page :subtitle="__('dsar.admin.subtitle')">
        <x-validation-errors />

        @if ($portal->exists)
            <x-card>
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('dsar.admin.link') }}</h2>
                <div class="mt-2 space-y-2">
                    <p>
                        <code>{{ url('/datenschutz/anfrage/' . $portal->public_slug) }}</code>
                        <x-status-badge :tone="$portal->is_enabled ? 'success' : 'ghost'" size="sm">
                            {{ $portal->is_enabled ? __('aktiv') : __('inaktiv') }}
                        </x-status-badge>
                    </p>
                    <p class="text-sm text-base-content/70">{{ __('dsar.admin.link_hint') }}</p>
                    <form method="post" action="{{ route('dataprotection.portal.rotate') }}"
                          data-confirm-dialog
                          data-confirm-icon="autorenew"
                          data-confirm-tone="warning"
                          data-confirm-message="{{ __('dsar.admin.rotate_confirm') }}">
                        @csrf
                        <x-icon-btn icon="autorenew" tone="ghost" size="sm" type="submit" show-label>{{ __('dsar.admin.rotate') }}</x-icon-btn>
                    </form>
                </div>
            </x-card>
        @else
            <div class="alert">{{ __('dsar.admin.not_created') }}</div>
        @endif

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('dsar.admin.settings') }}</h2>
            <form method="post" action="{{ route('dataprotection.portal.update') }}" class="mt-2 space-y-3">
                @csrf
                @method('PUT')

                <x-form-group :legend="__('dsar.admin.visibility')" icon="visibility" tone="primary" cols="1">
                    <x-input-field name="is_enabled">
                        <label class="label cursor-pointer justify-start gap-3" for="is_enabled">
                            <input type="checkbox" id="is_enabled" name="is_enabled" value="1" class="checkbox checkbox-sm" @checked(old('is_enabled', $portal->is_enabled))>
                            <span class="fieldset-label">{{ __('dsar.admin.is_enabled') }}</span>
                        </label>
                    </x-input-field>
                    <x-input-field name="allow_attachments">
                        <label class="label cursor-pointer justify-start gap-3" for="allow_attachments">
                            <input type="checkbox" id="allow_attachments" name="allow_attachments" value="1" class="checkbox checkbox-sm" @checked(old('allow_attachments', $portal->allow_attachments ?? true))>
                            <span class="fieldset-label">{{ __('dsar.admin.allow_attachments') }}</span>
                        </label>
                    </x-input-field>
                </x-form-group>

                <x-form-group :legend="__('dsar.admin.presentation')" icon="tune" tone="ghost" cols="2">
                    <x-input-field name="intro_text" :label="__('dsar.admin.intro_text')" span="2">
                        <textarea id="intro_text" name="intro_text" rows="4" class="textarea textarea-bordered w-full">{{ old('intro_text', $portal->intro_text) }}</textarea>
                    </x-input-field>
                    <x-input-field name="default_locale" :label="__('dsar.admin.default_locale')" :value="old('default_locale', $portal->default_locale)" />
                </x-form-group>

                <x-icon-btn icon="save" tone="primary" size="sm" type="submit" show-label>{{ __('Speichern') }}</x-icon-btn>
            </form>
        </x-card>
    </x-index-page>
@endsection
