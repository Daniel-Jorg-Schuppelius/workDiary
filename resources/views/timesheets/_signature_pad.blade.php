{{--
    Signatur-Pad — kompakte Karte unterhalb des Stundenzettels.
    Erwartet: $action (POST-Ziel), $timesheet (Eloquent-Modell).

    Layout: max-w-md Container, Canvas mit fester CSS-Höhe (h-32 = 128px).
    Bugfix: SignaturePad wird VOR dem ersten resize() instanziiert, damit pad.clear()
    nicht ins Leere läuft. requestAnimationFrame stellt sicher, dass offsetWidth > 0
    ist (Layout-Pass abgeschlossen). resize-Listener wird beim Alpine-destroy entfernt.
--}}
<div x-data="signaturePad()" x-init="init()" x-destroy="teardown()" class="flex w-full flex-col gap-3">
    <div class="grid grid-cols-1 gap-2">
        <input type="text" x-model="customerName" placeholder="{{ __('Name') }}"
               class="input input-bordered input-sm w-full" required value="{{ $timesheet->customer_name }}">
        <input type="text" x-model="customerRole" placeholder="{{ __('Rolle / Funktion') }}"
               class="input input-bordered input-sm w-full" value="{{ $timesheet->customer_role }}">
        <input type="email" x-model="customerEmail" placeholder="{{ __('E-Mail (optional)') }}"
               class="input input-bordered input-sm w-full" value="{{ $timesheet->customer_email }}">
    </div>

    <div class="rounded-box border border-base-300 bg-white p-2">
        <canvas x-ref="canvas" class="block h-32 w-full rounded bg-white touch-none"></canvas>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <button type="button" class="btn btn-ghost btn-xs" @click="clear()">{{ __('Leeren') }}</button>
        <span class="text-xs text-base-content/60" x-show="!isEmpty">{{ __('Mit dem Klick auf "Signieren" bestätigen Sie die Richtigkeit.') }}</span>
    </div>

    <form method="POST" action="{{ $action }}" @submit="prepare($event)" class="flex">
        @csrf
        <input type="hidden" name="signature"      x-ref="sigInput">
        <input type="hidden" name="customer_name"  :value="customerName">
        <input type="hidden" name="customer_role"  :value="customerRole">
        <input type="hidden" name="customer_email" :value="customerEmail">
        <button class="btn btn-primary btn-sm w-full" :disabled="isEmpty || !customerName">{{ __('Signieren') }}</button>
    </form>
</div>

@push('scripts')
<script>
function signaturePad() {
    return {
        pad: null,
        isEmpty: true,
        resizeHandler: null,
        customerName:  @json($timesheet->customer_name ?? ''),
        customerRole:  @json($timesheet->customer_role ?? ''),
        customerEmail: @json($timesheet->customer_email ?? ''),

        async init() {
            const SignaturePadClass = window.SignaturePad;
            if (!SignaturePadClass) {
                console.error('[signature-pad] Modul konnte nicht geladen werden: window.SignaturePad fehlt');
                return;
            }

            const c = this.$refs.canvas;
            if (!c) return;

            // Pad zuerst instanziieren — damit Datenpunkte beim Resize erhalten bleiben.
            this.pad = new SignaturePadClass(c, {
                penColor: '#111',
                backgroundColor: 'rgba(255,255,255,0)',
            });
            this.pad.addEventListener('endStroke', () => { this.isEmpty = this.pad.isEmpty(); });

            this.resizeHandler = () => this.resizeCanvas();
            window.addEventListener('resize', this.resizeHandler);

            // Layout-Pass abwarten, damit offsetWidth/offsetHeight gesetzt sind.
            requestAnimationFrame(() => this.resizeCanvas());
        },

        resizeCanvas() {
            const c = this.$refs.canvas;
            if (!c) return;

            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const cssWidth  = c.offsetWidth;
            const cssHeight = c.offsetHeight;

            if (cssWidth === 0 || cssHeight === 0) {
                // Container noch unsichtbar (z. B. innerhalb eines geschlossenen Tabs) — später erneut versuchen.
                requestAnimationFrame(() => this.resizeCanvas());
                return;
            }

            // Vorhandene Striche sichern, damit sie nach Neuskalierung erhalten bleiben.
            const data = this.pad ? this.pad.toData() : null;

            c.width  = cssWidth  * ratio;
            c.height = cssHeight * ratio;
            c.getContext('2d').scale(ratio, ratio);

            if (this.pad) {
                this.pad.clear();
                if (data && data.length) {
                    this.pad.fromData(data);
                }
                this.isEmpty = this.pad.isEmpty();
            }
        },

        clear() {
            this.pad?.clear();
            this.isEmpty = true;
        },

        prepare(e) {
            if (!this.pad || this.pad.isEmpty()) {
                e.preventDefault();
                return;
            }
            this.$refs.sigInput.value = this.pad.toDataURL('image/png');
        },

        teardown() {
            if (this.resizeHandler) {
                window.removeEventListener('resize', this.resizeHandler);
                this.resizeHandler = null;
            }
            this.pad?.off();
            this.pad = null;
        },
    };
}
</script>
@endpush
