{{--
  Created on   : Sun Jun 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : entity-header.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'title',
    'color' => false,            // false = kein Farbpunkt; sonst Hex (null ⇒ Fallback-Grau)
    'backRoute' => null,
    'backLabel' => null,
    'editRoute' => null,
    'editModal' => true,
    'archived' => false,
    'restoreRoute' => null,
    'archiveRoute' => null,
    'canManage' => false,
])

{{--
    <x-entity-header> — einheitlicher Kopf für Detail-/Show-Seiten:
    Farbpunkt + Titel + Status-Badges, darunter Meta-Zeile/Tags, rechts
    Zurück/Wiederherstellen/Archivieren/Bearbeiten.

    Slots:
      - badges  : Status-Badges neben dem Titel (z. B. <x-status-badge>)
      - meta    : graue Meta-Zeile unter dem Titel (Firma · Nr · Währung)
      - tags    : Tag-Badges (in flex-wrap-Zeile gerendert)
      - actions : zusätzliche Buttons links neben „Bearbeiten"

    Standard-Aktionen werden nur gerendert, wenn die jeweilige Route gesetzt
    ist; Wiederherstellen/Archivieren/Bearbeiten zusätzlich nur bei :canManage.
--}}

@php
    $backLabel = $backLabel ?? __('Zurück');
@endphp

<x-card>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                @if ($color !== false)
                    <span class="inline-block h-3 w-3 rounded-full" style="background:{{ $color ?: '#94a3b8' }}"></span>
                @endif
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold truncate">{{ $title }}</h1>
                @isset($badges){{ $badges }}@endisset
            </div>
            @isset($meta)
                <p class="mt-1 text-sm text-base-content/60">{{ $meta }}</p>
            @endisset
            @isset($tags)
                <div class="mt-2 flex flex-wrap gap-1">{{ $tags }}</div>
            @endisset
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($backRoute)
                <x-icon-btn icon="arrow_back" size="sm" :href="$backRoute" show-label>{{ $backLabel }}</x-icon-btn>
            @endif
            @isset($actions){{ $actions }}@endisset
            @if ($canManage)
                @if ($archived && $restoreRoute)
                    <x-action-form :action="$restoreRoute">
                        <x-icon-btn icon="restore" size="sm" type="submit" show-label>{{ __('Wiederherstellen') }}</x-icon-btn>
                    </x-action-form>
                @elseif (! $archived && $archiveRoute)
                    <x-action-form :action="$archiveRoute">
                        <x-icon-btn icon="archive" size="sm" type="submit" show-label>{{ __('Archivieren') }}</x-icon-btn>
                    </x-action-form>
                @endif
                @if ($editRoute)
                    @if ($editModal)
                        <x-icon-btn icon="edit" tone="primary" size="sm" data-entry-modal-trigger
                                    :href="$editRoute" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    @else
                        <x-icon-btn icon="edit" tone="primary" size="sm"
                                    :href="$editRoute" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    @endif
                @endif
            @endif
        </div>
    </div>
</x-card>
