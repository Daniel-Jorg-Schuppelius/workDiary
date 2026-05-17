{{-- Organisation-Settings (Pagination, Invoicing, Uploads, Validation, Notifications, UI). --}}
@php
    /** @var \App\Models\Organization|null $organization */
    $stored = (array) ($organization?->settings ?? []);

    // Helper: render a single override-input. Leerer Wert = systemweiter Default.
    $renderField = static function (string $name, string $label, mixed $default, string $type = 'number', array $attrs = []) use ($stored) {
        $segments = explode('.', $name);
        $value = data_get($stored, $name, '');
        $oldName = 'settings.' . $name;
        $defaultText = trans('settings.placeholder_default', ['value' => (string) $default]);
        $attrString = '';
        foreach ($attrs as $k => $v) {
            $attrString .= ' ' . e($k) . '="' . e((string) $v) . '"';
        }
        $nameAttr = 'settings[' . implode('][', $segments) . ']';
        return new \Illuminate\Support\HtmlString(<<<HTML
            <div class="fieldset">
                <label class="fieldset-label">{$label}</label>
                <input type="{$type}" name="{$nameAttr}" class="input input-bordered w-full"
                       value="{{old}}"
                       placeholder="{$defaultText}"{$attrString}>
            </div>
        HTML);
    };
@endphp

<div class="divider">{{ __('settings.tabs.pagination') }}</div>

<div role="tablist" class="tabs tabs-lifted" x-data="{ tab: 'pagination' }">
    @php
        $tabs = [
            'pagination' => __('settings.tabs.pagination'),
            'invoicing' => __('settings.tabs.invoicing'),
            'uploads' => __('settings.tabs.uploads'),
            'validation' => __('settings.tabs.validation'),
            'notifications' => __('settings.tabs.notifications'),
            'ui' => __('settings.tabs.ui'),
        ];
    @endphp
    @foreach ($tabs as $key => $label)
        <a role="tab" class="tab" :class="tab === '{{ $key }}' ? 'tab-active' : ''"
           @click.prevent="tab = '{{ $key }}'" href="#">{{ $label }}</a>
    @endforeach
</div>

<p class="text-xs opacity-70 mt-2">{{ __('settings.hint') }}</p>

{{-- PAGINATION --}}
<div x-show="tab === 'pagination'" x-cloak class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
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
</div>

{{-- INVOICING --}}
<div x-show="tab === 'invoicing'" x-cloak class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
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
</div>

{{-- UPLOADS --}}
<div x-show="tab === 'uploads'" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    @foreach ((array) config('uploads') as $k => $v)
        <div class="fieldset">
            <label class="fieldset-label">{{ __('settings.uploads.' . $k) }}</label>
            <input type="number" min="1" name="settings[uploads][{{ $k }}]"
                   value="{{ old('settings.uploads.' . $k, data_get($stored, 'uploads.' . $k, '')) }}"
                   placeholder="{{ __('settings.placeholder_default', ['value' => (string) $v]) }}"
                   class="input input-bordered w-full">
        </div>
    @endforeach
</div>

{{-- VALIDATION --}}
<div x-show="tab === 'validation'" x-cloak class="mt-4 space-y-4">
    @foreach ((array) config('validation') as $group => $fields)
        <div>
            <h4 class="font-semibold text-sm mb-2">{{ __('settings.validation.' . $group . '.heading') }}</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
            </div>
        </div>
    @endforeach
</div>

{{-- NOTIFICATIONS --}}
<div x-show="tab === 'notifications'" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('settings.notifications.push.body_truncate') }}</label>
        <input type="number" min="20" max="500"
               name="settings[notifications][push][body_truncate]"
               value="{{ old('settings.notifications.push.body_truncate', data_get($stored, 'notifications.push.body_truncate', '')) }}"
               placeholder="{{ __('settings.placeholder_default', ['value' => (string) config('notifications.push.body_truncate')]) }}"
               class="input input-bordered w-full">
    </div>
</div>

{{-- UI --}}
<div x-show="tab === 'ui'" x-cloak class="mt-4 space-y-4">
    @foreach ((array) config('ui') as $group => $fields)
        <div>
            <h4 class="font-semibold text-sm mb-2">{{ __('settings.ui.' . $group . '.heading') }}</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
            </div>
        </div>
    @endforeach
</div>
