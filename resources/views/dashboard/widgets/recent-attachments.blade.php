{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : recent-attachments.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Neue Anhänge auf meinen Einträgen" — Daten: RecentAttachmentsWidget.
--}}
<x-card :title="__('Neue Anhänge auf meinen Einträgen')" icon="attach_file">
    @if ($attachments->isEmpty())
        <x-empty-state compact icon="attach_file"
                       :title="__('Keine Anhänge')" :message="__('Noch keine Anhänge.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($attachments as $att)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <a href="{{ route('diary.show', $att->attachable_id) }}#attachments" class="link link-primary break-all"><x-icon name="attachment" class="align-middle" /> {{ $att->original_name }}</a>
                    <span class="text-xs text-muted">{{ optional($att->uploader)->name ?? '—' }} · {{ $att->created_at->diffForHumans() }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
