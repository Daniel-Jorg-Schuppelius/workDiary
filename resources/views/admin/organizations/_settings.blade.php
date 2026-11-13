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
    /** @var \App\Models\Platform\Organization|null $organization */
    $stored = (array) ($organization?->settings ?? []);

    // Nur, was auf Organisationsebene wirkt: systemweite Registry-Schlüssel fehlen;
    // im Reiter Oberfläche liest der Rest (z. B. news_feed) nur config().
    $registry = app(\App\Settings\SettingsRegistry::class);
    $orgScoped = static fn (string $key): bool => $registry->has($key)
        && $registry->definition($key)->allowsScope(\App\Settings\SettingScope::Organization);
    $paginationFields = array_filter(
        (array) config('pagination'),
        static fn (string $key): bool => ! $registry->has('pagination.' . $key) || $orgScoped('pagination.' . $key),
        ARRAY_FILTER_USE_KEY,
    );
    $uiGroups = [];
    foreach ((array) config('ui') as $group => $fields) {
        $fields = array_filter((array) $fields, static fn (string $field): bool => $orgScoped('ui.' . $group . '.' . $field), ARRAY_FILTER_USE_KEY);
        if ($fields !== []) {
            $uiGroups[$group] = $fields;
        }
    }

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
            @foreach ($paginationFields as $k => $v)
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
                           :placeholder="__('settings.invoicing.default_tax_rate_placeholder')"
                           :hint="__('settings.invoicing.default_tax_rate_hint')" />
            <x-select-field name="settings[invoicing][default_currency]" :label="__('settings.invoicing.default_currency')"
                            error="settings.invoicing.default_currency">
                <x-currency-options :selected="old('settings.invoicing.default_currency', data_get($stored, 'invoicing.default_currency', ''))" nullable :null-label="__('settings.placeholder_default', ['value' => (string) config('invoicing.default_currency')])" />
            </x-select-field>
            <x-input-field name="settings[invoicing][time_unit]" :label="__('settings.invoicing.time_unit')"
                           error="settings.invoicing.time_unit" maxlength="8"
                           :value="old('settings.invoicing.time_unit', data_get($stored, 'invoicing.time_unit', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) config('invoicing.time_unit')])" />
            {{-- Bindefrist: Vorbelegung von „gültig bis“ und Ablaufdatum bei Lexware. --}}
            <x-input-field name="settings[invoicing][quote_validity_days]" type="number" min="1" max="365"
                           :label="__('settings.invoicing.quote_validity_days')"
                           error="settings.invoicing.quote_validity_days"
                           :value="old('settings.invoicing.quote_validity_days', data_get($stored, 'invoicing.quote_validity_days', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) config('invoicing.quote_validity_days')])"
                           :hint="__('settings.invoicing.quote_validity_days_hint')" />
            {{-- Freigabezwang (MVP-1562): Ausstellung erst nach Freigabe durch eine zweite Person. --}}
            <x-checkbox-field name="settings[invoicing][require_approval]" span="2" tone="info"
                             :label="__('settings.invoicing.require_approval')"
                             error="settings.invoicing.require_approval"
                             :checked="(string) old('settings.invoicing.require_approval', data_get($stored, 'invoicing.require_approval', '0')) === '1'"
                             :hint="__('settings.invoicing.require_approval_hint')" />

            {{-- Standardleistung (MVP-486): Artikel aus dem Artikelkatalog (MVP-1026)
                 für Bezeichnung, Einheit, Standardtext und Preis-Rückfall.
                 Projekt-Abrechnungsregeln überschreiben sie. --}}
            @php
                $articleCatalog = app(\App\Services\Platform\Catalog\ArticleCatalog::class);
                $serviceArticles = $organization !== null ? $articleCatalog->active((int) $organization->id) : [];
                $selectedArticle = $organization !== null
                    ? $articleCatalog->find((int) $organization->id, data_get($stored, 'invoicing.default_service_article'))?->formKey
                    : null;
            @endphp
            <x-article-catalog-select name="settings[invoicing][default_service_article]" span="2"
                                      :articles="$serviceArticles" :selected="$selectedArticle"
                                      :label="__('settings.invoicing.default_service_article')"
                                      :empty="__('settings.invoicing.default_service_none')"
                                      error="settings.invoicing.default_service_article"
                                      :hint="$serviceArticles === [] ? __('article.catalog.empty_hint') : __('settings.invoicing.default_service_hint')" />

            {{-- Leistungsort (MVP-1688): Vorgabe, wo weder Abrechnungsregel noch Artikel einen nennen. --}}
            <x-select-field name="settings[invoicing][place_of_supply_kind]" span="2"
                            :label="__('settings.invoicing.place_of_supply_kind')"
                            error="settings.invoicing.place_of_supply_kind"
                            :hint="__('settings.invoicing.place_of_supply_kind_hint')">
                <option value="">{{ __('settings.placeholder_default', ['value' => \App\Enums\Invoicing\PlaceOfSupplyKind::General->label()]) }}</option>
                @foreach (\App\Enums\Invoicing\PlaceOfSupplyKind::options() as $value => $label)
                    <option value="{{ $value }}" @selected((string) old('settings.invoicing.place_of_supply_kind', data_get($stored, 'invoicing.place_of_supply_kind', '')) === (string) $value)>{{ $label }}</option>
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

            {{-- Stundensatz für Gerätelaufzeit (Feature 193). --}}
            <x-input-field name="settings[invoicing][machine_hourly_rate]" type="number" min="0" max="10000" step="0.01"
                           :label="__('settings.invoicing.machine_hourly_rate')"
                           error="settings.invoicing.machine_hourly_rate" inputmode="decimal"
                           :value="old('settings.invoicing.machine_hourly_rate', data_get($stored, 'invoicing.machine_hourly_rate', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) (config('invoicing.machine_hourly_rate') ?? '—')])"
                           :hint="__('settings.invoicing.machine_hourly_rate_hint')" />

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

        {{-- KOMMUNIKATION: Fußzeile unter Antworten, die Mitarbeitende aus WorkDiary schreiben (Notizen, Tickets). --}}
        <x-form-group :legend="__('settings.communication.heading')" icon="mail" tone="info" cols="1" compact>
            <x-textarea-field name="settings[communication][mail_footer]" rows="4" maxlength="4000"
                              :label="__('settings.communication.mail_footer')"
                              error="settings.communication.mail_footer"
                              :value="old('settings.communication.mail_footer', data_get($stored, 'communication.mail_footer', ''))"
                              :hint="__('settings.communication.mail_footer_hint')" />
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
            <x-select-field name="settings[invoicing][dunning][interest_mode]" :label="__('settings.dunning.interest_mode')"
                            error="settings.invoicing.dunning.interest_mode" :hint="__('settings.dunning.interest_mode_hint')">
                @php
                    $interestMode = old('settings.invoicing.dunning.interest_mode', data_get($stored, 'invoicing.dunning.interest_mode', ''));
                @endphp
                <option value="" @selected($interestMode === '')>{{ __('settings.placeholder_default', ['value' => __('settings.dunning.interest_mode_fixed')]) }}</option>
                <option value="fixed" @selected($interestMode === 'fixed')>{{ __('settings.dunning.interest_mode_fixed') }}</option>
                <option value="base_rate" @selected($interestMode === 'base_rate')>{{ __('settings.dunning.interest_mode_base_rate') }}</option>
            </x-select-field>
            <x-input-field name="settings[invoicing][dunning][interest_points]" type="number" min="0" max="20" step="0.01"
                           :label="__('settings.dunning.interest_points')" inputmode="decimal"
                           error="settings.invoicing.dunning.interest_points"
                           :value="old('settings.invoicing.dunning.interest_points', data_get($stored, 'invoicing.dunning.interest_points', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => '0'])"
                           :hint="__('settings.dunning.interest_points_hint')" />
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
            <x-checkbox-field name="settings[einvoice][enforce_validation]" span="2" tone="info"
                             :label="__('settings.einvoice.enforce_validation')"
                             error="settings.einvoice.enforce_validation"
                             :checked="(string) old('settings.einvoice.enforce_validation', data_get($stored, 'einvoice.enforce_validation', '0')) === '1'"
                             :hint="__('settings.einvoice.enforce_validation_hint')" />
        </x-form-group>

        {{-- Rechnungsbegründende Unterlagen und Empfängerprofil (Feature 196). --}}
        <x-form-group :legend="__('settings.einvoice.documents_heading')" icon="attach_file" tone="info" cols="2" compact
                      :description="__('settings.einvoice.documents_description')">
            @php $profileDefault = old('settings.einvoice.recipient_profile_default', data_get($stored, 'einvoice.recipient_profile_default', '')); @endphp
            <x-select-field name="settings[einvoice][recipient_profile_default]" :label="__('settings.einvoice.recipient_profile_default')"
                            error="settings.einvoice.recipient_profile_default" :hint="__('settings.einvoice.recipient_profile_default_hint')">
                <option value="" @selected($profileDefault === '')>{{ __('settings.placeholder_default', ['value' => \App\Enums\Invoicing\EInvoiceRecipientProfile::Standard->label()]) }}</option>
                @foreach (\App\Enums\Invoicing\EInvoiceRecipientProfile::options() as $value => $label)
                    <option value="{{ $value }}" @selected($profileDefault === $value)>{{ $label }}</option>
                @endforeach
            </x-select-field>
            @php $personDisplay = old('settings.einvoice.activity_report_person_display', data_get($stored, 'einvoice.activity_report_person_display', '')); @endphp
            <x-select-field name="settings[einvoice][activity_report_person_display]" :label="__('settings.einvoice.activity_report_person_display')"
                            error="settings.einvoice.activity_report_person_display" :hint="__('settings.einvoice.activity_report_person_display_hint')">
                <option value="" @selected($personDisplay === '')>{{ __('settings.placeholder_default', ['value' => \App\Enums\Invoicing\ActivityReportPersonDisplay::FullName->label()]) }}</option>
                @foreach (\App\Enums\Invoicing\ActivityReportPersonDisplay::options() as $value => $label)
                    <option value="{{ $value }}" @selected($personDisplay === $value)>{{ $label }}</option>
                @endforeach
            </x-select-field>
            <x-checkbox-field name="settings[einvoice][attach_activity_report]" tone="info"
                              :label="__('settings.einvoice.attach_activity_report')"
                              error="settings.einvoice.attach_activity_report"
                              :checked="(string) old('settings.einvoice.attach_activity_report', (int) (bool) data_get($stored, 'einvoice.attach_activity_report', false)) === '1'"
                              :hint="__('settings.einvoice.attach_activity_report_hint')" />
            <x-checkbox-field name="settings[einvoice][activity_report_include_signed_timesheets]" tone="info"
                              :label="__('settings.einvoice.activity_report_include_signed_timesheets')"
                              error="settings.einvoice.activity_report_include_signed_timesheets"
                              :checked="(string) old('settings.einvoice.activity_report_include_signed_timesheets', (int) (bool) data_get($stored, 'einvoice.activity_report_include_signed_timesheets', true)) === '1'"
                              :hint="__('settings.einvoice.activity_report_include_signed_timesheets_hint')" />
            <x-checkbox-field name="settings[einvoice][activity_report_csv]" tone="info"
                              :label="__('settings.einvoice.activity_report_csv')"
                              error="settings.einvoice.activity_report_csv"
                              :checked="(string) old('settings.einvoice.activity_report_csv', (int) (bool) data_get($stored, 'einvoice.activity_report_csv', false)) === '1'"
                              :hint="__('settings.einvoice.activity_report_csv_hint')" />
            <x-input-field name="settings[einvoice][incoming_attachment_limit_mb]" type="number" min="1" max="50"
                           :label="__('settings.einvoice.incoming_attachment_limit_mb')"
                           error="settings.einvoice.incoming_attachment_limit_mb"
                           :value="old('settings.einvoice.incoming_attachment_limit_mb', data_get($stored, 'einvoice.incoming_attachment_limit_mb', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => '15'])"
                           :hint="__('settings.einvoice.incoming_attachment_limit_mb_hint')" />
            @php $dispatchRoute = old('settings.einvoice.external_dispatch_route', data_get($stored, 'einvoice.external_dispatch_route', '')); @endphp
            <x-select-field name="settings[einvoice][external_dispatch_route]" :label="__('settings.einvoice.external_dispatch_route')"
                            error="settings.einvoice.external_dispatch_route" :hint="__('settings.einvoice.external_dispatch_route_hint')">
                <option value="" @selected($dispatchRoute === '')>{{ __('settings.placeholder_default', ['value' => \App\Enums\Invoicing\ExternalDispatchRoute::Provider->label()]) }}</option>
                @foreach (\App\Enums\Invoicing\ExternalDispatchRoute::options() as $value => $label)
                    <option value="{{ $value }}" @selected($dispatchRoute === $value)>{{ $label }}</option>
                @endforeach
            </x-select-field>
            <x-checkbox-field name="settings[einvoice][provider_send_disabled]" tone="info"
                              :label="__('settings.einvoice.provider_send_disabled')"
                              error="settings.einvoice.provider_send_disabled"
                              :checked="(string) old('settings.einvoice.provider_send_disabled', (int) (bool) data_get($stored, 'einvoice.provider_send_disabled', false)) === '1'"
                              :hint="__('settings.einvoice.provider_send_disabled_hint')" />
        </x-form-group>

        {{-- BUCHHALTUNG: Vier-Augen-Prinzip für Festschreiben, Direktbuchungen und Zahlungsläufe. --}}
        <x-form-group :legend="__('settings.accounting.heading')" icon="verified_user" tone="info" compact>
            <x-checkbox-field name="settings[finance][accounting_four_eyes]" tone="info"
                              :label="__('settings.accounting.four_eyes')"
                              error="settings.finance.accounting_four_eyes"
                              :checked="(string) old('settings.finance.accounting_four_eyes', (int) (bool) data_get($stored, 'finance.accounting_four_eyes', false)) === '1'"
                              :hint="__('settings.accounting.four_eyes_hint')" />
        </x-form-group>

        {{-- BANKABGLEICH (Feature 045, MVP-1623): Toleranzen der Zuordnungsvorschläge. --}}
        <x-form-group :legend="__('settings.reconciliation.heading')" icon="account_balance" tone="info" cols="2" compact
                      :description="__('settings.reconciliation.description')">
            <x-input-field name="settings[finance][reconciliation][cent_tolerance]" type="number" min="0" max="1" step="0.01"
                           :label="__('settings.reconciliation.cent_tolerance')"
                           error="settings.finance.reconciliation.cent_tolerance"
                           :value="old('settings.finance.reconciliation.cent_tolerance', data_get($stored, 'finance.reconciliation.cent_tolerance', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => '0,02'])"
                           :hint="__('settings.reconciliation.cent_tolerance_hint')" />
            <x-input-field name="settings[finance][reconciliation][skonto_percent]" type="number" min="0" max="10" step="0.1"
                           :label="__('settings.reconciliation.skonto_percent')"
                           error="settings.finance.reconciliation.skonto_percent"
                           :value="old('settings.finance.reconciliation.skonto_percent', data_get($stored, 'finance.reconciliation.skonto_percent', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => '3'])"
                           :hint="__('settings.reconciliation.skonto_percent_hint')" />
        </x-form-group>

        {{-- ANLAGEN (MVP-892): Wertgrenzen für GWG und Sammelposten, netto. --}}
        <x-form-group :legend="__('settings.fixed_assets.heading')" icon="inventory" tone="info" cols="2" compact
                      :description="__('settings.fixed_assets.description')">
            <x-input-field name="settings[finance][fixed_assets][gwg_limit]" type="number" min="0" step="0.01"
                           :label="__('settings.fixed_assets.gwg_limit')"
                           error="settings.finance.fixed_assets.gwg_limit"
                           :value="old('settings.finance.fixed_assets.gwg_limit', data_get($stored, 'finance.fixed_assets.gwg_limit', ''))"
                           placeholder="800" />
            <x-input-field name="settings[finance][fixed_assets][pool_lower]" type="number" min="0" step="0.01"
                           :label="__('settings.fixed_assets.pool_lower')"
                           error="settings.finance.fixed_assets.pool_lower"
                           :value="old('settings.finance.fixed_assets.pool_lower', data_get($stored, 'finance.fixed_assets.pool_lower', ''))"
                           placeholder="250" />
            <x-input-field name="settings[finance][fixed_assets][pool_upper]" type="number" min="0" step="0.01"
                           :label="__('settings.fixed_assets.pool_upper')"
                           error="settings.finance.fixed_assets.pool_upper"
                           :value="old('settings.finance.fixed_assets.pool_upper', data_get($stored, 'finance.fixed_assets.pool_upper', ''))"
                           placeholder="1000" />
            <x-input-field name="settings[finance][fixed_assets][pool_years]" type="number" min="0" step="1"
                           :label="__('settings.fixed_assets.pool_years')"
                           error="settings.finance.fixed_assets.pool_years"
                           :value="old('settings.finance.fixed_assets.pool_years', data_get($stored, 'finance.fixed_assets.pool_years', ''))"
                           placeholder="5" />
            <x-input-field name="settings[finance][fixed_assets][replacement_inflation_pct]" type="number" min="0" max="50" step="0.1"
                           :label="__('settings.fixed_assets.replacement_inflation_pct')"
                           error="settings.finance.fixed_assets.replacement_inflation_pct"
                           :value="old('settings.finance.fixed_assets.replacement_inflation_pct', data_get($stored, 'finance.fixed_assets.replacement_inflation_pct', ''))"
                           placeholder="0" />
        </x-form-group>

        {{-- VERSAND UND ZOLL (MVP-1007): Absenderangaben der Zollpapiere. --}}
        <x-form-group :legend="__('settings.shipping.heading')" icon="local_shipping" tone="info" cols="2" compact
                      :description="__('settings.shipping.description')">
            <x-input-field name="settings[shipping][eori_number]" maxlength="17" class="uppercase"
                           :label="__('settings.shipping.eori_number')"
                           error="settings.shipping.eori_number"
                           :value="old('settings.shipping.eori_number', data_get($stored, 'shipping.eori_number', ''))"
                           :hint="__('settings.shipping.eori_number_hint')" />
        </x-form-group>

        {{-- ONLINE-ZAHLUNG (MVP-1067): Anbieter sind Plugins mit eigenen Zugangsdaten. --}}
        @php($paymentProviders = app(\App\Plugins\PluginManager::class)->all()->filter(fn ($plugin): bool => $plugin instanceof \App\Plugins\Contracts\OnlinePaymentProvider))
        <x-form-group :legend="__('settings.payments.heading')" icon="credit_card" tone="info" cols="2" compact
                      :description="__('settings.payments.description')">
            <x-select-field name="settings[payments][online][provider]" :label="__('settings.payments.provider')"
                            error="settings.payments.online.provider" :hint="__('settings.payments.provider_hint')">
                <option value="">{{ __('settings.payments.provider_auto') }}</option>
                @foreach ($paymentProviders as $provider)
                    <option value="{{ $provider->onlinePaymentProviderId() }}" @selected(old('settings.payments.online.provider', data_get($stored, 'payments.online.provider', '')) === $provider->onlinePaymentProviderId())>{{ $provider->name() }}</option>
                @endforeach
            </x-select-field>
            <x-checkbox-field name="settings[payments][online][on_documents]" tone="info"
                              :label="__('settings.payments.on_documents')"
                              :hint="__('settings.payments.on_documents_hint')"
                              error="settings.payments.online.on_documents"
                              :checked="(string) old('settings.payments.online.on_documents', data_get($stored, 'payments.online.on_documents', true) ? '1' : '0') === '1'" />
        </x-form-group>

        {{-- KI-ASSISTENTEN (MVP-1063/1065): MCP-Zugriff ist Opt-in je Organisation. --}}
        <x-form-group :legend="__('settings.mcp.heading')" icon="smart_toy" tone="warning" cols="1" compact
                      :description="__('settings.mcp.description')">
            <x-checkbox-field name="settings[mcp][enabled]" tone="warning"
                              :label="__('settings.mcp.enabled')"
                              :hint="__('settings.mcp.enabled_hint')"
                              error="settings.mcp.enabled"
                              :checked="(string) old('settings.mcp.enabled', data_get($stored, 'mcp.enabled') ? '1' : '0') === '1'" />
        </x-form-group>

        {{-- KUNDENPORTAL (MVP-1237): sichtbarer Zeitraum der Portalzeiten, je Kunde abweichbar. --}}
        <x-form-group :legend="__('settings.customer_portal.heading')" icon="support_agent" tone="info" cols="1" compact
                      :description="__('settings.customer_portal.description')">
            <x-input-field name="settings[customer_portal][time_entry_years]" type="number" min="0" max="50"
                           :label="__('settings.customer_portal.time_entry_years')"
                           :hint="__('settings.customer_portal.time_entry_years_hint')"
                           error="settings.customer_portal.time_entry_years"
                           :value="old('settings.customer_portal.time_entry_years', data_get($stored, 'customer_portal.time_entry_years', ''))"
                           placeholder="3" />
        </x-form-group>

        {{-- MIETBEDINGUNGEN (MVP-895): Übergabe nur mit unterschriebener Fassung. --}}
        <x-form-group :legend="__('settings.rental_terms.heading')" icon="handshake" tone="info" cols="1" compact
                      :description="__('settings.rental_terms.description')">
            <x-checkbox-field name="settings[rental][require_signed_terms]" tone="info"
                              :label="__('settings.rental_terms.require_signed')"
                              error="settings.rental.require_signed_terms"
                              :checked="(string) old('settings.rental.require_signed_terms', data_get($stored, 'rental.require_signed_terms') ? '1' : '0') === '1'" />
            {{-- Direktbuchung im Kundenportal (MVP-916). --}}
            <x-checkbox-field name="settings[rental][portal_direct_booking]" tone="info"
                              :label="__('settings.rental_terms.portal_direct_booking')"
                              :hint="__('settings.rental_terms.portal_direct_booking_hint')"
                              error="settings.rental.portal_direct_booking"
                              :checked="(string) old('settings.rental.portal_direct_booking', data_get($stored, 'rental.portal_direct_booking') ? '1' : '0') === '1'" />
            {{-- Online-Vorauszahlung der Direktbuchung (MVP-1515). --}}
            <x-input-field name="settings[rental][portal_prepayment_percent]" type="number" min="0" max="100"
                           :label="__('settings.rental_terms.prepayment_percent')"
                           :hint="__('settings.rental_terms.prepayment_percent_hint')"
                           error="settings.rental.portal_prepayment_percent"
                           :value="old('settings.rental.portal_prepayment_percent', data_get($stored, 'rental.portal_prepayment_percent', ''))"
                           placeholder="0" />
            <x-input-field name="settings[rental][prepayment_hold_minutes]" type="number" min="10" max="1440"
                           :label="__('settings.rental_terms.prepayment_hold_minutes')"
                           :hint="__('settings.rental_terms.prepayment_hold_minutes_hint')"
                           error="settings.rental.prepayment_hold_minutes"
                           :value="old('settings.rental.prepayment_hold_minutes', data_get($stored, 'rental.prepayment_hold_minutes', ''))"
                           placeholder="30" />
            {{-- Geofence-Abweichung verliehener Geräte (MVP-975). --}}
            <x-input-field name="settings[rental][geofence_radius_m]" type="number" min="50" max="50000"
                           :label="__('settings.rental_terms.geofence_radius')"
                           :hint="__('settings.rental_terms.geofence_radius_hint')"
                           error="settings.rental.geofence_radius_m"
                           :value="old('settings.rental.geofence_radius_m', data_get($stored, 'rental.geofence_radius_m', ''))"
                           placeholder="500" />
        </x-form-group>

        {{-- FUHRPARK (MVP-994): Fahrtsperre bei überfälliger Pflichtprüfung. --}}
        <x-form-group :legend="__('settings.fleet.heading')" icon="directions_car" tone="info" cols="1" compact>
            <x-checkbox-field name="settings[fleet][block_trips_on_overdue_inspection]" tone="info"
                              :label="__('settings.fleet.block_trips')"
                              :hint="__('settings.fleet.block_trips_hint')"
                              error="settings.fleet.block_trips_on_overdue_inspection"
                              :checked="(string) old('settings.fleet.block_trips_on_overdue_inspection', data_get($stored, 'fleet.block_trips_on_overdue_inspection') ? '1' : '0') === '1'" />
            {{-- Abstand der Führerscheinkontrolle (MVP-1589). --}}
            <x-input-field name="settings[fleet][license_check_interval_months]" type="number" min="1" max="60"
                           :label="__('settings.fleet.license_interval')"
                           :hint="__('settings.fleet.license_interval_hint')"
                           error="settings.fleet.license_check_interval_months"
                           :value="old('settings.fleet.license_check_interval_months', data_get($stored, 'fleet.license_check_interval_months', ''))"
                           placeholder="6" />
        </x-form-group>

        {{-- HELPDESK (MVP-1589): Prioritätsmatrix Auswirkung × Dringlichkeit; leere Zelle = Standard. --}}
        <x-form-group :legend="__('settings.helpdesk_matrix.heading')" icon="grid_on" tone="info" cols="3" compact
                      :description="__('settings.helpdesk_matrix.description')">
            @foreach (\App\Enums\ServiceTicket\TicketSeverity::cases() as $impact)
                @foreach (\App\Enums\ServiceTicket\TicketSeverity::cases() as $urgency)
                    @php($cell = 'helpdesk.priority_matrix.' . $impact->value . '.' . $urgency->value)
                    <x-select-field name="settings[helpdesk][priority_matrix][{{ $impact->value }}][{{ $urgency->value }}]"
                                    id="settings-helpdesk-matrix-{{ $impact->value }}-{{ $urgency->value }}"
                                    :label="__('settings.helpdesk_matrix.cell', ['impact' => $impact->label(), 'urgency' => $urgency->label()])"
                                    error="settings.{{ $cell }}">
                        <option value="">{{ __('settings.helpdesk_matrix.default', ['priority' => app(\App\Services\ServiceTicket\TicketIncidentService::class)->defaultPriority($impact, $urgency)->label()]) }}</option>
                        @foreach (\App\Enums\ServiceTicket\ServiceTicketPriority::cases() as $priority)
                            <option value="{{ $priority->value }}" @selected((string) old('settings.' . $cell, data_get($stored, $cell, '')) === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </x-select-field>
                @endforeach
            @endforeach
        </x-form-group>

        {{-- KAPAZITÄT (MVP-1589): Schwelle der Frühwarnung. --}}
        <x-form-group :legend="__('settings.capacity.heading')" icon="groups" tone="warning" cols="2" compact>
            <x-input-field name="settings[hr][capacity][threshold]" type="number" min="50" max="300"
                           :label="__('settings.capacity.threshold')"
                           :hint="__('settings.capacity.threshold_hint')"
                           error="settings.hr.capacity.threshold"
                           :value="old('settings.hr.capacity.threshold', data_get($stored, 'hr.capacity.threshold', ''))"
                           placeholder="100" />
        </x-form-group>

        {{-- INVESTITIONEN (MVP-1589): Schwelle der zweiten Freigabestufe. --}}
        <x-form-group :legend="__('settings.investments.heading')" icon="savings" tone="info" cols="2" compact>
            <x-input-field name="settings[investments][approval_threshold]" type="number" min="0" step="0.01"
                           :label="__('settings.investments.approval_threshold')"
                           :hint="__('settings.investments.approval_threshold_hint')"
                           error="settings.investments.approval_threshold"
                           :value="old('settings.investments.approval_threshold', data_get($stored, 'investments.approval_threshold', ''))"
                           :placeholder="(string) config('investments.approval_threshold', 10000)" />
        </x-form-group>

        {{-- DOMAINS (MVP-1614): Kosten- und Saldowarnung der Domainverwaltung. --}}
        <x-form-group :legend="__('settings.domain.heading')" icon="language" tone="info" cols="2" compact>
            <x-input-field name="settings[domain][renewal_cost_threshold]" type="number" min="0" step="0.01"
                           :label="__('settings.domain.renewal_cost_threshold')"
                           :hint="__('settings.domain.renewal_cost_threshold_hint')"
                           error="settings.domain.renewal_cost_threshold"
                           :value="old('settings.domain.renewal_cost_threshold', data_get($stored, 'domain.renewal_cost_threshold', ''))" />
            <x-input-field name="settings[domain][balance_threshold]" type="number" min="0" step="0.01"
                           :label="__('settings.domain.balance_threshold')"
                           :hint="__('settings.domain.balance_threshold_hint')"
                           error="settings.domain.balance_threshold"
                           :value="old('settings.domain.balance_threshold', data_get($stored, 'domain.balance_threshold', ''))" />
        </x-form-group>

        {{-- BERICHTE (MVP-1589): CSV-Export je Organisation. --}}
        <x-form-group :legend="__('settings.report_csv.heading')" icon="table_view" tone="info" cols="2" compact>
            <x-select-field name="settings[reports][csv_delimiter_name]" :label="__('settings.report_csv.delimiter')" error="settings.reports.csv_delimiter_name">
                <option value="">{{ __('settings.report_csv.system_default') }}</option>
                @foreach (['semicolon', 'comma', 'tab'] as $delimiter)
                    <option value="{{ $delimiter }}" @selected((string) old('settings.reports.csv_delimiter_name', data_get($stored, 'reports.csv_delimiter_name', '')) === $delimiter)>{{ __('settings.report_csv.' . $delimiter) }}</option>
                @endforeach
            </x-select-field>
            @php($metaLines = (string) old('settings.reports.csv_meta_lines', data_get($stored, 'reports.csv_meta_lines', '')))
            <x-select-field name="settings[reports][csv_meta_lines]" :label="__('settings.report_csv.meta_lines')" :hint="__('settings.report_csv.meta_lines_hint')" error="settings.reports.csv_meta_lines">
                <option value="">{{ __('settings.report_csv.system_default') }}</option>
                <option value="1" @selected($metaLines === '1')>{{ __('settings.report_csv.meta_on') }}</option>
                <option value="0" @selected($metaLines === '0')>{{ __('settings.report_csv.meta_off') }}</option>
            </x-select-field>
        </x-form-group>

        {{-- BEWERBUNGEN (MVP-1589): Löschfrist abgelehnter Bewerbungen. --}}
        <x-form-group :legend="__('settings.applications_retention.heading')" icon="person_remove" tone="warning" cols="2" compact>
            <x-input-field name="settings[applications][rejected_retention_months]" type="number" min="1" max="36"
                           :label="__('settings.applications_retention.months')"
                           :hint="__('settings.applications_retention.months_hint')"
                           error="settings.applications.rejected_retention_months"
                           :value="old('settings.applications.rejected_retention_months', data_get($stored, 'applications.rejected_retention_months', ''))"
                           :placeholder="(string) config('applications.rejected_retention_months', 6)" />
        </x-form-group>

        {{-- REKLAMATIONSMUSTER (MVP-886): Schwelle und Zeitfenster für Bericht und Hinweis. --}}
        <x-form-group :legend="__('settings.claims_pattern.heading')" icon="troubleshoot" tone="warning" cols="2" compact
                      :description="__('settings.claims_pattern.description')">
            <x-input-field name="settings[claims][pattern][threshold]" type="number" min="2" max="50"
                           :label="__('settings.claims_pattern.threshold')"
                           error="settings.claims.pattern.threshold"
                           :value="old('settings.claims.pattern.threshold', data_get($stored, 'claims.pattern.threshold', ''))"
                           placeholder="3" />
            <x-input-field name="settings[claims][pattern][window_days]" type="number" min="7" max="365"
                           :label="__('settings.claims_pattern.window_days')"
                           error="settings.claims.pattern.window_days"
                           :value="old('settings.claims.pattern.window_days', data_get($stored, 'claims.pattern.window_days', ''))"
                           placeholder="90" />
        </x-form-group>

        {{-- WIEDERKEHRENDE PROBLEME (MVP-926): Ticket-Häufung je Kunde/Objekt. --}}
        <x-form-group :legend="__('settings.recurring_tickets.heading')" icon="repeat" tone="warning" cols="2" compact
                      :description="__('settings.recurring_tickets.description')">
            <x-input-field name="settings[reporting][recurring_tickets][threshold]" type="number" min="2" max="50"
                           :label="__('settings.recurring_tickets.threshold')"
                           error="settings.reporting.recurring_tickets.threshold"
                           :value="old('settings.reporting.recurring_tickets.threshold', data_get($stored, 'reporting.recurring_tickets.threshold', ''))"
                           placeholder="3" />
            <x-input-field name="settings[reporting][recurring_tickets][window_days]" type="number" min="7" max="365"
                           :label="__('settings.recurring_tickets.window_days')"
                           error="settings.reporting.recurring_tickets.window_days"
                           :value="old('settings.reporting.recurring_tickets.window_days', data_get($stored, 'reporting.recurring_tickets.window_days', ''))"
                           placeholder="90" />
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

        {{-- PROJEKT-SOLLZEITEN (Feature 194): Toleranz für Befunde unter Soll. --}}
        <x-form-group :legend="__('settings.project_targets.heading')" icon="flag" tone="info" cols="2" compact
                      :description="__('settings.project_targets.description')">
            <x-input-field name="settings[project][target_tolerance_minutes]" type="number" min="0" max="480" step="1"
                           :label="__('settings.project_targets.tolerance_minutes')"
                           error="settings.project.target_tolerance_minutes" inputmode="numeric"
                           :value="old('settings.project.target_tolerance_minutes', data_get($stored, 'project.target_tolerance_minutes', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => '15'])"
                           :hint="__('settings.project_targets.tolerance_minutes_hint')" />
        </x-form-group>

        {{-- PLAN/IST (MVP-1552): Schwellen der Anwesenheitswarnungen. --}}
        <x-form-group :legend="__('settings.plan_ist.heading')" icon="compare_arrows" tone="info" cols="2" compact
                      :description="__('settings.plan_ist.description')">
            <x-input-field name="settings[plan_ist][late_start_minutes]" type="number" min="0" max="240" step="1"
                           :label="__('settings.plan_ist.late_start_minutes')"
                           error="settings.plan_ist.late_start_minutes" inputmode="numeric"
                           :value="old('settings.plan_ist.late_start_minutes', data_get($stored, 'plan_ist.late_start_minutes', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => '15'])"
                           :hint="__('settings.plan_ist.late_start_minutes_hint')" />
            <x-input-field name="settings[plan_ist][hours_diff_percent]" type="number" min="0" max="100" step="1"
                           :label="__('settings.plan_ist.hours_diff_percent')"
                           error="settings.plan_ist.hours_diff_percent" inputmode="numeric"
                           :value="old('settings.plan_ist.hours_diff_percent', data_get($stored, 'plan_ist.hours_diff_percent', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => '10'])"
                           :hint="__('settings.plan_ist.hours_diff_percent_hint')" />
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
        {{-- Standort aus Protokollfotos (MVP-1573): Default aus, Hinweis auf den Datenschutz. --}}
        <x-form-group :legend="__('settings.protocol.heading')" icon="location_on" tone="info" cols="1" compact
                      :description="__('settings.protocol.description')">
            <x-checkbox-field name="settings[protocol][photo_geo]" tone="info"
                             :label="__('settings.protocol.photo_geo')"
                             error="settings.protocol.photo_geo"
                             :checked="(string) old('settings.protocol.photo_geo', data_get($stored, 'protocol.photo_geo', '0')) === '1'"
                             :hint="__('settings.protocol.photo_geo_hint')" />
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
        @foreach ($uiGroups as $group => $fields)
            <x-form-group :legend="__('settings.ui.' . $group . '.heading')" :icon="$tabs['ui']['icon']" :tone="$tabs['ui']['tone']" cols="3" compact>
                @foreach ($fields as $field => $val)
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

        <x-form-group :legend="__('settings.routing.vroom.heading')" :icon="$tabs['routing']['icon']" :tone="$tabs['routing']['tone']" cols="2" compact>
            <x-input-field name="settings[routing][vroom][base_url]" span="2" inputmode="url"
                           :label="__('settings.routing.vroom.base_url')"
                           error="settings.routing.vroom.base_url"
                           :value="old('settings.routing.vroom.base_url', data_get($stored, 'routing.vroom.base_url', ''))"
                           :placeholder="(string) config('routing.vroom.base_url') !== '' ? __('settings.placeholder_default', ['value' => (string) config('routing.vroom.base_url')]) : __('settings.routing.vroom.not_configured')" />
            <x-input-field name="settings[routing][vroom][timeout]" type="number" min="1" max="600"
                           :label="__('settings.routing.vroom.timeout')"
                           error="settings.routing.vroom.timeout"
                           :value="old('settings.routing.vroom.timeout', data_get($stored, 'routing.vroom.timeout', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => (string) config('routing.vroom.timeout')])" />
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
            <x-input-field name="settings[routing][tiles][attribution]" span="2" maxlength="255"
                           :label="__('settings.routing.tiles.attribution')"
                           error="settings.routing.tiles.attribution"
                           :hint="__('settings.routing.tiles.attribution_hint')"
                           :value="old('settings.routing.tiles.attribution', data_get($stored, 'routing.tiles.attribution', ''))"
                           :placeholder="__('settings.placeholder_default', ['value' => html_entity_decode(strip_tags((string) config('routing.tiles.attribution')))])" />
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
                           error="settings.travel.label" maxlength="50" :placeholder="__('Anfahrt')"
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
            @php($holidayLocale = (string) old('settings.holidays.locale', data_get($stored, 'holidays.locale', '')))
            <x-select-field name="settings[holidays][locale]" span="2"
                            :label="__('settings.region.holiday_locale')"
                            error="settings.holidays.locale"
                            :hint="__('settings.region.holiday_locale_hint')">
                <option value="">{{ __('settings.placeholder_default', ['value' => \Locale::getDisplayName((string) config('holidays.locale', 'de_DE'), app()->getLocale())]) }}</option>
                @foreach ($registry->definition('holidays.locale')->resolvedOptions() ?? [] as $locale)
                    <option value="{{ $locale }}" @selected($holidayLocale === (string) $locale)>{{ \Locale::getDisplayName((string) $locale, app()->getLocale()) }}</option>
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
                           :value="old('settings.maintenance.until', $organization?->maintenanceSettings()['until']?->orgTz()->format('Y-m-d\\TH:i') ?? '')"
                           :hint="__('settings.maintenance.until_hint')" />
            <x-checkbox-field name="settings[maintenance][block_ingest]" tone="warning"
                             :label="__('settings.maintenance.block_ingest')"
                             error="settings.maintenance.block_ingest"
                             :checked="(string) old('settings.maintenance.block_ingest', data_get($stored, 'maintenance.block_ingest', '0')) === '1'"
                             :hint="__('settings.maintenance.block_ingest_hint')" />
        </x-form-group>
    </div>
</x-form-group>
