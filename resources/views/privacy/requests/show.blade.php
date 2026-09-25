{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
                    @if ($request->isFromPortal())
                        <div class="alert alert-info text-sm">
                            <span>{{ __('dsar.internal.portal_banner') }}</span>
                        </div>
                        <p class="text-sm">
                            <span class="font-semibold">{{ __('dsar.internal.contact_email') }}:</span>
                            {{ $request->contact_email_ciphertext ?? '—' }}
                            @if ($request->contact_email_confirmed_at)
                                <x-status-badge tone="success" size="sm">{{ __('dsar.internal.email_confirmed', ['date' => $request->contact_email_confirmed_at->format('d.m.Y')]) }}</x-status-badge>
                            @else
                                <x-status-badge tone="ghost" size="sm">{{ __('dsar.internal.email_unconfirmed') }}</x-status-badge>
                            @endif
                        </p>
                    @endif
                    <p class="whitespace-pre-line"><span class="font-semibold">{{ __('Betroffene Person') }}:</span> {{ $request->subject_ciphertext ?? '—' }}</p>
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
                            <x-input-field name="user_id" :label="__('Zuweisen an')">
                                <select id="user_id" name="user_id" class="select select-bordered w-full">
                                    @foreach ($members ?? [] as $m)
                                        <option value="{{ $m->sqid }}" @selected($request->assigned_user_id === $m->id)>{{ $m->name }}</option>
                                    @endforeach
                                </select>
                            </x-input-field>
                            <x-icon-btn icon="person_add" tone="ghost" size="sm" type="submit" show-label class="w-full">{{ __('Zuweisen') }}</x-icon-btn>
                        </form>
                    </x-card>
                @endcan

                @can('export', $request)
                    <x-card>
                        <h3 class="text-sm font-semibold mb-1">{{ __('Auskunft erzeugen (Art. 15/20)') }}</h3>
                        <p class="text-xs text-muted mb-2">{{ __('Erstellt JSON, PDF und Art.-20-CSV mit den echten Betroffenendaten und legt sie verschlüsselt am Fall ab.') }}</p>
                        @if ($request->isFromPortal() && ! $request->identity_verified_at)
                            <p class="text-xs text-error mb-2">{{ __('dsar.internal.identity_required') }}</p>
                        @endif
                        {{-- Betroffenenart + Suche via Alpine.data("subjectSearch") (components.js, CSP-Build) —
                             MVP-878: Treffer kommen aus der org-gescopten Suche, nicht als Vollliste. --}}
                        <form method="post" action="{{ route('dataprotection.requests.subject-export', $request) }}" class="space-y-1"
                              x-data="subjectSearch"
                              data-url="{{ route('dataprotection.requests.subject-search', $request) }}"
                              data-kind="{{ \App\Enums\Privacy\DataSubjectKind::User->value }}">
                            @csrf
                            <x-input-field name="subject_type" :label="__('Betroffenenart')">
                                <select id="subject_type" name="subject_type" class="select select-bordered w-full" x-model="kind" x-on:change="search()">
                                    @foreach (\App\Enums\Privacy\DataSubjectKind::cases() as $kind)
                                        <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                                    @endforeach
                                </select>
                            </x-input-field>
                            <x-input-field name="subject_q" :label="__('dsar.internal.subject_search')">
                                <input id="subject_q" type="search" maxlength="100" class="input input-bordered w-full"
                                       x-model="q" x-on:input="onInput()" placeholder="{{ __('dsar.internal.subject_search_placeholder') }}">
                            </x-input-field>
                            <x-input-field name="subject_id" :label="__('Datensatz')">
                                <select id="subject_id" name="subject_id" class="select select-bordered w-full" required>
                                    <template x-for="item in results" :key="item.sqid">
                                        <option :value="item.sqid" x-text="item.label"></option>
                                    </template>
                                    <option value="" disabled x-show="isEmpty()">{{ __('Keine Datensätze vorhanden.') }}</option>
                                </select>
                            </x-input-field>
                            <x-icon-btn icon="description" tone="primary" size="sm" type="submit" show-label class="w-full">{{ __('Auskunft erzeugen') }}</x-icon-btn>
                        </form>
                    </x-card>
                @endcan

                @can('update', $request)
                    @if ($request->status->isOpen())
                        <x-card>
                            <form method="post" action="{{ route('dataprotection.requests.decide', $request) }}" class="space-y-1">
                                @csrf
                                <x-input-field name="decision" :label="__('Entscheidung')">
                                    <select id="decision" name="decision" class="select select-bordered w-full">
                                        <option value="granted">{{ __('Stattgegeben') }}</option>
                                        <option value="partially">{{ __('Teilweise') }}</option>
                                        <option value="rejected">{{ __('Abgelehnt') }}</option>
                                    </select>
                                </x-input-field>
                                <x-input-field name="note" :label="__('Begründung / Antwort')" required>
                                    <textarea id="note" name="note" rows="2" class="textarea textarea-bordered w-full" required></textarea>
                                </x-input-field>
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
                    <li class="text-muted">{{ __('Keine Anhänge.') }}</li>
                @endforelse
            </ul>
            @can('update', $request)
                <form method="post" action="{{ route('dataprotection.requests.attach', $request) }}" enctype="multipart/form-data" class="flex items-end gap-2 pt-2">
                    @csrf
                    <x-input-field name="file" type="file" :label="__('Datei')" required class="flex-1" />
                    <x-icon-btn icon="upload" tone="ghost" size="sm" type="submit" show-label>{{ __('Hochladen') }}</x-icon-btn>
                </form>
            @endcan
        </x-card>

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold mb-3">{{ __('Verlauf') }}</h2>
            <x-journal :entries="$events" />
        </x-card>
    </x-index-page>
@endsection
