{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : accept.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Abnahme im Kundenportal (MVP-1572): Protokoll lesen und mit gezeichneter
  Unterschrift abnehmen — dieselben Angaben wie am Signaturlink.
--}}
@extends('customer.layout')

@section('content')
    <x-page-toolbar :title="$protocol->title" :subtitle="$protocol->type->label() . ' · ' . optional($protocol->occurred_at)->fdate()" :back="$backUrl" :back-label="__('Zurück')" class="mb-4" />

    <x-validation-errors first class="mb-3" />

    <div class="space-y-6">
        @if ($protocol->description || $protocol->state_initial || $protocol->state_final)
            <x-card as="section" :title="__('protocol.field.description')" icon="description">
                @if ($protocol->description)
                    <p class="whitespace-pre-line text-sm">{{ $protocol->description }}</p>
                @endif
                @if ($protocol->state_initial)
                    <p class="mt-2 text-sm"><span class="font-medium">{{ __('protocol.field.state_initial') }}:</span> {{ $protocol->state_initial }}</p>
                @endif
                @if ($protocol->state_final)
                    <p class="mt-2 text-sm"><span class="font-medium">{{ __('protocol.field.state_final') }}:</span> {{ $protocol->state_final }}</p>
                @endif
            </x-card>
        @endif

        <x-card as="section" :title="__('protocol.pdf.items')" icon="checklist">
            @if ($protocol->items->isEmpty())
                <x-empty-state icon="checklist" :title="__('Keine Positionen erfasst.')" compact />
            @else
                @php($itemFields = app(\App\Services\Protocol\Fields\ProtocolItemFields::class))
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('protocol.pdf.col.label') }}</th>
                            <th>{{ __('protocol.pdf.col.value') }}</th>
                            <th>{{ __('protocol.pdf.col.result') }}</th>
                            <th>{{ __('protocol.pdf.col.note') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($protocol->items as $item)
                        <tr>
                            <td class="font-medium">{{ $item->label }}</td>
                            <td class="text-sm"><x-field-display :field="$itemFields->definition($item)" :values="$itemFields->values($item)" /></td>
                            <td>{{ $item->result?->label() ?? '—' }}</td>
                            <td class="text-sm opacity-70">{{ $item->note ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>

        <x-card as="section" :title="__('protocol.signature.approveHeading')" icon="draw">
            <form method="POST" action="{{ $action }}" x-data="signaturePad" @submit="prepare($event)" class="flex flex-col gap-3">
                @csrf
                <x-input-field name="signer_name" :label="__('Name des Unterzeichners')" :value="old('signer_name', $user->name)" required minlength="2" maxlength="120" />
                <div>
                    <span class="text-sm font-medium">{{ __('protocol.signature.drawHeading') }}</span>
                    <div class="mt-1 rounded-box border border-base-300 bg-white p-2">
                        <canvas x-ref="canvas" class="block h-40 w-full touch-none rounded bg-white" aria-label="{{ __('protocol.signature.drawHeading') }}"></canvas>
                    </div>
                    <input type="hidden" name="signature" x-ref="sigInput">
                    <div class="mt-1 flex items-center justify-between gap-2">
                        <x-button tone="ghost" size="xs" @click="clear()">{{ __('protocol.dialog.signature_clear') }}</x-button>
                        <span class="text-xs opacity-70">{{ __('protocol.signature.drawHint') }}</span>
                    </div>
                </div>
                <x-checkbox-field name="accept" :label="__('Ich bestätige die Richtigkeit der Angaben.')" :toggle="false" :with-hidden="false" required />
                <div class="flex justify-end">
                    <x-button type="submit" tone="primary" ::disabled="isEmpty">{{ __('Unterschreiben') }}</x-button>
                </div>
            </form>
            <p class="mt-3 text-xs opacity-70">{{ __('protocol.portal.query_hint') }}</p>
        </x-card>

        {{-- Ablehnen wie am Signaturlink: Begründung Pflicht, Mängel werden offene Punkte, das Protokoll geht zurück. --}}
        <x-card as="section" :title="__('protocol.signature.rejectHeading')" icon="thumb_down">
            <form method="POST" action="{{ $rejectAction }}" class="flex flex-col gap-3">
                @csrf
                <p class="text-xs opacity-70">{{ __('protocol.signature.rejectHint') }}</p>
                <x-textarea-field name="reason" :label="__('protocol.signature.rejectReason')" :value="old('reason')" rows="3" required minlength="3" maxlength="2000" />
                <x-textarea-field name="issues[]" id="protocol-reject-issue-1" :label="__('protocol.signature.rejectIssues')" rows="2" maxlength="200" :placeholder="__('protocol.signature.rejectIssuesPlaceholder')" />
                <x-textarea-field name="issues[]" id="protocol-reject-issue-2" :label="__('protocol.signature.rejectIssues')" rows="2" maxlength="200" />
                <div class="flex justify-end">
                    <x-button type="submit" tone="error" class="btn-outline">{{ __('protocol.signature.rejectSubmit') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
