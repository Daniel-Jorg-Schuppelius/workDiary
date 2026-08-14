{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _show_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </form>
        </x-slot:footerExtra>

        <x-slot:actions>
            <x-icon-btn icon="close" size="sm" type="button" data-entry-modal-close show-label>{{ __('Schließen') }}</x-icon-btn>
            <x-icon-btn icon="edit" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('legacy.diary.edit', $entry)"
                        show-label>{{ __('Bearbeiten') }}</x-icon-btn>
        </x-slot:actions>
    @endif
</x-modal>
