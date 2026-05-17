@extends('layouts.app')

@section('title', $log ? __('Fahrt bearbeiten') : __('Neue Fahrt'))

@section('content')
    @php
        $action = $log
            ? route('travel-logs.update', $log)
            : route('travel-logs.store');
    @endphp

    <div class="mx-auto max-w-3xl space-y-4">
        <h1 class="text-xl font-semibold">{{ $log ? __('Fahrt bearbeiten') : __('Neue Fahrt erfassen') }}</h1>

        <form method="POST" action="{{ $action }}" class="space-y-4 rounded-box border border-base-300 bg-base-100 p-4">
            @csrf
            @if ($log) @method('PUT') @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="form-control">
                    <span class="label-text">{{ __('Datum') }}</span>
                    <input type="date" name="date" required value="{{ old('date', $date) }}" class="input input-sm input-bordered" />
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Fahrzeug') }}</span>
                    <select name="vehicle" class="select select-sm select-bordered" required>
                        @foreach ($vehicles as $v)
                            <option value="{{ $v }}" @selected(old('vehicle', $log?->vehicle ?? 'private') === $v)>
                                {{ __($v) }}
                                @isset($rates[$v]) ({{ number_format((float) $rates[$v], 2, ',', '.') }} €/km) @endisset
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="form-control sm:col-span-2">
                    <span class="label-text">{{ __('Von (Adresse)') }}</span>
                    <input type="text" name="from_address" value="{{ old('from_address', $log?->from_address) }}" class="input input-sm input-bordered" />
                </label>
                <label class="form-control sm:col-span-2">
                    <span class="label-text">{{ __('Nach (Adresse)') }}</span>
                    <input type="text" name="to_address" value="{{ old('to_address', $log?->to_address) }}" class="input input-sm input-bordered" />
                </label>

                <label class="form-control">
                    <span class="label-text">{{ __('Distanz (km, einfach)') }}</span>
                    <input type="number" step="0.01" min="0" name="distance_km" required
                           value="{{ old('distance_km', $log?->distance_km) }}" class="input input-sm input-bordered" />
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Satz €/km (optional)') }}</span>
                    <input type="number" step="0.0001" min="0" name="rate_per_km"
                           value="{{ old('rate_per_km', $log?->rate_per_km) }}" class="input input-sm input-bordered"
                           placeholder="{{ __('Auto aus Fahrzeugtyp') }}" />
                </label>

                <label class="form-control">
                    <span class="label-text">{{ __('Start') }}</span>
                    <input type="datetime-local" name="started_at"
                           value="{{ old('started_at', $log?->started_at?->format('Y-m-d\TH:i')) }}" class="input input-sm input-bordered" />
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Ende') }}</span>
                    <input type="datetime-local" name="ended_at"
                           value="{{ old('ended_at', $log?->ended_at?->format('Y-m-d\TH:i')) }}" class="input input-sm input-bordered" />
                </label>

                <label class="form-control">
                    <span class="label-text">{{ __('Projekt (optional)') }}</span>
                    <select name="project_id" class="select select-sm select-bordered">
                        <option value="">—</option>
                        @foreach ($projects as $p)
                            <option value="{{ $p->id }}" @selected(old('project_id', $log?->project_id) == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Kunde (optional)') }}</span>
                    <select name="customer_id" class="select select-sm select-bordered">
                        <option value="">—</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" @selected(old('customer_id', $log?->customer_id) == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-control sm:col-span-2">
                    <span class="label-text">{{ __('Zweck') }}</span>
                    <input type="text" name="purpose" value="{{ old('purpose', $log?->purpose) }}" class="input input-sm input-bordered" />
                </label>

                <label class="label cursor-pointer justify-start gap-3">
                    <input type="hidden" name="round_trip" value="0">
                    <input type="checkbox" name="round_trip" value="1"
                           @checked(old('round_trip', $log?->round_trip)) class="checkbox checkbox-sm" />
                    <span class="label-text">{{ __('Hin- und Rückfahrt (verdoppelt km)') }}</span>
                </label>
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="hidden" name="reimbursable" value="0">
                    <input type="checkbox" name="reimbursable" value="1"
                           @checked(old('reimbursable', $log?->reimbursable ?? true)) class="checkbox checkbox-sm" />
                    <span class="label-text">{{ __('Erstattungsfähig') }}</span>
                </label>

                <label class="form-control sm:col-span-2">
                    <span class="label-text">{{ __('Notizen') }}</span>
                    <textarea name="notes" rows="3" class="textarea textarea-bordered">{{ old('notes', $log?->notes) }}</textarea>
                </label>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <a href="{{ route('travel-logs.index') }}" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
                <button type="submit" class="btn btn-sm btn-primary">
                    {{ $log ? __('Speichern') : __('Erfassen') }}
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const url = @json(route('api.internal.geocode'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            async function geocode(input) {
                const q = input.value.trim();
                if (q.length < 3) return;
                input.classList.add('opacity-70');
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ query: q }),
                    });
                    if (!res.ok) {
                        input.dataset.geocode = 'miss';
                        return;
                    }
                    const data = await res.json();
                    input.dataset.geocode = 'hit';
                    input.dataset.lat = data.lat;
                    input.dataset.lng = data.lng;
                    input.title = data.display_name || `${data.lat}, ${data.lng}`;
                } catch (e) {
                    input.dataset.geocode = 'error';
                } finally {
                    input.classList.remove('opacity-70');
                }
            }

            document.querySelectorAll('input[name="from_address"], input[name="to_address"]').forEach((el) => {
                el.addEventListener('blur', () => geocode(el));
            });
        })();
    </script>
@endpush
