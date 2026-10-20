{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('customer.layout')

{{-- Bekannte Fehler (Feature 065, MVP-156): read-only — nur Probleme mit
     status=known_error UND visibility=customer der eigenen Organisation. --}}

@section('content')
    <x-page-toolbar :title="__('Bekannte Fehler')" :subtitle="__('Bekannte Störungen mit empfohlenem Workaround — an der dauerhaften Lösung wird gearbeitet.')" class="mb-4" />

    @forelse ($problems as $problem)
        <x-card class="mb-3" :title="$problem->title" icon="bug_report">
            @if ($problem->workaround)
                <div class="mt-2 text-sm">
                    <span class="text-xs uppercase text-muted">{{ __('Workaround') }}</span>
                    <p class="whitespace-pre-wrap">{{ $problem->workaround }}</p>
                </div>
            @endif
        </x-card>
    @empty
        <x-empty-state framed icon="bug_report" :title="__('Derzeit sind keine bekannten Fehler dokumentiert.')" />
    @endforelse

    <x-pagination :paginator="$problems" standing />
@endsection
