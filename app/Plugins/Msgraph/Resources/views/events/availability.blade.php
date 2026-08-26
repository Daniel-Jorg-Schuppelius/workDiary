{{--
  Created on   : Thu Aug 06 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : availability.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Free/Busy-Prüfung im Termin-Dialog (Feature 102, C2): liest Zeiten +
     Teilnehmer aus dem umgebenden Formular und fragt die Kalender-Verbindung
     der Organisation ab (nur free/busy/unknown, keine Termindetails). --}}
<div class="rounded-box border border-base-300 bg-base-200/40 p-3 space-y-2" data-msgraph-availability>
    <div class="flex items-center gap-2">
        <button type="button" class="btn btn-sm btn-ghost" data-availability-check>
            <x-icon name="event_available" />
            <span>{{ __('msgraph.availability.check') }}</span>
        </button>
        <span class="text-sm text-muted">{{ __('msgraph.availability.hint') }}</span>
    </div>
    <ul class="hidden space-y-1 text-sm" data-availability-results></ul>
    <p class="hidden text-sm text-error" data-availability-error></p>
</div>
<script @cspNonce>
(function () {
    document.addEventListener('click', async function (event) {
        const btn = event.target.closest('[data-availability-check]');
        if (!btn) return;
        const box = btn.closest('[data-msgraph-availability]');
        const form = btn.closest('form');
        if (!box || !form) return;

        const results = box.querySelector('[data-availability-results]');
        const error = box.querySelector('[data-availability-error]');
        results.classList.add('hidden');
        error.classList.add('hidden');

        const start = form.querySelector('[name="started_at"]')?.value || '';
        const end = form.querySelector('[name="ended_at"]')?.value || '';
        const users = new Set();
        const responsible = form.querySelector('[name="responsible_user_id"]')?.value;
        if (responsible) users.add(responsible);
        form.querySelectorAll('select[name^="participants"][name$="[user_id]"]').forEach(function (el) {
            if (el.value) users.add(el.value);
        });
        if (!start || !end || users.size === 0) {
            error.textContent = @json(__('msgraph.availability.missing_input'));
            error.classList.remove('hidden');
            return;
        }

        btn.disabled = true;
        try {
            const params = new URLSearchParams({ start, end });
            users.forEach(u => params.append('users[]', u));
            const res = await fetch('{{ route('msgraph.availability') }}?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                error.textContent = data.message || @json(__('msgraph.availability.failed'));
                error.classList.remove('hidden');
                return;
            }
            const labels = { free: @json(__('msgraph.availability.free')), busy: @json(__('msgraph.availability.busy')), unknown: @json(__('msgraph.availability.unknown')) };
            const tones = { free: 'text-success', busy: 'text-error', unknown: 'text-muted' };
            results.innerHTML = '';
            (data.results || []).forEach(function (row) {
                const li = document.createElement('li');
                li.className = 'flex items-center gap-2';
                const dot = document.createElement('span');
                dot.className = 'material-symbols-outlined text-sm ' + (tones[row.status] || tones.unknown);
                dot.setAttribute('aria-hidden', 'true');
                dot.textContent = row.status === 'busy' ? 'event_busy' : (row.status === 'free' ? 'event_available' : 'help');
                const label = document.createElement('span');
                label.textContent = row.name + ' — ' + (labels[row.status] || labels.unknown);
                li.append(dot, label);
                results.appendChild(li);
            });
            results.classList.remove('hidden');
        } catch (e) {
            error.textContent = @json(__('msgraph.availability.failed'));
            error.classList.remove('hidden');
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>
