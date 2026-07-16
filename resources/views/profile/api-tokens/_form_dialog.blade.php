{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Variablen: $abilities (ApiAbility[]) --}}
@php
    /** @var array<int, \App\Enums\Api\ApiAbility> $abilities */
@endphp

<x-modal
    :title="__('Neuen API-Token erstellen')"
    :eyebrow="__('API-Tokens')"
    icon="key"
    tone="primary"
    size="md"
    :action="route('profile.api-tokens.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Erstellen')">

    <x-form-group :legend="__('Token')" icon="key" tone="primary" cols="1">
        <x-input-field name="name" :label="__('Token-Name')" required maxlength="64" :value="old('name')" />
    </x-form-group>

    {{-- Fähigkeiten (Feature 008): leer = voller Zugriff (`*`). --}}
    <x-form-group :legend="__('Fähigkeiten')" :description="__('Leer = voller Zugriff (*).')" icon="tune" tone="ghost" cols="1">
        <div class="grid gap-1 sm:grid-cols-2">
            @foreach ($abilities as $ability)
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="abilities[]" value="{{ $ability->value }}" class="checkbox checkbox-sm"
                           @checked(in_array($ability->value, old('abilities', []), true))>
                    <span>{{ $ability->label() }} <code class="text-xs opacity-60">{{ $ability->value }}</code></span>
                </label>
            @endforeach
        </div>
    </x-form-group>
</x-modal>
