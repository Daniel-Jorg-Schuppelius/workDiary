{{--
  Created on   : Sun Jun 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : attachments-section.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'attachments',
    'uploadType' => null,   // morph-Typ für attachments.store (z. B. 'customer')
    'uploadId' => null,     // Sqid des Parents
    'canUpload' => false,
])

{{--
    <x-attachments-section> — schlanke Anhänge-Karte (Liste + optionaler Upload)
    für Detail-/Show-Seiten. Löschen je Eintrag über die Attachment-Policy.
    Für den umfangreicheren Variante mit Uploader/Größe/Datum siehe das
    Partial attachments._panel.
--}}

<x-card :title="__('Anhänge')" icon="attach_file" :count="$attachments->count()">
    @if ($attachments->isEmpty())
        <x-empty-state compact icon='<span class="material-symbols-outlined">attach_file</span>'
                       :title="__('Keine Anhänge')"
                       :message="__('Keine Anhänge.')" />
    @else
        <ul class="divide-y divide-base-300 text-sm">
            @foreach ($attachments as $att)
                <li class="flex items-center justify-between gap-2 py-2">
                    <div class="min-w-0 truncate">
                        <a class="link link-hover" href="{{ URL::signedRoute('attachments.download', $att) }}">{{ $att->original_name }}</a>
                        <span class="text-base-content/60">· {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($att->size / 1024, 0, withThousandsSeparator: true) }} KB</span>
                    </div>
                    @can('delete', $att)
                        <x-action-form :action="route('attachments.destroy', $att)" method="DELETE"
                                       :confirm="__('Anhang löschen?')" confirm-icon="delete"
                                       confirm-tone="error" :confirm-label="__('Löschen')">
                            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                        </x-action-form>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif

    @if ($canUpload && $uploadType && $uploadId)
        <form method="POST" action="{{ route('attachments.store', ['type' => $uploadType, 'id' => $uploadId]) }}"
              enctype="multipart/form-data" class="mt-3 flex items-center gap-2">
            @csrf
            <input type="file" name="file" required class="file-input file-input-sm file-input-bordered">
            <x-icon-btn icon="upload" tone="primary" size="sm" type="submit" show-label>{{ __('Hochladen') }}</x-icon-btn>
        </form>
    @endif
</x-card>
