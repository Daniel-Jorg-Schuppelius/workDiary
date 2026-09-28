{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _node_comments_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kommentarfaden eines Knotens (Feature 054, MVP-1005). Variablen: $map, $node, $comments, $canComment
--}}
<x-modal :title="__('ideas.comments.title')" :eyebrow="$node->title" icon="forum" tone="primary"
         :action="$canComment ? route('ideas.nodes.comments.store', [$map, $node]) : null" method="POST"
         :form-data="['data-entry-form' => '']" :submit-label="$canComment ? __('ideas.comments.action.add') : null">
    @if ($comments->isEmpty())
        <p class="text-sm text-muted">{{ __('ideas.comments.empty') }}</p>
    @else
        <ul class="max-h-80 space-y-3 overflow-y-auto">
            @foreach ($comments as $comment)
                <li class="rounded-box bg-base-200 p-3 text-sm">
                    <div class="mb-1 text-xs text-muted">{{ $comment->user?->name ?? '—' }} · {{ $comment->created_at?->orgTz()->format('d.m.Y H:i') }}</div>
                    <div class="whitespace-pre-line">{{ $comment->body }}</div>
                </li>
            @endforeach
        </ul>
    @endif
    @if ($canComment)
        <x-textarea-field name="body" :label="__('ideas.comments.field.body')" rows="3" required maxlength="5000" :value="old('body')" />
    @endif
</x-modal>
