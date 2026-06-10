@extends('layouts.app')

@section('title', $request->request_number)
@section('nav-title', $request->request_number . ' — ' . $request->type->label())

@section('content')
    <x-index-page :subtitle="__('Betroffenenanfrage bearbeiten, zuweisen und entscheiden.')">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                        :href="route('dataprotection.requests.index')"
                        show-label>{{ __('Zurück') }}</x-icon-btn>
            <x-icon-btn icon="download" tone="ghost" size="sm"
                        :href="route('dataprotection.requests.export', $request)"
                        show-label>{{ __('Export (JSON)') }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="grid md:grid-cols-3 gap-4">
            <x-card class="md:col-span-2">
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <x-status-badge :tone="$request->isOverdue() ? 'error' : 'ghost'" size="sm">{{ $request->status->label() }}</x-status-badge>
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
                </div>
            </x-card>

            <aside class="space-y-3">
                @can('update', $request)
                    @unless ($request->identity_verified_at)
                        <x-card>
                            <form method="post" action="{{ route('dataprotection.requests.verify', $request) }}">
                                @csrf
                                <x-icon-btn icon="check" tone="outline" size="sm" type="submit" show-label class="w-full">{{ __('Identität bestätigen') }}</x-icon-btn>
                            </form>
                        </x-card>
                    @else
                        <x-card>
                            <p class="text-xs text-success">{{ __('Identität bestätigt') }} ({{ $request->identity_verified_at->format('d.m.Y') }})</p>
                        </x-card>
                    @endunless
                @endcan

                @can('assign', $request)
                    <x-card>
                        <form method="post" action="{{ route('dataprotection.requests.assign', $request) }}" class="space-y-1">
                            @csrf
                            <label class="label text-sm">{{ __('Zuweisen an') }}</label>
                            <select name="user_id" class="select select-sm select-bordered w-full">
                                @foreach ($members ?? [] as $m)
                                    <option value="{{ $m->id }}" @selected($request->assigned_user_id === $m->id)>{{ $m->name }}</option>
                                @endforeach
                            </select>
                            <x-icon-btn icon="person_add" tone="ghost" size="sm" type="submit" show-label class="w-full">{{ __('Zuweisen') }}</x-icon-btn>
                        </form>
                    </x-card>
                @endcan

                @can('update', $request)
                    @if ($request->status->isOpen())
                        <x-card>
                            <form method="post" action="{{ route('dataprotection.requests.decide', $request) }}" class="space-y-1">
                                @csrf
                                <label class="label text-sm">{{ __('Entscheidung') }}</label>
                                <select name="decision" class="select select-sm select-bordered w-full">
                                    <option value="granted">{{ __('Stattgegeben') }}</option>
                                    <option value="partially">{{ __('Teilweise') }}</option>
                                    <option value="rejected">{{ __('Abgelehnt') }}</option>
                                </select>
                                <textarea name="note" rows="2" class="textarea textarea-sm textarea-bordered w-full" placeholder="{{ __('Begründung / Antwort') }}" required></textarea>
                                <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label class="w-full">{{ __('Abschließen') }}</x-icon-btn>
                            </form>
                        </x-card>
                    @endif
                @endcan
            </aside>
        </div>

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold mb-3">{{ __('Anhänge') }}</h2>
            <ul class="text-sm space-y-1">
                @forelse ($request->attachments as $att)
                    <li class="flex items-center justify-between">
                        <a class="link" href="{{ route('dataprotection.attachment.download', $att) }}">{{ $att->filename }}</a>
                        @can('update', $request)
                            <form method="post" action="{{ route('dataprotection.attachment.destroy', $att) }}">@csrf @method('DELETE')<x-icon-btn icon="close" tone="error" size="xs" type="submit" :label="__('Entfernen')" /></form>
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
                    <x-icon-btn icon="upload" tone="ghost" size="sm" type="submit" show-label>{{ __('Hochladen') }}</x-icon-btn>
                </form>
            @endcan
        </x-card>

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold mb-3">{{ __('Verlauf') }}</h2>
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
