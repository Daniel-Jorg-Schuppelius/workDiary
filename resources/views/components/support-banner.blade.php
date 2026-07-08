@php
    $supportState = session(\App\Http\Controllers\Admin\SupportImpersonationController::SESSION_KEY);
@endphp

@if (is_array($supportState))
    <div role="status"
         class="border-b border-error/40 bg-error/15 px-4 py-2 text-xs"
         data-support-banner>
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2">
            <span class="inline-flex items-center gap-2 font-medium">
                <x-icon name="support_agent" class="text-base" />
                {{ __('Support-Modus: Sie handeln als :name. Alle Aktionen werden auditiert.', ['name' => auth()->user()?->name ?? '—']) }}
            </span>
            <form method="POST" action="{{ route('admin.support.impersonate.stop') }}">
                @csrf
                <button type="submit" class="btn btn-error btn-xs">
                    {{ __('Support-Sitzung beenden') }}
                </button>
            </form>
        </div>
    </div>
@endif
