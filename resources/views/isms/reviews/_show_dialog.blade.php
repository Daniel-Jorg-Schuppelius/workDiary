{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _show_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Read-Only-Anzeige einer Managementbewertung (in #entry-modal geladen) —
  insbesondere für freigegebene, unveränderliche Protokolle inkl.
  Freigabe-Nachweis (Person + Zeitpunkt, 046-Prinzip).
  Variablen: $review (IsmsManagementReview, mit scope/approvedBy)
--}}

<x-modal
    :title="$review->displayNo() . ' — ' . __('isms.title.reviews')"
    :eyebrow="optional($review->scope)->name"
    icon="grading"
    tone="primary"
    size="lg">

    <div class="space-y-4 text-sm">
        <div class="flex flex-wrap items-center gap-2">
            <x-status-badge :tone="$review->status->tone()">{{ $review->status->label() }}</x-status-badge>
            <span class="text-base-content/70">{{ __('isms.field.held_on') }}: {{ $review->held_on->format('d.m.Y') }}</span>
            @if ($review->isApproved())
                <span class="text-base-content/70">
                    {{ __('isms.review.approved_by_at', [
                        'name' => optional($review->approvedBy)->name ?? '—',
                        'date' => $review->approved_at?->format('d.m.Y H:i') ?? '—',
                    ]) }}
                </span>
            @endif
        </div>

        <div>
            <h4 class="font-semibold">{{ __('isms.field.participants') }}</h4>
            <p class="whitespace-pre-line text-base-content/80">{{ $review->participants }}</p>
        </div>
        <div>
            <h4 class="font-semibold">{{ __('isms.field.inputs') }}</h4>
            <p class="whitespace-pre-line text-base-content/80">{{ $review->inputs }}</p>
        </div>
        <div>
            <h4 class="font-semibold">{{ __('isms.field.decisions') }}</h4>
            <p class="whitespace-pre-line text-base-content/80">{{ $review->decisions }}</p>
        </div>
        @if ($review->follow_ups)
            <div>
                <h4 class="font-semibold">{{ __('isms.field.follow_ups') }}</h4>
                <p class="whitespace-pre-line text-base-content/80">{{ $review->follow_ups }}</p>
            </div>
        @endif
    </div>
</x-modal>
