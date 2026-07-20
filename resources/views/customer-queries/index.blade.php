{{--
  Created on   : Sun Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('customer-query.title'))
@section('nav-title', __('customer-query.title'))

@section('content')
<x-index-page :subtitle="__('customer-query.subtitle')">
    <x-filter-bar :action="route('customer-queries.index')" :reset="route('customer-queries.index')">
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            @foreach (\App\Enums\Customer\CustomerQueryStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if ($queries->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">contact_support</span>' />
    @else
        <div class="space-y-3">
            @foreach ($queries as $query)
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="badge badge-sm {{ $query->status === \App\Enums\Customer\CustomerQueryStatus::Open ? 'badge-warning' : 'badge-ghost' }}">
                                    {{ $query->status->label() }}
                                </span>
                                <span class="text-xs text-base-content/60">
                                    {{ $query->asker_name ?: __('protocol.signature.customer') }}
                                    · {{ $query->created_at?->fdatetime() }}
                                </span>
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm font-medium">{{ $query->question }}</p>
                            @if ($query->answer)
                                <div class="mt-2 border-l-2 border-primary/40 pl-3 text-sm text-base-content/80">
                                    <span class="text-xs uppercase text-base-content/50">{{ __('customer-query.answer') }}</span><br>
                                    {{ $query->answer }}
                                    <div class="mt-1 text-xs text-base-content/50">
                                        {{ $query->answeredBy?->name }} · {{ $query->answered_at?->fdatetime() }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($query->status !== \App\Enums\Customer\CustomerQueryStatus::Closed)
                        <div class="mt-3 flex flex-wrap items-end gap-2">
                            <form method="POST" action="{{ route('customer-queries.answer', $query) }}" class="flex flex-1 items-end gap-2">
                                @csrf
                                <div class="form-control flex-1">
                                    <label class="label py-0"><span class="label-text text-xs">{{ __('customer-query.answer') }}</span></label>
                                    {{-- old() nur für das Formular der betroffenen Rückfrage (Übersetzungs-Vorschau, Feature 084 Phase-36-Rest). --}}
                                    <textarea name="answer" class="textarea textarea-sm textarea-bordered" rows="2" required minlength="2" maxlength="5000">{{ old('query_sqid') === $query->sqid ? old('answer') : '' }}</textarea>
                                </div>
                                <input type="hidden" name="query_sqid" value="{{ $query->sqid }}">
                                @if ($translateUsable ?? false)
                                    {{-- Übersetzungs-Vorschau: mit Zielsprache wird NICHT gespeichert,
                                         sondern der Entwurf (Original + Übersetzung) neu angezeigt. --}}
                                    <div class="form-control">
                                        <label class="label py-0"><span class="label-text text-xs">{{ __('ai.covering.translate_to') }}</span></label>
                                        <select name="translate_to" class="select select-sm select-bordered">
                                            <option value="">{{ __('ai.covering.translate_none') }}</option>
                                            @foreach (\App\Support\Locales::enabled() as $code => $label)
                                                <option value="{{ $code }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <x-button type="submit" size="sm" tone="primary">{{ __('customer-query.answerSubmit') }}</x-button>
                            </form>
                            <form method="POST" action="{{ route('customer-queries.close', $query) }}">
                                @csrf
                                <x-button type="submit" size="sm" tone="ghost">{{ __('customer-query.close') }}</x-button>
                            </form>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            <x-pagination :paginator="$queries" standing />
        </div>
    @endif
</x-index-page>
@endsection
