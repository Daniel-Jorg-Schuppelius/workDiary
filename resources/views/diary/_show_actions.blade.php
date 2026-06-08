{{-- Aktions-Buttons der Diary-Detailansicht (Seite: inline im Kopf; Dialog: Modal-Footer). Erwartet: $diary --}}
@can('archive', $diary)
    @if ($diary->is_archived)
        <form method="POST" action="{{ route('diary.restore', $diary) }}" class="inline">
            @csrf
            <x-icon-btn icon="restore" tone="outline" size="sm" type="submit" show-label>{{ __('Wiederherstellen') }}</x-icon-btn>
        </form>
    @else
        <form method="POST" action="{{ route('diary.archive', $diary) }}" class="inline">
            @csrf
            <x-icon-btn icon="archive" tone="outline" size="sm" type="submit" show-label>{{ __('Archivieren') }}</x-icon-btn>
        </form>
    @endif
@endcan
@can('delete', $diary)
    <form method="POST" action="{{ route('diary.destroy', $diary) }}" class="inline"
        data-confirm-dialog
        data-confirm-title="{{ __('Eintrag löschen') }}"
        data-confirm-message="{{ __('Der Eintrag wird dauerhaft gelöscht. Möchtest du fortfahren?') }}"
        data-confirm-label="{{ __('Löschen') }}">
        @csrf @method('DELETE')
        <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
    </form>
@endcan
@can('update', $diary)
    <x-icon-btn icon="edit" tone="primary" size="sm"
                data-entry-modal-trigger
                :href="route('diary.edit', $diary)"
                show-label>{{ __('Bearbeiten') }}</x-icon-btn>
@endcan
