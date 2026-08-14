{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : integrations.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.install')

@section('install-content')
<h2 class="card-title mb-2">{{ __('Integrationen (optional)') }}</h2>
<p class="mb-4 text-sm text-base-content/70">
    {{ __('Diese Angaben sind optional und können später jederzeit ergänzt werden.') }}
</p>

<form method="POST" action="{{ route('install.integrations.store') }}" class="space-y-4">
    @csrf

    <fieldset class="fieldset">
        <label class="fieldset-label" for="lexoffice_api_key">{{ __('Lexoffice API-Schlüssel') }}</label>
        <input type="text" name="lexoffice_api_key" id="lexoffice_api_key" value="{{ old('lexoffice_api_key', $values['lexoffice_api_key']) }}"
               class="input input-sm input-bordered w-full" autocomplete="off">
    </fieldset>

    <div class="divider text-xs">{{ __('Web-Push (VAPID)') }}</div>

    <div class="flex items-center justify-between gap-2">
        <p class="text-xs text-base-content/60">{{ __('Schlüsselpaar für Browser-Push. Kann automatisch erzeugt werden.') }}</p>
        <x-button type="button" id="vapid-generate" tone="outline" size="sm" icon="key">{{ __('Schlüssel generieren') }}</x-button>
    </div>

    <fieldset class="fieldset">
        <label class="fieldset-label" for="vapid_subject">{{ __('VAPID Subject') }}</label>
        <input type="text" name="vapid_subject" id="vapid_subject" value="{{ old('vapid_subject', $values['vapid_subject']) }}"
               class="input input-sm input-bordered w-full">
    </fieldset>

    <div class="grid gap-4 sm:grid-cols-2">
        <fieldset class="fieldset">
            <label class="fieldset-label" for="vapid_public_key">{{ __('Public Key') }}</label>
            <input type="text" name="vapid_public_key" id="vapid_public_key" value="{{ old('vapid_public_key', $values['vapid_public_key']) }}"
                   class="input input-sm input-bordered w-full" autocomplete="off">
        </fieldset>
        <fieldset class="fieldset">
            <label class="fieldset-label" for="vapid_private_key">{{ __('Private Key') }}</label>
            <input type="text" name="vapid_private_key" id="vapid_private_key" value="{{ old('vapid_private_key', $values['vapid_private_key']) }}"
                   class="input input-sm input-bordered w-full" autocomplete="off">
        </fieldset>
    </div>

    <div class="card-actions justify-between pt-2">
        <x-button href="{{ route('install.mail') }}" tone="ghost" size="sm">{{ __('Zurück') }}</x-button>
        <x-button type="submit" tone="primary" size="sm" iconTrailing="arrow_forward">{{ __('Weiter') }}</x-button>
    </div>
</form>

<script @cspNonce>
    (function () {
        const btn = document.getElementById('vapid-generate');
        if (! btn) {
            return;
        }
        const pub = document.getElementById('vapid_public_key');
        const priv = document.getElementById('vapid_private_key');
        const subject = document.getElementById('vapid_subject');
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        btn.addEventListener('click', async function () {
            btn.disabled = true;
            const original = btn.innerHTML;
            btn.textContent = @json(__('Generiere …'));
            try {
                const res = await fetch(@json(route('install.integrations.vapid')), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                });
                if (! res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                const data = await res.json();
                pub.value = data.publicKey ?? '';
                priv.value = data.privateKey ?? '';
                if (subject && ! subject.value.trim()) {
                    subject.value = 'mailto:admin@' + window.location.hostname;
                }
            } catch (e) {
                const msg = @json(__('Schlüssel konnten nicht erzeugt werden. Bitte erneut versuchen.'));
                if (typeof window.notifyAction === 'function') {
                    window.notifyAction({ tone: 'error', message: msg });
                } else {
                    // browser-dialog-ok: Install-Layout ohne Notify-Dialoge.
                    alert(msg);
                }
            } finally {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        });
    })();
</script>
@endsection
