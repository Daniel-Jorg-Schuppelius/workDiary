@extends('layouts.app')
@section('title', $agreement->title)
@section('content')
    <div class="p-4 max-w-4xl space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ $agreement->title }} <span class="badge badge-ghost ml-2">{{ $agreement->status->label() }}</span></h1>
            <a class="link text-sm" href="{{ route('dataprotection.processors.show', $agreement->processor) }}">{{ $agreement->processor?->name }}</a>
        </div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <div class="grid md:grid-cols-3 gap-4">
            <section class="card bg-base-200 p-4 md:col-span-2 text-sm space-y-1">
                <p><span class="font-semibold">{{ __('Version') }}:</span> {{ $agreement->version }}</p>
                <p><span class="font-semibold">{{ __('Gültigkeit') }}:</span> {{ $agreement->valid_from?->format('d.m.Y') ?? '—' }} – {{ $agreement->valid_until?->format('d.m.Y') ?? '—' }}</p>
                <p><span class="font-semibold">{{ __('Datenkategorien') }}:</span> {{ $agreement->data_categories ?? '—' }}</p>
                @if ($agreement->document_path)
                    <p><a class="link" href="{{ route('dataprotection.agreements.document', $agreement) }}">{{ __('Vertragsdokument') }}: {{ $agreement->document_name }}</a></p>
                @endif
                @if ($agreement->terminated_at)
                    <p class="text-warning">{{ __('Gekündigt am') }} {{ $agreement->terminated_at->format('d.m.Y') }} —
                        {{ __('Datenrückgabe') }}: {{ $agreement->data_return ?? 'offen' }}
                        @if ($agreement->data_return_confirmed_at) ({{ $agreement->data_return_confirmed_at->format('d.m.Y') }}) @endif
                    </p>
                @endif
            </section>

            @can('update', $agreement)
                <aside class="space-y-2">
                    @if ($agreement->status->value === 'draft')
                        <form method="post" action="{{ route('dataprotection.agreements.activate', $agreement) }}">@csrf <button class="btn btn-sm btn-outline w-full">{{ __('Aktivieren') }}</button></form>
                    @endif
                    @if ($agreement->status->value !== 'terminated')
                        <form method="post" action="{{ route('dataprotection.agreements.terminate', $agreement) }}">@csrf <button class="btn btn-sm btn-error btn-outline w-full">{{ __('Kündigen') }}</button></form>
                    @else
                        <form method="post" action="{{ route('dataprotection.agreements.return', $agreement) }}" class="space-y-1">
                            @csrf
                            <select name="mode" class="select select-sm select-bordered w-full">
                                <option value="returned">{{ __('Daten zurückgegeben') }}</option>
                                <option value="deleted">{{ __('Daten gelöscht') }}</option>
                            </select>
                            <button class="btn btn-sm btn-primary w-full">{{ __('Nachweis bestätigen') }}</button>
                        </form>
                    @endif
                </aside>
            @endcan
        </div>

        {{-- Verknüpfte Verarbeitungstätigkeiten --}}
        @can('update', $agreement)
            <section class="card bg-base-200 p-4 space-y-2">
                <h2 class="font-semibold">{{ __('Verknüpfte Verarbeitungstätigkeiten') }}</h2>
                <form method="post" action="{{ route('dataprotection.agreements.activities', $agreement) }}" class="space-y-2">
                    @csrf
                    <div class="grid md:grid-cols-2 gap-1">
                        @foreach ($allActivities as $act)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="activity_ids[]" value="{{ $act->id }}" class="checkbox checkbox-sm" @checked(in_array($act->id, $linkedIds, true))>
                                {{ $act->name }}
                            </label>
                        @endforeach
                    </div>
                    <button class="btn btn-sm">{{ __('Verknüpfungen speichern') }}</button>
                </form>
            </section>
        @endcan

        {{-- Unterauftragsverarbeiter --}}
        <section class="card bg-base-200 p-4 space-y-2">
            <h2 class="font-semibold">{{ __('Unterauftragsverarbeiter') }}</h2>
            <ul class="space-y-1">
                @forelse ($agreement->subprocessors as $sub)
                    <li class="flex items-center justify-between text-sm rounded-box border border-base-300 px-3 py-2">
                        <span>{{ $sub->name }} @if ($sub->location)<span class="text-base-content/60">— {{ $sub->location }}</span>@endif {{ $sub->third_country ? '('.__('Drittland').')' : '' }}</span>
                        @if ($sub->approved)
                            <span class="badge badge-success badge-sm">{{ __('freigegeben') }}</span>
                        @else
                            @can('update', $agreement)
                                <form method="post" action="{{ route('dataprotection.agreements.subprocessor.approve', [$agreement, $sub]) }}">@csrf <button class="btn btn-xs">{{ __('Freigeben') }}</button></form>
                            @else
                                <span class="badge badge-warning badge-sm">{{ __('offen') }}</span>
                            @endcan
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-base-content/60">{{ __('Keine Unterauftragsverarbeiter.') }}</li>
                @endforelse
            </ul>
            @can('update', $agreement)
                <form method="post" action="{{ route('dataprotection.agreements.subprocessor.store', $agreement) }}" class="grid md:grid-cols-3 gap-2 pt-2">
                    @csrf
                    <input name="name" class="input input-sm input-bordered" placeholder="{{ __('Name') }}" required>
                    <input name="location" class="input input-sm input-bordered" placeholder="{{ __('Ort') }}">
                    <button class="btn btn-sm">{{ __('Hinzufügen') }}</button>
                </form>
            @endcan
        </section>
    </div>
@endsection
