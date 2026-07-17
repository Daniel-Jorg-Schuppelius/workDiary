{{-- Dialog wrapper for a new Project Billing Rule (Lexoffice) --}}
<x-modal
    :title="__('Neue Abrechnungs-Regel')"
    :eyebrow="$project->name"
    icon="receipt_long"
    tone="primary"
    :action="route('projects.billing-rules.store', $project)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Regel speichern')"
>
    <x-form-group :legend="__('Zuordnung')" icon="rule" tone="primary" cols="2">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Tätigkeitsart') }}</label>
            <select name="applies_to_kind" class="select select-bordered w-full">
                <option value="">{{ __('Alle (Fallback)') }}</option>
                @foreach ($kinds as $k)
                    <option value="{{ $k }}">{{ $k }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Item-Typ') }}</label>
            <select name="item_type" class="select select-bordered w-full">
                @foreach ($itemTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('Lexoffice-Artikel') }}</label>
            <select name="lexoffice_article_id" class="select select-bordered w-full">
                <option value="">{{ __('— ohne Artikel —') }}</option>
                @foreach ($articles as $art)
                    <option value="{{ $art->external_id }}">
                        {{ $art->name }}@if ($art->net_unit_price !== null) — {{ number_format((float) $art->net_unit_price, 2, ',', '.') }} €@endif
                    </option>
                @endforeach
            </select>
            @if ($articles->isEmpty())
                <p class="text-xs text-base-content/60 mt-1">
                    {{ __('Noch keine Artikel synchronisiert. Führe :cmd aus.', ['cmd' => 'php artisan lexoffice:sync-articles']) }}
                </p>
            @endif
        </div>
    </x-form-group>

    <x-form-group :legend="__('Preis & Priorität')" icon="payments" tone="info" cols="2">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Einheit') }}</label>
            <input type="text" name="unit_name" placeholder="Stunde" class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('VAT %') }}</label>
            <input type="number" step="0.01" min="0" max="100" name="vat_rate" placeholder="19" class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Preis (netto)') }}</label>
            <input type="number" step="0.0001" min="0" name="net_unit_price" class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Priorität') }}</label>
            <input type="number" step="1" min="0" max="1000" name="priority" value="0" class="input input-bordered w-full">
        </div>
    </x-form-group>

    <x-validation-errors />
</x-modal>
