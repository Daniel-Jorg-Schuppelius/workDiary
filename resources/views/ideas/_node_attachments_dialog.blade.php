{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _node_attachments_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anhänge eines Knotens (Feature 054, MVP-1018). Variablen: $map, $node, $attachments, $canUpload
--}}
<x-modal :title="__('ideas.attachments.title')" :eyebrow="$node->title" icon="attach_file" tone="primary"
         :action="$canUpload ? route('ideas.nodes.attachments.store', [$map, $node]) : null" method="POST" enctype="multipart/form-data"
         :form-data="['data-entry-form' => '']" :submit-label="$canUpload ? __('ideas.attachments.action.add') : null">
    @if ($attachments->isEmpty())
        <x-empty-state icon="attach_file" :title="__('ideas.attachments.empty')" compact />
    @else
        <ul class="max-h-80 space-y-2 overflow-y-auto">
            @foreach ($attachments as $attachment)
                <li class="flex items-center justify-between gap-2 rounded-box bg-base-200 px-3 py-2 text-sm">
                    <a class="link link-hover truncate" href="{{ \App\Http\Controllers\Attachments\AttachmentController::downloadUrl($attachment) }}" target="_blank" rel="noopener">{{ $attachment->original_name }}</a>
                    <span class="shrink-0 text-xs text-muted">{{ $attachment->created_at?->orgTz()->format('d.m.Y') }}</span>
                </li>
            @endforeach
        </ul>
    @endif
    @if ($canUpload)
        <label class="form-control mt-3">
            <span class="label-text">{{ __('ideas.attachments.field.file') }} *</span>
            <input type="file" name="file" required class="file-input file-input-bordered w-full">
            <span class="label-text-alt mt-1 text-muted">{{ __('ideas.attachments.hint', ['mb' => \App\Services\Attachments\FileAttacher::maxMb()]) }}</span>
        </label>
    @endif
</x-modal>
