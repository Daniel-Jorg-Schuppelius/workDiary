{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _show_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Legacy-Archiv Show-Dialog (read-only). Erwartet: $entry --}}
<x-modal
    :title="'Legacy #' . $entry->id"
    :eyebrow="__('Archiv-Eintrag')"
    icon="inventory_2"
    :badge="$entry->statusLabel()"
    :badge-tone="match((int)$entry->gelesen) { -1 => 'neutral', 1 => 'success', 2 => 'warning', 3 => 'error', default => 'ghost' }"
    tone="ghost">
    <?php $isDialog = true; ?>
    @include('legacy.archive._show_body')
</x-modal>
