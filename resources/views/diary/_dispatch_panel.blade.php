{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _dispatch_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Dispositions-Panel (Feature 028). Erwartet: $diary (DiaryEntry).
    Zeigt Dispositionsstatus, Konfliktwarnungen (über die bestehenden
    Compliance-Regeln) und die Fahrzeug-Reservierung am Auftrag.
--}}
@php($canDispatch = auth()->user()?->can(\App\Enums\User\Permission::DispatchManage->value) || \Illuminate\Support\Facades\Gate::allows('update', $diary))
@php($canReserve = auth()->user()?->can(\App\Enums\User\Permission::VehicleReserve->value))
@php($resolver = app(\App\Services\Dispatch\DispatchStatusResolver::class))
@php($checker = app(\App\Services\Dispatch\DispatchConflictChecker::class))
@php($dispatchStatus = $resolver->resolve($diary))
@php($report = $checker->check($diary))
@php($blocking = $checker->blockingConflicts($report))
@php($warnings = $checker->warnings($report))
@php($vehicles = \App\Models\Vehicle::query()->whereNull('archived_at')->orderBy('label')->get())
@php($reservations = \App\Models\VehicleReservation::query()->where('diary_entry_id', $diary->id)->with(['vehicle', 'reservedBy'])->orderBy('reserved_from')->get())

