{{--
  Created on   : Mon Aug 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Rückfragen und Kommentare (MVP-512) — erwartet: $queries, $subjects --}}
@extends('customer.layout')

@section('content')
    <h1 class="text-2xl font-semibold mb-4">{{ __('Rückfragen und Kommentare') }}</h1>
    <p class="mb-4 text-sm text-base-content/70">
        {{ __('Ihre Rückfragen zu freigegebenen Aufträgen, Zeiten und Dokumenten. Nach dem Absenden ist der Text aus Nachweisgründen nicht änderbar; eine Rücknahme wird protokolliert.') }}
    </p>

    <div class="space-y-3">
        @forelse ($queries as $query)
            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-muted">
                    <span class="font-medium text-base-content">{{ $query->subject !== null ? $subjects->label($query->subject) : __('(Vorgang nicht mehr verfügbar)') }}</span>
                    <span>·</span>
                    <span>{{ $query->created_at?->fdatetime() }}</span>
                    <span class="ms-auto">
                        @switch($query->status)
                            @case(\App\Enums\Customer\CustomerQueryStatus::Answered)
                                <span class="badge badge-sm badge-success">{{ __('beantwortet') }}</span>
                                @break
                            @case(\App\Enums\Customer\CustomerQueryStatus::Closed)
                                <span class="badge badge-sm badge-ghost">{{ __('geschlossen') }}</span>
                                @break
                            @default
                                <span class="badge badge-sm badge-info">{{ __('offen') }}</span>
                        @endswitch
                    </span>
                </div>

                <div class="text-sm">
                    <p class="text-xs font-semibold text-muted">{{ $query->asker_name ?? __('Sie') }} ({{ __('Kunde') }})</p>
                    <p class="whitespace-pre-line">{{ $query->question }}</p>
                    @if ($query->attachments->isNotEmpty())
                        <ul class="mt-2 flex flex-wrap gap-2 text-xs">
                            @foreach ($query->attachments as $attachment)
                                <li>
                                    <a class="link link-hover inline-flex items-center gap-1" href="{{ route('customer.queries.attachments.download', [$query, $attachment]) }}">
                                        <x-icon name="attach_file" class="text-sm" />{{ $attachment->original_name }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @if ($query->answer !== null)
                    <div class="mt-3 rounded-box bg-base-200/60 p-3 text-sm">
                        <p class="text-xs font-semibold text-muted">
                            {{ $query->answeredBy?->name ?? __('Service-Team') }} ({{ __('Team') }})
                            @if ($query->answered_at) · {{ $query->answered_at->fdatetime() }} @endif
                        </p>
                        <p class="whitespace-pre-line">{{ $query->answer }}</p>
                    </div>
                @endif

                @if ($query->isOpen())
                    <form method="POST" action="{{ route('customer.queries.withdraw', $query) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-xs">{{ __('Rückfrage zurückziehen') }}</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="rounded-box border border-base-300 bg-base-100 p-8 text-center">
                <x-icon name="forum" class="mb-2 text-4xl text-muted" />
                <p class="font-medium">{{ __('Noch keine Rückfragen.') }}</p>
                <p class="mt-1 text-sm text-muted">{{ __('Stellen Sie Rückfragen direkt an freigegebenen Aufträgen, Zeiten oder Dokumenten.') }}</p>
            </div>
        @endforelse
    </div>

    <x-pagination :paginator="$queries" standing />
@endsection
