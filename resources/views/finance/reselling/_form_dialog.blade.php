{{--
  Created on   : Thu Sep 03 2026
  License      : AGPL-3.0-or-later

  Upload-Dialog für einen neuen Abgleichslauf (Feature 151): Telekom-CSV,
  Quality-Hosting-XLSX, optional Preisliste und Zuordnungsdatei, Stichtag
  und Suchfenster. Der Lauf selbst läuft im Hintergrund.
--}}
<x-modal
    :title="__('reselling.dialog.title')"
    icon="upload"
    tone="primary"
    size="lg"
    :action="route('finance.reselling.store')"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('reselling.dialog.submit')"
>
    <div class="text-sm text-base-content/70">{{ __('reselling.dialog.hint') }}</div>

    <div>
        <label class="label" for="reselling-telekom">
            <span class="label-text">{{ __('reselling.dialog.telekom') }}</span>
        </label>
        <input id="reselling-telekom" type="file" name="telekom" accept=".csv,.txt,text/csv,text/plain"
               class="file-input file-input-bordered w-full @error('telekom') file-input-error @enderror">
        @error('telekom')
            <p class="text-error text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="label" for="reselling-qualityhosting">
            <span class="label-text">{{ __('reselling.dialog.qualityhosting') }}</span>
        </label>
        <input id="reselling-qualityhosting" type="file" name="qualityhosting" accept=".xlsx,.xlsm,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
               class="file-input file-input-bordered w-full @error('qualityhosting') file-input-error @enderror">
        @error('qualityhosting')
            <p class="text-error text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="label" for="reselling-pricelist">
            <span class="label-text">{{ __('reselling.dialog.pricelist') }}</span>
        </label>
        <input id="reselling-pricelist" type="file" name="pricelist" accept=".xlsx,.xlsm,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
               class="file-input file-input-bordered w-full @error('pricelist') file-input-error @enderror">
        @error('pricelist')
            <p class="text-error text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="label" for="reselling-map">
            <span class="label-text">{{ __('reselling.dialog.map') }}</span>
        </label>
        <input id="reselling-map" type="file" name="map" accept=".csv,.txt,text/csv,text/plain"
               class="file-input file-input-bordered w-full @error('map') file-input-error @enderror">
        <p class="text-xs text-muted mt-1">{{ __('reselling.dialog.map_hint') }}</p>
        @error('map')
            <p class="text-error text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
            <label class="label" for="reselling-reference">
                <span class="label-text">{{ __('reselling.dialog.reference') }}</span>
            </label>
            <input id="reselling-reference" type="date" name="reference_date" value="{{ old('reference_date', $defaultReference) }}"
                   class="input input-bordered w-full @error('reference_date') input-error @enderror">
            <p class="text-xs text-muted mt-1">{{ __('reselling.dialog.reference_hint') }}</p>
        </div>
        <div>
            <label class="label" for="reselling-before">
                <span class="label-text">{{ __('reselling.dialog.before') }}</span>
            </label>
            <input id="reselling-before" type="number" name="window_before" min="0" max="365" value="{{ old('window_before', \App\Services\Reselling\Marketplace\ReconciliationOptions::DEFAULT_BEFORE) }}"
                   class="input input-bordered w-full @error('window_before') input-error @enderror">
        </div>
        <div>
            <label class="label" for="reselling-after">
                <span class="label-text">{{ __('reselling.dialog.after') }}</span>
            </label>
            <input id="reselling-after" type="number" name="window_after" min="0" max="1825" value="{{ old('window_after', \App\Services\Reselling\Marketplace\ReconciliationOptions::DEFAULT_AFTER) }}"
                   class="input input-bordered w-full @error('window_after') input-error @enderror">
        </div>
    </div>
    <p class="text-xs text-muted">{{ __('reselling.dialog.window_hint') }}</p>

    <label class="flex items-start gap-2 text-sm cursor-pointer" for="reselling-strict">
        <input id="reselling-strict" type="checkbox" name="strict_products" value="1" class="checkbox checkbox-sm mt-0.5" @checked(old('strict_products'))>
        <span>
            <span class="font-medium">{{ __('reselling.dialog.strict') }}</span>
            <span class="block text-xs text-muted">{{ __('reselling.dialog.strict_hint') }}</span>
        </span>
    </label>
</x-modal>
