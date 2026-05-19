{{-- Organisation-Settings (Pagination, Invoicing, Uploads, Validation, Notifications, UI). --}}
@php
    /** @var \App\Models\Organization|null $organization */
    $stored = (array) ($organization?->settings ?? []);

    $tabs = [
        'pagination' => ['icon' => 'view_list', 'tone' => 'info', 'label' => __('settings.tabs.pagination')],
        'invoicing' => ['icon' => 'receipt_long', 'tone' => 'success', 'label' => __('settings.tabs.invoicing')],
        'uploads' => ['icon' => 'upload_file', 'tone' => 'warning', 'label' => __('settings.tabs.uploads')],
        'validation' => ['icon' => 'rule', 'tone' => 'error', 'label' => __('settings.tabs.validation')],
        'notifications' => ['icon' => 'notifications', 'tone' => 'primary', 'label' => __('settings.tabs.notifications')],
        'ui' => ['icon' => 'tune', 'tone' => 'ghost', 'label' => __('settings.tabs.ui')],
    ];
@endphp

<x-form-group :legend="__('Erweiterte Einstellungen')" icon="settings" tone="ghost" cols="1"
              :description="__('settings.hint')"
              x-data="{ tab: 'pagination' }">
    <div role="tablist" class="tabs tabs-lifted mb-2">
        @foreach ($tabs as $key => $meta)
            <a role="tab" class="tab" :class="tab === '{{ $key }}' ? 'tab-active' : ''"
               @click.prevent="tab = '{{ $key }}'" href="#">{{ $meta['label'] }}</a>
        @endforeach
    </div>

    {{-- PAGINATION --}}
    <div x-show="tab === 'pagination'" x-cloak>
        <x-form-group :legend="__('settings.tabs.pagination')" :icon="$tabs['pagination']['icon']" :tone="$tabs['pagination']['tone']" cols="3" compact>
            @foreach ((array) config('pagination') as $k => $v)
                <div class="fieldset">
                    <label class="fieldset-label">{{ __('settings.pagination.' . $k) }}</label>
                    <input type="number" min="1" max="500"
                           name="settings[pagination][{{ $k }}]"
                           value="{{ old('settings.pagination.' . $k, data_get($stored, 'pagination.' . $k, '')) }}"
                           placeholder="{{ __('settings.placeholder_default', ['value' => (string) $v]) }}"
                           class="input input-bordered w-full">
                </div>
            @endforeach
        </x-form-group>
    </div>

    {{-- INVOICING --}}
    <div x-show="tab === 'invoicing'" x-cloak>
        <x-form-group :legend="__('settings.tabs.invoicing')" :icon="$tabs['invoicing']['icon']" :tone="$tabs['invoicing']['tone']" cols="3" compact>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.invoicing.default_tax_rate') }}</label>
                <input type="text" name="settings[invoicing][default_tax_rate]"
                       value="{{ old('settings.invoicing.default_tax_rate', data_get($stored, 'invoicing.default_tax_rate', '')) }}"
                       placeholder="{{ __('settings.placeholder_default', ['value' => (string) config('invoicing.default_tax_rate')]) }}"
                       class="input input-bordered w-full" inputmode="decimal">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.invoicing.default_currency') }}</label>
                <input type="text" maxlength="3" name="settings[invoicing][default_currency]"
                       value="{{ old('settings.invoicing.default_currency', data_get($stored, 'invoicing.default_currency', '')) }}"
                       placeholder="{{ __('settings.placeholder_default', ['value' => (string) config('invoicing.default_currency')]) }}"
                       class="input input-bordered w-full uppercase">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.invoicing.time_unit') }}</label>
                <input type="text" maxlength="8" name="settings[invoicing][time_unit]"
                       value="{{ old('settings.invoicing.time_unit', data_get($stored, 'invoicing.time_unit', '')) }}"
                       placeholder="{{ __('settings.placeholder_default', ['value' => (string) config('invoicing.time_unit')]) }}"
                       class="input input-bordered w-full">
            </div>
        </x-form-group>
    </div>

    {{-- UPLOADS --}}
    <div x-show="tab === 'uploads'" x-cloak>
        <x-form-group :legend="__('settings.tabs.uploads')" :icon="$tabs['uploads']['icon']" :tone="$tabs['uploads']['tone']" cols="2" compact>
            @foreach ((array) config('uploads') as $k => $v)
                <div class="fieldset">
                    <label class="fieldset-label">{{ __('settings.uploads.' . $k) }}</label>
                    <input type="number" min="1" name="settings[uploads][{{ $k }}]"
                           value="{{ old('settings.uploads.' . $k, data_get($stored, 'uploads.' . $k, '')) }}"
                           placeholder="{{ __('settings.placeholder_default', ['value' => (string) $v]) }}"
                           class="input input-bordered w-full">
                </div>
            @endforeach
        </x-form-group>
    </div>

    {{-- VALIDATION --}}
    <div x-show="tab === 'validation'" x-cloak class="space-y-4">
        @foreach ((array) config('validation') as $group => $fields)
            <x-form-group :legend="__('settings.validation.' . $group . '.heading')" :icon="$tabs['validation']['icon']" :tone="$tabs['validation']['tone']" cols="3" compact>
                @foreach ((array) $fields as $field => $val)
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('settings.validation.' . $group . '.' . $field) }}</label>
                        <input type="number" min="1"
                               name="settings[validation][{{ $group }}][{{ $field }}]"
                               value="{{ old('settings.validation.' . $group . '.' . $field, data_get($stored, 'validation.' . $group . '.' . $field, '')) }}"
                               placeholder="{{ __('settings.placeholder_default', ['value' => (string) $val]) }}"
                               class="input input-bordered w-full">
                    </div>
                @endforeach
            </x-form-group>
        @endforeach
    </div>

    {{-- NOTIFICATIONS --}}
    <div x-show="tab === 'notifications'" x-cloak>
        <x-form-group :legend="__('settings.tabs.notifications')" :icon="$tabs['notifications']['icon']" :tone="$tabs['notifications']['tone']" cols="2" compact>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.notifications.push.body_truncate') }}</label>
                <input type="number" min="20" max="500"
                       name="settings[notifications][push][body_truncate]"
                       value="{{ old('settings.notifications.push.body_truncate', data_get($stored, 'notifications.push.body_truncate', '')) }}"
                       placeholder="{{ __('settings.placeholder_default', ['value' => (string) config('notifications.push.body_truncate')]) }}"
                       class="input input-bordered w-full">
            </div>
        </x-form-group>
    </div>

    {{-- UI --}}
    <div x-show="tab === 'ui'" x-cloak class="space-y-4">
        @foreach ((array) config('ui') as $group => $fields)
            <x-form-group :legend="__('settings.ui.' . $group . '.heading')" :icon="$tabs['ui']['icon']" :tone="$tabs['ui']['tone']" cols="3" compact>
                @foreach ((array) $fields as $field => $val)
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('settings.ui.' . $group . '.' . $field) }}</label>
                        <input type="number" min="1"
                               name="settings[ui][{{ $group }}][{{ $field }}]"
                               value="{{ old('settings.ui.' . $group . '.' . $field, data_get($stored, 'ui.' . $group . '.' . $field, '')) }}"
                               placeholder="{{ __('settings.placeholder_default', ['value' => (string) $val]) }}"
                               class="input input-bordered w-full">
                    </div>
                @endforeach
            </x-form-group>
        @endforeach
    </div>
</x-form-group>
