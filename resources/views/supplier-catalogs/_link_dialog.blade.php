{{-- Erwartet: $item, $articles --}}
<x-modal
    :title="__('procurement.catalog.action.link')"
    :eyebrow="$item->external_no . ' · ' . $item->name"
    icon="link"
    tone="primary"
    :action="route('supplier-catalogs.items.link', $item)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('procurement.catalog.action.link')">
    <input type="hidden" name="_dialog_url" value="{{ route('supplier-catalogs.items.link-form', $item) }}">

    <x-form-group :legend="__('procurement.catalog.action.link')" icon="link" tone="primary">
        <x-select-field name="article" :label="__('procurement.catalog.col.internal_article')" required>
            @foreach ($articles as $article)
                <option value="{{ $article->sqid }}">{{ $article->name }}</option>
            @endforeach
        </x-select-field>
        <p class="text-sm opacity-70">{{ __('procurement.catalog.link_hint') }}</p>
    </x-form-group>
</x-modal>
