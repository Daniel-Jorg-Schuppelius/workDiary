{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : order-review.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Zur Prüfung“ (Feature 198) — Daten: OrderReviewWidget ($total, $entries).
--}}
<x-card :title="__('diary.review.widget.title')" icon="fact_check" :count="$total ?: null">
    <x-slot:actions>
        <x-button href="{{ route('diary.review-queue') }}" tone="ghost" size="xs">{{ __('diary.review.widget.open_queue') }}</x-button>
    </x-slot:actions>

    @if ($entries->isEmpty())
        <x-empty-state compact icon="fact_check" :title="__('diary.review.queue.empty_title')" :message="__('diary.review.queue.empty_message')" />
    @else
        <ul class="divide-y divide-base-300 text-sm">
            @foreach ($entries as $entry)
                <li class="flex items-start justify-between gap-3 py-2">
                    <div class="min-w-0">
                        <x-order-link :entry="$entry" class="link link-hover font-medium">{{ \App\Services\Diary\Approvals\OrderReviewInboxSubject::title($entry) }}</x-order-link>
                        <p class="text-xs text-muted">{{ __('diary.review.inbox_reference', ['name' => $entry->reviewSubmitter?->name ?? '—', 'date' => $entry->review_submitted_at?->fdate() ?? '—']) }}</p>
                    </div>
                    @if ($entry->due_date)
                        <span class="shrink-0 text-xs {{ $entry->due_date->isPast() ? 'text-error font-semibold' : 'text-muted' }}">{{ __('Fällig bis') }} {{ $entry->due_date->fdate() }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
