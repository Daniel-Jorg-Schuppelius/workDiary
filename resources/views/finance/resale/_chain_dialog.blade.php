{{--
  Created on   : Thu Nov 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _chain_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Arbeitsliste der Abo-Ketten (MVP-1755): beendete Abos ohne Nachfolger, je Abo die Auswahl
  des Nachfolgers. Eindeutige Paare sind vorgewählt, die übrigen stehen auf „nicht verketten“.
  Erwartet: $rows (SubscriptionChain::worklist).
--}}
<x-modal
    :title="__('resale.chain.dialog_title')"
    icon="link"
    tone="info"
    size="lg"
    :action="route('finance.resale.chain.bulk')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.chain.link')"
>
    <div class="text-sm text-base-content/70">{{ __('resale.chain.dialog_hint') }}</div>
    @forelse ($rows as $row)
        @php $subscription = $row['subscription']; @endphp
        <div class="grid gap-1 md:grid-cols-2 md:items-center border-b border-base-300 pb-2">
            <div class="text-sm">
                <a href="{{ route('finance.resale.show', $subscription->sqid) }}" class="link link-hover font-medium">{{ $subscription->label }}</a>
                <span class="block text-xs text-muted">{{ $subscription->holderLabel() }} · ×{{ $subscription->quantity }} · {{ $subscription->provider->label() }} · {{ __('resale.chain.until', ['date' => $subscription->ends_on?->fdate()]) }}</span>
            </div>
            <select name="successors[{{ $subscription->sqid }}]" class="select select-bordered select-sm w-full" aria-label="{{ __('resale.field.successor') }}: {{ $subscription->label }}">
                <option value="">{{ __('resale.chain.skip') }}</option>
                @foreach ($row['candidates'] as $candidate)
                    <option value="{{ $candidate->sqid }}" @selected($row['unique']?->id === $candidate->id)>×{{ $candidate->quantity }} · {{ $candidate->provider->label() }} · {{ __('resale.chain.from', ['date' => $candidate->starts_on->fdate()]) }}</option>
                @endforeach
            </select>
        </div>
    @empty
        <x-empty-state compact icon="link" :title="__('resale.chain.empty')" />
    @endforelse
</x-modal>
