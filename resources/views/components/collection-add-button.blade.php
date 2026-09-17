{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : collection-add-button.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  <x-collection-add-button :item="$model" /> — „Zur Sammlung hinzufügen“ auf
  der Detailseite eines Inhalts (MVP-809). Nur mit collection.manage und nur
  für Typen aus {@see \App\Services\Collections\CollectableTypes}.
--}}
@props(['item', 'size' => 'sm'])

@php
    $collectableType = app(\App\Services\Collections\CollectableTypes::class)->keyFor($item);
@endphp

@if ($collectableType !== null && \Illuminate\Support\Facades\Gate::allows('create', \App\Models\ContentCollection::class))
    <x-icon-btn icon="bookmark_add" tone="outline" :size="$size" show-label data-entry-modal-trigger
                :href="route('collections.add', ['type' => $collectableType, 'item' => $item->sqid])"
                {{ $attributes }}>{{ __('collections.action.add_to_collection') }}</x-icon-btn>
@endif
