{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _draft_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Rechnungsvorschlag (Feature 152, MVP-764): Rechnungsempfänger mit offenen
  Perioden wählen; eine Position je Abo und Zeitraum. Empfänger, deren
  offene Perioden schon in einem Entwurf stehen, werden nur genannt, nicht
  erneut angeboten (Review 2026-09-10, A4).
--}}
<x-modal
    :title="__('resale.draft.dialog_title')"
    icon="receipt_long"
    tone="primary"
    size="md"
    :action="route('finance.resale.periods.draft.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.draft.submit')"
>
    <div class="text-sm text-base-content/70">{{ __('resale.draft.hint') }}</div>
    @if ($recipients === [])
        <div class="alert alert-info text-sm"><span>{{ __('resale.draft.error.nothing_open') }}</span></div>
    @endif
    <x-select-field name="customer_id" :label="__('resale.field.billed_to')" required>
        <option value="">—</option>
        @foreach ($recipients as $row)
            <option value="{{ $row['customer']->sqid }}" @selected(old('customer_id') === $row['customer']->sqid)>
                {{ $row['customer']->name }} · {{ trans_choice('resale.periods.open_count', $row['periods'], ['count' => $row['periods']]) }} · {{ $row['net']->format() }}
            </option>
        @endforeach
    </x-select-field>
    @if ($drafted !== [])
        <div class="mt-3">
            <div class="text-xs font-semibold uppercase tracking-wider text-muted mb-1">{{ __('resale.draft_dialog.drafted') }}</div>
            <ul class="text-sm space-y-1">
                @foreach ($drafted as $row)
                    <li>
                        <span class="font-medium">{{ $row['customer']->name }}</span>
                        <span class="text-muted">· {{ trans_choice('resale.draft_dialog.drafted_line', $row['drafted'], ['count' => $row['drafted'], 'reference' => $row['draft_reference'], 'date' => $row['draft_created_at'] !== null ? \App\Support\Tz::toLocal($row['draft_created_at'])?->fdate() : '—']) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="text-xs text-muted mt-1">{{ __('resale.draft_dialog.drafted_hint') }}</p>
        </div>
    @endif
</x-modal>