<section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-['Space_Grotesk'] text-xl font-bold text-base-content">{{ __('dispatch.heading') }}</h2>
        <span @class([
            'badge',
            'badge-success' => $dispatchStatus->tone() === 'done',
            'badge-info' => $dispatchStatus->tone() === 'progress',
            'badge-warning' => $dispatchStatus->tone() === 'open',
            'badge-ghost' => $dispatchStatus->tone() === 'neutral',
        ])>{{ $dispatchStatus->label() }}</span>
    </div>

    @if (! empty($blocking))
        <div class="alert alert-error text-sm" role="alert">
            <x-icon name="error" />
            <div>
                <p class="font-semibold">{{ __('dispatch.conflicts.hard') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($blocking as $conflict)
                        <li>{{ $conflict->message }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if (! empty($warnings))
        <div class="alert alert-warning text-sm" role="status">
            <x-icon name="warning" />
            <div>
                <p class="font-semibold">{{ __('dispatch.conflicts.soft') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($warnings as $conflict)
                        <li>{{ $conflict->message }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if (empty($blocking) && empty($warnings))
        <p class="text-sm text-success">{{ __('dispatch.conflicts.none') }}</p>
    @endif

    @error('dispatch')
        <div class="alert alert-error text-sm" role="alert"><x-icon name="error" /><span>{{ $message }}</span></div>
    @enderror

    {{-- Status-Übergänge --}}
    @if ($canDispatch && ! empty($dispatchStatus->allowedTransitions()))
        <div class="flex flex-wrap items-end gap-3 border-t border-base-200 pt-4">
            @foreach ($dispatchStatus->allowedTransitions() as $target)
                <form method="POST" action="{{ route('dispatch.transition', $diary) }}" class="flex items-end gap-2">
                    @csrf
                    <input type="hidden" name="dispatch_status" value="{{ $target->value }}">
                    @if (! empty($blocking) && in_array($target, [\App\Enums\Diary\DispatchStatus::Confirmed, \App\Enums\Diary\DispatchStatus::EnRoute], true))
                        <label class="form-control">
                            <span class="label-text text-xs">{{ __('dispatch.override_reason') }}</span>
                            <input type="text" name="override_reason" required maxlength="2000"
                                   class="input input-bordered input-sm w-64" placeholder="{{ __('dispatch.override_placeholder') }}">
                        </label>
                    @endif
                    <x-button type="submit" tone="outline" size="sm">
                        {{ __('dispatch.set_status', ['status' => $target->label()]) }}
                    </x-button>
                </form>
            @endforeach
        </div>
    @endif

    {{-- Qualifikations-Anforderungen (Feature 028, Rang 53) --}}
    @php($requiredQualifications = $diary->requiredQualifications()->orderBy('name')->get())
    @php($allQualifications = \App\Models\Qualification::query()->where('is_active', true)->orderBy('name')->get())
    <div class="border-t border-base-200 pt-4 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="font-semibold text-base-content">{{ __('Qualifikationen') }}</h3>
            @if ($requiredQualifications->isNotEmpty())
                <x-icon-btn icon="grid_on" tone="outline" size="sm"
                            :href="route('dispatch.qualifications', $diary)"
                            show-label>{{ __('Matrix öffnen') }}</x-icon-btn>
            @endif
        </div>

        @if ($requiredQualifications->isNotEmpty() && $diary->assignedUser !== null)
            @php($assigneeStatus = app(\App\Services\Schedule\QualificationGate::class)->statusFor(
                $diary->assignedUser->load('qualifications'),
                $requiredQualifications,
                $diary->start_at !== null ? \Carbon\CarbonImmutable::parse((string) $diary->start_at) : null,
            ))
            <div class="flex flex-wrap gap-1">
                @foreach ($requiredQualifications as $qualification)
                    @php($qStatus = $assigneeStatus[$qualification->id] ?? 'missing')
                    <span @class([
                        'badge badge-sm gap-1',
                        'badge-success' => $qStatus === 'ok',
                        'badge-warning' => $qStatus === 'expiring',
                        'badge-error' => $qStatus === 'missing',
                    ])>
                        <x-icon name="{{ $qStatus === 'ok' ? 'check' : ($qStatus === 'expiring' ? 'schedule' : 'close') }}" class="text-xs" />
                        {{ $qualification->abbreviation ?? $qualification->name }}
                    </span>
                @endforeach
            </div>
        @elseif ($requiredQualifications->isEmpty())
            <p class="text-sm text-muted">{{ __('Keine Qualifikationen gefordert.') }}</p>
        @endif

        @if ($canDispatch && $allQualifications->isNotEmpty())
            <form method="POST" action="{{ route('diary.qualifications.update', $diary) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                @method('PUT')
                <label class="form-control">
                    <span class="label-text text-xs">{{ __('Geforderte Qualifikationen') }}</span>
                    <select name="qualifications[]" multiple size="4" class="select select-bordered select-sm min-w-56">
                        @foreach ($allQualifications as $qualification)
                            <option value="{{ $qualification->sqid }}"
                                    @selected($requiredQualifications->contains('id', $qualification->id))>
                                {{ $qualification->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <x-button type="submit" tone="outline" size="sm">{{ __('Speichern') }}</x-button>
            </form>
        @endif
    </div>

    {{-- Fahrzeug-Reservierung --}}
    <div class="border-t border-base-200 pt-4 space-y-3">
        <h3 class="font-semibold text-base-content">{{ __('dispatch.vehicle.heading') }}</h3>

        @if ($reservations->isNotEmpty())
            <ul class="space-y-2">
                @foreach ($reservations as $reservation)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-base-300 bg-base-200 px-4 py-2 text-sm">
                        <span>
                            <strong>{{ $reservation->vehicle?->displayName() ?? '—' }}</strong>
                            · {{ $reservation->reserved_from->fdatetime() }} – {{ $reservation->reserved_to->fdatetime() }}
                            @if ($reservation->note) · {{ $reservation->note }} @endif
                        </span>
                        @if ($canReserve)
                            <form method="POST" action="{{ route('vehicle-reservations.destroy', $reservation) }}">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" tone="ghost" size="xs" class="text-error">{{ __('dispatch.vehicle.release') }}</x-button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-muted">{{ __('dispatch.vehicle.none') }}</p>
        @endif

        @if ($canReserve && $vehicles->isNotEmpty())
            <form method="POST" action="{{ route('vehicle-reservations.store') }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 items-end">
                @csrf
                <input type="hidden" name="diary_entry_id" value="{{ $diary->sqid }}">
                <label class="form-control">
                    <span class="label-text text-xs">{{ __('dispatch.vehicle.label') }}</span>
                    <select name="vehicle_id" class="select select-bordered select-sm" required>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->sqid }}">{{ $vehicle->displayName() }}</option>
                        @endforeach
                    </select>
                </label>
                {{-- Von-Bis gekoppelt (I6): Rückgabe nie vor Abholung; Feldnamen unverändert. --}}
                <x-date-range layout="split" grid-class="contents" type="datetime-local" size="sm" required
                              from-name="reserved_from" to-name="reserved_to"
                              from-id="reserved_from" to-id="reserved_to"
                              label-class="label text-xs py-1"
                              :from-label="__('dispatch.vehicle.from')"
                              :to-label="__('dispatch.vehicle.to')"
                              :from="optional($diary->start_at)->format('Y-m-d\TH:i')"
                              :to="optional($diary->end_at)->format('Y-m-d\TH:i')"
                              :from-error="$errors->first('reserved_from') ?: null"
                              :to-error="$errors->first('reserved_to') ?: null" />
                <x-button type="submit" tone="primary" size="sm">{{ __('dispatch.vehicle.reserve') }}</x-button>
            </form>
        @endif
    </div>
</section>
