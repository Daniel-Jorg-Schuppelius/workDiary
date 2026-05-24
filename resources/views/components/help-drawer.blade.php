{{-- In-App-Hilfe-Drawer (MVP-051). Wird einmal pro Seite eingebunden und über --}}
{{-- data-help-trigger / [data-help-topic] gefüllt. JS in resources/js/help-drawer.js. --}}
<div id="help-drawer"
     class="fixed inset-y-0 right-0 z-[60] hidden w-full max-w-md translate-x-full transform overflow-hidden border-l border-base-300 bg-base-100 shadow-lg transition-transform"
     data-help-drawer
     role="dialog"
     aria-modal="true"
     aria-labelledby="help-drawer-title">
    <header class="flex items-start justify-between gap-3 border-b border-base-300 px-4 py-3">
        <div class="min-w-0">
            <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Hilfe') }}</p>
            <h2 id="help-drawer-title" class="font-['Space_Grotesk'] text-base font-semibold text-base-content" data-help-title>{{ __('Wird geladen…') }}</h2>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" data-help-close aria-label="{{ __('Schließen') }}">
            <x-icon name="close" />
        </button>
    </header>

    <div class="flex h-full flex-col">
        <div class="flex-1 overflow-y-auto px-4 py-4 text-sm leading-relaxed text-base-content" data-help-body>
            <p class="text-base-content/60">{{ __('Wird geladen…') }}</p>
        </div>

        <footer class="border-t border-base-300 px-4 py-3" data-help-footer>
            <p class="mb-2 text-xs uppercase tracking-wider text-base-content/60">{{ __('War das hilfreich?') }}</p>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline" data-help-feedback="1">
                    <x-icon name="thumb_up" /> {{ __('Ja') }}
                </button>
                <button type="button" class="btn btn-sm btn-outline" data-help-feedback="0">
                    <x-icon name="thumb_down" /> {{ __('Nein') }}
                </button>
                <span class="ml-2 text-xs text-base-content/60 hidden" data-help-feedback-thanks>{{ __('Danke für dein Feedback.') }}</span>
            </div>
            <div class="mt-3 hidden" data-help-related>
                <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Verwandte Themen') }}</p>
                <ul class="mt-1 space-y-1 text-sm" data-help-related-list></ul>
            </div>
        </footer>
    </div>
</div>

<div id="help-drawer-backdrop"
     class="fixed inset-0 z-[55] hidden bg-base-300/40 backdrop-blur-sm"
     data-help-backdrop></div>
