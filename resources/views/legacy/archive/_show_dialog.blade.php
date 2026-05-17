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
