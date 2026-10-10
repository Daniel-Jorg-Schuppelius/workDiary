{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

@section('title', $claim->number)

@section('content')
<div class="space-y-4">
    <h1 class="text-xl font-semibold">{{ $claim->number }} — {{ $claim->title }}</h1>


    <x-card class="flex flex-col gap-2">
        <p><x-status-badge tone="plain" size="md" outline>{{ $claim->status->label() }}</x-status-badge></p>
        <x-detail-grid class="grid-cols-2">
            <x-detail-grid.row :label="__('Gemeldet am')">{{ $claim->reported_at->fdate() }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('Rücksendungen')">
                @if ($claim->rmaReturns->isEmpty())
                    —
                @else
                    @foreach ($claim->rmaReturns as $rma)
                        <span class="font-mono">{{ $rma->rma_number }}</span> ({{ $rma->status->label() }})
                        @if ($portalReturns)
                            @foreach ($rma->returnShipments as $shipment)
                                @if ($shipment->labelAttachment())
                                    <a class="link ml-1" href="{{ route('customer.returns.label', [$rma, $shipment]) }}">{{ __('claims.portal_return.label') }}</a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                @endif
            </x-detail-grid.row>
        </x-detail-grid>
        @if ($claim->description !== null)
            <p class="whitespace-pre-line text-sm">{{ $claim->description }}</p>
        @endif
    </x-card>

    <x-card class="flex flex-col gap-2">
        <h2 class="card-title text-base">{{ __('Nachreichung') }}</h2>
        <p class="text-sm text-muted">{{ __('Ergänzende Informationen zu Ihrer Reklamation übermitteln.') }}</p>
        <form method="POST" action="{{ route('customer.claims.note', $claim) }}" class="space-y-2">
            @csrf
            <textarea aria-label="{{ __('Ihre Nachricht …') }}" name="note" rows="3" class="textarea textarea-bordered w-full" required minlength="3" maxlength="2000" placeholder="{{ __('Ihre Nachricht …') }}"></textarea>
            @error('note')<p class="text-sm text-error">{{ $message }}</p>@enderror
            <x-button type="submit">{{ __('Absenden') }}</x-button>
        </form>
        @if ($portalNotes->isNotEmpty())
            <h3 class="mt-2 text-sm font-semibold">{{ __('claims.portal_note.history') }}</h3>
            <ul class="divide-y divide-base-300 text-sm">
                @foreach ($portalNotes as $portalNote)
                    <li class="py-2">
                        <p class="text-xs text-muted">{{ $portalNote->recorded_at->fdatetime() }}</p>
                        <p class="whitespace-pre-line">{{ $portalNote->note }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <a class="link" href="{{ route('customer.claims.index') }}">{{ __('Zurück zur Übersicht') }}</a>
</div>
@endsection
