{{-- Variablen: $order (Model|null), $date, $customers, $projects, $users, $statuses, $priorities --}}
@php
    $action = $order
        ? route('service-orders.update', $order)
        : route('service-orders.store');
@endphp

<x-modal
    :title="$order ? __('Auftrag bearbeiten') : __('Neuer Auftrag')"
    :eyebrow="__('Service-Aufträge')"
    icon="assignment"
    :badge="$order ? __($order->status) : null"
    :badge-tone="$order ? 'ghost' : 'ghost'"
    tone="primary"
    :action="$action"
    :method="$order ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$order ? __('Speichern') : __('Anlegen')">

    @include('service-orders._form_body', ['order' => $order ?? null])

    @if ($order)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('service-orders.destroy', $order) }}"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Auftrag wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-error btn-outline btn-sm gap-2">
                    <x-icon name="delete" /> {{ __('Löschen') }}
                </button>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
