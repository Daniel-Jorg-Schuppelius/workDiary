{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _draft_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Rechnungsvorschlag (Feature 152, MVP-764): Rechnungsempfänger mit offenen
  Perioden wählen; eine Position je Abo und zusammenhängendem Zeitraum
  (MVP-1436). Empfänger, deren offene Perioden schon in einem Entwurf stehen,
  werden nur genannt, nicht erneut angeboten (Review 2026-09-10, A4). Der
  Stichtag (MVP-1437) nimmt Perioden hinzu, die erst bis dahin beginnen; die
  Links laden den Dialog mit dem Stichtag neu, das Formular trägt ihn mit.
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
    <div class="text-sm">
        <span class="font-medium">{{ __('resale.draft.until') }}</span>
        <span class="inline-flex flex-wrap gap-1 ms-2">
            @foreach ([0, 1, 2, 3] as $years)
                @php($presetDate = $years === 0 ? $today : $today->addYears($years))
                <x-button size="xs" :tone="$presetDate->isSameDay($until) ? 'primary' : 'ghost'" :href="route('finance.resale.periods.draft.create', array_filter(['until' => $presetDate->toDateString(), 'customer' => $preselect ?? '']))" data-entry-modal-trigger>{{ $years === 0 ? __('resale.draft.until_today') : trans_choice('resale.draft.until_years', $years, ['years' => $years]) }}</x-button>
            @endforeach
        </span>
        <p class="text-xs text-muted mt-1">{{ __('resale.draft.until_hint', ['date' => $until->fdate()]) }}</p>
    </div>
    <input type="hidden" name="until" value="{{ $until->toDateString() }}">
    @if ($recipients === [])
        <div role="status" class="alert alert-info text-sm"><span>{{ __('resale.draft.error.nothing_open') }}</span></div>
    @endif
    <x-select-field name="customer_id" :label="__('resale.field.billed_to')" required>
        <option value="">—</option>
        @foreach ($recipients as $row)
            <option value="{{ $row['customer']->sqid }}" @selected(old('customer_id', $preselect ?? '') === $row['customer']->sqid)>
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
