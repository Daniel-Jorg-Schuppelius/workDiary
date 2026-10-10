{{--
  Created on   : Sat Oct 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _posting_category.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Buchungskategorie der Partei für die Übergabe aus dem Rechnungseingang
  (Feature 163, MVP-1111). Erwartet: $action, $categories (Collection),
  $categoryRef (?ExternalReference).
--}}
<form method="POST" action="{{ $action }}"
      class="flex h-full flex-col gap-3 rounded-box border border-base-300 bg-base-200/40 p-3">
    @csrf
    <div class="flex items-center gap-2 text-sm font-semibold">
        <x-icon name="category" class="text-muted" /> {{ __('lexoffice::incoming.category.title') }}
    </div>
    <p class="text-sm text-base-content/70">{{ __('lexoffice::incoming.category.help') }}</p>
    <x-select-field name="category" :label="__('lexoffice::incoming.category.title')">
        <option value="">{{ __('lexoffice::incoming.settings.no_category') }}</option>
        @foreach ($categories as $category)
            <option value="{{ $category->external_id }}" @selected(old('category', $categoryRef?->external_id) === $category->external_id)>{{ $category->group_name ? $category->group_name . ' › ' . $category->name : $category->name }}</option>
        @endforeach
    </x-select-field>
    <div class="mt-auto pt-1">
        <x-icon-btn icon="save" tone="primary" size="sm" type="submit" show-label>{{ __('lexoffice::incoming.category.save') }}</x-icon-btn>
    </div>
</form>
