@extends('layouts.app')
@section('title', $processor->name)
@section('nav-title', $processor->name)
@section('content')
    <x-index-page :subtitle="__('Stammdaten des Dienstleisters und zugeordnete Auftragsverarbeitungsverträge.')">
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Stammdaten') }}
                <x-status-badge tone="ghost" size="sm" class="ml-2">{{ $processor->role->label() }}</x-status-badge>
            </h2>
            <div class="text-sm space-y-1 mt-2">
                <p><span class="font-semibold">{{ __('Kontakt') }}:</span> {{ $processor->contact ?? '—' }}</p>
                <p><span class="font-semibold">{{ __('Verarbeitungsort') }}:</span> {{ $processor->location ?? '—' }} {{ $processor->third_country ? '('.__('Drittland').')' : '' }}</p>
                @if ($processor->notes)<p class="whitespace-pre-line">{{ $processor->notes }}</p>@endif
            </div>
        </x-card>

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Auftragsverarbeitungsverträge') }}</h2>
            <ul class="space-y-1 mt-2">
                @forelse ($processor->agreements as $a)
                    <li class="flex items-center justify-between rounded-box border border-base-300 px-3 py-2">
                        <a class="link" href="{{ route('dataprotection.agreements.show', $a) }}">{{ $a->title }} (v{{ $a->version }})</a>
                        <x-status-badge tone="ghost" size="sm">{{ $a->status->label() }}</x-status-badge>
                    </li>
                @empty
                    <li class="text-sm text-base-content/60">{{ __('Noch kein AVV.') }}</li>
                @endforelse
            </ul>
        </x-card>

        @can('create', \App\Models\Privacy\ProcessingAgreement::class)
            <x-card>
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Neuen AVV anlegen') }}</h2>
                <form method="post" action="{{ route('dataprotection.agreements.store') }}" enctype="multipart/form-data" class="space-y-2 mt-2">
                    @csrf
                    <input type="hidden" name="processor_id" value="{{ $processor->id }}">
                    <div class="grid md:grid-cols-2 gap-2">
                        <input name="title" class="input input-sm input-bordered" placeholder="{{ __('Titel') }}" required>
                        <input name="version" class="input input-sm input-bordered" placeholder="{{ __('Version') }}" value="1.0">
                        <input name="valid_from" type="date" class="input input-sm input-bordered" title="{{ __('Gültig ab') }}">
                        <input name="valid_until" type="date" class="input input-sm input-bordered" title="{{ __('Gültig bis') }}">
                    </div>
                    <textarea name="data_categories" rows="2" class="textarea textarea-sm textarea-bordered w-full" placeholder="{{ __('Betroffene Datenkategorien') }}"></textarea>
                    <input type="file" name="document" class="file-input file-input-sm file-input-bordered w-full" accept=".pdf,.doc,.docx">
                    <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('AVV anlegen') }}</x-icon-btn>
                </form>
            </x-card>
        @endcan
    </x-index-page>
@endsection
