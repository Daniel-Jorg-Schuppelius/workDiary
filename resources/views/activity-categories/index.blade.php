@extends('layouts.app')
@section('title', __('Tätigkeiten') . ' — WorkDiary')
@section('nav-title', __('Tätigkeiten'))

@php
    /** @var \Illuminate\Support\Collection $categories */
    $types = \App\Models\ActivityCategory::TYPES;
@endphp

@section('content')
    <div class="mx-auto w-full max-w-screen-xl space-y-6 px-4 py-4 xl:px-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="font-['Space_Grotesk'] text-2xl font-bold">{{ __('Tätigkeiten') }}</h1>
                <p class="text-sm text-base-content/60">{{ __('Verwaltet die Kategorien für nicht-projektgebundene Arbeitszeit.') }}</p>
            </div>
        </div>

        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

        <section class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h2 class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Neue Tätigkeit') }}</h2>
            <form method="POST" action="{{ route('activity-categories.store') }}" class="mt-3 grid gap-3 md:grid-cols-4">
                @csrf
                <label class="form-control md:col-span-1">
                    <span class="label-text text-xs">{{ __('Schlüssel') }}</span>
                    <input name="key" required class="input input-bordered input-sm" placeholder="team_meeting" pattern="[a-z0-9_\-]+">
                </label>
                <label class="form-control md:col-span-1">
                    <span class="label-text text-xs">{{ __('Bezeichnung') }}</span>
                    <input name="label" required class="input input-bordered input-sm">
                </label>
                <label class="form-control md:col-span-1">
                    <span class="label-text text-xs">{{ __('Typ') }}</span>
                    <select name="activity_type" class="select select-bordered select-sm">
                        @foreach ($types as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control md:col-span-1">
                    <span class="label-text text-xs">{{ __('Reihenfolge') }}</span>
                    <input name="sort_order" type="number" min="0" max="999" value="100" class="input input-bordered input-sm">
                </label>
                <div class="flex items-center gap-4 md:col-span-3">
                    <label class="label cursor-pointer gap-2"><input type="checkbox" name="counts_as_work" value="1" checked class="checkbox checkbox-sm"><span class="label-text text-xs">{{ __('Zählt als Arbeit') }}</span></label>
                    <label class="label cursor-pointer gap-2"><input type="checkbox" name="billable_default" value="1" class="checkbox checkbox-sm"><span class="label-text text-xs">{{ __('Standardmäßig abrechenbar') }}</span></label>
                    <label class="label cursor-pointer gap-2"><input type="checkbox" name="active" value="1" checked class="checkbox checkbox-sm"><span class="label-text text-xs">{{ __('Aktiv') }}</span></label>
                </div>
                <button type="submit" class="btn btn-sm btn-primary md:col-span-1">{{ __('Anlegen') }}</button>
            </form>
        </section>

        <section class="rounded-box border border-base-300 bg-base-100 shadow-xs">
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr><th>{{ __('Schlüssel') }}</th><th>{{ __('Bezeichnung') }}</th><th>{{ __('Typ') }}</th><th class="text-center">{{ __('Arbeit') }}</th><th class="text-center">{{ __('Abrechenbar') }}</th><th class="text-center">{{ __('Aktiv') }}</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $c)
                            <tr>
                                <td><code class="text-xs">{{ $c->key }}</code></td>
                                <td>{{ $c->label }}</td>
                                <td><span class="badge badge-sm badge-ghost">{{ $c->activity_type }}</span></td>
                                <td class="text-center">{!! $c->counts_as_work ? '✓' : '—' !!}</td>
                                <td class="text-center">{!! $c->billable_default ? '✓' : '—' !!}</td>
                                <td class="text-center">{!! $c->active ? '✓' : '—' !!}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('activity-categories.destroy', $c) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-xs text-error" onclick="return confirm('{{ __('Wirklich löschen?') }}')">{{ __('Löschen') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-sm text-base-content/60">{{ __('Noch keine Tätigkeiten.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
