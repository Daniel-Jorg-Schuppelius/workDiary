{{-- Legacy-Diary Show-Dialog. Erwartet: $entry --}}
@php
    $canManage = (int) $entry->user === (int) (Auth::user()->legacy_user_id ?? 0)
        || \App\Legacy\Support\LegacyRoleResolver::isAdmin(Auth::user());
@endphp
<x-modal
    :title="'Legacy #' . $entry->id"
    :eyebrow="__('Legacy Eintrag')"
    icon="info"
    :badge="$entry->statusLabel()"
    :badge-tone="match((int)$entry->gelesen) { -1 => 'neutral', 1 => 'success', 2 => 'warning', 3 => 'error', default => 'ghost' }"
    tone="ghost">
    <?php $isDialog = true; ?>
    @include('legacy.diary._show_body')

    @if ($canManage)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('legacy.diary.destroy', $entry) }}" class="inline"
                data-confirm-dialog
                data-confirm-title="{{ __('Eintrag löschen') }}"
                data-confirm-message="{{ __('Legacy-Eintrag wirklich löschen?') }}"
                data-confirm-label="{{ __('Löschen') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-error btn-outline gap-2">
                    <x-icon name="delete" /> {{ __('Löschen') }}
                </button>
            </form>
        </x-slot:footerExtra>

        <x-slot:actions>
            <a href="{{ route('legacy.diary.edit', $entry) }}" data-entry-modal-trigger class="btn btn-sm btn-primary gap-2">
                <x-icon name="edit" /> {{ __('Bearbeiten') }}
            </a>
            <button type="button" class="btn btn-sm btn-ghost gap-2" data-entry-modal-close>
                <x-icon name="close" /> {{ __('Schließen') }}
            </button>
        </x-slot:actions>
    @endif
</x-modal>
