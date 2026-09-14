{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : wrapper.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Hülle am Inhalts-Host. Stellt window.API (1.2) und window.API_1484_11 (2004) im
  Ursprung des Kurspakets bereit, lädt das Paket darunter und reicht Commits per
  postMessage an die Player-Seite der Anwendung weiter. Personendaten kommen erst
  mit der Startnachricht der Player-Seite, nie über diese Adresse.
--}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="referrer" content="no-referrer">
    <title>SCORM</title>
    <style>html, body, iframe { margin: 0; border: 0; width: 100%; height: 100%; overflow: hidden; }</style>
</head>
<body>
<iframe id="scorm-sco" title="Kursinhalt"></iframe>
<script nonce="{{ $nonce }}">
(function () {
    'use strict';

    const appOrigin = @json($appOrigin);
    const launchUrl = @json($launchUrl);
    const is2004 = @json($is2004);
    const data = {};
    let lastError = '0';
    let started = false;
    // Der Server addiert — also die Zeit SEIT dem letzten Commit senden.
    let lastCommitAt = Date.now();

    function commit() {
        if (!started) { return 'true'; }

        const payload = {
            lesson_status: is2004 ? data['cmi.completion_status'] : data['cmi.core.lesson_status'],
            success_status: data['cmi.success_status'],
            score_scaled: (data['cmi.score.scaled'] === undefined || data['cmi.score.scaled'] === '') ? null : Number(data['cmi.score.scaled']),
            suspend_data: data['cmi.suspend_data'],
            location: is2004 ? data['cmi.location'] : data['cmi.core.lesson_location'],
            session_seconds: Math.max(0, Math.round((Date.now() - lastCommitAt) / 1000)),
        };
        lastCommitAt = Date.now();

        window.parent.postMessage({ type: 'scorm.commit', payload: payload }, appOrigin);

        return 'true';
    }

    function get(key) {
        lastError = '0';
        return Object.prototype.hasOwnProperty.call(data, key) ? String(data[key]) : '';
    }

    function set(key, value) {
        lastError = '0';
        data[key] = String(value);
        // 1.2 und 2004 halten Status und Position an verschiedenen Stellen.
        if (key === 'cmi.core.lesson_status') { data['cmi.completion_status'] = String(value); }
        if (key === 'cmi.completion_status') { data['cmi.core.lesson_status'] = String(value); }
        if (key === 'cmi.core.lesson_location') { data['cmi.location'] = String(value); }
        if (key === 'cmi.location') { data['cmi.core.lesson_location'] = String(value); }
        // 1.2 kennt cmi.score.scaled nicht: Rohpunkte gegen ein Maximum (Vorgabe 100).
        if (key === 'cmi.core.score.raw' || key === 'cmi.core.score.max') {
            const max = Number(data['cmi.core.score.max'] || 100);
            const raw = Number(data['cmi.core.score.raw']);
            data['cmi.score.scaled'] = (max > 0 && !Number.isNaN(raw)) ? String(raw / max) : '';
        }
        return 'true';
    }

    window.API = {
        LMSInitialize: function () { lastError = '0'; return 'true'; },
        LMSFinish: commit,
        LMSGetValue: get,
        LMSSetValue: set,
        LMSCommit: commit,
        LMSGetLastError: function () { return lastError; },
        LMSGetErrorString: function () { return ''; },
        LMSGetDiagnostic: function () { return ''; },
    };

    window.API_1484_11 = {
        Initialize: function () { lastError = '0'; return 'true'; },
        Terminate: commit,
        GetValue: get,
        SetValue: set,
        Commit: commit,
        GetLastError: function () { return lastError; },
        GetErrorString: function () { return ''; },
        GetDiagnostic: function () { return ''; },
    };

    window.addEventListener('message', function (event) {
        // Nur die einbettende Anwendung darf starten — exakt geprüft.
        if (event.origin !== appOrigin || event.source !== window.parent) { return; }

        const message = event.data || {};
        if (message.type !== 'scorm.init' || started) { return; }

        const values = message.values || {};
        Object.keys(values).forEach(function (key) { data[key] = String(values[key]); });
        started = true;
        lastCommitAt = Date.now();
        document.getElementById('scorm-sco').src = launchUrl;
    });

    window.addEventListener('pagehide', commit);

    window.parent.postMessage({ type: 'scorm.ready' }, appOrigin);
})();
</script>
</body>
</html>
