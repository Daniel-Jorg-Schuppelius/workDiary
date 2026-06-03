@extends('layouts.app')
@section('title', __('Toggl-Import'))
@section('nav-title', __('Toggl-Import'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        {{-- Importquellen --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Toggl Track importieren') }}</h1>
                <div class="flex gap-2">
                    <a href="{{ route('admin.toggl.import-api') }}" class="btn btn-ghost btn-sm">{{ __('Workspaces aus API importieren') }}</a>
                    <a href="{{ route('admin.toggl.import-export') }}" class="btn btn-ghost btn-sm">{{ __('Workspace-Export importieren') }}</a>
                    <a href="{{ route('admin.toggl.mappings.index') }}" class="btn btn-ghost btn-sm">{{ __('Zuordnungen verwalten') }}</a>
                </div>
            </div>
            <p class="mb-4 text-sm text-base-content/60">
                {{ __('Zeiteinträge per API abrufen oder einen Detailed-Report-CSV-Export hochladen. Zuordenbare Einträge werden direkt im Kundenprojekt gebucht, der Rest landet unten in der Inbox.') }}
            </p>

            @if (session('status'))
                <div class="alert alert-success mb-3 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error mb-3 text-sm">{{ $errors->first() }}</div>
            @endif

            <div class="grid gap-3 md:grid-cols-2">
                <form method="POST" action="{{ route('admin.toggl.sync') }}"
                      class="flex items-center justify-between gap-2 rounded-box bg-base-200/50 p-3">
                    @csrf
                    <div>
                        <div class="text-sm font-semibold">{{ __('Per API synchronisieren') }}</div>
                        <div class="text-xs text-base-content/60">{{ __('Nutzt das hinterlegte API-Token und Zeitfenster.') }}</div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Jetzt synchronisieren') }}</button>
                </form>

                <form method="POST" action="{{ route('admin.toggl.import-csv') }}" enctype="multipart/form-data"
                      class="rounded-box bg-base-200/50 p-3 space-y-2">
                    @csrf
                    <div class="text-sm font-semibold">{{ __('CSV-Export hochladen') }}</div>
                    <div class="flex items-end gap-2">
                        <input type="file" name="csv" accept=".csv,text/csv" required
                               class="file-input file-input-sm file-input-bordered flex-1">
                        <button type="submit" class="btn btn-sm">{{ __('Importieren') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Inbox: unzugeordnete Einträge --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Unzugeordnete Einträge') }}</h2>
                <p class="text-sm text-base-content/60">
                    {{ __('Diese Toggl-Client/Projekt-Kombinationen ließen sich keinem Kunden bzw. Projekt zuordnen. Ordne jede Gruppe einem bestehenden Kunden + Projekt zu oder lege Kunde/Projekt direkt neu an — die Einträge werden dann gebucht und künftige Importe matchen automatisch.') }}
                </p>
            </div>

            @if ($groups->isEmpty())
                <p class="rounded-box border border-base-300 p-6 text-center text-sm text-base-content/60">
                    {{ __('Keine offenen Einträge. Alles zugeordnet.') }}
                </p>
            @else
                <div class="space-y-3">
                    @foreach ($groups as $group)
                        <div class="rounded-box border border-base-300 p-3"
                             x-data="togglAssign({
                                customerSqid: @js($group->suggested_customer_sqid),
                                projectSqid: @js($group->suggested_project_sqid),
                             })">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <x-status-badge tone="neutral" size="md">{{ $group->client_name ?: __('(ohne Client)') }}</x-status-badge>
                                    <span class="ml-2 font-semibold">{{ $group->project_name ?: __('(ohne Projekt)') }}</span>
                                    <span class="ml-2 text-sm text-base-content/60">
                                        {{ trans_choice(':count Eintrag|:count Einträge', $group->count, ['count' => $group->count]) }},
                                        {{ $group->minutes }} {{ __('Min.') }} ·
                                        {{ \Illuminate\Support\Carbon::parse($group->first_seen)->isoFormat('L') }} – {{ \Illuminate\Support\Carbon::parse($group->last_seen)->isoFormat('L') }}
                                    </span>
                                </div>
                                <form method="POST" action="{{ route('admin.toggl.pending.dismiss') }}"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Diese Einträge verwerfen? Sie werden nicht gebucht.') }}">
                                    @csrf
                                    <input type="hidden" name="client_name" value="{{ $group->client_name }}">
                                    <input type="hidden" name="project_name" value="{{ $group->project_name }}">
                                    <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('Verwerfen') }}</button>
                                </form>
                            </div>

                            <form method="POST" action="{{ route('admin.toggl.pending.assign') }}"
                                  class="grid gap-3 rounded-box bg-base-200/50 p-3 md:grid-cols-2">
                                @csrf
                                <input type="hidden" name="client_name" value="{{ $group->client_name }}">
                                <input type="hidden" name="project_name" value="{{ $group->project_name }}">
                                <input type="hidden" name="customer_mode" :value="customerMode">
                                <input type="hidden" name="project_mode" :value="projectMode">

                                {{-- Kunde --}}
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="label-text text-xs font-semibold">{{ __('Kunde') }}</span>
                                        <label class="label cursor-pointer gap-1 py-0">
                                            <input type="checkbox" class="toggle toggle-xs" x-model="newCustomer">
                                            <span class="label-text text-xs">{{ __('neu anlegen') }}</span>
                                        </label>
                                    </div>
                                    <select name="customer_id" x-model="customerSqid" :disabled="newCustomer" x-show="!newCustomer"
                                            class="select select-sm select-bordered w-full">
                                        <option value="">{{ __('— Kunde wählen —') }}</option>
                                        <template x-for="c in customers" :key="c.sqid">
                                            <option :value="c.sqid" x-text="c.label"></option>
                                        </template>
                                    </select>
                                    <input type="text" name="new_customer_name" :disabled="!newCustomer" x-show="newCustomer"
                                           value="{{ $group->client_name }}" placeholder="{{ __('Name des neuen Kunden') }}"
                                           class="input input-sm input-bordered w-full" x-cloak>
                                </div>

                                {{-- Projekt --}}
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="label-text text-xs font-semibold">{{ __('Projekt') }}</span>
                                        <label class="label cursor-pointer gap-1 py-0" :class="newCustomer && 'opacity-50'">
                                            <input type="checkbox" class="toggle toggle-xs" x-model="newProject" :disabled="newCustomer">
                                            <span class="label-text text-xs">{{ __('neu anlegen') }}</span>
                                        </label>
                                    </div>
                                    <select name="project_id" x-model="projectSqid" :disabled="newProject" x-show="!newProject"
                                            class="select select-sm select-bordered w-full">
                                        <option value="">{{ __('— Projekt wählen —') }}</option>
                                        <template x-for="p in filteredProjects" :key="p.sqid">
                                            <option :value="p.sqid" x-text="p.name"></option>
                                        </template>
                                    </select>
                                    <input type="text" name="new_project_name" :disabled="!newProject" x-show="newProject"
                                           value="{{ $group->project_name }}" placeholder="{{ __('Name des neuen Projekts') }}"
                                           class="input input-sm input-bordered w-full" x-cloak>
                                </div>

                                <div class="md:col-span-2 flex justify-end">
                                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Zuordnen & buchen') }}</button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-page-shell>

@push('scripts')
<script>
    window.togglCustomers = @json($customers);
    window.togglProjects = @json($projects);

    window.togglAssign = function (init) {
        return {
            customers: window.togglCustomers || [],
            customerSqid: init.customerSqid || '',
            projectSqid: init.projectSqid || '',
            newCustomer: !init.customerSqid,
            newProject: !init.projectSqid,
            init() {
                // Ein neuer Kunde hat noch keine Projekte → Projekt muss neu angelegt werden.
                this.$watch('newCustomer', (v) => { if (v) this.newProject = true; });
                // Kundenwechsel: Projektvorauswahl verwerfen, wenn sie nicht zum Kunden passt.
                this.$watch('customerSqid', () => {
                    if (!this.filteredProjects.some((p) => p.sqid === this.projectSqid)) {
                        this.projectSqid = '';
                    }
                });
            },
            get customerId() {
                const c = this.customers.find((c) => c.sqid === this.customerSqid);
                return c ? c.id : null;
            },
            get customerMode() {
                return this.newCustomer ? 'new' : 'existing';
            },
            get projectMode() {
                return this.newProject ? 'new' : 'existing';
            },
            get filteredProjects() {
                const id = this.customerId;
                if (!id) {
                    return [];
                }
                return (window.togglProjects || []).filter((p) => p.customer_id === id);
            },
        };
    };
</script>
@endpush
@endsection
