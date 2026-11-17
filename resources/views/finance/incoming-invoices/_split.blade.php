{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _split.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Sammel-PDF (Feature 208, MVP-1662): Stand der Grenzerkennung am Sammel-Eingang, Verweis eines Teils aufs Original, Teile am Original. --}}
@php
    $splitter = \App\Services\Invoicing\Split\IncomingInvoiceSplitter::class;
    $splitState = $incoming?->splitState();
    $splitProposal = (array) ($incoming?->split_proposal ?? []);
    $splitParts = $incoming === null ? $splitter::partsOf($document) : collect();
    $splitManage = $incoming !== null && (auth()->user()?->canManageBilling() ?? false);
    $splitMessage = match ($splitState) {
        $splitter::PENDING => __('incoming_pdf.split.state.pending'),
        $splitter::PROPOSED => __('incoming_pdf.split.state.proposed', ['count' => count((array) ($splitProposal['starts'] ?? []))]),
        $splitter::SINGLE => __('incoming_pdf.split.state.single', ['pages' => $splitter::pageCount($incoming)]),
        $splitter::DISMISSED => __('incoming_pdf.split.state.dismissed'),
        $splitter::SPLITTING => __('incoming_pdf.split.state.splitting'),
        $splitter::TOO_LARGE => __('incoming_pdf.split.state.too_large', ['max' => $splitter::MAX_PAGES]),
        $splitter::FAILED => __('incoming_pdf.split.state.failed'),
        default => null,
    };
    $splitContainer ??= null;
@endphp
@if ($incoming !== null && $incoming->isSplitPart())
    <x-card :title="__('incoming_pdf.split.card_title')" icon="call_split">
        <p class="text-sm">{{ __('incoming_pdf.split.part_of', ['from' => $incoming->source_page_from, 'to' => $incoming->source_page_to]) }}</p>
        @if ($incoming->sourceDocument !== null)
            <div class="mt-2">
                <x-icon-btn icon="description" tone="outline" size="sm"
                            :href="route('finance.incoming-invoices.show', $incoming->sourceDocument)"
                            show-label>{{ __('incoming_pdf.split.open_original') }}</x-icon-btn>
            </div>
        @endif
    </x-card>
@elseif ($incoming !== null && $splitMessage !== null)
    <x-card :title="__('incoming_pdf.split.card_title')" icon="call_split">
        <div role="status" @class(['alert text-sm', 'alert-warning' => $splitter::isHolding($incoming), 'alert-info' => ! $splitter::isHolding($incoming)])>
            <x-icon name="call_split" />
            <span>{{ $splitMessage }}</span>
        </div>
        @if (is_string($splitProposal['error'] ?? null) && $splitProposal['error'] !== '')
            <div role="alert" class="alert alert-error mt-2 text-sm">
                <x-icon name="error" />
                <span>{{ $splitProposal['error'] }}</span>
            </div>
        @endif
        @if ($splitManage)
            <div class="mt-2 flex flex-wrap items-center gap-2">
                {{-- Ausweg, wenn der Medien-Worker beim Teilen abgestürzt ist (Sicherheitsprüfung N8). --}}
                @if ($splitter::isStalled($incoming))
                    <x-action-form :action="route('finance.incoming-invoices.split.reset', $incoming)"
                                   :confirm="__('incoming_pdf.split.action.reset_confirm')"
                                   confirm-icon="restart_alt" confirm-tone="warning" :confirm-label="__('incoming_pdf.split.action.reset')">
                        <x-icon-btn icon="restart_alt" tone="outline" size="sm" type="submit" show-label>{{ __('incoming_pdf.split.action.reset') }}</x-icon-btn>
                    </x-action-form>
                @endif
                @if (in_array($splitState, [$splitter::PROPOSED, $splitter::SINGLE, $splitter::DISMISSED], true))
                    <x-icon-btn icon="call_split" tone="primary" size="sm" data-entry-modal-trigger
                                :href="route('finance.incoming-invoices.split.form', $incoming)"
                                show-label>{{ __('incoming_pdf.split.action.open') }}</x-icon-btn>
                @endif
                @if (in_array($splitState, [$splitter::PENDING, $splitter::PROPOSED], true))
                    <x-action-form :action="route('finance.incoming-invoices.split.dismiss', $incoming)"
                                   :confirm="__('incoming_pdf.split.action.dismiss_confirm')"
                                   confirm-icon="check" confirm-tone="primary" :confirm-label="__('incoming_pdf.split.action.dismiss')">
                        <x-icon-btn icon="check" tone="outline" size="sm" type="submit" show-label>{{ __('incoming_pdf.split.action.dismiss') }}</x-icon-btn>
                    </x-action-form>
                @endif
            </div>
        @endif
    </x-card>
@elseif ($splitParts->isNotEmpty())
    <x-card :title="__('incoming_pdf.split.parts.title')" icon="call_split">
        <p class="text-sm text-muted">{{ __('incoming_pdf.split.parts.intro') }}</p>
        <x-detail-grid>
            @foreach ($splitParts as $part)
                <x-detail-grid.row :label="__('incoming_pdf.split.parts.pages', ['from' => $part->source_page_from, 'to' => $part->source_page_to])">
                    @if ($part->document !== null)
                        <a class="link link-hover" href="{{ route('finance.incoming-invoices.show', $part->document) }}">{{ $part->invoice_number ?? $part->document->title }}</a>
                    @else
                        {{ $part->invoice_number ?? '—' }}
                    @endif
                    <span class="text-xs text-muted">{{ $part->seller_name ?? '—' }} · {{ $part->status->label() }}</span>
                </x-detail-grid.row>
            @endforeach
        </x-detail-grid>
        <p class="mt-2 text-xs text-muted">{{ __('incoming_pdf.split.parts.protected') }}</p>
        {{-- Der Sammel-Eingang bleibt als Nachweis (GoBD): Eingangszeit, Prüfsumme, Begleitdateien der Mail. --}}
        @if ($splitContainer !== null)
            <x-detail-grid>
                <x-detail-grid.row :label="__('Status')">{{ $splitContainer->status->label() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Empfangen')">{{ $splitContainer->received_at->orgTz()->isoFormat('L LT') }} · {{ $splitContainer->source }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('SHA-256')"><span class="break-all font-mono text-xs">{{ $splitContainer->sha256 }}</span></x-detail-grid.row>
            </x-detail-grid>
        @endif
    </x-card>
    @if ($splitContainer !== null && $splitContainer->attachments->isNotEmpty())
        <x-attachments-section :attachments="$splitContainer->attachments" details />
    @endif
@endif
