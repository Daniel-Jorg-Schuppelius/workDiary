{{--
    Arbeitsbereich-Dialog (Feature 082, MVP-378): Großkachel-Auswahl der
    schaltbaren Fokus-Ansichten. Jede Kachel ist ein POST-Formular auf
    me.focus.switch — server-seitig, kein AJAX (wie mode.switch). Öffnet über
    data-open-dialog="focus-dialog", schließt über data-entry-modal-close.

    Erwartet aus dem Layout: $navFocusAvailable (Liste {key,label,description,icon}),
    $navFocusActive (aktiver Schlüssel).
--}}
<x-modal
    id="focus-dialog"
    :embedded="false"
    size="wide"
    tone="primary"
    icon="dashboard_customize"
    :eyebrow="__('scope.focus.dialog.eyebrow')"
    :title="__('scope.focus.dialog.title')">

    <p class="mb-4 text-sm text-base-content/70">{{ __('scope.focus.dialog.subtitle') }}</p>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($navFocusAvailable as $focus)
            @php $active = $focus['key'] === $navFocusActive; @endphp
            <form method="POST" action="{{ route('me.focus.switch', $focus['key']) }}" class="m-0">
                @csrf
                <button type="submit"
                        class="group relative flex h-full w-full flex-col gap-3 rounded-box border p-4 text-left transition hover:-translate-y-0.5 hover:shadow-md
                               {{ $active ? 'border-primary bg-primary/10 ring-1 ring-primary' : 'border-base-300 bg-base-100 hover:border-primary/40 hover:bg-base-200' }}">
                    @if ($active)
                        <span class="absolute right-3 top-3 badge badge-primary badge-sm">{{ __('scope.focus.active') }}</span>
                    @endif
                    <span class="flex size-12 items-center justify-center rounded-field {{ $active ? 'bg-primary text-primary-content' : 'bg-base-200 text-primary' }}">
                        <x-icon :name="$focus['icon']" class="text-[1.6rem]" />
                    </span>
                    <span class="font-semibold leading-tight">{{ $focus['label'] }}</span>
                    <span class="text-xs leading-snug text-base-content/65">{{ $focus['description'] }}</span>
                </button>
            </form>
        @endforeach
    </div>

    <x-slot:footerExtra>
        <span class="flex items-start gap-2 text-left text-xs text-base-content/60">
            <x-icon name="info" class="mt-0.5 shrink-0 text-[1.05rem] text-primary" />
            <span>{{ __('scope.focus.dialog.footnote') }}</span>
        </span>
    </x-slot:footerExtra>

    <x-slot:actions>
        <x-button type="button" tone="ghost" data-entry-modal-close icon="close">{{ __('Schließen') }}</x-button>
    </x-slot:actions>
</x-modal>
