@extends('layouts.app')

@section('title', $case->case_number)
@section('nav-title', $case->case_number)

@section('content')
    <x-index-page :subtitle="__('Fallakte einer Hinweisgeber-Meldung bearbeiten.')">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                        :href="route('whistleblowing.internal.index')"
                        show-label>{{ __('Zurueck zur Liste') }}</x-icon-btn>
        </x-slot:actions>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Fallinformationen') }}</h2>
            <div class="mt-2 space-y-1">
                <p class="text-sm">
                    {{ __('Status') }}: <strong>{{ __('whistleblowing.status.' . $case->status->value) }}</strong> ·
                    {{ __('Prioritaet') }}: {{ $case->priority->value }} ·
                    {{ __('Kategorie') }}: {{ __('whistleblowing.category.' . $case->category->value) }}
                </p>
                <p class="text-sm">
                    {{ __('Eingang bis') }}: {{ optional($case->acknowledgement_due_at)->format('d.m.Y') }} ·
                    {{ __('Rueckmeldung bis') }}: {{ optional($case->feedback_due_at)->format('d.m.Y') }}
                    @if ($case->acknowledged_at) · {{ __('bestaetigt am') }} {{ $case->acknowledged_at->format('d.m.Y') }} @endif
                </p>
            </div>
        </x-card>

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Meldeinhalt') }}</h2>
            <div class="mt-2 space-y-2">
                <p><strong>{{ __('Betreff') }}:</strong> {{ $case->subject_ciphertext }}</p>
                <p class="whitespace-pre-line">{{ $case->description_ciphertext }}</p>
                @if ($case->contact_ciphertext)
                    <p><strong>{{ __('Kontakt (freiwillig)') }}:</strong> {{ $case->contact_ciphertext }}</p>
                @endif
            </div>
        </x-card>

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Bearbeiter') }}</h2>
            <ul class="mt-2 list-disc ml-5">
                @forelse ($case->assignments->whereNull('revoked_at') as $a)
                    <li>{{ $a->user?->name }} ({{ $a->role->value }})</li>
                @empty
                    <li>{{ __('Niemand zugewiesen.') }}</li>
                @endforelse
            </ul>
        </x-card>

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Kommunikation & Notizen') }}</h2>
            <div class="mt-2 space-y-2">
                @forelse ($case->messages->sortBy('sent_at') as $m)
                    <div class="border-l-2 pl-2">
                        <x-status-badge tone="ghost" size="sm">{{ $m->visibility->value === 'internal' ? __('intern') : __('an Reporter') }}</x-status-badge>
                        <p class="whitespace-pre-line">{{ $m->body_ciphertext }}</p>
                    </div>
                @empty
                    <p>{{ __('Noch keine Eintraege.') }}</p>
                @endforelse
            </div>
        </x-card>

        <div class="grid gap-4 md:grid-cols-2">
            @can('process', $case)
                @if ($case->status->value === 'submitted')
                    <x-card>
                        <form method="post" action="{{ route('whistleblowing.internal.acknowledge', $case) }}">
                            @csrf
                            <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Eingang bestaetigen') }}</x-icon-btn>
                        </form>
                    </x-card>
                @endif

                <x-card>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Status aendern') }}</h2>
                    <form method="post" action="{{ route('whistleblowing.internal.status', $case) }}" class="mt-2 space-y-2">
                        @csrf
                        <select name="to" class="select select-bordered w-full">
                            @foreach (\App\Enums\Whistleblowing\CaseStatus::cases() as $s)
                                <option value="{{ $s->value }}">{{ __('whistleblowing.status.' . $s->value) }}</option>
                            @endforeach
                        </select>
                        <textarea name="reason" class="textarea textarea-bordered w-full" placeholder="{{ __('Begruendung (bei Abschluss erforderlich)') }}"></textarea>
                        <x-icon-btn icon="edit" tone="ghost" size="sm" type="submit" show-label>{{ __('Status setzen') }}</x-icon-btn>
                    </form>
                </x-card>
            @endcan

            @can('assign', $case)
                <x-card>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Bearbeiter zuweisen') }}</h2>
                    <form method="post" action="{{ route('whistleblowing.internal.assign', $case) }}" class="mt-2 space-y-2">
                        @csrf
                        <input name="user_id" type="number" class="input input-bordered w-full" placeholder="{{ __('Benutzer-ID') }}">
                        <select name="role" class="select select-bordered w-full">
                            @foreach (\App\Enums\Whistleblowing\CaseRole::cases() as $r)
                                <option value="{{ $r->value }}">{{ $r->value }}</option>
                            @endforeach
                        </select>
                        <x-icon-btn icon="person_add" tone="ghost" size="sm" type="submit" show-label>{{ __('Zuweisen') }}</x-icon-btn>
                    </form>
                </x-card>
            @endcan

            @can('note', $case)
                <x-card>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Interne Notiz') }}</h2>
                    <form method="post" action="{{ route('whistleblowing.internal.note', $case) }}" class="mt-2 space-y-2">
                        @csrf
                        <textarea name="body" class="textarea textarea-bordered w-full" required></textarea>
                        <x-icon-btn icon="edit_note" tone="ghost" size="sm" type="submit" show-label>{{ __('Notiz speichern') }}</x-icon-btn>
                    </form>
                </x-card>
            @endcan

            @can('message', $case)
                <x-card>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Nachricht an die meldende Person') }}</h2>
                    <form method="post" action="{{ route('whistleblowing.internal.message', $case) }}" class="mt-2 space-y-2">
                        @csrf
                        <textarea name="body" class="textarea textarea-bordered w-full" required></textarea>
                        <x-icon-btn icon="send" tone="primary" size="sm" type="submit" show-label>{{ __('Senden') }}</x-icon-btn>
                    </form>
                </x-card>
            @endcan
        </div>
    </x-index-page>
@endsection
