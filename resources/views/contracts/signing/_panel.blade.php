{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Unterzeichnung einer Kundenvereinbarung (Feature 157, MVP-822).
  Erwartet: $contract, $signingRevisions (Collection, neueste zuerst).
--}}
@php
    $canSign = \Illuminate\Support\Facades\Gate::allows('signing', $contract);
    $canReview = \Illuminate\Support\Facades\Gate::allows('review', $contract);
    $openRevision = $signingRevisions->first(fn ($r) => $r->status->isOpen());
    $signedRevisions = $signingRevisions->filter(fn ($r) => $r->status === \App\Enums\Contract\SigningRevisionStatus::Signed);
@endphp

<x-card as="section" id="signing" :title="__('contract-signing.panel.title')" icon="draw" :count="$signingRevisions->count()">
    @if ($canSign && $openRevision === null && $contract->status->isOpen())
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                        :href="route('contracts.signing.create', $contract)"
                        show-label>{{ __('contract-signing.action.new_revision') }}</x-icon-btn>
        </x-slot:actions>
    @endif

    @if (session('signing_link'))
        <div role="status" class="alert alert-info mb-4 flex-col items-start gap-2">
            <span class="font-medium">{{ __('contract-signing.panel.link_once') }}</span>
            <x-input-field name="signing_link_display" id="signing-link-display" :label="__('contract-signing.panel.link_label')" :value="session('signing_link')" readonly />
        </div>
    @endif

    <p class="mb-3 text-sm text-muted">{{ __('contract-signing.panel.intro') }}</p>

    @if ($signingRevisions->isEmpty())
        <x-empty-state compact icon="draw"
                       :title="__('contract-signing.panel.empty_title')"
                       :message="__('contract-signing.panel.empty_message')" />
    @else
        <div class="space-y-4">
            @foreach ($signingRevisions as $revision)
                @include('contracts.signing._revision', [
                    'contract' => $contract,
                    'revision' => $revision,
                    'canSign' => $canSign,
                    'canReview' => $canReview,
                    'supersedable' => $signedRevisions->reject(fn ($r) => $r->id === $revision->id),
                    'expanded' => $loop->first,
                ])
            @endforeach
        </div>
    @endif
</x-card>
