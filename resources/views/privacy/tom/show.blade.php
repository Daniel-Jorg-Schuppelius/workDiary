@extends('layouts.app')
@section('title', $measure->name)
@section('content')
    <div class="p-4 max-w-4xl space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ $measure->name }}</h1>
            <span class="badge badge-ghost">{{ $measure->category->label() }} · {{ $measure->implementation_status->label() }}</span>
        </div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        @if ($measure->currentVersion)
            <section class="card bg-base-200 p-4 text-sm space-y-1">
                <p><span class="font-semibold">{{ __('Gültige Version') }}:</span> v{{ $measure->currentVersion->version_no }}</p>
                <p class="whitespace-pre-line">{{ data_get($measure->currentVersion->payload, 'description') }}</p>
            </section>
        @endif

        {{-- Versionen --}}
        <section class="space-y-2">
            <h2 class="font-semibold">{{ __('Versionen') }}</h2>
            <div class="overflow-x-auto rounded-box border border-base-300">
                <table class="table table-sm">
                    <thead><tr><th>{{ __('Version') }}</th><th>{{ __('Notiz') }}</th><th>{{ __('Freigabe') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($versions as $v)
                            <tr>
                                <td>v{{ $v->version_no }}</td>
                                <td class="text-sm">{{ $v->note ?? '—' }}</td>
                                <td class="text-sm">{{ $v->approved_at?->format('d.m.Y') ?? __('Entwurf') }}</td>
                                <td>
                                    @can('update', $measure)
                                        @unless ($v->approved_at)
                                            <form method="post" action="{{ route('dataprotection.tom.approve', $measure) }}">@csrf <input type="hidden" name="version_id" value="{{ $v->id }}"><button class="btn btn-xs btn-primary">{{ __('Freigeben') }}</button></form>
                                        @endunless
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @can('update', $measure)
            <section class="card bg-base-200 p-4 space-y-2">
                <h2 class="font-semibold">{{ __('Neue Version') }}</h2>
                <form method="post" action="{{ route('dataprotection.tom.version', $measure) }}" class="space-y-2">
                    @csrf
                    <textarea name="description" rows="2" class="textarea textarea-sm textarea-bordered w-full" placeholder="{{ __('Beschreibung') }}">{{ data_get($measure->currentVersion?->payload, 'description') }}</textarea>
                    <textarea name="addressed_risks" rows="2" class="textarea textarea-sm textarea-bordered w-full" placeholder="{{ __('Adressierte Risiken') }}">{{ data_get($measure->currentVersion?->payload, 'addressed_risks') }}</textarea>
                    <input name="note" class="input input-sm input-bordered w-full" placeholder="{{ __('Änderungsnotiz') }}">
                    <button class="btn btn-sm btn-primary">{{ __('Version speichern') }}</button>
                </form>
            </section>
        @endcan

        {{-- Zuordnung zu Verarbeitungstätigkeiten --}}
        <section class="card bg-base-200 p-4 space-y-2">
            <h2 class="font-semibold">{{ __('Zugeordnete Verarbeitungstätigkeiten') }}</h2>
            <ul class="text-sm space-y-1">
                @forelse ($measure->assignments->whereNotNull('activity_id') as $as)
                    <li>• {{ $as->activity?->name ?? '—' }}</li>
                @empty
                    <li class="text-base-content/60">{{ __('Keine Zuordnung.') }}</li>
                @endforelse
            </ul>
            @can('update', $measure)
                <form method="post" action="{{ route('dataprotection.tom.assign', $measure) }}" class="flex gap-2 pt-2">
                    @csrf
                    <select name="activity_id" class="select select-sm select-bordered flex-1">
                        @foreach ($activities as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                    </select>
                    <button class="btn btn-sm">{{ __('Zuordnen') }}</button>
                </form>
            @endcan
        </section>

        {{-- Wirksamkeitsprüfungen --}}
        <section class="card bg-base-200 p-4 space-y-2">
            <h2 class="font-semibold">{{ __('Wirksamkeitsprüfungen') }}</h2>
            <ul class="text-sm space-y-1">
                @forelse ($measure->reviews as $r)
                    <li class="rounded-box border border-base-300 px-3 py-2">
                        {{ $r->reviewed_at?->format('d.m.Y') }} — <span class="font-semibold">{{ $r->result->label() }}</span>
                        @if ($r->deviation) · {{ $r->deviation }} @endif
                        @if ($r->due_at) <span class="text-base-content/60">({{ __('Folgemaßnahme bis') }} {{ $r->due_at->format('d.m.Y') }})</span> @endif
                    </li>
                @empty
                    <li class="text-base-content/60">{{ __('Noch keine Prüfung.') }}</li>
                @endforelse
            </ul>
            @can('update', $measure)
                <form method="post" action="{{ route('dataprotection.tom.review', $measure) }}" class="space-y-2 pt-2">
                    @csrf
                    <div class="grid md:grid-cols-2 gap-2">
                        <select name="result" class="select select-sm select-bordered">
                            @foreach ($results as $res)<option value="{{ $res->value }}">{{ $res->label() }}</option>@endforeach
                        </select>
                        <input name="due_at" type="date" class="input input-sm input-bordered" title="{{ __('Folgemaßnahme fällig') }}">
                    </div>
                    <textarea name="deviation" rows="2" class="textarea textarea-sm textarea-bordered w-full" placeholder="{{ __('Abweichung / Folgemaßnahme') }}"></textarea>
                    <button class="btn btn-sm">{{ __('Prüfung dokumentieren') }}</button>
                </form>
            @endcan
        </section>
    </div>
@endsection
