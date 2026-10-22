{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', $case->case_number)
@section('nav-title', $case->case_number)

@section('content')
    <x-index-page :subtitle="__('Fallakte einer Hinweisgeber-Meldung bearbeiten.')"
                  back-route="whistleblowing.internal.index" :back-label="__('Zurück zur Liste')">

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Fallinformationen') }}</h2>
            <div class="mt-2 space-y-1">
                <p class="text-sm">
                    {{ __('Status') }}: <strong>{{ __('whistleblowing.status.' . $case->status->value) }}</strong> ·
                    {{ __('Priorität') }}: {{ __('whistleblowing.priority.' . $case->priority->value) }} ·
                    {{ __('Kategorie') }}: {{ __('whistleblowing.category.' . $case->category->value) }}
                </p>
                <p class="text-sm">
                    {{ __('Eingang bis') }}: {{ optional($case->acknowledgement_due_at)->format('d.m.Y') }} ·
                    {{ __('Rückmeldung bis') }}: {{ optional($case->feedback_due_at)->format('d.m.Y') }}
                    @if ($case->acknowledged_at) · {{ __('bestätigt am') }} {{ $case->acknowledged_at->fdate() }} @endif
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

        {{-- Beweismittel des Melders: Download nur nach freigegebener Prüfung (Quarantäne-Gate im Controller). --}}
        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Anhänge') }}</h2>
            <ul class="mt-2 space-y-1">
                @forelse ($case->attachments as $attachment)
                    <li class="flex flex-wrap items-center gap-2">
                        <span class="break-all">{{ $attachment->original_name_ciphertext }}</span>
                        <span class="text-xs text-muted">{{ \CommonToolkit\Helper\Data\NumberHelper::formatBytes((int) $attachment->size, 1) }}</span>
                        @if ($attachment->scan_status === \App\Enums\Whistleblowing\AttachmentScanStatus::Clean)
                            <x-icon-btn icon="download" tone="ghost" size="xs"
                                        :href="route('whistleblowing.internal.attachment', ['case' => $case, 'attachment' => $attachment])"
                                        :label="__('Herunterladen')" />
                        @else
                            <x-status-badge size="xs" :tone="$attachment->scan_status === \App\Enums\Whistleblowing\AttachmentScanStatus::Pending ? 'warning' : 'error'">{{ $attachment->scan_status->label() }}</x-status-badge>
                        @endif
                    </li>
                @empty
                    <li class="text-muted">{{ __('Keine Anhänge.') }}</li>
                @endforelse
            </ul>
        </x-card>

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Bearbeiter') }}</h2>
            <ul class="mt-2 list-disc ml-5">
                @forelse ($case->assignments->whereNull('revoked_at') as $a)
                    <li>{{ $a->user?->name }} ({{ __('whistleblowing.role.' . $a->role->value) }})</li>
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
                    <x-empty-state icon="forum" :title="__('Noch keine Einträge.')" compact />
                @endforelse
            </div>
        </x-card>

        <div class="grid gap-4 md:grid-cols-2">
            @can('process', $case)
                @if ($case->status->value === 'submitted')
                    <x-card>
                        <form method="post" action="{{ route('whistleblowing.internal.acknowledge', $case) }}">
                            @csrf
                            <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Eingang bestätigen') }}</x-icon-btn>
                        </form>
                    </x-card>
                @endif

                <x-card>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Status ändern') }}</h2>
                    @php
                        // Nur zulässige Ziele; „Gelöscht" geht allein über die kontrollierte Löschung unten.
                        $statusTargets = array_values(array_filter(
                            $case->status->allowedTransitions(),
                            static fn (\App\Enums\Whistleblowing\CaseStatus $target): bool => $target !== \App\Enums\Whistleblowing\CaseStatus::Deleted,
                        ));
                    @endphp
                    @if ($statusTargets === [])
                        <p class="mt-2 text-sm text-muted">{{ __('Aus diesem Status führt kein weiterer Schritt über dieses Formular.') }}</p>
                    @else
                    <form method="post" action="{{ route('whistleblowing.internal.status', $case) }}" class="mt-2 space-y-2">
                        @csrf
                        <x-form-group tone="ghost" cols="1">
                            <x-input-field name="to" :label="__('Status')">
                                <select id="to" name="to" class="select select-bordered w-full">
                                    @foreach ($statusTargets as $s)
                                        <option value="{{ $s->value }}" @selected(old('to') === $s->value)>{{ $s->label() }}</option>
                                    @endforeach
                                </select>
                            </x-input-field>
                            <x-input-field name="reason" :label="__('Begründung')">
                                <textarea id="reason" name="reason" class="textarea textarea-bordered w-full" placeholder="{{ __('Begründung (bei Abschluss erforderlich)') }}">{{ old('reason') }}</textarea>
                            </x-input-field>
                        </x-form-group>
                        <x-icon-btn icon="edit" tone="ghost" size="sm" type="submit" show-label>{{ __('Status setzen') }}</x-icon-btn>
                    </form>
                    @endif
                </x-card>
            @endcan

            @can('assign', $case)
                <x-card>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Bearbeiter zuweisen') }}</h2>
                    <form method="post" action="{{ route('whistleblowing.internal.assign', $case) }}" class="mt-2 space-y-2">
                        @csrf
                        <x-form-group tone="ghost" cols="1">
                            <x-input-field name="user_id" type="number" :label="__('Benutzer-ID')" placeholder="{{ __('Benutzer-ID') }}" />
                            <x-input-field name="role" :label="__('Rolle')">
                                <select id="role" name="role" class="select select-bordered w-full">
                                    @foreach (\App\Enums\Whistleblowing\CaseRole::cases() as $r)
                                        <option value="{{ $r->value }}">{{ __('whistleblowing.role.' . $r->value) }}</option>
                                    @endforeach
                                </select>
                            </x-input-field>
                        </x-form-group>
                        <x-icon-btn icon="person_add" tone="ghost" size="sm" type="submit" show-label>{{ __('Zuweisen') }}</x-icon-btn>
                    </form>
                </x-card>
            @endcan

            @can('note', $case)
                <x-card>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Interne Notiz') }}</h2>
                    <form method="post" action="{{ route('whistleblowing.internal.note', $case) }}" class="mt-2 space-y-2">
                        @csrf
                        <x-form-group tone="ghost" cols="1">
                            <x-input-field name="body" :label="__('Interne Notiz')" required>
                                <textarea id="body" name="body" class="textarea textarea-bordered w-full" required></textarea>
                            </x-input-field>
                        </x-form-group>
                        <x-icon-btn icon="edit_note" tone="ghost" size="sm" type="submit" show-label>{{ __('Notiz speichern') }}</x-icon-btn>
                    </form>
                </x-card>
            @endcan

            @can('message', $case)
                <x-card>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Nachricht an die meldende Person') }}</h2>
                    <form method="post" action="{{ route('whistleblowing.internal.message', $case) }}" class="mt-2 space-y-2">
                        @csrf
                        <x-form-group tone="ghost" cols="1">
                            <x-input-field name="body" :label="__('Nachricht an die meldende Person')" required>
                                <textarea id="body" name="body" class="textarea textarea-bordered w-full" required></textarea>
                            </x-input-field>
                        </x-form-group>
                        <x-icon-btn icon="send" tone="primary" size="sm" type="submit" show-label>{{ __('Senden') }}</x-icon-btn>
                    </form>
                </x-card>
            @endcan

            {{-- Betroffene markieren, Notfallfreigabe und Export hatten keinen Einstieg (Vollscan 2026-10-21). --}}
            @can('process', $case)
                <x-card>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Betroffene Person markieren') }}</h2>
                    <p class="mt-1 text-sm text-base-content/70">{{ __('Die markierte Person wird für diesen Fall gesperrt; bestehende Zuweisungen enden.') }}</p>
                    <x-action-form :action="route('whistleblowing.internal.subject', $case)" class="mt-2 space-y-2"
                                   :confirm="__('Person als betroffen markieren? Sie verliert sofort jeden Zugriff auf diesen Fall.')"
                                   confirm-icon="person_off" confirm-tone="warning"
                                   :confirm-label="__('Markieren')">
                        <x-form-group tone="ghost" cols="1">
                            <x-input-field name="user_id" id="subject-user-id" type="number" :label="__('Benutzer-ID')" required />
                            <x-textarea-field name="note" id="subject-note" rows="2" maxlength="2000" :label="__('Notiz (optional)')" />
                        </x-form-group>
                        <x-icon-btn icon="person_off" tone="warning" size="sm" type="submit" show-label>{{ __('Markieren') }}</x-icon-btn>
                    </x-action-form>
                </x-card>
            @endcan

            @can('grantEmergency', $case)
                <x-card>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Notfallfreigabe') }}</h2>
                    <p class="mt-1 text-sm text-base-content/70">{{ __('Gibt einer nicht zugewiesenen Person für :minutes Minuten Zugriff auf diesen Fall. Freigeben darf nur eine andere Person; jede Freigabe wird protokolliert.', ['minutes' => (int) config('whistleblowing.emergency_ttl_minutes', 240)]) }}</p>
                    <x-action-form :action="route('whistleblowing.internal.emergency', $case)" class="mt-2 space-y-2"
                                   :confirm="__('Notfallfreigabe erteilen?')"
                                   confirm-icon="emergency" confirm-tone="warning"
                                   :confirm-label="__('Freigeben')">
                        <x-form-group tone="ghost" cols="1">
                            <x-input-field name="user_id" id="emergency-user-id" type="number" :label="__('Benutzer-ID')" required />
                            <x-textarea-field name="reason" id="emergency-reason" rows="2" maxlength="2000" :label="__('Begründung')" required />
                        </x-form-group>
                        <x-icon-btn icon="emergency" tone="warning" size="sm" type="submit" show-label>{{ __('Freigeben') }}</x-icon-btn>
                    </x-action-form>
                </x-card>
            @endcan

            @can('export', $case)
                <x-card>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Fall exportieren') }}</h2>
                    <p class="mt-1 text-sm text-base-content/70">{{ __('Das Paket ist mit AES-256 verschlüsselt. Geben Sie das Passwort getrennt vom Paket weiter; der Export wird im Fall protokolliert.') }}</p>
                    <form method="post" action="{{ route('whistleblowing.internal.export', $case) }}" class="mt-2 space-y-2">
                        @csrf
                        <x-form-group tone="ghost" cols="1">
                            <x-textarea-field name="reason" id="export-reason" rows="2" maxlength="2000" :label="__('Begründung')" required />
                            <x-input-field name="password" id="export-password" type="password" autocomplete="new-password" minlength="12" :label="__('Passwort')" required />
                            <x-input-field name="password_confirmation" id="export-password-confirmation" type="password" autocomplete="new-password" minlength="12" :label="__('Passwort wiederholen')" required />
                        </x-form-group>
                        <x-icon-btn icon="download" tone="ghost" size="sm" type="submit" show-label>{{ __('Exportieren') }}</x-icon-btn>
                    </form>
                </x-card>
            @endcan

            {{-- Selbstsperre (Konzept 7.4): Die Zuweisung endet sofort; aufheben kann die Sperre niemand über die Oberfläche. --}}
            @can('declareConflict', $case)
                <x-card>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Interessenkonflikt') }}</h2>
                    <p class="mt-1 text-sm text-base-content/70">{{ __('Sind Sie selbst betroffen oder befangen, sperren Sie sich für diesen Fall. Ihre Zuweisung endet sofort, und Sie können die Sperre nicht selbst aufheben.') }}</p>
                    <x-action-form :action="route('whistleblowing.internal.conflict', $case)" class="mt-2 space-y-2"
                                   :confirm="__('Interessenkonflikt melden? Sie verlieren sofort den Zugriff auf diesen Fall und können die Sperre nicht selbst aufheben.')"
                                   confirm-icon="block" confirm-tone="error"
                                   :confirm-label="__('Interessenkonflikt melden')">
                        <x-form-group tone="ghost" cols="1">
                            <x-textarea-field name="reason" id="conflict-reason" rows="2" maxlength="2000"
                                              :label="__('Begründung (optional)')" />
                        </x-form-group>
                        <x-icon-btn icon="block" tone="error" size="sm" type="submit" show-label>{{ __('Interessenkonflikt melden') }}</x-icon-btn>
                    </x-action-form>
                </x-card>
            @endcan

            {{-- Löschen nimmt der Dienst nur aus der Aufbewahrungsprüfung an (Konzept 16); unter Löschsperre gibt es keinen Knopf. --}}
            @can('retention', $case)
                @if ($case->status === \App\Enums\Whistleblowing\CaseStatus::RetentionReview)
                    <x-card>
                        <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Kontrollierte Löschung') }}</h2>
                        <p class="mt-1 text-sm text-base-content/70">{{ __('Der Fall steht in der Aufbewahrungsprüfung. Die Löschung vernichtet den Schlüssel des Falls: Meldeinhalt, Nachrichten, Anhänge und Zuweisungen sind danach unwiederbringlich verloren, es bleibt nur ein inhaltsfreier Löschnachweis. Steht ein Verfahren oder eine Aufbewahrungspflicht entgegen, setzen Sie stattdessen die Löschsperre.') }}</p>
                        <x-action-form :action="route('whistleblowing.internal.destroy', $case)" class="mt-2"
                                       :confirm="__('Fall :number endgültig löschen? Meldeinhalt, Nachrichten und Anhänge lassen sich danach nicht wiederherstellen.', ['number' => $case->case_number])"
                                       confirm-icon="delete_forever" confirm-tone="error"
                                       :confirm-label="__('Endgültig löschen')">
                            <x-icon-btn icon="delete_forever" tone="error" size="sm" type="submit" show-label>{{ __('Fall löschen') }}</x-icon-btn>
                        </x-action-form>
                    </x-card>
                @endif
            @endcan
        </div>
    </x-index-page>
@endsection
