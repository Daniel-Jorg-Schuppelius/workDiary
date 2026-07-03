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

    <x-form-group :legend="__('Stammdaten')" icon="import_export" tone="primary" cols="2">
        <x-select-field name="supplier" :label="__('procurement.field.supplier')" required>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->sqid }}" @selected($editing && $source->supplier_id === $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="name" :label="__('procurement.catalog.field.name')" required maxlength="191" :value="$val('name')" />

        <x-select-field name="format" :label="__('procurement.catalog.col.format')" required>
            @foreach (['csv', 'datanorm', 'bmecat'] as $f)
                <option value="{{ $f }}" @selected($val('format', 'csv') === $f || ($editing && $source->format->value === $f && old('format') === null))>{{ __('procurement.catalog.format.' . $f) }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="encoding" :label="__('procurement.catalog.field.encoding')" required>
            @foreach (['UTF-8', 'ISO-8859-1', 'Windows-1252'] as $enc)
                <option value="{{ $enc }}" @selected($val('encoding', 'UTF-8') === $enc)>{{ $enc }}</option>
            @endforeach
        </x-select-field>

        <x-input-field name="delimiter" :label="__('procurement.catalog.field.delimiter')" required maxlength="4" :value="$val('delimiter', ';')" />
        <x-select-field name="decimal_separator" :label="__('procurement.catalog.field.decimal_separator')" required>
            <option value="," @selected($val('decimal_separator', ',') === ',')>,  (1.234,56)</option>
            <option value="." @selected($val('decimal_separator', ',') === '.')>.  (1,234.56)</option>
        </x-select-field>

        <x-checkbox-field name="has_header" :label="__('procurement.catalog.field.has_header')" :checked="(bool) $val('has_header', true)" value="1" />
    </x-form-group>

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
        <x-input-field name="fetch_interval_minutes" type="number" min="0" :label="__('procurement.catalog.remote.interval')"
                       :value="$val('fetch_interval_minutes')" :placeholder="__('procurement.catalog.remote.interval_off')" />
    </x-form-group>
    <p class="text-xs opacity-60">{{ __('procurement.catalog.remote.hint') }}</p>

    <x-form-group :legend="__('procurement.oci.punchout.legend')" icon="shopping_cart_checkout" tone="primary" cols="2">
        <x-input-field name="punchout_url" type="url" :label="__('procurement.oci.punchout.url')" :value="$val('punchout_url')" />
        <x-input-field name="punchout_username" :label="__('procurement.catalog.remote.username')" :value="$val('punchout_username')" autocomplete="off" />
        <x-input-field name="punchout_password" type="password" :label="__('procurement.catalog.remote.password')"
                       :placeholder="$editing ? __('procurement.catalog.remote.password_keep') : null" autocomplete="new-password" />
    </x-form-group>
    <p class="text-xs opacity-60">{{ __('procurement.oci.punchout.hint') }}</p>
</x-modal>
</content>
