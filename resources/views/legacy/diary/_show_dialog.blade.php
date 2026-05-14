{{-- Legacy-Diary Show-Dialog. Erwartet: $entry --}}
<x-dialog
    :title="'Legacy #' . $entry->id"
    :eyebrow="__('Legacy Eintrag')"
    icon="ⓘ"
    :badge="$entry->statusLabel()"
    :badge-tone="match((int)$entry->gelesen) { -1 => 'neutral', 1 => 'success', 2 => 'warning', 3 => 'error', default => 'ghost' }"
    tone="ghost">
    <?php $isDialog = true; ?>
    @include('legacy.diary._show_body')
</x-dialog>
