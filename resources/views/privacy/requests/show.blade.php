@extends('layouts.app')

@section('title', $request->request_number)

@section('content')
    <div class="p-4 max-w-4xl space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ $request->request_number }} — {{ $request->type->label() }}</h1>
            <a href="{{ route('dataprotection.requests.export', $request) }}" class="btn btn-sm">{{ __('Export (JSON)') }}</a>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="grid md:grid-cols-3 gap-4">
            <section class="card bg-base-200 p-4 md:col-span-2 space-y-2">
                <div class="flex items-center gap-2">
                    <span class="badge {{ $request->isOverdue() ? 'badge-error' : 'badge-ghost' }}">{{ $request->status->label() }}</span>
                    @if ($request->deadline_at)
                        <span class="text-sm {{ $request->isOverdue() ? 'text-error font-semibold' : 'text-base-content/70' }}">
                            {{ __('Frist') }}: {{ $request->deadline_at->format('d.m.Y') }}
                        </span>
                    @endif
                </div>
                <p><span class="font-semibold">{{ __('Betroffene Person') }}:</span> {{ $request->subject_ciphertext ?? '—' }}</p>
                <p class="whitespace-pre-line"><span class="font-semibold">{{ __('Anliegen') }}:</span><br>{{ $request->content_ciphertext ?? '—' }}</p>
                @if ($request->decision)
                    <p><span class="font-semibold">{{ __('Entscheidung') }}:</span> {{ $request->decision }} — {{ $request->decision_note_ciphertext }}</p>
                @endif
            </section>

            <aside class="space-y-3">
                @can('update', $request)
                    @unless ($request->identity_verified_at)
                        <form method="post" action="{{ route('dataprotection.requests.verify', $request) }}">
                            @csrf <button class="btn btn-sm btn-outline w-full">{{ __('Identität bestätigen') }}</button>
                        </form>
                    @else
                        <p class="text-xs text-success">{{ __('Identität bestätigt') }} ({{ $request->identity_verified_at->format('d.m.Y') }})</p>
                    @endunless
                @endcan

                @can('assign', $request)
                    <form method="post" action="{{ route('dataprotection.requests.assign', $request) }}" class="space-y-1">
                        @csrf
                        <label class="label text-sm">{{ __('Zuweisen an') }}</label>
                        <select name="user_id" class="select select-sm select-bordered w-full">
                            @foreach ($members ?? [] as $m)
                                <option value="{{ $m->id }}" @selected($request->assigned_user_id === $m->id)>{{ $m->name }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm w-full">{{ __('Zuweisen') }}</button>
                    </form>
                @endcan

                @can('update', $request)
                    @if ($request->status->isOpen())
                        <form method="post" action="{{ route('dataprotection.requests.decide', $request) }}" class="space-y-1">
                            @csrf
                            <label class="label text-sm">{{ __('Entscheidung') }}</label>
                            <select name="decision" class="select select-sm select-bordered w-full">
                                <option value="granted">{{ __('Stattgegeben') }}</option>
                                <option value="partially">{{ __('Teilweise') }}</option>
                                <option value="rejected">{{ __('Abgelehnt') }}</option>
                            </select>
                            <textarea name="note" rows="2" class="textarea textarea-sm textarea-bordered w-full" placeholder="{{ __('Begründung / Antwort') }}" required></textarea>
                            <button class="btn btn-sm btn-primary w-full">{{ __('Abschließen') }}</button>
                        </form>
                    @endif
                @endcan
            </aside>
        </div>

        <section class="card bg-base-200 p-4 space-y-2">
            <h2 class="font-semibold">{{ __('Anhänge') }}</h2>
            <ul class="text-sm space-y-1">
                @forelse ($request->attachments as $att)
                    <li class="flex items-center justify-between">
                        <a class="link" href="{{ route('dataprotection.attachment.download', $att) }}">{{ $att->filename }}</a>
                        @can('update', $request)
                            <form method="post" action="{{ route('dataprotection.attachment.destroy', $att) }}">@csrf @method('DELETE')<button class="btn btn-xs btn-ghost text-error">✕</button></form>
                        @endcan
                    </li>
                @empty
                    <li class="text-base-content/60">{{ __('Keine Anhänge.') }}</li>
                @endforelse
            </ul>
            @can('update', $request)
                <form method="post" action="{{ route('dataprotection.requests.attach', $request) }}" enctype="multipart/form-data" class="flex gap-2 pt-2">
                    @csrf
                    <input type="file" name="file" class="file-input file-input-sm file-input-bordered flex-1" required>
                    <button class="btn btn-sm">{{ __('Hochladen') }}</button>
                </form>
            @endcan
        </section>

        <section class="space-y-2">
            <h2 class="font-semibold">{{ __('Verlauf') }}</h2>
            <ul class="timeline timeline-vertical">
                @foreach ($events as $e)
                    <li>
                        <div class="timeline-start text-xs text-base-content/60">{{ $e->created_at?->format('d.m.Y H:i') }}</div>
                        <div class="timeline-middle">●</div>
                        <div class="timeline-end timeline-box text-sm">{{ $e->event }}</div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
@endsection
