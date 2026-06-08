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
                <form method="POST" action="{{ route('diary.restore', $diary) }}" class="inline">
                    @csrf
                    <button type="submit" class="btn btn-outline gap-2">
                        <x-icon name="restore" /> {{ __('Wiederherstellen') }}
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('diary.archive', $diary) }}" class="inline">
                    @csrf
                    <button type="submit" class="btn btn-outline gap-2">
                        <x-icon name="archive" /> {{ __('Archivieren') }}
                    </button>
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
                <button type="submit" class="btn btn-ghost gap-2 text-error">
                    <x-icon name="delete" /> {{ __('Löschen') }}
                </button>
            </form>
        @endcan
    </x-slot:footerExtra>

    <x-slot:actions>
        <button type="button" class="btn btn-ghost gap-2" data-entry-modal-close>
            <x-icon name="close" /> {{ __('Schließen') }}
        </button>
        @can('update', $diary)
            <a href="{{ route('diary.edit', $diary) }}" class="btn btn-primary gap-2" data-entry-modal-trigger>
                <x-icon name="edit" /> {{ __('Bearbeiten') }}
            </a>
        @endcan
    </x-slot:actions>
</x-modal>
