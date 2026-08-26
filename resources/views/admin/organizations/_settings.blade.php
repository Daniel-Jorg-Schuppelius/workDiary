{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _settings.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
    <div role="tablist" class="tabs tabs-box flex-nowrap w-full overflow-x-auto mb-2">
        @foreach ($tabs as $key => $meta)
            <a role="tab" class="tab whitespace-nowrap" :class="tabClass('{{ $key }}')"
               @click.prevent="setTab('{{ $key }}')" href="#">{{ $meta['label'] }}</a>
        @endforeach
    </div>

    {{-- PAGINATION --}}
    <div x-show="isTab('pagination')" x-cloak>
        <x-form-group :legend="__('settings.tabs.pagination')" :icon="$tabs['pagination']['icon']" :tone="$tabs['pagination']['tone']" cols="3" compact>
            @foreach ((array) config('pagination') as $k => $v)
                <x-input-field name="settings[pagination][{{ $k }}]" type="number" min="1" max="500"
                               :label="__('settings.pagination.' . $k)"
                               :error="'settings.pagination.' . $k"
                               :value="old('settings.pagination.' . $k, data_get($stored, 'pagination.' . $k, ''))"
                               :placeholder="__('settings.placeholder_default', ['value' => (string) $v])" />
            @endforeach
        </x-form-group>
    </div>

    {{-- INVOICING --}}
    <div x-show="isTab('invoicing')" x-cloak class="space-y-4">
        <x-form-group :legend="__('settings.tabs.invoicing')" :icon="$tabs['invoicing']['icon']" :tone="$tabs['invoicing']['tone']" cols="3" compact>
            <x-input-field name="settings[invoicing][default_tax_rate]" :label="__('settings.invoicing.default_tax_rate')"
                           error="settings.invoicing.default_tax_rate" inputmode="decimal"
                           :value="old('settings.invoicing.default_tax_rate', data_get($stored, 'invoicing.default_tax_rate', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) config('invoicing.default_tax_rate')])" />
            <x-select-field name="settings[invoicing][default_currency]" :label="__('settings.invoicing.default_currency')"
                            error="settings.invoicing.default_currency">
                <x-currency-options :selected="old('settings.invoicing.default_currency', data_get($stored, 'invoicing.default_currency', ''))" nullable :null-label="__('settings.placeholder_default', ['value' => (string) config('invoicing.default_currency')])" />
            </x-select-field>
            <x-input-field name="settings[invoicing][time_unit]" :label="__('settings.invoicing.time_unit')"
                           error="settings.invoicing.time_unit" maxlength="8"
                           :value="old('settings.invoicing.time_unit', data_get($stored, 'invoicing.time_unit', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) config('invoicing.time_unit')])" />

            {{-- Standardleistung (MVP-486): Artikel des Faktura-Systems für
                 Bezeichnung, Einheit, Standardtext und Preis-Rückfall.
                 Projekt-Abrechnungsregeln überschreiben sie. --}}
            @php
                $serviceArticles = $organization !== null
                    ? \App\Models\LexofficeArticle::query()
                        ->withoutGlobalScopes()
                        ->where('organization_id', $organization->id)
                        ->active()
                        ->orderBy('name')
                        ->get(['external_id', 'name', 'unit_name', 'net_unit_price', 'currency'])
                    : collect();
                $selectedArticle = (string) old('settings.invoicing.default_service_article', data_get($stored, 'invoicing.default_service_article', ''));
            @endphp
            <x-select-field name="settings[invoicing][default_service_article]" span="2"
                            :label="__('settings.invoicing.default_service_article')"
                            error="settings.invoicing.default_service_article"
                            :hint="$serviceArticles->isEmpty() ? __('settings.invoicing.default_service_empty') : __('settings.invoicing.default_service_hint')">
                <option value="">{{ __('settings.invoicing.default_service_none') }}</option>
                @foreach ($serviceArticles as $article)
                    <option value="{{ $article->external_id }}" @selected($selectedArticle === (string) $article->external_id)>
                        {{ $article->name }}@if ($article->unit_name) · {{ $article->unit_name }}@endif @if ($article->net_unit_price) · {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($article->net_unit_price->toFloat(), 2) }} {{ $article->currency->value }}@endif
                    </option>
                @endforeach
            </x-select-field>

            {{-- Rechnungstexte-Vorlagen der Übergabe (MVP-491). --}}
            <x-textarea-field name="settings[invoicing][transfer_intro_text]" span="2" rows="2" maxlength="2000"
                              :label="__('settings.invoicing.transfer_intro_text')"
                              error="settings.invoicing.transfer_intro_text"
                              :value="old('settings.invoicing.transfer_intro_text', data_get($stored, 'invoicing.transfer_intro_text', ''))"
                              :hint="__('settings.invoicing.transfer_text_hint')" />
            <x-textarea-field name="settings[invoicing][transfer_closing_text]" span="2" rows="2" maxlength="2000"
                              :label="__('settings.invoicing.transfer_closing_text')"
                              error="settings.invoicing.transfer_closing_text"
                              :value="old('settings.invoicing.transfer_closing_text', data_get($stored, 'invoicing.transfer_closing_text', ''))"
                              :hint="__('settings.invoicing.transfer_closing_hint')" />

            {{-- Standard-Erlös: letzte Stufe der Satzhierarchie (MVP-482). --}}
            <x-input-field name="settings[invoicing][default_hourly_rate]" type="number" min="0" max="10000" step="0.01"
                           :label="__('settings.invoicing.default_hourly_rate')"
                           error="settings.invoicing.default_hourly_rate" inputmode="decimal"
                           :value="old('settings.invoicing.default_hourly_rate', data_get($stored, 'invoicing.default_hourly_rate', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) (config('invoicing.default_hourly_rate') ?? '—')])"
                           :hint="__('settings.invoicing.default_hourly_rate_hint')" />

            {{-- Kalkulationsstundensatz Montage (Feature 107, MVP-602). --}}
            <x-input-field name="settings[invoicing][assembly_hourly_rate]" type="number" min="0" max="10000" step="0.01"
                           :label="__('settings.invoicing.assembly_hourly_rate')"
                           error="settings.invoicing.assembly_hourly_rate" inputmode="decimal"
                           :value="old('settings.invoicing.assembly_hourly_rate', data_get($stored, 'invoicing.assembly_hourly_rate', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) (config('invoicing.assembly_hourly_rate') ?? '—')])"
                           :hint="__('settings.invoicing.assembly_hourly_rate_hint')" />

            {{-- Standardtaktung: greift, wenn weder Projekt noch Kunde eine Taktung setzen. --}}
            <x-input-field name="settings[invoicing][billing_increment_minutes]" type="number" min="1" max="1440" step="1"
                           :label="__('settings.invoicing.billing_increment_minutes')"
                           error="settings.invoicing.billing_increment_minutes"
                           :value="old('settings.invoicing.billing_increment_minutes', data_get($stored, 'invoicing.billing_increment_minutes', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => '1'])"
                           :hint="__('settings.invoicing.billing_increment_minutes_hint')" />
            <x-input-field name="settings[invoicing][billing_grouping_gap_minutes]" type="number" min="0" max="1440" step="1"
                           :label="__('settings.invoicing.billing_grouping_gap_minutes')"
                           error="settings.invoicing.billing_grouping_gap_minutes"
                           :value="old('settings.invoicing.billing_grouping_gap_minutes', data_get($stored, 'invoicing.billing_grouping_gap_minutes', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => '0'])"
                           :hint="__('settings.invoicing.billing_grouping_gap_minutes_hint')" />

            @can(\App\Enums\User\Permission::FinanceConfig->value)
                {{-- Fakturierungsweg (Feature 045): Org-Default, Kunden können übersteuern. --}}
                <x-select-field name="settings[billing_mode]" :label="__('finance.field.billing_mode')"
                                error="settings.billing_mode"
                                :hint="__('finance.field.billing_mode_org_hint')">
                    <option value="">{{ __('finance.field.billing_mode_default') }}</option>
                    @foreach (\App\Enums\Finance\BillingMode::options() as $value => $label)
                        <option value="{{ $value }}" @selected(old('settings.billing_mode', data_get($stored, 'billing_mode', '')) === $value)>{{ $label }}</option>
                    @endforeach
                </x-select-field>
            @endcan
        </x-form-group>

        {{-- MAHNWESEN (Feature 127, MVP-691): Stufen-Defaults für Einzelmahnung und Mahnlauf. --}}
        <x-form-group :legend="__('settings.dunning.heading')" icon="notification_important" tone="warning" cols="3" compact
                      :description="__('settings.dunning.description')">
            @foreach ([1, 2, 3] as $dunLevel)
                <x-input-field name="settings[invoicing][dunning][level{{ $dunLevel }}][grace_days]" type="number" min="0" max="365" step="1"
                               :label="__('settings.dunning.grace_days', ['level' => $dunLevel])"
                               error="settings.invoicing.dunning.level{{ $dunLevel }}.grace_days"
                               :value="old('settings.invoicing.dunning.level' . $dunLevel . '.grace_days', data_get($stored, 'invoicing.dunning.level' . $dunLevel . '.grace_days', ''))"
                               :placeholder="__('settings.placeholder_default', ['value' => (string) config('invoicing.dunning.level' . $dunLevel . '.grace_days')])"
                               :hint="$dunLevel === 1 ? __('settings.dunning.grace_days_hint_first') : __('settings.dunning.grace_days_hint_next')" />
                <x-input-field name="settings[invoicing][dunning][level{{ $dunLevel }}][fee]" type="number" min="0" max="10000" step="0.01"
                               :label="__('settings.dunning.fee', ['level' => $dunLevel])" inputmode="decimal"
                               error="settings.invoicing.dunning.level{{ $dunLevel }}.fee"
                               :value="old('settings.invoicing.dunning.level' . $dunLevel . '.fee', data_get($stored, 'invoicing.dunning.level' . $dunLevel . '.fee', ''))"
                               :placeholder="__('settings.placeholder_default', ['value' => '0,00'])" />
                <x-input-field name="settings[invoicing][dunning][level{{ $dunLevel }}][pay_days]" type="number" min="0" max="90" step="1"
                               :label="__('settings.dunning.pay_days', ['level' => $dunLevel])"
                               error="settings.invoicing.dunning.level{{ $dunLevel }}.pay_days"
                               :value="old('settings.invoicing.dunning.level' . $dunLevel . '.pay_days', data_get($stored, 'invoicing.dunning.level' . $dunLevel . '.pay_days', ''))"
                               :placeholder="__('settings.placeholder_default', ['value' => (string) config('invoicing.dunning.level' . $dunLevel . '.pay_days')])" />
            @endforeach
            <x-input-field name="settings[invoicing][dunning][interest_rate]" type="number" min="0" max="30" step="0.01"
                           :label="__('settings.dunning.interest_rate')" inputmode="decimal"
                           error="settings.invoicing.dunning.interest_rate"
                           :value="old('settings.invoicing.dunning.interest_rate', data_get($stored, 'invoicing.dunning.interest_rate', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => '0'])"
                           :hint="__('settings.dunning.interest_rate_hint')" />
        </x-form-group>

        {{-- E-RECHNUNG (Feature 045, Abschnitt 8): Verkäuferstammdaten für XRechnung (EN 16931). --}}
        <x-form-group :legend="__('settings.einvoice.heading')" icon="receipt" tone="info" cols="3" compact
                      :description="__('settings.einvoice.description')">
            <x-input-field name="settings[einvoice][seller_name]" :label="__('settings.einvoice.seller_name')"
                           error="settings.einvoice.seller_name" maxlength="200"
                           :value="old('settings.einvoice.seller_name', data_get($stored, 'einvoice.seller_name', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) ($organization?->name ?? '')])" />
            <x-input-field name="settings[einvoice][street]" :label="__('settings.einvoice.street')"
                           error="settings.einvoice.street" maxlength="255"
                           :value="old('settings.einvoice.street', data_get($stored, 'einvoice.street', ''))" />
            <x-input-field name="settings[einvoice][zip]" :label="__('settings.einvoice.zip')"
                           error="settings.einvoice.zip" maxlength="32"
                           :value="old('settings.einvoice.zip', data_get($stored, 'einvoice.zip', ''))" />
            <x-input-field name="settings[einvoice][city]" :label="__('settings.einvoice.city')"
                           error="settings.einvoice.city" maxlength="128"
                           :value="old('settings.einvoice.city', data_get($stored, 'einvoice.city', ''))" />
            <x-input-field name="settings[einvoice][country]" :label="__('settings.einvoice.country')"
                           error="settings.einvoice.country" maxlength="2" class="uppercase"
                           :value="old('settings.einvoice.country', data_get($stored, 'einvoice.country', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => 'DE'])" />
            <x-input-field name="settings[einvoice][vat_id]" :label="__('settings.einvoice.vat_id')"
                           error="settings.einvoice.vat_id" maxlength="64" placeholder="DE123456789"
                           :value="old('settings.einvoice.vat_id', data_get($stored, 'einvoice.vat_id', ''))" />
            <x-input-field name="settings[einvoice][tax_number]" :label="__('settings.einvoice.tax_number')"
                           error="settings.einvoice.tax_number" maxlength="64"
                           :value="old('settings.einvoice.tax_number', data_get($stored, 'einvoice.tax_number', ''))" />
            <x-input-field name="settings[einvoice][contact_name]" :label="__('settings.einvoice.contact_name')"
                           error="settings.einvoice.contact_name" maxlength="200"
                           :value="old('settings.einvoice.contact_name', data_get($stored, 'einvoice.contact_name', ''))" />
            <x-input-field name="settings[einvoice][contact_email]" type="email" :label="__('settings.einvoice.contact_email')"
                           error="settings.einvoice.contact_email" maxlength="255"
                           :value="old('settings.einvoice.contact_email', data_get($stored, 'einvoice.contact_email', ''))" />
            <x-input-field name="settings[einvoice][contact_phone]" :label="__('settings.einvoice.contact_phone')"
                           error="settings.einvoice.contact_phone" maxlength="64"
                           :value="old('settings.einvoice.contact_phone', data_get($stored, 'einvoice.contact_phone', ''))" />
            <x-input-field name="settings[einvoice][iban]" :label="__('settings.einvoice.iban')"
                           error="settings.einvoice.iban" maxlength="64" class="uppercase"
                           :value="old('settings.einvoice.iban', data_get($stored, 'einvoice.iban', ''))" />
            <x-input-field name="settings[einvoice][bic]" :label="__('settings.einvoice.bic')"
                           error="settings.einvoice.bic" maxlength="32" class="uppercase"
                           :value="old('settings.einvoice.bic', data_get($stored, 'einvoice.bic', ''))" />
            <x-input-field name="settings[einvoice][account_holder]" :label="__('settings.einvoice.account_holder')"
                           error="settings.einvoice.account_holder" maxlength="200"
                           :value="old('settings.einvoice.account_holder', data_get($stored, 'einvoice.account_holder', ''))" />
            <x-input-field name="settings[einvoice][payment_terms_days]" type="number" min="0" max="365"
                           :label="__('settings.einvoice.payment_terms_days')"
                           error="settings.einvoice.payment_terms_days"
                           :value="old('settings.einvoice.payment_terms_days', data_get($stored, 'einvoice.payment_terms_days', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => '14'])" />
            <x-checkbox-field name="settings[einvoice][small_business]" span="2" tone="info"
                             :label="__('settings.einvoice.small_business')"
                             error="settings.einvoice.small_business"
                             :checked="(string) old('settings.einvoice.small_business', data_get($stored, 'einvoice.small_business', '0')) === '1'"
                             :hint="__('settings.einvoice.small_business_hint')" />
        </x-form-group>

        {{-- ZEIT-IMPORT (MVP-483): Schlüsselwort-Zuordnung importierter Zeiten. --}}
        <x-form-group :legend="__('settings.time_import.heading')" icon="conversion_path" tone="info" cols="1" compact
                      :description="__('settings.time_import.description')">
            <x-checkbox-field name="settings[project][keyword_matching][enabled]" tone="info"
                             :label="__('settings.time_import.keyword_matching')"
                             error="settings.project.keyword_matching.enabled"
                             :checked="(string) old('settings.project.keyword_matching.enabled', data_get($stored, 'project.keyword_matching.enabled', config('project.keyword_matching.enabled') ? '1' : '0')) === '1'"
                             :hint="__('settings.time_import.keyword_matching_hint')" />
        </x-form-group>
    </div>

    {{-- UPLOADS --}}
    <div x-show="isTab('uploads')" x-cloak>
        <x-form-group :legend="__('settings.tabs.uploads')" :icon="$tabs['uploads']['icon']" :tone="$tabs['uploads']['tone']" cols="2" compact>
            @foreach ((array) config('uploads') as $k => $v)
                <x-input-field name="settings[uploads][{{ $k }}]" type="number" min="1"
                               :label="__('settings.uploads.' . $k)"
                               :error="'settings.uploads.' . $k"
                               :value="old('settings.uploads.' . $k, data_get($stored, 'uploads.' . $k, ''))"
                               :placeholder="__('settings.placeholder_default', ['value' => (string) $v])" />
            @endforeach
        </x-form-group>
    </div>

    {{-- VALIDATION --}}
    <div x-show="isTab('validation')" x-cloak class="space-y-4">
        @foreach ((array) config('validation') as $group => $fields)
            <x-form-group :legend="__('settings.validation.' . $group . '.heading')" :icon="$tabs['validation']['icon']" :tone="$tabs['validation']['tone']" cols="3" compact>
                @foreach ((array) $fields as $field => $val)
                    <x-input-field name="settings[validation][{{ $group }}][{{ $field }}]" type="number" min="1"
                                   :label="__('settings.validation.' . $group . '.' . $field)"
                                   :error="'settings.validation.' . $group . '.' . $field"
                                   :value="old('settings.validation.' . $group . '.' . $field, data_get($stored, 'validation.' . $group . '.' . $field, ''))"
                                   :placeholder="__('settings.placeholder_default', ['value' => (string) $val])" />
                @endforeach
            </x-form-group>
        @endforeach
    </div>

    {{-- NOTIFICATIONS --}}
    <div x-show="isTab('notifications')" x-cloak>
        <x-form-group :legend="__('settings.tabs.notifications')" :icon="$tabs['notifications']['icon']" :tone="$tabs['notifications']['tone']" cols="2" compact>
            <x-input-field name="settings[notifications][push][body_truncate]" type="number" min="20" max="500"
                           :label="__('settings.notifications.push.body_truncate')"
                           error="settings.notifications.push.body_truncate"
                           :value="old('settings.notifications.push.body_truncate', data_get($stored, 'notifications.push.body_truncate', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) config('notifications.push.body_truncate')])" />
        </x-form-group>
    </div>

    {{-- UI --}}
    <div x-show="isTab('ui')" x-cloak class="space-y-4">
        @foreach ((array) config('ui') as $group => $fields)
            <x-form-group :legend="__('settings.ui.' . $group . '.heading')" :icon="$tabs['ui']['icon']" :tone="$tabs['ui']['tone']" cols="3" compact>
                @foreach ((array) $fields as $field => $val)
                    <x-input-field name="settings[ui][{{ $group }}][{{ $field }}]" type="number" min="1"
                                   :label="__('settings.ui.' . $group . '.' . $field)"
                                   :error="'settings.ui.' . $group . '.' . $field"
                                   :value="old('settings.ui.' . $group . '.' . $field, data_get($stored, 'ui.' . $group . '.' . $field, ''))"
                                   :placeholder="__('settings.placeholder_default', ['value' => (string) $val])" />
                @endforeach
            </x-form-group>
        @endforeach
    </div>

    {{-- ROUTING --}}
    <div x-show="isTab('routing')" x-cloak class="space-y-4">
        <x-form-group :legend="__('settings.routing.nominatim.heading')" :icon="$tabs['routing']['icon']" :tone="$tabs['routing']['tone']" cols="2" compact>
            <x-input-field name="settings[routing][nominatim][base_url]" span="2" inputmode="url"
                           :label="__('settings.routing.nominatim.base_url')"
                           error="settings.routing.nominatim.base_url"
                           :value="old('settings.routing.nominatim.base_url', data_get($stored, 'routing.nominatim.base_url', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) config('routing.nominatim.base_url')])" />
            <x-input-field name="settings[routing][nominatim][email]" type="email"
                           :label="__('settings.routing.nominatim.email')"
                           error="settings.routing.nominatim.email"
                           :value="old('settings.routing.nominatim.email', data_get($stored, 'routing.nominatim.email', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) config('routing.nominatim.email')])" />
            <x-input-field name="settings[routing][nominatim][rate_limit_per_sec]" type="number" min="1" max="50"
                           :label="__('settings.routing.nominatim.rate_limit_per_sec')"
                           error="settings.routing.nominatim.rate_limit_per_sec"
                           :value="old('settings.routing.nominatim.rate_limit_per_sec', data_get($stored, 'routing.nominatim.rate_limit_per_sec', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) config('routing.nominatim.rate_limit_per_sec')])" />
        </x-form-group>

        <x-form-group :legend="__('settings.routing.osrm.heading')" :icon="$tabs['routing']['icon']" :tone="$tabs['routing']['tone']" cols="2" compact>
            <x-input-field name="settings[routing][osrm][base_url]" span="2" inputmode="url"
                           :label="__('settings.routing.osrm.base_url')"
                           error="settings.routing.osrm.base_url"
                           :value="old('settings.routing.osrm.base_url', data_get($stored, 'routing.osrm.base_url', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) config('routing.osrm.base_url')])" />
            <x-input-field name="settings[routing][osrm][profile]" maxlength="32"
                           :label="__('settings.routing.osrm.profile')"
                           error="settings.routing.osrm.profile"
                           :value="old('settings.routing.osrm.profile', data_get($stored, 'routing.osrm.profile', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) config('routing.osrm.profile')])" />
            <x-input-field name="settings[routing][osrm][timeout]" type="number" min="1" max="120"
                           :label="__('settings.routing.osrm.timeout')"
                           error="settings.routing.osrm.timeout"
                           :value="old('settings.routing.osrm.timeout', data_get($stored, 'routing.osrm.timeout', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) config('routing.osrm.timeout')])" />
        </x-form-group>

        <x-form-group :legend="__('settings.routing.tiles.heading')" :icon="$tabs['routing']['icon']" :tone="$tabs['routing']['tone']" cols="2" compact>
            <x-input-field name="settings[routing][tiles][url]" span="2" inputmode="url"
                           :label="__('settings.routing.tiles.url')"
                           error="settings.routing.tiles.url"
                           :value="old('settings.routing.tiles.url', data_get($stored, 'routing.tiles.url', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) config('routing.tiles.url')])" />
            <x-input-field name="settings[routing][tiles][max_zoom]" type="number" min="1" max="22"
                           :label="__('settings.routing.tiles.max_zoom')"
                           error="settings.routing.tiles.max_zoom"
                           :value="old('settings.routing.tiles.max_zoom', data_get($stored, 'routing.tiles.max_zoom', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) config('routing.tiles.max_zoom')])" />
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

            <x-select-field name="settings[travel][mode]" :label="__('Modus')"
                            error="settings.travel.mode" x-model="mode">
                <option value="flat">{{ __('Pauschale') }}</option>
                <option value="km">{{ __('Kilometer') }}</option>
            </x-select-field>
            <x-input-field name="settings[travel][label]" :label="__('Positionstext')"
                           error="settings.travel.label" maxlength="50" placeholder="Anfahrt"
                           :value="old('settings.travel.label', data_get($stored, 'travel.label', ''))" />

            {{-- x-show sitzt auf dem Wrapper (Komponente kapselt nur das Feld). --}}
            <div x-show="isMode('flat')">
                <x-input-field name="settings[travel][flat_amount]" type="number" step="0.01" min="0"
                               :label="__('Pauschale (netto €)')"
                               error="settings.travel.flat_amount"
                               :value="old('settings.travel.flat_amount', data_get($stored, 'travel.flat_amount', ''))" />
            </div>

            <template x-if="isMode('km')">
                <div class="contents">
                    <x-input-field name="settings[travel][rate_per_km]" type="number" step="0.01" min="0"
                                   :label="__('Satz (€/km)')"
                                   error="settings.travel.rate_per_km"
                                   :value="old('settings.travel.rate_per_km', data_get($stored, 'travel.rate_per_km', ''))" />
                    <x-select-field name="settings[travel][km_source]" :label="__('Kilometer-Quelle')"
                                    error="settings.travel.km_source" x-model="kmSource">
                        <option value="company">{{ __('Immer vom Firmenstandort') }}</option>
                        <option value="tour">{{ __('Je nach Tour (tatsächliche km)') }}</option>
                    </x-select-field>
                    <div class="fieldset md:col-span-2">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="hidden" name="settings[travel][round_trip]" :value="roundTripValue">
                            <input type="checkbox" class="toggle toggle-success" x-model="roundTrip">
                            <span class="label-text">{{ __('Hin- und Rückfahrt (×2, nur Firmenstandort)') }}</span>
                        </label>
                    </div>
                    <div x-show="isKmSource('company')">
                        <x-input-field name="settings[travel][origin_lat]" type="number" step="0.0000001" min="-90" max="90"
                                       :label="__('Firmenstandort Breite (lat)')"
                                       error="settings.travel.origin_lat"
                                       :value="old('settings.travel.origin_lat', data_get($stored, 'travel.origin_lat', ''))" />
                    </div>
                    <div x-show="isKmSource('company')">
                        <x-input-field name="settings[travel][origin_lng]" type="number" step="0.0000001" min="-180" max="180"
                                       :label="__('Firmenstandort Länge (lng)')"
                                       error="settings.travel.origin_lng"
                                       :value="old('settings.travel.origin_lng', data_get($stored, 'travel.origin_lng', ''))" />
                    </div>
                </div>
            </template>
        </x-form-group>
    </div>

    {{-- REGION / FEIERTAGE (Feature 034) --}}
    <div x-show="isTab('region')" x-cloak class="space-y-4">
        <x-form-group :legend="__('settings.region.heading')" icon="public" tone="info" cols="2" compact
                      :description="__('settings.region.description')">
            <x-select-field name="settings[holidays][provider]" span="2"
                            :label="__('settings.region.holiday_provider')"
                            error="settings.holidays.provider"
                            :hint="__('settings.region.holiday_provider_hint')">
                <option value="">{{ __('settings.placeholder_default', ['value' => \App\Support\HolidayRegions::label((string) config('holidays.provider', 'Germany'))]) }}</option>
                @foreach (\App\Support\HolidayRegions::grouped() as $group => $providers)
                    <optgroup label="{{ $group }}">
                        @foreach ($providers as $value => $label)
                            <option value="{{ $value }}"
                                @selected((string) old('settings.holidays.provider', data_get($stored, 'holidays.provider', '')) === $value)>{{ $label }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </x-select-field>
        </x-form-group>
    </div>

    {{-- WETTER / WEATHER (Feature 062, Rang 12) --}}
    <div x-show="isTab('weather')" x-cloak>
        <x-form-group :legend="__('settings.weather.heading')" icon="partly_cloudy_day" tone="info" cols="1" compact
                      :description="__('settings.weather.description')">
            <x-checkbox-field name="settings[weather][auto_fetch]" tone="info"
                             :label="__('settings.weather.auto_fetch')"
                             error="settings.weather.auto_fetch"
                             :checked="(string) old('settings.weather.auto_fetch', data_get($stored, 'weather.auto_fetch', '0')) === '1'"
                             :hint="__('settings.weather.auto_fetch_hint')" />
            {{-- Provider-Auswahl (Bauturbo A7/MVP-131): Open-Meteo (Default) oder amtliche DWD-Open-Data. --}}
            <div class="fieldset">
                <label class="label" for="settings_weather_provider">{{ __('settings.weather.provider') }}</label>
                <select id="settings_weather_provider" name="settings[weather][provider]" class="select select-bordered w-full">
                    <option value="">{{ __('settings.placeholder_default', ['value' => __('weather.providers.open-meteo')]) }}</option>
                    @foreach (['open-meteo', 'dwd'] as $weatherProvider)
                        <option value="{{ $weatherProvider }}"
                            @selected((string) old('settings.weather.provider', data_get($stored, 'weather.provider', '')) === $weatherProvider)>{{ __('weather.providers.' . $weatherProvider) }}</option>
                    @endforeach
                </select>
                <p class="fieldset-label text-muted">{{ __('settings.weather.provider_hint') }}</p>
            </div>
            <div class="fieldset">
                <label class="label" for="settings_weather_dwd_max_station_km">{{ __('settings.weather.dwd_max_station_km') }}</label>
                <input type="number" min="1" max="200" id="settings_weather_dwd_max_station_km"
                       name="settings[weather][dwd_max_station_km]" class="input input-bordered w-full"
                       placeholder="{{ \App\Services\Weather\DwdProvider::DEFAULT_MAX_STATION_KM }}"
                       value="{{ old('settings.weather.dwd_max_station_km', data_get($stored, 'weather.dwd_max_station_km', '')) }}">
                <p class="fieldset-label text-muted">{{ __('settings.weather.dwd_max_station_km_hint') }}</p>
            </div>
        </x-form-group>

        {{-- Wetterwarnungen für die Disposition (Feature 062, MVP-716) --}}
        <x-form-group :legend="__('settings.weather.warnings_heading')" icon="thunderstorm" tone="warning" cols="2" compact
                      :description="__('settings.weather.warn_hint')">
            <x-checkbox-field name="settings[weather][warnings_enabled]" tone="warning" class="sm:col-span-2"
                             :label="__('settings.weather.warnings_enabled')"
                             error="settings.weather.warnings_enabled"
                             :checked="(string) old('settings.weather.warnings_enabled', data_get($stored, 'weather.warnings_enabled', '1')) === '1'"
                             :hint="__('settings.weather.warnings_enabled_hint')" />
            @foreach (\App\Enums\Weather\WeatherWarningThreshold::cases() as $weatherThreshold)
                @php($weatherSettingKey = substr($weatherThreshold->settingKey(), strlen('weather.')))
                <div class="fieldset">
                    <label class="label" for="settings_weather_{{ $weatherSettingKey }}">{{ __('settings.weather.' . $weatherSettingKey) }}</label>
                    <input type="number" step="0.1" id="settings_weather_{{ $weatherSettingKey }}"
                           name="settings[weather][{{ $weatherSettingKey }}]" class="input input-bordered w-full"
                           placeholder="{{ $weatherThreshold->defaultLimit() }}"
                           value="{{ old('settings.weather.' . $weatherSettingKey, data_get($stored, 'weather.' . $weatherSettingKey, '')) }}">
                </div>
            @endforeach
        </x-form-group>
    </div>

    {{-- WARTUNGSMODUS (Rang 65) --}}
    <div x-show="isTab('maintenance')" x-cloak>
        <x-form-group :legend="__('settings.maintenance.heading')" icon="engineering" tone="warning" cols="2" compact
                      :description="__('settings.maintenance.description')">
            <x-checkbox-field name="settings[maintenance][enabled]" span="2" tone="warning"
                             :label="__('settings.maintenance.enabled')"
                             error="settings.maintenance.enabled"
                             :checked="(string) old('settings.maintenance.enabled', data_get($stored, 'maintenance.enabled', '0')) === '1'" />
            <x-input-field name="settings[maintenance][message]" span="2" maxlength="300"
                           :label="__('settings.maintenance.message')"
                           error="settings.maintenance.message"
                           :value="old('settings.maintenance.message', data_get($stored, 'maintenance.message', ''))"
                           :placeholder="__('settings.maintenance.message_placeholder')" />
            <x-input-field name="settings[maintenance][until]" type="datetime-local"
                           :label="__('settings.maintenance.until')"
                           error="settings.maintenance.until"
                           :value="old('settings.maintenance.until', data_get($stored, 'maintenance.until', ''))"
                           :hint="__('settings.maintenance.until_hint')" />
            <x-checkbox-field name="settings[maintenance][block_ingest]" tone="warning"
                             :label="__('settings.maintenance.block_ingest')"
                             error="settings.maintenance.block_ingest"
                             :checked="(string) old('settings.maintenance.block_ingest', data_get($stored, 'maintenance.block_ingest', '0')) === '1'"
                             :hint="__('settings.maintenance.block_ingest_hint')" />
        </x-form-group>
    </div>
</x-form-group>
