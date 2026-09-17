{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _add_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Inhalt in eine Sammlung legen (MVP-809). Variablen: $type, $itemSqid,
  $itemTitle, $options (Baumzeilen), $memberOf (IDs, in denen er schon liegt).
--}}
<x-modal
    :title="__('collections.action.add_to_collection')"
    :eyebrow="$itemTitle"
    icon="bookmark_add"
    tone="primary"
    :action="route('collections.items.store')"
    :submit-label="__('collections.action.add')">

    <input type="hidden" name="type" value="{{ $type }}">
    <input type="hidden" name="item" value="{{ $itemSqid }}">

    @if ($options === [])
        <x-empty-state icon="folder_special" :title="__('collections.empty.tree')" :message="__('collections.help.create_first')" compact />
    @else
        <x-form-group :legend="__('collections.title.index')" icon="folder_special" tone="primary">
            <x-select-field name="collection" :label="__('collections.field.collection')" :hint="__('collections.help.multiple_membership')" required>
                @foreach ($options as $row)
                    @php $node = $row['collection']; $member = in_array((int) $node->id, $memberOf, true); @endphp
                    <option value="{{ $node->sqid }}" @disabled($member)>{{ str_repeat('– ', $row['depth'] - 1) }}{{ $node->title }}{{ $member ? ' · ' . __('collections.badge.already_in') : '' }}</option>
                @endforeach
            </x-select-field>
        </x-form-group>
    @endif
</x-modal>
