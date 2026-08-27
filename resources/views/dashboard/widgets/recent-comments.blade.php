{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : recent-comments.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Neue Kommentare auf meinen Einträgen" — Daten: RecentCommentsWidget.
--}}
<x-card :title="__('Neue Kommentare auf meinen Einträgen')" icon="comment">
    @if ($comments->isEmpty())
        <x-empty-state compact icon="comment"
                       :title="__('Keine Kommentare')" :message="__('Noch keine Kommentare.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($comments as $comment)
                <li class="rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <div class="text-xs text-muted">{{ optional($comment->user)->name ?? '—' }} · {{ $comment->created_at->diffForHumans() }}</div>
                    <a href="{{ route('diary.show', $comment->commentable_id) }}#comments" class="link block">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($comment->body, 100) }}</a>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
