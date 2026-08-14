{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _show_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    $statusToneMap = ['done' => 'success', 'progress' => 'info', 'open' => 'warning', 'alert' => 'error', 'neutral' => 'ghost'];
    $diaryBadgeTone = $statusToneMap[$diary->statusTone()] ?? 'ghost';
@endphp
<x-modal
    :title="$diary->title ?: __('Eintrag')"
    :eyebrow="__('Tagebuch')"
    icon="menu_book"
    :badge="$diary->statusLabel()"
    :badge-tone="$diaryBadgeTone"
    tone="primary"
>
    @include('diary._show_body', ['isDialog' => true])

    {{-- Footer-Buttons im App-Standard-Stil (btn, Default-Größe, gap-2) wie der
         Standard-Modal-Footer (Abbrechen/Speichern) – nicht btn-sm. --}}
    <x-slot:footerExtra>
        @can('archive', $diary)
            @if ($diary->is_archived)
                <x-action-form :action="route('diary.restore', $diary)">
                    <x-button type="submit" tone="outline" class="gap-2" icon="restore">{{ __('Wiederherstellen') }}</x-button>
                </x-action-form>
            @else
                <x-action-form :action="route('diary.archive', $diary)">
                    <x-button type="submit" tone="outline" class="gap-2" icon="archive">{{ __('Archivieren') }}</x-button>
                </x-action-form>
            @endif
        @endcan
        @can('delete', $diary)
            <x-action-form :action="route('diary.destroy', $diary)" method="DELETE"
                data-confirm-title="{{ __('Eintrag löschen') }}"
                :confirm="__('Der Eintrag wird dauerhaft gelöscht. Möchtest du fortfahren?')"
                :confirm-label="__('Löschen')">
                <x-button type="submit" tone="ghost" class="gap-2 text-error" icon="delete">{{ __('Löschen') }}</x-button>
            </x-action-form>
        @endcan
    </x-slot:footerExtra>

    <x-slot:actions>
        <x-button type="button" tone="ghost" class="gap-2" data-entry-modal-close icon="close">{{ __('Schließen') }}</x-button>
        @can('update', $diary)
            <x-button :href="route('diary.edit', $diary)" tone="primary" class="gap-2" data-entry-modal-trigger icon="edit">{{ __('Bearbeiten') }}</x-button>
        @endcan
    </x-slot:actions>
</x-modal>
