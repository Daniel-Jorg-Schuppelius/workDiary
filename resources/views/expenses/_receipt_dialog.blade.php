{{--
  Created on   : Sun Aug 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _receipt_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $expense, $attachments, $canUpload (Feature 105, MVP-550) --}}
<x-modal
    :title="__('expenses.receipt.title')"
    :eyebrow="__('expenses.title.index')"
    icon="receipt_long"
    :submit-label="null">

    <p class="text-sm text-base-content/70">
        {{ __('expenses.receipt.hint') }}
    </p>

    <div class="alert alert-info mt-3 text-sm">
        <div>
            <div class="font-semibold">{{ $expense->vendor ?: __('expenses.receipt.no_vendor') }}</div>
            <div class="text-base-content/70">
                {{ $expense->date?->fdate() }} ·
                {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($expense->amount_gross?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }}
                {{ $expense->currency->value }}
            </div>
            @if ($expense->description)
                <div class="mt-1">{{ $expense->description }}</div>
            @endif
        </div>
    </div>

    <div class="mt-3">
        <x-attachments-section :attachments="$attachments"
                               upload-type="expense"
                               :upload-id="$expense->sqid"
                               :can-upload="$canUpload" />
    </div>

    {{-- MVP-551: Zuordnung zum Buchhaltungsbeleg. Solange sie fehlt, zählt die
         Auslage getrennt — sonst stünde derselbe Aufwand zweimal in den
         Kennzahlen. --}}
    <div class="mt-3">
        <x-card :title="__('expenses.receipt.link_title')" icon="link">
            @if ($linkedVoucher)
                <div class="flex items-center justify-between gap-2 text-sm">
                    <div class="min-w-0">
                        <a class="link link-hover font-medium"
                           href="{{ route('lexoffice.vouchers.preview', $linkedVoucher) }}"
                           data-entry-modal-trigger>
                            {{ $linkedVoucher->voucher_number ?: '—' }}
                        </a>
                        <span class="text-base-content/60">
                            · {{ optional($linkedVoucher->voucher_date)->format('d.m.Y') }}
                            · {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($linkedVoucher->total_amount?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }}
                            {{ $linkedVoucher->currency->value }}
                        </span>
                    </div>
                    @if ($wasPushed ?? false)
                        {{-- Feature 106: aktiv übergebener Beleg — er existiert
                             unwiderruflich, die Verknüpfung bleibt. --}}
                        <span class="badge badge-success badge-sm shrink-0">{{ __('Als Beleg übergeben') }}</span>
                    @elseif ($canLink)
                        <x-action-form :action="route('expenses.unlink-voucher', $expense)" method="DELETE"
                                       :confirm="__('expenses.receipt.unlink_confirm')"
                                       :confirm-label="__('expenses.receipt.unlink')">
                            <x-icon-btn icon="link_off" tone="error" size="sm" type="submit"
                                        :label="__('expenses.receipt.unlink')" />
                        </x-action-form>
                    @endif
                </div>
            @elseif (($canPush ?? false))
                {{-- Feature 106: Die Dublette gar nicht erst entstehen lassen —
                     die Auslage wird selbst zum Beleg, die externe ID kommt
                     beim Anlegen zurück. Terminal: danach führt der Beleg. --}}
                <div class="flex items-center justify-between gap-3 text-sm">
                    <p class="text-base-content/70">{{ __('Diese genehmigte Auslage kann direkt als Einkaufsbeleg an die Buchhaltung übergeben werden — statt sie dort ein zweites Mal zu erfassen.') }}</p>
                    <x-action-form :action="route('expenses.push-voucher', $expense)"
                                   :confirm="__('Auslage unwiderruflich als Beleg an die Buchhaltung übergeben? Der Beleg lässt sich dort nicht löschen; Korrekturen laufen als Gegenbeleg.')"
                                   :confirm-label="__('Übergeben')" confirm-icon="outbox">
                        <x-icon-btn icon="outbox" tone="primary" size="sm" type="submit" show-label>{{ __('In die Buchhaltung übernehmen') }}</x-icon-btn>
                    </x-action-form>
                </div>
                @if ($suggestions->isNotEmpty())
                    <div class="divider my-2 text-xs">{{ __('oder vorhandenen Beleg zuordnen') }}</div>
                @endif
                @include('expenses._receipt_suggestions', ['suggestions' => $suggestions, 'expense' => $expense, 'canLink' => $canLink])
            @elseif ($suggestions->isEmpty())
                <x-empty-state compact icon='<span class="material-symbols-outlined">link_off</span>'
                               :title="__('expenses.receipt.no_suggestions')"
                               :message="__('expenses.receipt.no_suggestions_hint')" />
            @else
                @include('expenses._receipt_suggestions', ['suggestions' => $suggestions, 'expense' => $expense, 'canLink' => $canLink])
            @endif
        </x-card>
    </div>
</x-modal>
