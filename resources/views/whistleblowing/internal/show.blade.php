@extends('layouts.app')

@section('title', $case->case_number)

@section('content')
    <div class="p-4 space-y-4 max-w-3xl">
        <a class="link" href="{{ route('whistleblowing.internal.index') }}">&larr; {{ __('Zurueck zur Liste') }}</a>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <header class="space-y-1">
            <h1 class="text-xl font-semibold">{{ $case->case_number }}</h1>
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
        </header>

        <section class="card bg-base-200 p-4 space-y-2">
            <h2 class="font-semibold">{{ __('Meldeinhalt') }}</h2>
            <p><strong>{{ __('Betreff') }}:</strong> {{ $case->subject_ciphertext }}</p>
            <p class="whitespace-pre-line">{{ $case->description_ciphertext }}</p>
            @if ($case->contact_ciphertext)
                <p><strong>{{ __('Kontakt (freiwillig)') }}:</strong> {{ $case->contact_ciphertext }}</p>
            @endif
        </section>

        <section class="card bg-base-200 p-4 space-y-2">
            <h2 class="font-semibold">{{ __('Bearbeiter') }}</h2>
            <ul class="list-disc ml-5">
                @forelse ($case->assignments->whereNull('revoked_at') as $a)
                    <li>{{ $a->user?->name }} ({{ $a->role->value }})</li>
                @empty
                    <li>{{ __('Niemand zugewiesen.') }}</li>
                @endforelse
            </ul>
        </section>

        <section class="card bg-base-200 p-4 space-y-2">
            <h2 class="font-semibold">{{ __('Kommunikation & Notizen') }}</h2>
            @forelse ($case->messages->sortBy('sent_at') as $m)
                <div class="border-l-2 pl-2">
                    <span class="badge">{{ $m->visibility->value === 'internal' ? __('intern') : __('an Reporter') }}</span>
                    <p class="whitespace-pre-line">{{ $m->body_ciphertext }}</p>
                </div>
            @empty
                <p>{{ __('Noch keine Eintraege.') }}</p>
            @endforelse
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            @can('process', $case)
                @if ($case->status->value === 'submitted')
                    <form method="post" action="{{ route('whistleblowing.internal.acknowledge', $case) }}">
                        @csrf
                        <button class="btn btn-primary">{{ __('Eingang bestaetigen') }}</button>
                    </form>
                @endif

                <form method="post" action="{{ route('whistleblowing.internal.status', $case) }}" class="space-y-2">
                    @csrf
                    <label class="font-semibold">{{ __('Status aendern') }}</label>
                    <select name="to" class="select select-bordered w-full">
                        @foreach (\App\Enums\Whistleblowing\CaseStatus::cases() as $s)
                            <option value="{{ $s->value }}">{{ __('whistleblowing.status.' . $s->value) }}</option>
                        @endforeach
                    </select>
                    <textarea name="reason" class="textarea textarea-bordered w-full" placeholder="{{ __('Begruendung (bei Abschluss erforderlich)') }}"></textarea>
                    <button class="btn">{{ __('Status setzen') }}</button>
                </form>
            @endcan

            @can('assign', $case)
                <form method="post" action="{{ route('whistleblowing.internal.assign', $case) }}" class="space-y-2">
                    @csrf
                    <label class="font-semibold">{{ __('Bearbeiter zuweisen') }}</label>
                    <input name="user_id" type="number" class="input input-bordered w-full" placeholder="{{ __('Benutzer-ID') }}">
                    <select name="role" class="select select-bordered w-full">
                        @foreach (\App\Enums\Whistleblowing\CaseRole::cases() as $r)
                            <option value="{{ $r->value }}">{{ $r->value }}</option>
                        @endforeach
                    </select>
                    <button class="btn">{{ __('Zuweisen') }}</button>
                </form>
            @endcan

            @can('note', $case)
                <form method="post" action="{{ route('whistleblowing.internal.note', $case) }}" class="space-y-2">
                    @csrf
                    <label class="font-semibold">{{ __('Interne Notiz') }}</label>
                    <textarea name="body" class="textarea textarea-bordered w-full" required></textarea>
                    <button class="btn">{{ __('Notiz speichern') }}</button>
                </form>
            @endcan

            @can('message', $case)
                <form method="post" action="{{ route('whistleblowing.internal.message', $case) }}" class="space-y-2">
                    @csrf
                    <label class="font-semibold">{{ __('Nachricht an die meldende Person') }}</label>
                    <textarea name="body" class="textarea textarea-bordered w-full" required></textarea>
                    <button class="btn">{{ __('Senden') }}</button>
                </form>
            @endcan
        </section>
    </div>
@endsection
