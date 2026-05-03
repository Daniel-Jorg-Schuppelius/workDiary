@extends('layouts.app')
@section('title', __('Bereitschaft & Notdienst') . ' — WorkDiary')
@section('nav-title', __('Bereitschaft & Notdienst'))

@section('content')
    <div class="flex h-full min-h-0 w-full flex-col overflow-auto">
        <div class="tabs tabs-box">
            <input type="radio" name="duty_tabs" class="tab" aria-label="⏱ {{ __('Bereitschaft') }} ({{ $shifts->total() }})" @checked($tab === 'bereitschaft') />
            <div class="tab-content border-base-300 bg-base-100 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Bereitschaftsdienste') }}</h2>
                    <a href="{{ route('shifts.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary">{{ __('+ Neue Bereitschaft') }}</a>
                </div>
                <x-table>
                        <thead>
                            <tr>
                                <th>{{ __('Mitarbeiter') }}</th>
                                <th>{{ __('Beginn') }}</th>
                                <th>{{ __('Ende') }}</th>
                                <th>{{ __('Notiz') }}</th>
                                <th class="w-px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($shifts as $shift)
                                <tr>
                                    <td>{{ $shift->user?->name ?? '—' }}</td>
                                    <td>{{ $shift->start_at?->format('d.m.Y H:i') }}</td>
                                    <td>{{ $shift->end_at?->format('d.m.Y H:i') }}</td>
                                    <td class="max-w-xs truncate">{{ $shift->note }}</td>
                                    <td><a href="{{ route('shifts.edit', $shift) }}" data-entry-modal-trigger class="btn btn-xs btn-ghost">{{ __('Bearbeiten') }}</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
                            @endforelse
                        </tbody>
                </x-table>
                {{ $shifts->links() }}
            </div>

            <input type="radio" name="duty_tabs" class="tab" aria-label="⚠ {{ __('Notdienst') }} ({{ $assignments->total() }})" @checked($tab === 'notdienst') />
            <div class="tab-content border-base-300 bg-base-100 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Notdienste') }}</h2>
                    <a href="{{ route('assignments.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary">{{ __('+ Neuer Notdienst') }}</a>
                </div>
                <x-table>
                        <thead>
                            <tr>
                                <th>{{ __('Mitarbeiter') }}</th>
                                <th>{{ __('Beginn') }}</th>
                                <th>{{ __('Ende') }}</th>
                                <th>{{ __('Bereitschaft') }}</th>
                                <th>{{ __('Grund') }}</th>
                                <th class="w-px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($assignments as $a)
                                <tr>
                                    <td>{{ $a->user?->name ?? '—' }}</td>
                                    <td>{{ $a->start_at?->format('d.m.Y H:i') }}</td>
                                    <td>{{ $a->end_at?->format('d.m.Y H:i') }}</td>
                                    <td class="text-base-content/60">
                                        @if ($a->shift)
                                            {{ $a->shift->start_at?->format('d.m.') }}–{{ $a->shift->end_at?->format('d.m.') }}
                                        @else — @endif
                                    </td>
                                    <td class="max-w-xs truncate">{{ $a->reason }}</td>
                                    <td><a href="{{ route('assignments.edit', $a) }}" data-entry-modal-trigger class="btn btn-xs btn-ghost">{{ __('Bearbeiten') }}</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
                            @endforelse
                        </tbody>
                </x-table>
                {{ $assignments->links() }}
            </div>
        </div>
    </div>
@endsection
