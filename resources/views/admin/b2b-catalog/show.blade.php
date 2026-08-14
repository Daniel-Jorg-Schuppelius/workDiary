{{--
  Created on   : Thu Jul 30 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('b2b_catalog.access_title', ['label' => $access->label]))
@section('nav-title', __('b2b_catalog.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        <x-validation-errors first />

        {{-- Einmalige Klartext-Anzeige des Secrets (Muster SCIM-Token) --}}
        @if ($issuedSecret)
            <div class="rounded-box border border-warning bg-warning/10 p-4">
                <h2 class="mb-1 font-semibold">{{ __('b2b_catalog.new_secret_heading') }}</h2>
                <p class="mb-2 text-sm">{{ __('b2b_catalog.new_secret_hint') }}</p>
                <code class="block rounded bg-base-200 px-3 py-2 text-sm select-all">{{ $issuedSecret }}</code>
            </div>
        @endif

        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ $access->label }}</h1>
                <div class="flex items-center gap-2">
                    @if ($access->isActive())
                        <span class="badge badge-success badge-sm">{{ __('b2b_catalog.status.active') }}</span>
                    @else
                        <span class="badge badge-ghost badge-sm">{{ __('b2b_catalog.status.revoked') }}</span>
                    @endif
                </div>
            </div>
            <div class="mb-3 space-y-1 text-sm">
                <div><span class="text-base-content/60">{{ __('b2b_catalog.field.customer') }}:</span> {{ $access->customer?->name }}</div>
                <div><span class="text-base-content/60">{{ __('b2b_catalog.field.username') }}:</span> <code class="rounded bg-base-200 px-1.5 py-0.5 text-xs">{{ $access->username }}</code></div>
                <div><span class="text-base-content/60">{{ __('b2b_catalog.field.last_used') }}:</span> {{ $access->last_used_at?->diffForHumans() ?? '—' }}</div>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('b2b-catalog.rotate', $access) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm">{{ __('b2b_catalog.action.rotate') }}</button>
                </form>
                @if ($access->isActive())
                    <form method="POST" action="{{ route('b2b-catalog.revoke', $access) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-ghost text-error">{{ __('b2b_catalog.action.revoke') }}</button>
                    </form>
                @endif
                <a href="{{ route('b2b-catalog.index') }}" class="btn btn-sm btn-ghost">{{ __('b2b_catalog.action.back') }}</a>
            </div>
        </div>

        {{-- Artikel-Freigaben --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h2 class="mb-1 font-['Space_Grotesk'] text-base font-semibold">{{ __('b2b_catalog.items_heading') }}</h2>
            <p class="mb-3 text-xs text-base-content/60">{{ __('b2b_catalog.items_hint') }}</p>

            <form method="POST" action="{{ route('b2b-catalog.items.store', $access) }}" class="mb-4 flex flex-wrap items-end gap-2">
                @csrf
                <label class="form-control">
                    <span class="label-text">{{ __('b2b_catalog.field.article') }}</span>
                    <select name="article" required class="select select-bordered select-sm min-w-64">
                        <option value="">{{ __('b2b_catalog.field.article_placeholder') }}</option>
                        @foreach ($articles as $article)
                            <option value="{{ $article->sqid }}">{{ $article->number }} — {{ $article->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('b2b_catalog.field.custom_price') }}</span>
                    <input type="number" name="custom_price" step="0.0001" min="0" value="{{ old('custom_price') }}" class="input input-bordered input-sm w-36" placeholder="{{ __('b2b_catalog.field.custom_price_placeholder') }}">
                </label>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('b2b_catalog.action.release') }}</button>
            </form>

            @if ($items->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('b2b_catalog.items_empty') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('b2b_catalog.field.article_number') }}</th>
                                <th>{{ __('b2b_catalog.field.article_name') }}</th>
                                <th class="text-right">{{ __('b2b_catalog.field.default_price') }}</th>
                                <th class="text-right">{{ __('b2b_catalog.field.custom_price') }}</th>
                                <th class="text-right">{{ __('b2b_catalog.field.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td><code class="text-xs">{{ $item->article?->number }}</code></td>
                                    <td>{{ $item->article?->name }}</td>
                                    <td class="text-right">{{ $item->article?->default_sale_price?->format() ?? '—' }}</td>
                                    <td class="text-right">{{ $item->custom_price?->format() ?? '—' }}</td>
                                    <td class="text-right">
                                        <div class="flex justify-end">
                                            <form method="POST" action="{{ route('b2b-catalog.items.destroy', [$access, $item]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('b2b_catalog.action.remove') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
