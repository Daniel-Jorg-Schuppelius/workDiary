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
            <p class="text-sm text-base-content/60">{{ __('dispatch.vehicle.none') }}</p>
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
                <label class="form-control">
                    <span class="label-text text-xs">{{ __('dispatch.vehicle.from') }}</span>
                    <input type="datetime-local" name="reserved_from" required
                           value="{{ optional($diary->start_at)->format('Y-m-d\TH:i') }}"
                           class="input input-bordered input-sm @error('reserved_from') input-error @enderror">
                </label>
                <label class="form-control">
                    <span class="label-text text-xs">{{ __('dispatch.vehicle.to') }}</span>
                    <input type="datetime-local" name="reserved_to" required
                           value="{{ optional($diary->end_at)->format('Y-m-d\TH:i') }}"
                           class="input input-bordered input-sm">
                </label>
                <x-button type="submit" tone="primary" size="sm">{{ __('dispatch.vehicle.reserve') }}</x-button>
                @error('reserved_from')
                    <p class="text-error text-xs sm:col-span-2 lg:col-span-4">{{ $message }}</p>
                @enderror
            </form>
        @endif
    </div>
</section>
