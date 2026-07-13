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
        <x-input-field name="name" :label="__('Name')" required maxlength="255"
                       :value="old('name', $article?->name)" />
        <x-input-field name="number" :label="__('article.field.sku')" maxlength="64"
                       :value="old('number', $article?->number)"
                       :placeholder="__('article.sku_auto_hint')" />

        <x-select-field name="type" :label="__('article.field.type')" required>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(old('type', $article?->type?->value) === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="base_unit" :label="__('article.field.base_unit')" required maxlength="20"
                       :value="old('base_unit', $article?->base_unit ?? 'Stk')" />

        <x-select-field name="status" :label="__('article.field.status')" required>
            @foreach ($statuses as $st)
                <option value="{{ $st->value }}" @selected(old('status', $article?->status?->value ?? 'draft') === $st->value)>{{ $st->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="gtin" :label="__('article.field.gtin')" maxlength="14"
                       :value="old('gtin', $article?->gtin)" />
    </x-form-group>

    <x-form-group :legend="__('article.group.pricing')" icon="payments" tone="primary" cols="3">
        <x-input-field name="default_purchase_price" type="number" step="0.0001" min="0"
                       :label="__('article.field.default_purchase_price')"
                       :value="old('default_purchase_price', $article?->default_purchase_price)" />
        <x-input-field name="default_sale_price" type="number" step="0.0001" min="0"
                       :label="__('article.field.default_sale_price')"
                       :value="old('default_sale_price', $article?->default_sale_price)" />
        <x-select-field name="currency" :label="__('article.field.currency')">
            <x-currency-options :selected="old('currency', $article?->currency?->value ?? 'EUR')" />
        </x-select-field>
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

    @php
        $allTags = $allTags ?? collect();
        $selectedTagIds = old('tag_ids', $article?->tags?->map(fn ($t) => $t->sqid)->all() ?? []);
    @endphp
    <div>
        <label class="label"><span class="label-text">{{ __('Tags') }}</span></label>
        <x-tag-picker :tags="$allTags" :selected="$selectedTagIds" />
    </div>
</x-modal>
