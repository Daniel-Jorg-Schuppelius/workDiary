@extends('layouts.app')

@section('title', $activity->name)

@section('content')
    <div class="p-4 max-w-4xl space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ $activity->name }}</h1>
            <span class="badge badge-ghost">{{ $activity->status->label() }}</span>
        </div>

        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <section class="card bg-base-200 p-4 space-y-1 text-sm">
            <p><span class="font-semibold">{{ __('Rolle') }}:</span> {{ $activity->controller_role->label() }}</p>
            <p><span class="font-semibold">{{ __('Zweck') }}:</span> {{ $activity->purpose ?? '—' }}</p>
            <p><span class="font-semibold">{{ __('Review fällig') }}:</span> {{ $activity->review_due_at?->format('d.m.Y') ?? '—' }}</p>
            @if ($activity->currentVersion)
                <p><span class="font-semibold">{{ __('Gültige Version') }}:</span> v{{ $activity->currentVersion->version_no }}
                    ({{ __('gültig ab') }} {{ $activity->currentVersion->valid_from?->format('d.m.Y') }})</p>
            @endif
        </section>

        <section class="space-y-2">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">{{ __('Versionen') }}</h2>
                @can('update', $activity)
                    <form method="post" action="{{ route('dataprotection.activities.submit', $activity) }}">
                        @csrf <button class="btn btn-xs btn-outline">{{ __('Zur Prüfung einreichen') }}</button>
                    </form>
                @endcan
            </div>
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
                                    @can('approve', $activity)
                                        @unless ($v->approved_at)
                                            <form method="post" action="{{ route('dataprotection.activities.approve', $activity) }}">
                                                @csrf <input type="hidden" name="version_id" value="{{ $v->id }}">
                                                <button class="btn btn-xs btn-primary">{{ __('Freigeben') }}</button>
                                            </form>
                                        @endunless
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @can('update', $activity)
            <section class="card bg-base-200 p-4 space-y-3">
                <h2 class="font-semibold">{{ __('Neue Version anlegen') }}</h2>
                <form method="post" action="{{ route('dataprotection.activities.version', $activity) }}" class="space-y-3">
                    @csrf
                    @include('privacy.activities._payload_fields', ['payload' => $activity->currentVersion?->payload])
                    <input name="note" class="input input-sm input-bordered w-full" placeholder="{{ __('Änderungsnotiz') }}">
                    {{-- name/role aus dem Kopf erneut mitsenden (validateActivity erwartet sie) --}}
                    <input type="hidden" name="name" value="{{ $activity->name }}">
                    <input type="hidden" name="controller_role" value="{{ $activity->controller_role->value }}">
                    <button class="btn btn-sm btn-primary">{{ __('Version speichern') }}</button>
                </form>
            </section>
        @endcan

        @can('create', \App\Models\Privacy\Dpia::class)
            @php $d = $activity->dpia; @endphp
            <section class="card bg-base-200 p-4 space-y-3">
                <h2 class="font-semibold">{{ __('Datenschutz-Folgenabschätzung (Art. 35)') }}
                    @if ($d && $d->outcome->value !== 'open')<span class="badge badge-info badge-sm ml-2">{{ $d->outcome->label() }}</span>@endif
                </h2>
                <form method="post" action="{{ route('dataprotection.activities.dpia', $activity) }}" class="space-y-2">
                    @csrf
                    <textarea name="necessity" rows="2" class="textarea textarea-sm textarea-bordered w-full" placeholder="{{ __('Notwendigkeit & Verhältnismäßigkeit') }}">{{ old('necessity', $d?->necessity) }}</textarea>
                    <textarea name="risks" rows="2" class="textarea textarea-sm textarea-bordered w-full" placeholder="{{ __('Risiken für Betroffene') }}">{{ old('risks', $d?->risks) }}</textarea>
                    <textarea name="mitigations" rows="2" class="textarea textarea-sm textarea-bordered w-full" placeholder="{{ __('Abhilfemaßnahmen') }}">{{ old('mitigations', $d?->mitigations) }}</textarea>
                    <div class="grid md:grid-cols-2 gap-2">
                        <select name="residual_risk" class="select select-sm select-bordered">
                            <option value="">{{ __('Restrisiko …') }}</option>
                            @foreach (['low' => __('gering'), 'medium' => __('mittel'), 'high' => __('hoch')] as $v => $l)
                                <option value="{{ $v }}" @selected(old('residual_risk', $d?->residual_risk) === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                        <select name="outcome" class="select select-sm select-bordered">
                            @foreach (\App\Enums\Privacy\DpiaOutcome::cases() as $o)
                                <option value="{{ $o->value }}" @selected(old('outcome', $d?->outcome?->value ?? 'open') === $o->value)>{{ $o->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-sm">{{ __('DSFA speichern') }}</button>
                </form>
            </section>
        @endcan
    </div>
@endsection
