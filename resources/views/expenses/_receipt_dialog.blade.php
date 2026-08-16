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
                    @if ($canLink)
                        <x-action-form :action="route('expenses.unlink-voucher', $expense)" method="DELETE"
                                       :confirm="__('expenses.receipt.unlink_confirm')"
                                       :confirm-label="__('expenses.receipt.unlink')">
                            <x-icon-btn icon="link_off" tone="error" size="sm" type="submit"
                                        :label="__('expenses.receipt.unlink')" />
                        </x-action-form>
                    @endif
                </div>
            @elseif ($suggestions->isEmpty())
                <x-empty-state compact icon='<span class="material-symbols-outlined">link_off</span>'
                               :title="__('expenses.receipt.no_suggestions')"
                               :message="__('expenses.receipt.no_suggestions_hint')" />
            @else
                <p class="text-sm text-base-content/70">{{ __('expenses.receipt.suggestions_hint') }}</p>
                <ul class="mt-2 divide-y divide-base-300 text-sm">
                    @foreach ($suggestions as $candidate)
                        <li class="flex items-center justify-between gap-2 py-2">
                            <div class="min-w-0">
                                <span class="font-medium">{{ $candidate->voucher_number ?: '—' }}</span>
                                <span class="text-base-content/60">
                                    · {{ optional($candidate->voucher_date)->format('d.m.Y') }}
                                    · {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($candidate->total_amount?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }}
                                    {{ $candidate->currency->value }}
                                    @if ($candidate->supplier)
                                        · {{ $candidate->supplier->name }}
                                    @endif
                                </span>
                            </div>
                            @if ($canLink)
                                <x-action-form :action="route('expenses.link-voucher', $expense)">
                                    <input type="hidden" name="voucher" value="{{ $candidate->sqid }}">
                                    <x-icon-btn icon="link" tone="primary" size="sm" type="submit"
                                                :label="__('expenses.receipt.link')" />
                                </x-action-form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-modal>
