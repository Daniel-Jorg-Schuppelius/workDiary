{{--
  Created on   : Sat Oct 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _transfers.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Übergabe an die Buchhaltungsziele (Feature 163, MVP-1111): Stand je Ziel,
  Gründe des Tors und Anstoß von Hand. Erwartet: $incoming, $transferTargets,
  $transferJournals (Collection nach Ziel), $transferBlockers.
--}}
@php
    $allFinal = collect($transferTargets)->every(static fn ($target): bool => $transferJournals->get($target->key())?->status->isFinal() ?? false);
@endphp
<x-card :title="__('Übergabe an die Buchhaltung')">
    @if ($transferBlockers !== [] && ! $allFinal)
        <div role="alert" class="alert alert-warning mb-3 text-sm">
            <x-icon name="warning" />
            <ul class="list-inside list-disc">
                @foreach ($transferBlockers as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <x-detail-grid>
        @foreach ($transferTargets as $target)
            @php($journal = $transferJournals->get($target->key()))
            <x-detail-grid.row :label="$target->label()">
                @if ($journal === null)
                    <x-status-badge tone="ghost">{{ __('Noch nicht übergeben') }}</x-status-badge>
                @else
                    <x-status-badge :tone="$journal->status->tone()">{{ $journal->status->label() }}</x-status-badge>
                    @if ($journal->external_number || $journal->external_id)
                        <span class="font-mono text-xs">{{ $journal->external_number ?? $journal->external_id }}</span>
                    @endif
                    @if ($journal->transferred_at)
                        <span class="text-xs text-muted">· {{ $journal->transferred_at->fdatetime() }}</span>
                    @elseif ($journal->attempts > 0)
                        <span class="text-xs text-muted">· {{ __('Versuche: :count', ['count' => $journal->attempts]) }}</span>
                    @endif
                    @if ($journal->error)
                        <div class="text-xs {{ $journal->status === \App\Enums\Invoicing\IncomingInvoiceTransferStatus::Failed ? 'text-error' : 'text-muted' }}">{{ $journal->error }}</div>
                    @endif
                @endif
            </x-detail-grid.row>
        @endforeach
    </x-detail-grid>
    @if (! $allFinal && $incoming->counterparty() !== null && (auth()->user()?->canManageBilling() ?? false))
        <x-action-form :action="route('finance.incoming-invoices.transfer', $incoming)" class="mt-2"
                       :confirm="__('Eingang jetzt an die Buchhaltung übergeben? Ein dort angelegter Beleg lässt sich nicht mehr löschen.')"
                       confirm-icon="outbox"
                       confirm-tone="primary"
                       :confirm-label="__('Übergeben')">
            <x-icon-btn icon="outbox" tone="primary" size="sm" type="submit" show-label>{{ $transferJournals->isEmpty() ? __('Jetzt übergeben') : __('Erneut versuchen') }}</x-icon-btn>
        </x-action-form>
    @endif
</x-card>
