{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Eingangs-E-Rechnungen (Nachtrag 045b): Upload + Liste der abgelegten
     Originale (Documents, Typ Rechnung). Rechnungshoheit bleibt extern. --}}

@extends('layouts.app')

@section('title', __('Eingangs-E-Rechnungen'))
@section('nav-title', __('Eingangs-E-Rechnungen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('XRechnung/ZUGFeRD empfangen und visualisieren — das Original wird als Dokument abgelegt, die Rechnungsführung bleibt im externen Programm.')">
    <x-slot:actions>
        @if ($canUpload)
            <form method="POST" action="{{ route('finance.incoming-invoices.store') }}" enctype="multipart/form-data" class="flex items-center gap-2">
                @csrf
                <input type="file" name="file" accept=".xml,.pdf,application/xml,text/xml,application/pdf"
                       class="file-input file-input-bordered file-input-sm max-w-64" required
                       aria-label="{{ __('E-Rechnung (XML oder PDF)') }}">
                <x-icon-btn icon="upload_file" tone="primary" size="sm" type="submit"
                            show-label>{{ __('E-Rechnung hochladen') }}</x-icon-btn>
            </form>
        @endif
    </x-slot:actions>

    <x-table scroll="flex" :pinRows="true">
        <x-slot:head>
            <tr>
                <th>{{ __('Titel') }}</th>
                <th>{{ __('Beschreibung') }}</th>
                <th>{{ __('Eingegangen') }}</th>
                <th>{{ __('Abgelegt von') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($documents as $document)
            <tr>
                <td>
                    <a class="link font-medium" href="{{ route('finance.incoming-invoices.show', $document) }}">{{ $document->title }}</a>
                </td>
                <td class="max-w-md truncate text-sm text-base-content/70">{{ $document->description ?? '—' }}</td>
                <td class="tabular-nums text-sm">{{ $document->created_at?->format('d.m.Y H:i') }}</td>
                <td class="text-sm">{{ $document->creator?->name ?? '—' }}</td>
                <td class="text-right">
                    <x-icon-btn icon="download" tone="ghost" size="xs"
                                :href="route('documents.download', $document)"
                                :label="__('Original herunterladen')" />
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="5" icon="receipt_long" :title="__('Noch keine Eingangs-E-Rechnungen erfasst.')" />
        @endforelse
    </x-table>

    <x-slot:footer>
        <x-pagination :paginator="$documents" standing />
    </x-slot:footer>
</x-index-page>
@endsection
