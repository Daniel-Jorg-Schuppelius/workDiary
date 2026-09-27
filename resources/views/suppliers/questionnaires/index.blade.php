{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Fragebögen und Anfragen der Selbstauskunft (MVP-937). Erwartet: $questionnaires, $requests, $canManage --}}
@extends('layouts.app')

@section('title', __('supplier_questionnaire.title'))
@section('nav-title', __('supplier_questionnaire.title'))

@section('content')
<x-index-page :subtitle="__('supplier_questionnaire.subtitle')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" size="sm" tone="primary" data-entry-modal-trigger :href="route('supplier-questionnaires.create')" show-label>{{ __('supplier_questionnaire.create') }}</x-icon-btn>
        @endif
        <x-icon-btn icon="arrow_back" size="sm" :href="route('suppliers.index')" show-label>{{ __('Lieferanten') }}</x-icon-btn>
    </x-slot:actions>

    <x-card padding="p-0" :title="__('supplier_questionnaire.questionnaires')">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('supplier_questionnaire.field.name') }}</th>
                    <th class="text-right">{{ __('supplier_questionnaire.field.questions') }}</th>
                    <th class="text-right">{{ __('supplier_questionnaire.field.validity_months') }}</th>
                    <th class="text-right">{{ __('supplier_questionnaire.field.requests') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($questionnaires as $questionnaire)
                <tr>
                    <td>{{ $questionnaire->name }}@unless ($questionnaire->is_active) <span class="wd-badge badge-ghost">{{ __('supplier_questionnaire.inactive') }}</span>@endunless</td>
                    <td class="text-right tabular-nums">{{ $questionnaire->schema->count() }}</td>
                    <td class="text-right tabular-nums">{{ $questionnaire->validity_months }}</td>
                    <td class="text-right tabular-nums">{{ $questionnaire->requests_count }}</td>
                    <td>
                        <div class="flex justify-end">
                            @if ($canManage)
                                <x-icon-btn icon="edit" size="xs" data-entry-modal-trigger :href="route('supplier-questionnaires.edit', $questionnaire)" :label="__('supplier_questionnaire.edit')" />
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty icon="fact_check" :colspan="5" :title="__('supplier_questionnaire.empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-card padding="p-0" class="mt-4" :title="__('supplier_questionnaire.recent')">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Lieferant') }}</th>
                    <th>{{ __('supplier_questionnaire.field.name') }}</th>
                    <th>{{ __('supplier_questionnaire.field.sent_at') }}</th>
                    <th>{{ __('supplier_questionnaire.field.status') }}</th>
                    <th>{{ __('supplier_questionnaire.field.valid_until') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($requests as $qr)
                <tr>
                    <td><a class="link" href="{{ route('suppliers.show', $qr->supplier) }}">{{ $qr->supplier?->name }}</a></td>
                    <td>{{ $qr->questionnaire?->name }}</td>
                    <td>{{ $qr->sent_at?->format('d.m.Y') ?? '—' }}</td>
                    <td><span class="wd-badge badge-ghost">{{ $qr->status->label() }}</span></td>
                    <td>{{ $qr->valid_until?->format('d.m.Y') ?? '—' }}</td>
                    <td><div class="flex justify-end"><x-icon-btn icon="visibility" size="xs" data-entry-modal-trigger :href="route('supplier-questionnaires.requests.show', $qr)" :label="__('supplier_questionnaire.open')" /></div></td>
                </tr>
            @empty
                <x-table.empty icon="send" :colspan="6" :title="__('supplier_questionnaire.no_requests')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection
