{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _capability_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Capability-Routing je Organisation (Feature 025, MVP-400).
     Reihenfolge der Checkbox-Liste = Fallback-Kette. --}}
<x-modal
    :title="__('ai.capability.title', ['capability' => $definition->key])"
    icon="tune"
    tone="primary"
    :action="route('admin.ai.capability.update', ['capability' => $definition->key])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('ai.capability.submit')"
>
    <x-form-group :legend="__('ai.capability.legend')" icon="tune" tone="primary" cols="1">
        <div class="text-sm text-base-content/70">
            {{ __('ai.field.verb') }}: <strong>{{ $definition->verb->label() }}</strong> ·
            {{ __('ai.field.sensitivity') }}: <strong>{{ $definition->sensitivity->label() }}</strong> ·
            {{ __('ai.preview.prompt_version') }}: <strong>v{{ $definition->promptVersion }}</strong>
        </div>

        <div class="fieldset">
            <label class="label cursor-pointer justify-start gap-3">
                <input type="checkbox" name="enabled" value="1" class="checkbox checkbox-sm"
                       @checked(old('enabled', $setting?->enabled ?? false))>
                <span class="label-text">{{ __('ai.capability.enable') }}</span>
            </label>
            <p class="text-xs text-base-content/60">{{ __('ai.capability.enable_help') }}</p>
        </div>

        <div class="fieldset">
            <span class="fieldset-label">{{ __('ai.capability.allowed') }}</span>
            @forelse ($connections as $connection)
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="checkbox" name="allowed_connection_ids[]" value="{{ $connection->sqid }}"
                           class="checkbox checkbox-sm"
                           @checked(in_array((int) $connection->id, array_map('intval', (array) ($setting?->allowed_connection_ids ?? [])), true))>
                    <span class="label-text">
                        {{ $connection->name }}
                        <span class="badge badge-{{ $connection->is_local ? 'success' : 'warning' }} badge-xs align-middle">
                            {{ $connection->is_local ? __('ai.field.local') : __('ai.field.cloud') }}
                        </span>
                    </span>
                </label>
            @empty
                <p class="text-sm italic text-base-content/50">{{ __('ai.empty.connections') }}</p>
            @endforelse
            <p class="text-xs text-base-content/60">{{ __('ai.capability.allowed_help') }}</p>
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ai-cap-default">{{ __('ai.field.default') }}</label>
            <select id="ai-cap-default" name="default_connection_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($connections as $connection)
                    <option value="{{ $connection->sqid }}"
                            @selected((string) old('default_connection_id', \App\Support\Sqid::encode(\App\Models\Ai\AiProviderConnection::class, $setting?->default_connection_id)) === $connection->sqid)>
                        {{ $connection->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="fieldset">
            <label class="label cursor-pointer justify-start gap-3">
                <input type="checkbox" name="allow_user_choice" value="1" class="checkbox checkbox-sm"
                       @checked(old('allow_user_choice', $setting?->allow_user_choice ?? false))>
                <span class="label-text">{{ __('ai.capability.user_choice') }}</span>
            </label>
            <p class="text-xs text-base-content/60">{{ __('ai.capability.user_choice_help') }}</p>
        </div>
    </x-form-group>
</x-modal>
