{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _version_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Versions-Dialog (in #entry-modal geladen): Historie aller Versionen +
  Upload-Formular für eine neue Version.
  Variablen: $document (Document mit versions.uploader), $canAddVersion (bool)
--}}
<x-modal
    :title="__('document.title.versions')"
    :eyebrow="$document->title"
    icon="history"
    tone="info"
    size="lg"
    :action="$canAddVersion ? route('documents.versions.store', $document) : null"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('document.action.add_version')">

    <div class="mb-4">
        <h3 class="mb-2 text-sm font-semibold text-base-content">{{ __('document.title.version_history') }}</h3>
        @if ($document->versions->isEmpty())
            <p class="text-sm text-base-content/60">{{ __('document.empty_versions') }}</p>
        @else
            <ul class="divide-y divide-base-300 rounded-box border border-base-300">
                @foreach ($document->versions as $version)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm">
                        <div class="min-w-0">
                            <span class="font-mono font-semibold">v{{ $version->version_no }}</span>
                            @if ((int) $document->current_version_id === (int) $version->id)
                                <x-status-badge tone="success" size="sm">{{ __('document.badge.current') }}</x-status-badge>
                            @endif
                            <span class="text-base-content/70">{{ $version->original_name }}</span>
                            @if ($version->note)
                                <span class="block text-xs italic text-base-content/60">{{ $version->note }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 text-xs text-base-content/60">
                            <span>{{ $version->humanSize() }}</span>
                            <span>{{ optional($version->uploader)->name ?? '—' }}</span>
                            <span>{{ $version->created_at?->fdatetime() }}</span>
                            <x-icon-btn icon="download" tone="outline" size="xs"
                                        :href="route('documents.download', ['document' => $document, 'version' => $version])"
                                        :label="__('document.action.download')" />
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($canAddVersion)
        <x-form-group :legend="__('document.action.add_version')" icon="upload_file" tone="warning" cols="2">
            <label class="form-control sm:col-span-2">
                <span class="label-text">{{ __('document.field.file') }} *</span>
                <input type="file" name="file" required class="file-input file-input-bordered w-full"
                       accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.log,.zip,.docx,.xlsx">
                <span class="label-text-alt mt-1 text-base-content/60">{{ __('document.hint.upload', ['mb' => \App\Services\Attachments\FileAttacher::maxMb()]) }}</span>
            </label>
            <label class="form-control sm:col-span-2">
                <span class="label-text">{{ __('document.field.version_note') }}</span>
                <input type="text" name="note" maxlength="500" class="input input-bordered w-full">
            </label>
        </x-form-group>
    @endif
</x-modal>
