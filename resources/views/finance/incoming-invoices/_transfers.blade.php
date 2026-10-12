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
                    {{-- Treffer ohne Rechnungsnummer (MVP-1146): ein Mensch entscheidet einmal. --}}
                    @if ($journal->candidate_external_id !== null && ! $journal->status->isFinal() && (auth()->user()?->canManageBilling() ?? false))
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <x-action-form :action="route('finance.incoming-invoices.transfer.link', [$incoming, $journal->target])"
                                           :confirm="__('Mit dem Beleg :voucher verknüpfen? Im Buchhaltungsprogramm wird dann nichts angelegt.', ['voucher' => $journal->candidate_label])"
                                           confirm-icon="link" confirm-tone="primary" :confirm-label="__('Verknüpfen')">
                                <x-icon-btn icon="link" tone="primary" size="xs" type="submit" show-label>{{ __('Mit diesem Beleg verknüpfen') }}</x-icon-btn>
                            </x-action-form>
                            <x-action-form :action="route('finance.incoming-invoices.transfer.create-new', [$incoming, $journal->target])"
                                           :confirm="__('Einen neuen Beleg anlegen? Ein dort angelegter Beleg lässt sich nicht mehr löschen.')"
                                           confirm-icon="add" confirm-tone="primary" :confirm-label="__('Neu anlegen')">
                                <x-icon-btn icon="add" size="xs" type="submit" show-label>{{ __('Anderer Beleg – neu anlegen') }}</x-icon-btn>
                            </x-action-form>
                        </div>
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
