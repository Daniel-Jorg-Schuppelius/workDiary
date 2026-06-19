{{-- Erwartet: $isDialog, $articles, $variants, $warehouses --}}
@php $isDialog = $isDialog ?? false; @endphp

<x-modal
    :title="__('manufacturing.order.action.create')"
    :eyebrow="__('manufacturing.order.title')"
    icon="precision_manufacturing"
    tone="primary"
    :action="route('manufacturing-orders.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('manufacturing-orders.create') . '?dialog=1' }}">
    @endif

    <x-form-group :legend="__('Stammdaten')" icon="precision_manufacturing" tone="primary" cols="2">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Artikel') }} *</label>
            <select name="article" class="select select-bordered w-full" required>
                @foreach ($articles as $article)
                    <option value="{{ $article->sqid }}">{{ $article->name }}</option>
                @endforeach
            </select>
            @error('article')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('manufacturing.quantity_kind.per_unit') }} — {{ __('Variante') }}</label>
            <select name="variant" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($variants as $variant)
                    <option value="{{ $variant->sqid }}">{{ $variant->article?->name }} — {{ $variant->name ?? $variant->option_signature }}</option>
                @endforeach
            </select>
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('manufacturing.order.field.target_qty') }} *</label>
            <input name="target_qty" type="number" step="0.0001" min="0.0001" required class="input input-bordered w-full" value="{{ old('target_qty', 1) }}">
            @error('target_qty')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('article.field.base_unit') }} *</label>
            <input name="unit" type="text" required maxlength="20" class="input input-bordered w-full" value="{{ old('unit', 'Stk') }}">
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('inventory.field.warehouse') }}</label>
            <select name="warehouse" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->sqid }}">{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('inventory.overview.priority') }}</label>
            <input name="priority" type="number" min="1" class="input input-bordered w-full" value="{{ old('priority', 100) }}">
        </div>
    </x-form-group>
</x-modal>
