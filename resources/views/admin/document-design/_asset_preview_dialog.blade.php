{{--
  Created on   : Tue Sep 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _asset_preview_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Firmenbogen ansehen (Feature 076) — zeigt die Druckfassung (Rasterseite)
     oder, wenn die Prüfung fehlschlug, das Original; PDFs ohne Rasterseite nur als Download. --}}
@php
    /** @var \App\Models\Document\DocumentDesign\LetterheadAsset $asset */
    $isImage = in_array($asset->source_type, ['png', 'jpg'], true);
    $imageUrl = $hasNormalized
        ? route('admin.document-design.assets.preview', $asset->sqid)
        : ($hasOriginal && $isImage ? route('admin.document-design.assets.original', $asset->sqid) : null);
    $statusTone = $asset->status->tone();
    $badgeTone = $statusTone === 'success' ? 'success' : ($statusTone === 'warning' ? 'warning' : 'ghost');
    $format = $asset->page_format ?? \App\Enums\DocumentDesign\PageFormat::A4Portrait;
@endphp
<x-modal :title="$asset->name" :eyebrow="__('document_design.assets_heading')" icon="wallpaper" tone="primary" hide-footer>
    <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_15rem]">
        <div class="flex items-start justify-center rounded-box border border-base-300 bg-base-200 p-3">
            @if ($imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ $asset->name }}"
                     class="max-h-[65vh] w-auto max-w-full border border-base-300 bg-white shadow-sm">
            @else
                <x-empty-state icon="picture_as_pdf" :title="__('document_design.asset.no_preview')" compact />
            @endif
        </div>

        <dl class="space-y-2 text-sm">
            <div>
                <dt class="text-xs text-muted">{{ __('document_design.asset.status') }}</dt>
                <dd><span class="badge badge-sm badge-{{ $badgeTone }}">{{ $asset->status->label() }}</span></dd>
            </div>
            @if ($asset->review_notes)
                <div class="rounded-box border border-warning/50 bg-warning/10 p-2 text-xs">
                    <div class="font-medium">{{ __('document_design.asset.review_notes') }}</div>
                    <ul class="mt-1 list-disc pl-4">
                        @foreach ($asset->review_notes as $note)
                            <li>{{ $note }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (! $hasNormalized && $imageUrl)
                <p class="text-xs text-muted">{{ __('document_design.asset.preview_of_original') }}</p>
            @endif
            <div>
                <dt class="text-xs text-muted">{{ __('document_design.asset.page_role') }}</dt>
                <dd>{{ $asset->page_role->label() }} · {{ $format->label() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">{{ __('document_design.asset.original_name') }}</dt>
                <dd class="break-all"><span class="uppercase">{{ $asset->source_type }}</span> · {{ $asset->original_name }} · {{ \CommonToolkit\Helper\Data\NumberHelper::formatBytes((int) $asset->size, 1) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">{{ __('document_design.asset.uploaded') }}</dt>
                <dd>{{ $asset->created_at->fdate() }}@if ($asset->uploader) · {{ $asset->uploader->name }}@endif</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">{{ __('document_design.asset.usage') }}</dt>
                <dd>{{ $inUse ? __('document_design.asset.in_use') : __('document_design.asset.not_in_use') }}</dd>
            </div>
            <div class="flex flex-col items-start gap-1 pt-2">
                @if ($hasOriginal)
                    <x-icon-btn icon="download" tone="outline" size="sm" target="_blank"
                                :href="route('admin.document-design.assets.original', $asset->sqid)" show-label>
                        {{ __('document_design.asset.download_original') }}
                    </x-icon-btn>
                @endif
                @if ($hasNormalized)
                    <x-icon-btn icon="open_in_new" tone="ghost" size="sm" target="_blank"
                                :href="route('admin.document-design.assets.preview', $asset->sqid)" show-label>
                        {{ __('document_design.asset.open_normalized') }}
                    </x-icon-btn>
                @endif
            </div>
        </dl>
    </div>
</x-modal>
