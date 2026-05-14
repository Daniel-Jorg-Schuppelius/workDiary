{{-- Signature-Pad-Komponente — erwartet: $action, $timesheet --}}
<div x-data="signaturePad()" x-init="init()" class="mt-3 flex flex-col gap-3">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <input type="text" x-model="customerName" placeholder="{{ __('Name') }}"
               class="input input-bordered input-sm" required value="{{ $timesheet->customer_name }}">
        <input type="text" x-model="customerRole" placeholder="{{ __('Rolle / Funktion') }}"
               class="input input-bordered input-sm" value="{{ $timesheet->customer_role }}">
        <input type="email" x-model="customerEmail" placeholder="{{ __('E-Mail (optional)') }}"
               class="input input-bordered input-sm" value="{{ $timesheet->customer_email }}">
    </div>

    <div class="rounded-box border border-base-300 bg-white p-2">
        <canvas x-ref="canvas" class="block w-full rounded touch-none" height="200"></canvas>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" class="btn btn-sm" @click="clear()">{{ __('Leeren') }}</button>
        <span class="text-xs text-base-content/60" x-show="!empty()">{{ __('Mit dem Klick auf "Signieren" bestätigen Sie die Richtigkeit.') }}</span>
    </div>

    <form method="POST" action="{{ $action }}" @submit="prepare($event)" class="flex">
        @csrf
        <input type="hidden" name="signature"      x-ref="sigInput">
        <input type="hidden" name="customer_name"  :value="customerName">
        <input type="hidden" name="customer_role"  :value="customerRole">
        <input type="hidden" name="customer_email" :value="customerEmail">
        <button class="btn btn-primary btn-sm" :disabled="empty() || !customerName">{{ __('Signieren') }}</button>
    </form>
</div>

@push('scripts')
<script>
function signaturePad() {
    return {
        pad: null,
        customerName:  @json($timesheet->customer_name ?? ''),
        customerRole:  @json($timesheet->customer_role ?? ''),
        customerEmail: @json($timesheet->customer_email ?? ''),
        async init() {
            const mod = await import('signature_pad');
            const SignaturePad = mod.default;
            const c = this.$refs.canvas;
            const resize = () => {
                const ratio = window.devicePixelRatio || 1;
                c.width  = c.offsetWidth  * ratio;
                c.height = c.offsetHeight * ratio;
                c.getContext('2d').scale(ratio, ratio);
                this.pad?.clear();
            };
            window.addEventListener('resize', resize);
            resize();
            this.pad = new SignaturePad(c, { penColor: '#111' });
        },
        empty()  { return !this.pad || this.pad.isEmpty(); },
        clear()  { this.pad?.clear(); },
        prepare(e) {
            if (this.empty()) { e.preventDefault(); return; }
            this.$refs.sigInput.value = this.pad.toDataURL('image/png');
        },
    };
}
</script>
@endpush
