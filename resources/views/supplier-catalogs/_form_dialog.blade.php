{{--
  Created on   : Sun Jun 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $isDialog, $suppliers, optional $source (null = Anlegen) --}}
@php
    $isDialog = $isDialog ?? false;
    $source = $source ?? null;
    $editing = $source !== null;
    $val = fn (string $field, $default = null) => old($field, $editing ? data_get($source, $field) : $default);
@endphp

<x-modal
    :title="$editing ? __('procurement.catalog.action.edit_source') : __('procurement.catalog.action.new_source')"
    :eyebrow="__('procurement.catalog.title')"
    icon="import_export"
    tone="primary"
    :action="$editing ? route('supplier-catalogs.update', $source) : route('supplier-catalogs.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="$editing ? __('Speichern') : __('Anlegen')">
    @if ($editing)
        @method('PUT')
    @endif
    @if ($isDialog)
        <input type="hidden" name="_dialog_url"
               value="{{ ($editing ? route('supplier-catalogs.edit', $source) : route('supplier-catalogs.create')) . '?dialog=1' }}">
    @endif

    @php($omdValues = (array) old('omd', $editing && is_array($source->omd_config) ? $source->omd_config : []))
    <div x-data="catalogSourceForm(@js(old('format', $editing ? $source->format->value : 'csv')))">
    <x-form-group :legend="__('Stammdaten')" icon="import_export" tone="primary" cols="2">
        <x-select-field name="supplier" :label="__('procurement.field.supplier')" required>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->sqid }}" @selected($editing && $source->supplier_id === $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="name" :label="__('procurement.catalog.field.name')" required maxlength="191" :value="$val('name')" />

        <x-select-field name="format" :label="__('procurement.catalog.col.format')" required x-model="format">
            @foreach (['csv', 'xlsx', 'datanorm', 'bmecat', 'omd'] as $f)
                <option value="{{ $f }}" @selected($val('format', 'csv') === $f || ($editing && $source->format->value === $f && old('format') === null))>{{ __('procurement.catalog.format.' . $f) }}</option>
            @endforeach
        </x-select-field>
        <div class="contents" x-show="!isOmd()">
        <x-select-field name="encoding" :label="__('procurement.catalog.field.encoding')" required>
            @foreach (['UTF-8', 'ISO-8859-1', 'Windows-1252', 'CP850'] as $enc)
                <option value="{{ $enc }}" @selected($val('encoding', 'UTF-8') === $enc)>{{ $enc === 'CP850' ? 'CP850 (DATANORM)' : $enc }}</option>
            @endforeach
        </x-select-field>

        <x-input-field name="delimiter" :label="__('procurement.catalog.field.delimiter')" required maxlength="4" :value="$val('delimiter', ';')" />
        <x-select-field name="decimal_separator" :label="__('procurement.catalog.field.decimal_separator')" required>
            <option value="," @selected($val('decimal_separator', ',') === ',')>,  (1.234,56)</option>
            <option value="." @selected($val('decimal_separator', ',') === '.')>.  (1,234.56)</option>
        </x-select-field>

        <x-checkbox-field name="has_header" :label="__('procurement.catalog.field.has_header')" :checked="(bool) $val('has_header', true)" value="1" />
        <x-input-field name="sheet_name" :label="__('procurement.catalog.field.sheet_name')" maxlength="64"
                       :value="$val('sheet_name')" :placeholder="__('procurement.catalog.field.sheet_name_first')" />
        <x-input-field name="expected_customer_no" :label="__('procurement.catalog.field.expected_customer_no')" maxlength="32"
                       :value="$val('expected_customer_no')" :hint="__('procurement.catalog.field.expected_customer_no_hint')" />
        </div>
    </x-form-group>

    {{-- Open Masterdata (MVP-1072): Webservice des Großhändlers statt Datei. --}}
    <div x-show="isOmd()" x-cloak>
        <x-form-group :legend="__('procurement.omd.legend')" icon="hub" tone="primary" cols="2">
            <x-input-field name="omd[token_url]" type="url" :label="__('procurement.omd.field.token_url')" error="omd.token_url" :value="$omdValues['token_url'] ?? ''" />
            <x-input-field name="omd[base_url]" type="url" :label="__('procurement.omd.field.base_url')" error="omd.base_url" :value="$omdValues['base_url'] ?? ''" :hint="__('procurement.omd.field.base_url_hint')" />
            <x-input-field name="omd[client_id]" :label="__('procurement.omd.field.client_id')" error="omd.client_id" :value="$omdValues['client_id'] ?? ''" autocomplete="off" />
            <x-input-field name="omd[client_secret]" type="password" :label="__('procurement.omd.field.client_secret')" error="omd.client_secret"
                           :placeholder="$editing && ($omdValues['client_secret'] ?? '') !== '' ? __('procurement.catalog.remote.password_keep') : null" autocomplete="new-password" :hint="__('procurement.omd.field.client_secret_hint')" />
            <x-input-field name="omd[username]" :label="__('procurement.omd.field.username')" error="omd.username" :value="$omdValues['username'] ?? ''" autocomplete="off" />
            <x-input-field name="omd[password]" type="password" :label="__('procurement.omd.field.password')" error="omd.password"
                           :placeholder="$editing && ($omdValues['password'] ?? '') !== '' ? __('procurement.catalog.remote.password_keep') : null" autocomplete="new-password" />
            <x-input-field name="omd[customer_number]" maxlength="50" :label="__('procurement.omd.field.customer_number')" error="omd.customer_number" :value="$omdValues['customer_number'] ?? ''" />
            <x-checkbox-field name="omd[customer_number_in_login]" value="1" :label="__('procurement.omd.field.customer_number_in_login')" error="omd.customer_number_in_login"
                              :checked="filter_var($omdValues['customer_number_in_login'] ?? false, FILTER_VALIDATE_BOOL)" :hint="__('procurement.omd.field.customer_number_in_login_hint')" />
            <x-select-field name="omd[grant_type]" :label="__('procurement.omd.field.grant_type')" error="omd.grant_type">
                @foreach (['password', 'client_credentials'] as $grant)
                    <option value="{{ $grant }}" @selected(($omdValues['grant_type'] ?? 'password') === $grant)>{{ __('procurement.omd.field.grant_' . $grant) }}</option>
                @endforeach
            </x-select-field>
            <x-select-field name="omd[package_mode]" :label="__('procurement.omd.field.package_mode')" error="omd.package_mode">
                @foreach (['pipe', 'exploded'] as $mode)
                    <option value="{{ $mode }}" @selected(($omdValues['package_mode'] ?? 'pipe') === $mode)>{{ __('procurement.omd.field.package_' . $mode) }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="omd[scope]" maxlength="191" :label="__('procurement.omd.field.scope')" error="omd.scope" :value="$omdValues['scope'] ?? 'openMasterdata'" />
            <x-input-field name="omd[customer_id]" maxlength="50" :label="__('procurement.omd.field.customer_id')" error="omd.customer_id" :value="$omdValues['customer_id'] ?? ''" :hint="__('procurement.omd.field.customer_id_hint')" />
        </x-form-group>
        <p class="text-xs opacity-60">{{ __('procurement.omd.hint') }}</p>
    </div>

    <div x-show="isOmd()" x-cloak>
        <x-form-group :legend="__('procurement.omd.refresh_legend')" icon="schedule" tone="primary" cols="2">
            <x-input-field name="fetch_interval_minutes" id="fetch_interval_minutes_omd" type="number" min="0" :label="__('procurement.catalog.remote.interval')"
                           :value="$val('fetch_interval_minutes')" :placeholder="__('procurement.catalog.remote.interval_off')" :hint="__('procurement.omd.refresh_hint')" x-bind:disabled="!isOmd()" />
        </x-form-group>
    </div>
    <div x-show="!isOmd()">
    <x-form-group :legend="__('procurement.catalog.remote.legend')" icon="cloud_download" tone="primary" cols="2">
        <x-select-field name="source_type" :label="__('procurement.catalog.remote.type')">
            @foreach (['upload', 'http', 'ftp', 'sftp'] as $t)
                <option value="{{ $t }}" @selected($val('source_type', 'upload') === $t)>{{ __('procurement.catalog.remote.type_' . $t) }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="remote_url" type="url" :label="__('procurement.catalog.remote.url')" :value="$val('remote_url')" />
        <x-input-field name="remote_host" :label="__('procurement.catalog.remote.host')" :value="$val('remote_host')" />
        <x-input-field name="remote_port" type="number" min="1" max="65535" :label="__('procurement.catalog.remote.port')" :value="$val('remote_port')" />
        <x-input-field name="remote_path" :label="__('procurement.catalog.remote.path')" :value="$val('remote_path')" />
        <x-input-field name="remote_username" :label="__('procurement.catalog.remote.username')" :value="$val('remote_username')" autocomplete="off" />
        <x-input-field name="remote_password" type="password" :label="__('procurement.catalog.remote.password')"
                       :placeholder="$editing ? __('procurement.catalog.remote.password_keep') : null" autocomplete="new-password" />
        <x-input-field name="remote_host_fingerprint" :label="__('procurement.catalog.remote.fingerprint')"
                       :value="$val('remote_host_fingerprint')" :hint="__('procurement.catalog.remote.fingerprint_hint')" />
        <x-input-field name="fetch_interval_minutes" type="number" min="0" :label="__('procurement.catalog.remote.interval')"
                       :value="$val('fetch_interval_minutes')" :placeholder="__('procurement.catalog.remote.interval_off')" x-bind:disabled="isOmd()" />
    </x-form-group>
    <p class="text-xs opacity-60">{{ __('procurement.catalog.remote.hint') }}</p>

    @php($protocol = old('punchout_protocol', $editing ? $source->punchout_protocol->value : \App\Enums\Procurement\PunchoutProtocol::Oci->value))
    <x-form-group :legend="__('procurement.oci.punchout.legend')" icon="shopping_cart_checkout" tone="primary" cols="2">
        <x-select-field name="punchout_protocol" :label="__('procurement.oci.punchout.protocol')">
            @foreach (\App\Enums\Procurement\PunchoutProtocol::cases() as $case)
                <option value="{{ $case->value }}" @selected($protocol === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="punchout_customer_number" :label="__('procurement.ids.customer_number')" maxlength="50"
                       :value="$val('punchout_customer_number')" :hint="__('procurement.ids.customer_number_hint')" />
        <x-input-field name="punchout_url" type="url" :label="__('procurement.oci.punchout.url')" :value="$val('punchout_url')" />
        <x-input-field name="punchout_username" :label="__('procurement.catalog.remote.username')" :value="$val('punchout_username')" autocomplete="off" />
        <x-input-field name="punchout_password" type="password" :label="__('procurement.catalog.remote.password')"
                       :placeholder="$editing ? __('procurement.catalog.remote.password_keep') : null" autocomplete="new-password" />
    </x-form-group>
    <p class="text-xs opacity-60">{{ __('procurement.oci.punchout.hint') }}</p>
    </div>{{-- /!isOmd --}}
    </div>{{-- /x-data --}}
</x-modal>
</content>
