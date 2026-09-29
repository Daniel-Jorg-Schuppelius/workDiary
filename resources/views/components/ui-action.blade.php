{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : ui-action.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'action',              // App\Support\Ui\UiAction
    'size' => 'xs',
    'showLabel' => false,
])

{{--
    <x-ui-action> — stellt eine {@see \App\Support\Ui\UiAction} aus einem
    Plugin oder einer Quelle dar: Formular (POST, optional mit Rückfrage),
    Dialog-Link oder einfacher Link.
--}}

@if ($action->post)
    <x-action-form :action="$action->url"
                   :confirm="$action->confirm"
                   :confirm-label="$action->confirm !== null ? $action->label : null">
        <x-icon-btn :icon="$action->icon" :size="$size" :tone="$action->tone" type="submit" :label="$action->label" :show-label="$showLabel" />
    </x-action-form>
@elseif ($action->modal)
    <x-icon-btn :icon="$action->icon" :size="$size" :tone="$action->tone" :href="$action->url"
                data-entry-modal-trigger :label="$action->label" :show-label="$showLabel" />
@else
    <x-icon-btn :icon="$action->icon" :size="$size" :tone="$action->tone" :href="$action->url"
                :label="$action->label" :show-label="$showLabel" />
@endif
