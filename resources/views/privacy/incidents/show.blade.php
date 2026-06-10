@extends('layouts.app')
@section('title', $incident->incident_number)
@section('nav-title', $incident->incident_number . ' — ' . $incident->type->label())
@section('content')
    <x-index-page :subtitle="__('Vorfall bewerten, melden, Maßnahmen verfolgen und dokumentieren.')">
        <x-slot:actions>
            <x-status-badge :tone="$incident->isDeadlineBreached() ? 'error' : 'ghost'" size="sm">{{ $incident->status->label() }}</x-status-badge>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                        :href="route('dataprotection.incidents.index')"
                        show-label>{{ __('Zurück') }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        @if ($incident->authority_deadline_at)
            <div class="alert {{ $incident->isDeadlineBreached() ? 'alert-error' : 'alert-warning' }}">
                {{ __('72-h-Meldefrist') }}: {{ $incident->authority_deadline_at->format('d.m.Y H:i') }}
                @if ($incident->authority_notified_at) — {{ __('Behörde gemeldet am') }} {{ $incident->authority_notified_at->format('d.m.Y H:i') }} @endif
            </div>
        @endif

        <div class="grid md:grid-cols-3 gap-4">
            <x-card class="md:col-span-2 text-sm space-y-2">
                <p class="whitespace-pre-line"><span class="font-semibold">{{ __('Sachverhalt') }}:</span><br>{{ $incident->summary_ciphertext ?? '—' }}</p>
                <p class="whitespace-pre-line"><span class="font-semibold">{{ __('Betroffenes') }}:</span><br>{{ $incident->affected_ciphertext ?? '—' }}</p>
                @if ($incident->measures_ciphertext)<p class="whitespace-pre-line"><span class="font-semibold">{{ __('Sofortmaßnahmen') }}:</span><br>{{ $incident->measures_ciphertext }}</p>@endif
                <p><span class="font-semibold">{{ __('Risiko') }}:</span> {{ $incident->risk_level ?? '—' }}
                   · {{ __('Meldung Behörde') }}: {{ $incident->notify_authority ? __('ja') : '—' }}
                   · {{ __('Betroffene') }}: {{ $incident->notify_subjects ? __('ja') : '—' }}</p>
            </x-card>

            @can('update', $incident)
                <x-card class="space-y-2">
                    <form method="post" action="{{ route('dataprotection.incidents.assess', $incident) }}" class="space-y-1">
                        @csrf
                        <select name="risk_level" class="select select-sm select-bordered w-full">
                            <option value="low">{{ __('Geringes Risiko') }}</option>
                            <option value="medium">{{ __('Mittleres Risiko') }}</option>
                            <option value="high">{{ __('Hohes Risiko') }}</option>
                        </select>
                        <textarea name="measures" rows="2" class="textarea textarea-sm textarea-bordered w-full" placeholder="{{ __('Sofortmaßnahmen') }}"></textarea>
                        <button class="btn btn-sm w-full">{{ __('Bewertung speichern') }}</button>
                    </form>
                    <form method="post" action="{{ route('dataprotection.incidents.decide', $incident) }}" class="space-y-1 border-t border-base-300 pt-2">
                        @csrf
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="authority" value="1" class="checkbox checkbox-sm"> {{ __('Behörde melden') }}</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="subjects" value="1" class="checkbox checkbox-sm"> {{ __('Betroffene benachrichtigen') }}</label>
                        <button class="btn btn-sm w-full">{{ __('Meldeentscheidung') }}</button>
                    </form>
                    <form method="post" action="{{ route('dataprotection.incidents.reported', $incident) }}" class="space-y-1">
                        @csrf
                        <input type="hidden" name="authority" value="1">
                        <button class="btn btn-sm btn-outline w-full">{{ __('Als gemeldet vermerken') }}</button>
                    </form>
                    @if ($incident->status->isOpen())
                        <form method="post" action="{{ route('dataprotection.incidents.close', $incident) }}" class="space-y-1 border-t border-base-300 pt-2">
                            @csrf
                            <textarea name="lessons" rows="2" class="textarea textarea-sm textarea-bordered w-full" placeholder="{{ __('Lessons Learned') }}"></textarea>
                            <button class="btn btn-sm btn-primary w-full">{{ __('Abschließen') }}</button>
                        </form>
                    @endif
                </x-card>
            @endcan
        </div>

        {{-- Maßnahmenverfolgung --}}
        <x-card class="space-y-2">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Maßnahmen') }}</h2>
            <ul class="space-y-1">
                @forelse ($incident->measures as $m)
                    <li class="flex items-center justify-between text-sm rounded-box border border-base-300 px-3 py-2">
                        <span>{{ $m->title }} @if ($m->due_at)<span class="{{ $m->isOverdue() ? 'text-error' : 'text-base-content/60' }}">({{ __('bis') }} {{ $m->due_at->format('d.m.Y') }})</span>@endif</span>
                        @if ($m->status === 'done')
                            <x-status-badge tone="success" size="sm">{{ __('erledigt') }}</x-status-badge>
                        @else
                            @can('update', $incident)
                                <form method="post" action="{{ route('dataprotection.incidents.measure.complete', [$incident, $m]) }}">@csrf <button class="btn btn-xs">{{ __('Erledigt') }}</button></form>
                            @endcan
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-base-content/60">{{ __('Keine Maßnahmen.') }}</li>
                @endforelse
            </ul>
            @can('update', $incident)
                <form method="post" action="{{ route('dataprotection.incidents.measure.store', $incident) }}" class="grid md:grid-cols-3 gap-2 pt-2">
                    @csrf
                    <input name="title" class="input input-sm input-bordered" placeholder="{{ __('Maßnahme') }}" required>
                    <input name="due_at" type="date" class="input input-sm input-bordered">
                    <button class="btn btn-sm">{{ __('Hinzufügen') }}</button>
                </form>
            @endcan
        </x-card>

        {{-- Meldungsentwürfe (nicht versendet) --}}
        <x-card class="space-y-2">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Meldungsentwürfe') }}</h2>
            <p class="text-xs text-base-content/60">{{ __('Vorbereitete Entwürfe – werden NICHT automatisch versendet.') }}</p>
            <div class="flex gap-2">
                <a href="{{ route('dataprotection.incidents.draft', [$incident, 'authority']) }}" class="btn btn-sm btn-outline">{{ __('An Aufsichtsbehörde (Art. 33)') }}</a>
                <a href="{{ route('dataprotection.incidents.draft', [$incident, 'subjects']) }}" class="btn btn-sm btn-outline">{{ __('An Betroffene (Art. 34)') }}</a>
            </div>
        </x-card>

        {{-- Anhänge --}}
        <x-card class="space-y-2">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Anhänge') }}</h2>
            <ul class="text-sm space-y-1">
                @forelse ($incident->attachments as $att)
                    <li class="flex items-center justify-between">
                        <a class="link" href="{{ route('dataprotection.attachment.download', $att) }}">{{ $att->filename }}</a>
                        @can('update', $incident)
                            <form method="post" action="{{ route('dataprotection.attachment.destroy', $att) }}">@csrf @method('DELETE')<x-icon-btn icon="close" tone="error" size="xs" type="submit" :label="__('Löschen')" /></form>
                        @endcan
                    </li>
                @empty
                    <li class="text-base-content/60">{{ __('Keine Anhänge.') }}</li>
                @endforelse
            </ul>
            @can('update', $incident)
                <form method="post" action="{{ route('dataprotection.incidents.attach', $incident) }}" enctype="multipart/form-data" class="flex gap-2 pt-2">
                    @csrf
                    <input type="file" name="file" class="file-input file-input-sm file-input-bordered flex-1" required>
                    <button class="btn btn-sm">{{ __('Hochladen') }}</button>
                </form>
            @endcan
        </x-card>

        <x-card class="space-y-2">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Verlauf') }}</h2>
            <ul class="timeline timeline-vertical">
                @foreach ($events as $e)
                    <li>
                        <div class="timeline-start text-xs text-base-content/60">{{ $e->created_at?->format('d.m.Y H:i') }}</div>
                        <div class="timeline-middle">●</div>
                        <div class="timeline-end timeline-box text-sm">{{ $e->event }}</div>
                    </li>
                @endforeach
            </ul>
        </x-card>
    </x-index-page>
@endsection
