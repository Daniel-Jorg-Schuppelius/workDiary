{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

@section('title', __('Meine Reklamationen'))

@section('content')
<div class="space-y-4">
    <h1 class="text-xl font-semibold">{{ __('Meine Reklamationen') }}</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($cases->isEmpty())
        <p class="text-sm text-base-content/60">{{ __('Keine Reklamationen vorhanden.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Nummer') }}</th>
                        <th>{{ __('Titel') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Gemeldet am') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cases as $case)
                        <tr>
                            <td><a class="link font-mono" href="{{ route('customer.claims.show', $case) }}">{{ $case->number }}</a></td>
                            <td>{{ $case->title }}</td>
                            <td><span class="badge badge-outline">{{ $case->status->label() }}</span></td>
                            <td>{{ $case->reported_at->fdate() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-pagination :paginator="$cases" />
    @endif
</div>
@endsection
