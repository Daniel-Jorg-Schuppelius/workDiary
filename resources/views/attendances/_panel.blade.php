{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Attendance-Panel — erwartet: $current (App\Models\Attendance|null) --}}
<x-card padding="px-4 py-3" data-attendance-panel>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-xs uppercase tracking-[0.18em] text-muted">{{ __('Stempeluhr') }}</h2>
        <div class="flex flex-wrap items-center justify-end gap-1.5">
            @if ($current)
                <x-status-badge tone="success" size="sm">{{ __('Offen') }}</x-status-badge>
                <x-status-badge size="sm" outline>
                    {{ __('Eingestempelt seit :time', ['time' => $current->started_at?->ftime()]) }}
                </x-status-badge>
                @if ($current->break_minutes_total > 0)
                    <x-status-badge tone="ghost" size="sm">
                        {{ __(':min Min. Pause', ['min' => $current->break_minutes_total]) }}
                    </x-status-badge>
                @endif
                {{-- Zwischen-Status (MVP-532): laufende Phase sichtbar machen. --}}
                @if ($current->homeoffice_started_at !== null)
                    <x-status-badge tone="info" size="sm">
                        {{ __('attendance.intermediate.homeoffice') }} {{ __('seit :time', ['time' => $current->homeoffice_started_at->ftime()]) }}
                    </x-status-badge>
                @endif
                @if ($current->errand_started_at !== null)
                    <x-status-badge tone="warning" size="sm">
                        {{ __('attendance.intermediate.errand') }} {{ __('seit :time', ['time' => $current->errand_started_at->ftime()]) }}
                    </x-status-badge>
                @endif
            @else
                <x-status-badge tone="ghost" size="sm">{{ __('Geschlossen') }}</x-status-badge>
                <x-status-badge size="sm" outline>{{ __('Nicht eingestempelt.') }}</x-status-badge>
            @endif
        </div>
    </div>

    @if ($current)
        <div class="mt-2 flex flex-wrap items-center justify-between gap-1.5" x-data="stopwatch('{{ $current->started_at?->toIso8601String() }}')">
            <div class="font-['Space_Grotesk'] text-lg font-bold tabular-nums text-success" x-text="display">00:00:00</div>

            <div class="ml-auto flex flex-wrap items-center justify-end gap-1.5">
                {{-- Zwischen-Status togglen (MVP-532): klassifiziert, mindert die Arbeitszeit nicht. --}}
                <form method="POST" action="{{ route('attendance.intermediate') }}" class="leading-none">
                    @csrf
                    <input type="hidden" name="kind" value="homeoffice">
                    <x-button type="submit" tone="info" size="xs" class="h-7 min-h-7 gap-1 px-2" icon="home_work">
                        {{ $current->homeoffice_started_at !== null ? __('attendance.intermediate.end_homeoffice') : __('attendance.intermediate.start_homeoffice') }}
                    </x-button>
                </form>
                <form method="POST" action="{{ route('attendance.intermediate') }}" class="leading-none">
                    @csrf
                    <input type="hidden" name="kind" value="errand">
                    <x-button type="submit" tone="ghost" size="xs" class="h-7 min-h-7 gap-1 px-2" icon="directions_walk">
                        {{ $current->errand_started_at !== null ? __('attendance.intermediate.end_errand') : __('attendance.intermediate.start_errand') }}
                    </x-button>
                </form>
                <form method="POST" action="{{ route('attendance.clock-out') }}" class="flex flex-wrap items-center justify-end gap-1.5" data-offline-sync="attendance.clock-out">
                    @csrf
                    <div class="join">
                        <span class="join-item flex h-7 items-center border border-base-300 bg-base-200 px-2 text-xs text-muted">{{ __('Pause') }}</span>
                        <input type="number" name="break_minutes" min="0" max="600" value="0" class="input input-bordered input-xs join-item h-7 min-h-7 w-16 px-2 text-right tabular-nums" aria-label="{{ __('Pause (Min.)') }}">
                    </div>
                    <x-button type="submit" tone="warning" size="xs" class="h-7 min-h-7 gap-1 px-2" icon="logout">{{ __('Ausstempeln') }}</x-button>
                </form>

                <form method="POST" action="{{ route('attendance.cancel') }}" class="leading-none">
                    @csrf
                    <button type="submit" class="btn btn-xs btn-ghost btn-square h-7 min-h-7 text-error" title="{{ __('Stempelung verwerfen') }}" aria-label="{{ __('Stempelung verwerfen') }}">
                        <x-icon name="delete" class="text-[0.95rem]" />
                    </button>
                </form>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('attendance.clock-in') }}" class="mt-2 flex justify-end leading-none" data-offline-sync="attendance.clock-in">
            @csrf
            <x-button type="submit" tone="success" size="xs" class="h-7 min-h-7 gap-1 px-2" title="{{ __('Einstempeln') }}">
                <x-icon name="login" class="text-[0.95rem]" /> {{ __('Einstempeln') }}
            </x-button>
        </form>
    @endif
</x-card>
