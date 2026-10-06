{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _item_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Arbeitselement bearbeiten (Feature 064, MVP-140): Typ und Story Points.
  Der Rang läuft über die Rangliste, das Epic über die Zuordnung dort.
--}}
<x-modal
    :title="__('Arbeitselement bearbeiten')"
    :eyebrow="$item->task?->title ?? __('Produkt-Backlog')"
    icon="edit"
    size="sm"
    :action="route('agile.items.update', [$project, $item])"
    method="PATCH"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
>
    <x-select-field name="item_type" id="item-edit-type" :label="__('Typ')"
                    :hint="count($types) < count(\App\Enums\Agile\AgileItemType::cases()) ? __('Die Epic-Zuordnung schränkt die Typwahl ein: Ein Epic mit zugeordneten Elementen bleibt ein Epic, ein zugeordnetes Element wird keines.') : null">
        @foreach ($types as $type)
            <option value="{{ $type->value }}" @selected($item->item_type === $type)>{{ $type->label() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="story_points" id="item-edit-points" type="number" min="1" max="999" step="1"
                   :label="__('Story Points')"
                   :hint="__('Leer lassen, wenn das Element noch nicht geschätzt ist.')"
                   :value="old('story_points', $item->story_points)" />
</x-modal>
