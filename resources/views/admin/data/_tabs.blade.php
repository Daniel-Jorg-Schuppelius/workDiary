{{--
    Tab-Navigation für den Datentransfer-Bereich: Import · Export · Verlauf.
    Der Import nutzt den bestehenden MVP-049-Wizard (admin.imports.*),
    Export und Verlauf liegen unter admin.data.*.
--}}
<div role="tablist" class="tabs tabs-box w-full">
    <a role="tab"
       href="{{ route('admin.imports.index') }}"
       @class(['tab gap-1', 'tab-active' => request()->routeIs('admin.imports.*')])
       @if (request()->routeIs('admin.imports.*')) aria-current="page" @endif>
        <span class="material-symbols-outlined text-base" aria-hidden="true">upload_file</span>
        {{ __('Import') }}
    </a>
    <a role="tab"
       href="{{ route('admin.data.index') }}"
       @class(['tab gap-1', 'tab-active' => request()->routeIs('admin.data.index')])
       @if (request()->routeIs('admin.data.index')) aria-current="page" @endif>
        <span class="material-symbols-outlined text-base" aria-hidden="true">download</span>
        {{ __('Export') }}
    </a>
    <a role="tab"
       href="{{ route('admin.data.history') }}"
       @class(['tab gap-1', 'tab-active' => request()->routeIs('admin.data.history')])
       @if (request()->routeIs('admin.data.history')) aria-current="page" @endif>
        <span class="material-symbols-outlined text-base" aria-hidden="true">history</span>
        {{ __('Verlauf') }}
    </a>
</div>
