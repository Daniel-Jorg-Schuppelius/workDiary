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
        'routing' => ['icon' => 'route', 'tone' => 'info', 'label' => __('settings.tabs.routing')],
        'travel' => ['icon' => 'local_shipping', 'tone' => 'success', 'label' => __('Anfahrt')],
        'region' => ['icon' => 'public', 'tone' => 'info', 'label' => __('settings.tabs.region')],
        'weather' => ['icon' => 'partly_cloudy_day', 'tone' => 'info', 'label' => __('settings.tabs.weather')],
        'maintenance' => ['icon' => 'engineering', 'tone' => 'warning', 'label' => __('settings.tabs.maintenance')],
    ];
@endphp

<x-form-group :legend="__('Erweiterte Einstellungen')" icon="settings" tone="ghost" cols="1"
              :description="__('settings.hint')"
              x-data="tabs('pagination')">
    <div role="tablist" class="tabs tabs-box mb-2">
        @foreach ($tabs as $key => $meta)
            <a role="tab" class="tab" :class="tabClass('{{ $key }}')"
               @click.prevent="setTab('{{ $key }}')" href="#">{{ $meta['label'] }}</a>
        @endforeach
    </div>

    {{-- PAGINATION --}}
    <div x-show="isTab('pagination')" x-cloak>
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
    <div x-show="isTab('invoicing')" x-cloak class="space-y-4">
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
                <select name="settings[invoicing][default_currency]" class="select select-bordered w-full">
                    <x-currency-options :selected="old('settings.invoicing.default_currency', data_get($stored, 'invoicing.default_currency', ''))" nullable :null-label="__('settings.placeholder_default', ['value' => (string) config('invoicing.default_currency')])" />
                </select>
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.invoicing.time_unit') }}</label>
                <input type="text" maxlength="8" name="settings[invoicing][time_unit]"
                       value="{{ old('settings.invoicing.time_unit', data_get($stored, 'invoicing.time_unit', '')) }}"
                       placeholder="{{ __('settings.placeholder_default', ['value' => (string) config('invoicing.time_unit')]) }}"
                       class="input input-bordered w-full">
            </div>

            @can(\App\Enums\User\Permission::FinanceConfig->value)
                {{-- Fakturierungsweg (Feature 045): Org-Default, Kunden können übersteuern. --}}
                <div class="fieldset">
                    <label class="fieldset-label">{{ __('finance.field.billing_mode') }}</label>
                    <select name="settings[billing_mode]" class="select select-bordered w-full">
                        <option value="">{{ __('finance.field.billing_mode_default') }}</option>
                        @foreach (\App\Enums\Finance\BillingMode::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('settings.billing_mode', data_get($stored, 'billing_mode', '')) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-base-content/60 mt-1">{{ __('finance.field.billing_mode_org_hint') }}</p>
                </div>
            @endcan
        </x-form-group>

        {{-- E-RECHNUNG (Feature 045, Abschnitt 8): Verkäuferstammdaten für XRechnung (EN 16931). --}}
        <x-form-group :legend="__('settings.einvoice.heading')" icon="receipt" tone="info" cols="3" compact
                      :description="__('settings.einvoice.description')">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.einvoice.seller_name') }}</label>
                <input type="text" maxlength="200" name="settings[einvoice][seller_name]"
                       value="{{ old('settings.einvoice.seller_name', data_get($stored, 'einvoice.seller_name', '')) }}"
                       placeholder="{{ __('settings.placeholder_default', ['value' => (string) ($organization?->name ?? '')]) }}"
                       class="input input-bordered w-full">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.einvoice.street') }}</label>
                <input type="text" maxlength="255" name="settings[einvoice][street]"
                       value="{{ old('settings.einvoice.street', data_get($stored, 'einvoice.street', '')) }}"
                       class="input input-bordered w-full">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.einvoice.zip') }}</label>
                <input type="text" maxlength="32" name="settings[einvoice][zip]"
                       value="{{ old('settings.einvoice.zip', data_get($stored, 'einvoice.zip', '')) }}"
                       class="input input-bordered w-full">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.einvoice.city') }}</label>
                <input type="text" maxlength="128" name="settings[einvoice][city]"
                       value="{{ old('settings.einvoice.city', data_get($stored, 'einvoice.city', '')) }}"
                       class="input input-bordered w-full">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.einvoice.country') }}</label>
                <input type="text" maxlength="2" name="settings[einvoice][country]"
                       value="{{ old('settings.einvoice.country', data_get($stored, 'einvoice.country', '')) }}"
                       placeholder="{{ __('settings.placeholder_default', ['value' => 'DE']) }}"
                       class="input input-bordered w-full uppercase">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.einvoice.vat_id') }}</label>
                <input type="text" maxlength="64" name="settings[einvoice][vat_id]"
                       value="{{ old('settings.einvoice.vat_id', data_get($stored, 'einvoice.vat_id', '')) }}"
                       placeholder="DE123456789" class="input input-bordered w-full">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.einvoice.tax_number') }}</label>
                <input type="text" maxlength="64" name="settings[einvoice][tax_number]"
                       value="{{ old('settings.einvoice.tax_number', data_get($stored, 'einvoice.tax_number', '')) }}"
                       class="input input-bordered w-full">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.einvoice.contact_name') }}</label>
                <input type="text" maxlength="200" name="settings[einvoice][contact_name]"
                       value="{{ old('settings.einvoice.contact_name', data_get($stored, 'einvoice.contact_name', '')) }}"
                       class="input input-bordered w-full">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.einvoice.contact_email') }}</label>
                <input type="email" maxlength="255" name="settings[einvoice][contact_email]"
                       value="{{ old('settings.einvoice.contact_email', data_get($stored, 'einvoice.contact_email', '')) }}"
                       class="input input-bordered w-full">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.einvoice.contact_phone') }}</label>
                <input type="text" maxlength="64" name="settings[einvoice][contact_phone]"
                       value="{{ old('settings.einvoice.contact_phone', data_get($stored, 'einvoice.contact_phone', '')) }}"
                       class="input input-bordered w-full">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.einvoice.iban') }}</label>
                <input type="text" maxlength="64" name="settings[einvoice][iban]"
                       value="{{ old('settings.einvoice.iban', data_get($stored, 'einvoice.iban', '')) }}"
                       class="input input-bordered w-full uppercase">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.einvoice.bic') }}</label>
                <input type="text" maxlength="32" name="settings[einvoice][bic]"
                       value="{{ old('settings.einvoice.bic', data_get($stored, 'einvoice.bic', '')) }}"
                       class="input input-bordered w-full uppercase">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.einvoice.account_holder') }}</label>
                <input type="text" maxlength="200" name="settings[einvoice][account_holder]"
                       value="{{ old('settings.einvoice.account_holder', data_get($stored, 'einvoice.account_holder', '')) }}"
                       class="input input-bordered w-full">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.einvoice.payment_terms_days') }}</label>
                <input type="number" min="0" max="365" name="settings[einvoice][payment_terms_days]"
                       value="{{ old('settings.einvoice.payment_terms_days', data_get($stored, 'einvoice.payment_terms_days', '')) }}"
                       placeholder="{{ __('settings.placeholder_default', ['value' => '14']) }}"
                       class="input input-bordered w-full">
            </div>
            <div class="fieldset md:col-span-2">
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="hidden" name="settings[einvoice][small_business]" value="0">
                    <input type="checkbox" name="settings[einvoice][small_business]" value="1" class="toggle toggle-info"
                           @checked((string) old('settings.einvoice.small_business', data_get($stored, 'einvoice.small_business', '0')) === '1')>
                    <span class="label-text">{{ __('settings.einvoice.small_business') }}</span>
                </label>
                <p class="text-xs text-base-content/60 mt-1">{{ __('settings.einvoice.small_business_hint') }}</p>
            </div>
        </x-form-group>
    </div>

    {{-- UPLOADS --}}
    <div x-show="isTab('uploads')" x-cloak>
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
    <div x-show="isTab('validation')" x-cloak class="space-y-4">
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
    <div x-show="isTab('notifications')" x-cloak>
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
    <div x-show="isTab('ui')" x-cloak class="space-y-4">
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

    {{-- ROUTING --}}
    <div x-show="isTab('routing')" x-cloak class="space-y-4">
        <x-form-group :legend="__('settings.routing.nominatim.heading')" :icon="$tabs['routing']['icon']" :tone="$tabs['routing']['tone']" cols="2" compact>
            <div class="fieldset md:col-span-2">
                <label class="fieldset-label">{{ __('settings.routing.nominatim.base_url') }}</label>
                <input type="text" name="settings[routing][nominatim][base_url]"
                       value="{{ old('settings.routing.nominatim.base_url', data_get($stored, 'routing.nominatim.base_url', '')) }}"
                       placeholder="{{ __('settings.placeholder_default', ['value' => (string) config('routing.nominatim.base_url')]) }}"
                       class="input input-bordered w-full" inputmode="url">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.routing.nominatim.email') }}</label>
                <input type="email" name="settings[routing][nominatim][email]"
                       value="{{ old('settings.routing.nominatim.email', data_get($stored, 'routing.nominatim.email', '')) }}"
                       placeholder="{{ __('settings.placeholder_default', ['value' => (string) config('routing.nominatim.email')]) }}"
                       class="input input-bordered w-full">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.routing.nominatim.rate_limit_per_sec') }}</label>
                <input type="number" min="1" max="50" name="settings[routing][nominatim][rate_limit_per_sec]"
                       value="{{ old('settings.routing.nominatim.rate_limit_per_sec', data_get($stored, 'routing.nominatim.rate_limit_per_sec', '')) }}"
                       placeholder="{{ __('settings.placeholder_default', ['value' => (string) config('routing.nominatim.rate_limit_per_sec')]) }}"
                       class="input input-bordered w-full">
            </div>
        </x-form-group>

        <x-form-group :legend="__('settings.routing.osrm.heading')" :icon="$tabs['routing']['icon']" :tone="$tabs['routing']['tone']" cols="2" compact>
            <div class="fieldset md:col-span-2">
                <label class="fieldset-label">{{ __('settings.routing.osrm.base_url') }}</label>
                <input type="text" name="settings[routing][osrm][base_url]"
                       value="{{ old('settings.routing.osrm.base_url', data_get($stored, 'routing.osrm.base_url', '')) }}"
                       placeholder="{{ __('settings.placeholder_default', ['value' => (string) config('routing.osrm.base_url')]) }}"
                       class="input input-bordered w-full" inputmode="url">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.routing.osrm.profile') }}</label>
                <input type="text" maxlength="32" name="settings[routing][osrm][profile]"
                       value="{{ old('settings.routing.osrm.profile', data_get($stored, 'routing.osrm.profile', '')) }}"
                       placeholder="{{ __('settings.placeholder_default', ['value' => (string) config('routing.osrm.profile')]) }}"
                       class="input input-bordered w-full">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.routing.osrm.timeout') }}</label>
                <input type="number" min="1" max="120" name="settings[routing][osrm][timeout]"
                       value="{{ old('settings.routing.osrm.timeout', data_get($stored, 'routing.osrm.timeout', '')) }}"
                       placeholder="{{ __('settings.placeholder_default', ['value' => (string) config('routing.osrm.timeout')]) }}"
                       class="input input-bordered w-full">
            </div>
        </x-form-group>

        <x-form-group :legend="__('settings.routing.tiles.heading')" :icon="$tabs['routing']['icon']" :tone="$tabs['routing']['tone']" cols="2" compact>
            <div class="fieldset md:col-span-2">
                <label class="fieldset-label">{{ __('settings.routing.tiles.url') }}</label>
                <input type="text" name="settings[routing][tiles][url]"
                       value="{{ old('settings.routing.tiles.url', data_get($stored, 'routing.tiles.url', '')) }}"
                       placeholder="{{ __('settings.placeholder_default', ['value' => (string) config('routing.tiles.url')]) }}"
                       class="input input-bordered w-full" inputmode="url">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.routing.tiles.max_zoom') }}</label>
                <input type="number" min="1" max="22" name="settings[routing][tiles][max_zoom]"
                       value="{{ old('settings.routing.tiles.max_zoom', data_get($stored, 'routing.tiles.max_zoom', '')) }}"
                       placeholder="{{ __('settings.placeholder_default', ['value' => (string) config('routing.tiles.max_zoom')]) }}"
                       class="input input-bordered w-full">
            </div>
        </x-form-group>
    </div>

    {{-- ANFAHRT / TRAVEL --}}
    <div x-show="isTab('travel')" x-cloak
         x-data="travelSettings({{ (string) old('settings.travel.enabled', data_get($stored, 'travel.enabled', '0')) === '1' ? 'true' : 'false' }}, @js(old('settings.travel.mode', data_get($stored, 'travel.mode', 'flat'))), @js(old('settings.travel.km_source', data_get($stored, 'travel.km_source', 'company'))), {{ (string) old('settings.travel.round_trip', data_get($stored, 'travel.round_trip', '1')) !== '0' ? 'true' : 'false' }})">
        <x-form-group :legend="__('Anfahrt-Abrechnung')" icon="local_shipping" tone="success" cols="2" compact
                      :description="__('Bei einer Tour zum Kunden an einem Tag wird bei Projekt- oder Materialabrechnung automatisch eine Anfahrt berechnet.')">
            <div class="fieldset md:col-span-2">
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="hidden" name="settings[travel][enabled]" :value="enabledValue">
                    <input type="checkbox" class="toggle toggle-success" x-model="enabled">
                    <span class="label-text">{{ __('Anfahrt automatisch berechnen') }}</span>
                </label>
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Modus') }}</label>
                <select name="settings[travel][mode]" class="select select-bordered w-full" x-model="mode">
                    <option value="flat">{{ __('Pauschale') }}</option>
                    <option value="km">{{ __('Kilometer') }}</option>
                </select>
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Positionstext') }}</label>
                <input type="text" maxlength="50" name="settings[travel][label]"
                       value="{{ old('settings.travel.label', data_get($stored, 'travel.label', '')) }}"
                       placeholder="Anfahrt" class="input input-bordered w-full">
            </div>

            <div class="fieldset" x-show="isMode('flat')">
                <label class="fieldset-label">{{ __('Pauschale (netto €)') }}</label>
                <input type="number" step="0.01" min="0" name="settings[travel][flat_amount]"
                       value="{{ old('settings.travel.flat_amount', data_get($stored, 'travel.flat_amount', '')) }}"
                       class="input input-bordered w-full">
            </div>

            <template x-if="isMode('km')">
                <div class="contents">
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Satz (€/km)') }}</label>
                        <input type="number" step="0.01" min="0" name="settings[travel][rate_per_km]"
                               value="{{ old('settings.travel.rate_per_km', data_get($stored, 'travel.rate_per_km', '')) }}"
                               class="input input-bordered w-full">
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Kilometer-Quelle') }}</label>
                        <select name="settings[travel][km_source]" class="select select-bordered w-full" x-model="kmSource">
                            <option value="company">{{ __('Immer vom Firmenstandort') }}</option>
                            <option value="tour">{{ __('Je nach Tour (tatsächliche km)') }}</option>
                        </select>
                    </div>
                    <div class="fieldset md:col-span-2">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="hidden" name="settings[travel][round_trip]" :value="roundTripValue">
                            <input type="checkbox" class="toggle toggle-success" x-model="roundTrip">
                            <span class="label-text">{{ __('Hin- und Rückfahrt (×2, nur Firmenstandort)') }}</span>
                        </label>
                    </div>
                    <div class="fieldset" x-show="isKmSource('company')">
                        <label class="fieldset-label">{{ __('Firmenstandort Breite (lat)') }}</label>
                        <input type="number" step="0.0000001" min="-90" max="90" name="settings[travel][origin_lat]"
                               value="{{ old('settings.travel.origin_lat', data_get($stored, 'travel.origin_lat', '')) }}"
                               class="input input-bordered w-full">
                    </div>
                    <div class="fieldset" x-show="isKmSource('company')">
                        <label class="fieldset-label">{{ __('Firmenstandort Länge (lng)') }}</label>
                        <input type="number" step="0.0000001" min="-180" max="180" name="settings[travel][origin_lng]"
                               value="{{ old('settings.travel.origin_lng', data_get($stored, 'travel.origin_lng', '')) }}"
                               class="input input-bordered w-full">
                    </div>
                </div>
            </template>
        </x-form-group>
    </div>

    {{-- REGION / FEIERTAGE (Feature 034) --}}
    <div x-show="isTab('region')" x-cloak class="space-y-4">
        <x-form-group :legend="__('settings.region.heading')" icon="public" tone="info" cols="2" compact
                      :description="__('settings.region.description')">
            <div class="fieldset md:col-span-2">
                <label class="fieldset-label">{{ __('settings.region.holiday_provider') }}</label>
                <select name="settings[holidays][provider]" class="select select-bordered w-full">
                    <option value="">{{ __('settings.placeholder_default', ['value' => \App\Support\HolidayRegions::label((string) config('holidays.provider', 'Germany'))]) }}</option>
                    @foreach (\App\Support\HolidayRegions::grouped() as $group => $providers)
                        <optgroup label="{{ $group }}">
                            @foreach ($providers as $value => $label)
                                <option value="{{ $value }}"
                                    @selected((string) old('settings.holidays.provider', data_get($stored, 'holidays.provider', '')) === $value)>{{ $label }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <p class="fieldset-label text-base-content/60">{{ __('settings.region.holiday_provider_hint') }}</p>
            </div>
        </x-form-group>
    </div>

    {{-- WETTER / WEATHER (Feature 062, Rang 12) --}}
    <div x-show="isTab('weather')" x-cloak>
        <x-form-group :legend="__('settings.weather.heading')" icon="partly_cloudy_day" tone="info" cols="1" compact
                      :description="__('settings.weather.description')">
            <div class="fieldset">
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="hidden" name="settings[weather][auto_fetch]" value="0">
                    <input type="checkbox" name="settings[weather][auto_fetch]" value="1" class="toggle toggle-info"
                           @checked((string) old('settings.weather.auto_fetch', data_get($stored, 'weather.auto_fetch', '0')) === '1')>
                    <span class="label-text">{{ __('settings.weather.auto_fetch') }}</span>
                </label>
                <p class="fieldset-label text-base-content/60">{{ __('settings.weather.auto_fetch_hint') }}</p>
            </div>
        </x-form-group>
    </div>

    {{-- WARTUNGSMODUS (Rang 65) --}}
    <div x-show="isTab('maintenance')" x-cloak>
        <x-form-group :legend="__('settings.maintenance.heading')" icon="engineering" tone="warning" cols="2" compact
                      :description="__('settings.maintenance.description')">
            <div class="fieldset md:col-span-2">
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="hidden" name="settings[maintenance][enabled]" value="0">
                    <input type="checkbox" name="settings[maintenance][enabled]" value="1" class="toggle toggle-warning"
                           @checked((string) old('settings.maintenance.enabled', data_get($stored, 'maintenance.enabled', '0')) === '1')>
                    <span class="label-text">{{ __('settings.maintenance.enabled') }}</span>
                </label>
            </div>
            <div class="fieldset md:col-span-2">
                <label class="fieldset-label">{{ __('settings.maintenance.message') }}</label>
                <input type="text" maxlength="300"
                       name="settings[maintenance][message]"
                       value="{{ old('settings.maintenance.message', data_get($stored, 'maintenance.message', '')) }}"
                       placeholder="{{ __('settings.maintenance.message_placeholder') }}"
                       class="input input-bordered w-full">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('settings.maintenance.until') }}</label>
                <input type="datetime-local"
                       name="settings[maintenance][until]"
                       value="{{ old('settings.maintenance.until', data_get($stored, 'maintenance.until', '')) }}"
                       class="input input-bordered w-full">
                <p class="fieldset-label text-base-content/60">{{ __('settings.maintenance.until_hint') }}</p>
            </div>
            <div class="fieldset">
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="hidden" name="settings[maintenance][block_ingest]" value="0">
                    <input type="checkbox" name="settings[maintenance][block_ingest]" value="1" class="toggle toggle-warning"
                           @checked((string) old('settings.maintenance.block_ingest', data_get($stored, 'maintenance.block_ingest', '0')) === '1')>
                    <span class="label-text">{{ __('settings.maintenance.block_ingest') }}</span>
                </label>
                <p class="fieldset-label text-base-content/60">{{ __('settings.maintenance.block_ingest_hint') }}</p>
            </div>
        </x-form-group>
    </div>
</x-form-group>
