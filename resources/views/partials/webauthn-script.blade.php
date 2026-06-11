{{-- WebAuthn-Browser-Ceremony (Registrierung + Anmeldung). Bindet window.wdWebauthn ein. --}}
<script @cspNonce>
(function () {
    function b64uToBuf(s) {
        s = s.replace(/-/g, '+').replace(/_/g, '/');
        const pad = s.length % 4 ? '='.repeat(4 - (s.length % 4)) : '';
        const bin = atob(s + pad);
        const buf = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
        return buf.buffer;
    }
    function bufToB64u(buf) {
        const bytes = new Uint8Array(buf);
        let bin = '';
        for (let i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }
    async function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body === undefined ? undefined : (typeof body === 'string' ? body : JSON.stringify(body)),
        });
    }

    async function register(optionsUrl, registerUrl, onError) {
        try {
            const opt = await (await postJson(optionsUrl)).json();
            opt.challenge = b64uToBuf(opt.challenge);
            opt.user.id = b64uToBuf(opt.user.id);
            (opt.excludeCredentials || []).forEach(c => c.id = b64uToBuf(c.id));
            const cred = await navigator.credentials.create({ publicKey: opt });
            const body = {
                id: cred.id,
                rawId: bufToB64u(cred.rawId),
                type: cred.type,
                response: {
                    clientDataJSON: bufToB64u(cred.response.clientDataJSON),
                    attestationObject: bufToB64u(cred.response.attestationObject),
                },
            };
            const res = await postJson(registerUrl, body);
            if (res.ok) { window.location.reload(); }
            else { onError && onError((await res.json().catch(() => ({}))).message || 'Fehler'); }
        } catch (e) { onError && onError(e.message || 'Abgebrochen'); }
    }

    async function assert(optionsUrl, verifyUrl, onError) {
        try {
            const opt = await (await postJson(optionsUrl)).json();
            opt.challenge = b64uToBuf(opt.challenge);
            (opt.allowCredentials || []).forEach(c => c.id = b64uToBuf(c.id));
            const cred = await navigator.credentials.get({ publicKey: opt });
            const body = {
                id: cred.id,
                rawId: bufToB64u(cred.rawId),
                type: cred.type,
                response: {
                    clientDataJSON: bufToB64u(cred.response.clientDataJSON),
                    authenticatorData: bufToB64u(cred.response.authenticatorData),
                    signature: bufToB64u(cred.response.signature),
                    userHandle: cred.response.userHandle ? bufToB64u(cred.response.userHandle) : null,
                },
            };
            const res = await postJson(verifyUrl, body);
            if (res.ok) { const d = await res.json(); window.location.href = d.redirect || '/'; }
            else { onError && onError((await res.json().catch(() => ({}))).message || 'Fehler'); }
        } catch (e) { onError && onError(e.message || 'Abgebrochen'); }
    }

    window.wdWebauthn = { register, assert, supported: !!(window.PublicKeyCredential && navigator.credentials) };

    function wire(el, fn) {
        const errId = el.getAttribute('data-error');
        const err = errId ? document.getElementById(errId) : null;
        el.addEventListener('click', function () {
            el.disabled = true;
            fn(el.getAttribute('data-options'), el.getAttribute('data-target'), function (m) {
                el.disabled = false;
                if (err) { err.textContent = m; err.classList.remove('hidden'); }
            });
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-webauthn-register]').forEach(el => wire(el, register));
        document.querySelectorAll('[data-webauthn-assert]').forEach(el => wire(el, assert));
        if (!window.wdWebauthn.supported) {
            document.querySelectorAll('[data-webauthn-block]').forEach(el => el.classList.add('hidden'));
        }
    });
})();
</script>
