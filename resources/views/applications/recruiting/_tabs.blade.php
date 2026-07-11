{{-- Tab-Navigation Personalbereich (Feature 068): Stellen · Bewerbungen --}}
<div role="tablist" class="tabs tabs-box w-full">
    <a role="tab"
       href="{{ route('recruiting.requisitions.index') }}"
       @class(['tab gap-1', 'tab-active' => request()->routeIs('recruiting.requisitions.*')])
       @if (request()->routeIs('recruiting.requisitions.*')) aria-current="page" @endif>
        <span class="material-symbols-outlined text-base" aria-hidden="true">work</span>
        {{ __('Stellen') }}
    </a>
    <a role="tab"
       href="{{ route('recruiting.applications.index') }}"
       @class(['tab gap-1', 'tab-active' => request()->routeIs('recruiting.applications.*')])
       @if (request()->routeIs('recruiting.applications.*')) aria-current="page" @endif>
        <span class="material-symbols-outlined text-base" aria-hidden="true">person_search</span>
        {{ __('Bewerbungen') }}
    </a>
</div>
