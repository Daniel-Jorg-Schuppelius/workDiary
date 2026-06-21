{{-- Aktions-Buttons der Diary-Detailansicht (Seite: inline im Kopf; Dialog: Modal-Footer). Erwartet: $diary --}}
@can('archive', $diary)
    @if ($diary->is_archived)
        <x-action-form :action="route('diary.restore', $diary)">
            <x-icon-btn icon="restore" tone="outline" size="sm" type="submit" show-label>{{ __('Wiederherstellen') }}</x-icon-btn>
        </x-action-form>
    @else
        <x-action-form :action="route('diary.archive', $diary)">
            <x-icon-btn icon="archive" tone="outline" size="sm" type="submit" show-label>{{ __('Archivieren') }}</x-icon-btn>
        </x-action-form>
    @endif
@endcan
@can('delete', $diary)
    <x-action-form :action="route('diary.destroy', $diary)" method="DELETE"
        data-confirm-title="{{ __('Eintrag löschen') }}"
        :confirm="__('Der Eintrag wird dauerhaft gelöscht. Möchtest du fortfahren?')"
        :confirm-label="__('Löschen')">
        <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
    </x-action-form>
@endcan
@can('update', $diary)
    <x-icon-btn icon="edit" tone="primary" size="sm"
                data-entry-modal-trigger
                :href="route('diary.edit', $diary)"
                show-label>{{ __('Bearbeiten') }}</x-icon-btn>
@endcan
