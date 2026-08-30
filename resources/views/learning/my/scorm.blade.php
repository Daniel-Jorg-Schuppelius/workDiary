{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : scorm.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  SCORM-Player (Feature 149, MVP-743). Der Rahmen stellt die Laufzeit als
  window.API (1.2) bzw. window.API_1484_11 (2004) bereit — der Inhalt sucht
  sie über window.parent. Deshalb läuft das Paket gleichursprünglich; die
  Absicherung liegt im Extractor und in der engen CSP der Inhaltsdateien.
--}}
@extends('layouts.app')
@section('title', $package->title)
@section('nav-title', $package->title)
@section('content')
@php
    $messages = [
        'saved' => __('learning.scorm.saved'),
        'failed' => __('learning.scorm.commit_failed'),
        'passed' => __('learning.scorm.passed'),
    ];
    $commitUrl = route('learning.my.scorm.commit', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]);
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$unit->title"
                        :badge="$package->version"
                        badgeTone="neutral">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('learning.my.show', $enrollment->sqid)"
                            show-label>{{ __('learning.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="alert alert-info" role="status" id="scorm-status">
        <x-icon name="school" />
        <span data-scorm-message>{{ __('learning.scorm.running') }}</span>
    </div>

    <div class="rounded-box border border-base-300 overflow-hidden bg-base-100"
         style="height: calc(100vh - 16rem); min-height: 24rem;">
        <iframe title="{{ $package->title }}"
                src="{{ $launchUrl }}"
                class="w-full h-full border-0"
                referrerpolicy="no-referrer"></iframe>
    </div>
</x-page-shell>

<script @cspNonce>
(function () {
    'use strict';

    const endpoint = @json($commitUrl);
    const token = document.querySelector('meta[name="csrf-token"]');
    const is2004 = @json($package->isScorm2004());
    const messages = @json($messages);

    // Der Anfangszustand kommt vom Server — der Inhalt setzt dort fort, wo
    // er aufgehört hat (suspend_data/location sind sein Eigentum).
    const data = {
        'cmi.core.lesson_status': @json($state->lesson_status ?? 'not attempted'),
        'cmi.completion_status': @json($state->lesson_status ?? 'unknown'),
        'cmi.success_status': @json($state->success_status ?? 'unknown'),
        'cmi.suspend_data': @json($state->suspend_data ?? ''),
        'cmi.core.lesson_location': @json($state->location ?? ''),
        'cmi.location': @json($state->location ?? ''),
        'cmi.score.scaled': @json($state->score_scaled ?? ''),
        'cmi.core.score.raw': '',
        'cmi.core.score.max': '100',
        'cmi.core.entry': @json(($state->suspend_data ?? '') !== '' ? 'resume' : 'ab-initio'),
        'cmi.core.student_id': @json((string) $enrollment->sqid),
        'cmi.core.student_name': @json((string) ($enrollment->user->name ?? '')),
        'cmi.learner_id': @json((string) $enrollment->sqid),
        'cmi.learner_name': @json((string) ($enrollment->user->name ?? '')),
        'cmi.core.credit': 'credit',
        'cmi.credit': 'credit',
        'cmi.mode': 'normal',
        'cmi.core.lesson_mode': 'normal',
    };

    let lastError = '0';
    // Der Server addiert, was hier ankommt — also die Zeit SEIT dem letzten
    // Commit senden. Die verstrichene Gesamtzeit zu schicken, zählte jede
    // Sitzung mit dem zweiten Commit doppelt.
    let lastCommitAt = Date.now();

    function note(text, tone) {
        const box = document.getElementById('scorm-status');
        const label = box ? box.querySelector('[data-scorm-message]') : null;
        if (!box || !label) { return; }
        label.textContent = text;
        box.className = 'alert alert-' + (tone || 'info');
    }

    function commit() {
        const payload = {
            lesson_status: is2004 ? data['cmi.completion_status'] : data['cmi.core.lesson_status'],
            success_status: data['cmi.success_status'],
            score_scaled: data['cmi.score.scaled'] === '' ? null : Number(data['cmi.score.scaled']),
            suspend_data: data['cmi.suspend_data'],
            location: is2004 ? data['cmi.location'] : data['cmi.core.lesson_location'],
            session_seconds: Math.max(0, Math.round((Date.now() - lastCommitAt) / 1000)),
        };

        lastCommitAt = Date.now();

        return fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            // Hält die Anfrage am Leben, wenn die Seite gerade geschlossen wird.
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
            },
            body: JSON.stringify(payload),
        }).then(function (response) {
            if (!response.ok) { throw new Error('commit'); }
            return response.json();
        }).then(function (body) {
            note(body.passed ? messages.passed : messages.saved, body.passed ? 'success' : 'info');
            return true;
        }).catch(function () {
            note(messages.failed, 'warning');
            return false;
        });
    }

    function get(key) {
        lastError = '0';
        if (Object.prototype.hasOwnProperty.call(data, key)) { return String(data[key]); }
        // Unbekannte Elemente sind kein Fehlerfall im Player — leerer Wert.
        return '';
    }

    function set(key, value) {
        lastError = '0';
        data[key] = String(value);
        // 1.2 und 2004 halten den Status an verschiedenen Stellen; hier
        // gespiegelt, damit ein Commit beide Dialekte bedienen kann.
        if (key === 'cmi.core.lesson_status') { data['cmi.completion_status'] = String(value); }
        if (key === 'cmi.completion_status') { data['cmi.core.lesson_status'] = String(value); }
        if (key === 'cmi.core.lesson_location') { data['cmi.location'] = String(value); }
        if (key === 'cmi.location') { data['cmi.core.lesson_location'] = String(value); }
        // 1.2 kennt kein cmi.score.scaled — dort kommen Rohpunkte gegen ein
        // Maximum (Vorgabe 100). Ohne diese Umrechnung bliebe das Ergebnis leer.
        if (key === 'cmi.core.score.raw' || key === 'cmi.core.score.max') {
            const max = Number(data['cmi.core.score.max'] || 100);
            const raw = Number(data['cmi.core.score.raw']);
            data['cmi.score.scaled'] = (max > 0 && !Number.isNaN(raw)) ? String(raw / max) : '';
        }
        return 'true';
    }

    const runtime12 = {
        LMSInitialize: function () { lastError = '0'; return 'true'; },
        LMSFinish: function () { commit(); return 'true'; },
        LMSGetValue: get,
        LMSSetValue: set,
        LMSCommit: function () { commit(); return 'true'; },
        LMSGetLastError: function () { return lastError; },
        LMSGetErrorString: function () { return ''; },
        LMSGetDiagnostic: function () { return ''; },
    };

    const runtime2004 = {
        Initialize: function () { lastError = '0'; return 'true'; },
        Terminate: function () { commit(); return 'true'; },
        GetValue: get,
        SetValue: set,
        Commit: function () { commit(); return 'true'; },
        GetLastError: function () { return lastError; },
        GetErrorString: function () { return ''; },
        GetDiagnostic: function () { return ''; },
    };

    // Beide Namen belegen: manche Pakete melden 2004 im Manifest und
    // sprechen trotzdem die 1.2-Laufzeit an (und umgekehrt).
    window.API = runtime12;
    window.API_1484_11 = runtime2004;

    // Ein geschlossener Tab darf den Fortschritt nicht verschlucken.
    window.addEventListener('pagehide', function () {
        commit();
    });
})();
</script>
@endsection
