{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Anfragen und Aufträge (MVP-1074) — erwartet: $intakes, $stages, $hasUploadTargets --}}
@extends('customer.layout')

@section('content')
    <x-page-toolbar :title="__('customer_intake.portal.title')" :subtitle="__('customer_intake.portal.intro')" class="mb-4">
        <x-slot:actions>
            <x-icon-btn icon="print" tone="primary" :href="route('customer.intakes.create', ['kind' => 'print'])" show-label placement="bar">{{ __('customer_intake.portal.request_print') }}</x-icon-btn>
            <x-icon-btn icon="computer" tone="primary" :href="route('customer.intakes.create', ['kind' => 'it'])" show-label placement="bar">{{ __('customer_intake.portal.request_it') }}</x-icon-btn>
            @if ($hasUploadTargets)
                <x-icon-btn icon="upload_file" :href="route('customer.intakes.upload')" show-label>{{ __('customer_intake.portal.upload_files') }}</x-icon-btn>
            @endif
        </x-slot:actions>
    </x-page-toolbar>

    <x-card>
    <x-table bare>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('Nummer') }}</x-table.th>
                <x-table.th>{{ __('customer_intake.field.subject') }}</x-table.th>
                <x-table.th>{{ __('customer_intake.field.kind') }}</x-table.th>
                <x-table.th>{{ __('customer_intake.field.stage') }}</x-table.th>
                <x-table.th>{{ __('customer_intake.field.received_at') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($intakes as $intake)
            @php($stage = $stages->for($intake))
            <tr>
                <td class="whitespace-nowrap font-mono text-sm"><a class="link link-hover" href="{{ route('customer.intakes.show', $intake) }}">{{ $intake->number }}</a></td>
                <td>{{ $intake->subject }}</td>
                <td>{{ $intake->kind->label() }}</td>
                <td>
                    <x-status-badge :tone="$stage->tone">{{ $stage->label }}</x-status-badge>
                    @if ($stage->actionRequired)
                        <span class="block text-xs text-warning">{{ $stage->nextStep }}</span>
                    @endif
                </td>
                <td class="whitespace-nowrap">{{ $intake->created_at?->fdate() }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="5" icon="move_to_inbox" :title="__('customer_intake.portal.empty')" compact />
        @endforelse
    </x-table>
    </x-card>

    <x-pagination :paginator="$intakes" standing />
@endsection
