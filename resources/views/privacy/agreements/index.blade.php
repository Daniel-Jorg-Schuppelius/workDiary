{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('AVV-Register'))
@section('nav-title', __('Auftragsverarbeitungsverträge'))
@section('content')
    <x-index-page :subtitle="__('Auftragsverarbeitungsverträge (AVV) verwalten und Review-Fristen im Blick behalten.')">
        <x-slot:actions>
            <x-icon-btn icon="diversity_3" tone="ghost" size="sm"
                        :href="route('dataprotection.processors.index')"
                        show-label>{{ __('Dienstleister') }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        <p class="text-sm text-base-content/60">{{ __('Neue AVV werden auf der jeweiligen Dienstleister-Seite angelegt.') }}</p>

        <x-card padding="p-0">
            <x-table>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('Titel') }}</x-table.th>
                        <x-table.th>{{ __('Dienstleister') }}</x-table.th>
                        <x-table.th>{{ __('Version') }}</x-table.th>
                        <x-table.th>{{ __('Status') }}</x-table.th>
                        <x-table.th>{{ __('Gültig bis') }}</x-table.th>
                        <x-table.th>{{ __('Review') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($agreements as $a)
                    <tr class="hover">
                        <td><a class="link" href="{{ route('dataprotection.agreements.show', $a) }}">{{ $a->title }}</a></td>
                        <td>{{ $a->processor?->name ?? '—' }}</td>
                        <td>v{{ $a->version }}</td>
                        <td><x-status-badge tone="ghost" size="sm">{{ $a->status->label() }}</x-status-badge></td>
                        <td>{{ $a->valid_until?->format('d.m.Y') ?? '—' }}</td>
                        <td class="{{ $a->isReviewOverdue() ? 'text-error font-semibold' : '' }}">{{ $a->review_due_at?->format('d.m.Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="6" :title="__('Keine Verträge erfasst.')" />
                @endforelse
            </x-table>
        </x-card>

        <x-pagination :paginator="$agreements" standing />
    </x-index-page>
@endsection
