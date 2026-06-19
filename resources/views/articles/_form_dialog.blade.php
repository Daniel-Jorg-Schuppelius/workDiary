{{-- Erwartet: $article (Article|null), $isDialog, $types, $statuses --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $article ? route('articles.update', $article) : route('articles.store');
    $dialogUrl = ($article ? route('articles.edit', $article) : route('articles.create')) . '?dialog=1';
    $flags = [
        'stockable' => __('article.flag.stockable'),
        'purchasable' => __('article.flag.purchasable'),
        'sellable' => __('article.flag.sellable'),
        'manufacturable' => __('article.flag.manufacturable'),
        'batch_required' => __('article.flag.batch_required'),
        'serial_required' => __('article.flag.serial_required'),
        'shelf_life_required' => __('article.flag.shelf_life_required'),
    ];
@endphp

<x-modal
    :title="$article ? __('article.action.edit') : __('article.action.create')"
    :eyebrow="__('article.title')"
    icon="inventory_2"
    tone="primary"
    :action="$action"
    :method="$article ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$article ? __('Speichern') : __('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <x-form-group :legend="__('Stammdaten')" icon="inventory_2" tone="primary" cols="2">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Name') }} *</label>
            <input name="name" type="text" required maxlength="255"
                   class="input input-bordered w-full" value="{{ old('name', $article?->name) }}">
            @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('article.field.sku') }}</label>
            <input name="number" type="text" maxlength="64"
                   class="input input-bordered w-full" value="{{ old('number', $article?->number) }}"
                   placeholder="{{ __('article.sku_auto_hint') }}">
            @error('number')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('article.field.type') }} *</label>
            <select name="type" class="select select-bordered w-full">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $article?->type?->value) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('article.field.base_unit') }} *</label>
            <input name="base_unit" type="text" required maxlength="20"
                   class="input input-bordered w-full" value="{{ old('base_unit', $article?->base_unit ?? 'Stk') }}">
            @error('base_unit')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('article.field.status') }} *</label>
            <select name="status" class="select select-bordered w-full">
                @foreach ($statuses as $st)
                    <option value="{{ $st->value }}" @selected(old('status', $article?->status?->value ?? 'draft') === $st->value)>{{ $st->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('article.field.gtin') }}</label>
            <input name="gtin" type="text" maxlength="14"
                   class="input input-bordered w-full" value="{{ old('gtin', $article?->gtin) }}">
        </div>
    </x-form-group>

    <x-form-group :legend="__('article.group.pricing')" icon="payments" tone="primary" cols="3">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('article.field.default_purchase_price') }}</label>
            <input name="default_purchase_price" type="number" step="0.0001" min="0"
                   class="input input-bordered w-full" value="{{ old('default_purchase_price', $article?->default_purchase_price) }}">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('article.field.default_sale_price') }}</label>
            <input name="default_sale_price" type="number" step="0.0001" min="0"
                   class="input input-bordered w-full" value="{{ old('default_sale_price', $article?->default_sale_price) }}">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('article.field.currency') }}</label>
            <input name="currency" type="text" maxlength="3"
                   class="input input-bordered w-full" value="{{ old('currency', $article?->currency ?? 'EUR') }}">
        </div>
    </x-form-group>

    <x-form-group :legend="__('article.group.flags')" icon="tune" tone="primary" cols="2">
        @foreach ($flags as $key => $label)
            <label class="label cursor-pointer justify-start gap-3">
                <input type="hidden" name="{{ $key }}" value="0">
                <input type="checkbox" name="{{ $key }}" value="1" class="checkbox checkbox-sm"
                       @checked(old($key, $article ? $article->{$key} : in_array($key, ['stockable','purchasable','sellable'], true)))>
                <span class="label-text">{{ $label }}</span>
            </label>
        @endforeach
    </x-form-group>
</x-modal>
