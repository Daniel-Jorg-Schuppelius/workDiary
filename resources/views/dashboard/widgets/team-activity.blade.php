{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : team-activity.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Letzte Team-Aktivität" (nur Admins) — Daten: TeamActivityWidget.
--}}
<x-card :title="__('Letzte Team-Aktivität')" icon="groups">
    @if ($comments->isEmpty())
        <x-empty-state compact icon="groups"
                       :title="__('Keine Aktivität')" :message="__('Noch keine Aktivität.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($comments as $comment)
                <li class="rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <div class="text-xs text-muted">{{ optional($comment->user)->name ?? '—' }} · {{ $comment->created_at->diffForHumans() }}</div>
                    <a href="{{ route('diary.show', $comment->commentable_id) }}#comments" class="link block">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($comment->body, 120) }}</a>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
