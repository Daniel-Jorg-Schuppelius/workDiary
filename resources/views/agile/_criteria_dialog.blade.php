{{--
  Created on   : Wed Oct 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _criteria_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Akzeptanzkriterien eines Arbeitselements (Feature 064, MVP-140): anlegen,
  einzeln abhaken, entfernen. Kein Dialog-Formular — jede Aktion ist ein
  eigenes Formular, der Dialog öffnet sich danach erneut (open_dialog).
--}}
<x-modal
    :title="__('Akzeptanzkriterien')"
    :eyebrow="$item->task?->title ?? __('Produkt-Backlog')"
    icon="checklist"
    size="sm"
>
    @if ($criteria->isEmpty())
        <x-empty-state compact icon="checklist" :title="__('Noch kein Kriterium.')" />
    @else
        <ul class="space-y-2" data-criteria-list>
            @foreach ($criteria as $criterion)
                <li class="flex items-start gap-2">
                    <form method="POST" action="{{ route('agile.criteria.toggle', [$project, $item, $criterion]) }}">
                        @csrf @method('PATCH')
                        <x-icon-btn :icon="$criterion->checked_at ? 'check_box' : 'check_box_outline_blank'" type="submit" tone="ghost" size="sm"
                                    :label="$criterion->checked_at ? __('Als offen markieren') : __('Als erledigt markieren')" />
                    </form>
                    <span class="flex-1 pt-1 text-sm {{ $criterion->checked_at ? 'text-muted line-through' : '' }}">{{ $criterion->text }}</span>
                    @if ($canPrioritize)
                        <form method="POST" action="{{ route('agile.criteria.destroy', [$project, $item, $criterion]) }}">
                            @csrf @method('DELETE')
                            <x-icon-btn icon="delete" type="submit" tone="ghost" size="sm" :label="__('Kriterium entfernen')" />
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    @if ($canPrioritize)
        <form method="POST" action="{{ route('agile.criteria.store', [$project, $item]) }}" class="flex items-end gap-2">
            @csrf
            <div class="flex-1">
                <x-input-field name="text" id="criterion-text" :label="__('Kriterium hinzufügen')" maxlength="500" required :value="old('text')" />
            </div>
            <x-button type="submit" tone="primary">{{ __('Hinzufügen') }}</x-button>
        </form>
    @endif
</x-modal>
