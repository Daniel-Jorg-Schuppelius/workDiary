{{-- Dialog: Kostenstellen-Regel anlegen/bearbeiten (Rang 35) --}}
@php
    /** @var \App\Models\CostCenterRule $rule */
    $isEdit = $rule->exists;
    $sourceValue = old('source', $rule->user_id !== null ? 'user' : ($rule->team_id !== null ? 'team' : 'default'));
@endphp
<x-modal
    :title="$isEdit ? __('costcenter.title.edit_rule') : __('costcenter.title.create_rule')"
    icon="account_balance"
    tone="primary"
    :action="$isEdit ? route('admin.cost-center-rules.update', $rule) : route('admin.cost-center-rules.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('costcenter.action.save') : __('costcenter.action.create')"
>
    {{-- Quell-Umschaltung via Alpine.data("reveal") (components.js) — CSP-Build-konform. --}}
    <x-form-group :legend="__('costcenter.field.basics')" icon="account_balance" tone="primary" cols="2"
                  x-data="reveal(@js($sourceValue))">
        <div class="fieldset">
            <label class="fieldset-label" for="ccr-source">{{ __('costcenter.field.source') }}</label>
            <select id="ccr-source" name="source" class="select select-bordered w-full" required x-model="value">
                <option value="default">{{ __('costcenter.field.source_default') }}</option>
                <option value="user">{{ __('costcenter.field.source_user') }}</option>
                <option value="team">{{ __('costcenter.field.source_team') }}</option>
            </select>
            <p class="text-xs text-base-content/60">{{ __('costcenter.field.source_help') }}</p>
            @error('source')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <x-input-field name="cost_center" :label="__('costcenter.field.cost_center')" required maxlength="32"
                       :value="old('cost_center', $rule->cost_center)"
                       class="font-mono"
                       placeholder="4711" />

        <div class="fieldset" x-show="is('user')" x-cloak>
            <label class="fieldset-label" for="ccr-user">{{ __('costcenter.field.user') }}</label>
            <select id="ccr-user" name="user_id" class="select select-bordered w-full">
                <option value="">{{ __('costcenter.field.choose') }}</option>
                @foreach ($users as $u)
                    <option value="{{ $u['sqid'] }}" @selected(old('user_id', $rule->user?->sqid) === $u['sqid'])>{{ $u['label'] }}</option>
                @endforeach
            </select>
            @error('user_id')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset" x-show="is('team')" x-cloak>
            <label class="fieldset-label" for="ccr-team">{{ __('costcenter.field.team') }}</label>
            <select id="ccr-team" name="team_id" class="select select-bordered w-full">
                <option value="">{{ __('costcenter.field.choose') }}</option>
                @foreach ($teams as $t)
                    <option value="{{ $t['sqid'] }}" @selected(old('team_id', $rule->team?->sqid) === $t['sqid'])>{{ $t['label'] }}</option>
                @endforeach
            </select>
            @error('team_id')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ccr-priority">{{ __('costcenter.field.priority') }}</label>
            <input id="ccr-priority" type="number" name="priority" required min="0" max="1000"
                   value="{{ old('priority', $rule->priority ?? 0) }}"
                   class="input input-bordered w-full tabular-nums">
            <p class="text-xs text-base-content/60">{{ __('costcenter.field.priority_help') }}</p>
            @error('priority')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    @if ($isEdit)
        <x-slot:footerExtra>
            <x-action-form :action="route('admin.cost-center-rules.destroy', $rule)"
                  method="DELETE"
                  :confirm="__('costcenter.action.delete_confirm')"
                  :confirm-label="__('costcenter.action.delete')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('costcenter.action.delete') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
