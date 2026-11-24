{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : form.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Auftrag anlegen bzw. bearbeiten wie im Altportal. $form wählt die Teilmaske:
    entry (Eintragung, kp_edit.php), appointment (Termin ändern, Löschen, superedit.php),
    customer (Kundenauswahl, mod_kunde.php). Erwartet: $order, $isEdit, $form, $customers, $selectedCustomer, $orderer, $mechanics
--}}
@extends('legacy.classic.shell')
@section('title', $isEdit ? __('legacy_support.orders.edit_title', ['number' => $order->id]) : __('legacy_support.orders.create'))

@section('content')
    @php
        $orderModel = \App\Legacy\Models\LegacySupportOrder::class;
        $customerSqid = fn (int $id): string => \App\Support\Sqid::encode(\App\Legacy\Models\LegacySupportCustomer::class, $id);
        $statusOptions = [
            $orderModel::STATUS_NEW => __('legacy_support.classic.status_option.unread'),
            $orderModel::STATUS_SEEN => __('legacy_support.classic.status_option.read'),
            $orderModel::STATUS_SCHEDULED => __('legacy_support.classic.status_option.scheduled'),
            $orderModel::STATUS_DONE => __('legacy_support.classic.status_option.done'),
            $orderModel::STATUS_ARCHIVED => __('legacy_support.classic.status_option.archived'),
        ];
        $action = $isEdit ? route('legacy.support.orders.update', $order) : route('legacy.support.orders.store');
    @endphp

    <p><a href="{{ \App\Http\Middleware\RememberListUrl::urlFor('legacy.support.orders.index') }}">{{ __('legacy_support.classic.to_list') }}</a></p>
    @include('legacy.classic._flash')

    @if (! $isEdit)
        <p>{{ __('legacy_support.classic.new_entry') }}</p>
        <form method="POST" action="{{ $action }}">
            @csrf
            <table class="eintrag">
                <tr>
                    <td class="kp-label"><label for="kp-kunde">{{ __('legacy_support.classic.label.customer') }}</label></td>
                    <td>
                        <select id="kp-kunde" name="kunde" required>
                            @foreach ($customers as $customer)
                                @php $sqid = $customerSqid((int) $customer->id); @endphp
                                <option value="{{ $sqid }}" @selected((string) old('kunde', $selectedCustomer) === $sqid)>{{ $customer->kunde }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.name') }}</td><td>{{ $orderer }}</td></tr>
                <tr><td class="kp-label"><label for="kp-telefon">{{ __('legacy_support.classic.label.phone') }}</label></td><td><input id="kp-telefon" type="text" name="telefon" maxlength="30" value="{{ old('telefon') }}"></td></tr>
                <tr><td class="kp-label"><label for="kp-objekt">{{ __('legacy_support.classic.label.object') }}</label></td><td><input id="kp-objekt" type="text" name="objekt" maxlength="255" value="{{ old('objekt') }}"></td></tr>
                <tr><td class="kp-label"><label for="kp-auftrag">{{ __('legacy_support.classic.label.order') }}</label></td><td><textarea id="kp-auftrag" name="auftrag" rows="4" required>{{ old('auftrag') }}</textarea></td></tr>
                <tr><td>&nbsp;</td><td><button type="submit">{{ __('legacy_support.classic.save') }}</button></td></tr>
            </table>
        </form>
    @elseif ($form === 'appointment')
        <form method="POST" action="{{ $action }}">
            @csrf
            @method('PUT')
            @include('legacy.classic.support._order_hidden', ['order' => $order, 'except' => ['termin']])
            <table class="eintrag">
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.number') }}</td><td>{{ $order->id }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.customer') }}</td><td>{{ $order->customer?->kunde ?? __('legacy_support.customer_unknown', ['id' => $order->kunde]) }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.date') }}</td><td>{{ \App\Legacy\Support\LegacyClock::format($order->datum) }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.orderer') }}</td><td>{{ $order->kname }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.phone') }}</td><td>{{ $order->telefon }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.object') }}</td><td>{{ $order->objekt }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.order_text') }}</td><td class="kp-pre">{{ $order->auftrag }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.appointment_input') }}</td><td>@include('legacy.classic.support._appointment_inputs', ['order' => $order])</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.mechanic') }}</td><td>{{ $order->wer !== '' ? $order->wer : __('legacy_support.classic.unnamed') }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.reply') }}</td><td class="kp-pre">{{ $order->antwort }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.internal') }}</td><td class="kp-pre">{{ $order->intern }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.duration') }}</td><td>{{ $order->dauer }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.status') }}</td><td>{{ $statusOptions[(int) $order->status] ?? $order->status }}</td></tr>
                <tr><td>&nbsp;</td><td><button type="submit">{{ __('legacy_support.classic.save') }}</button></td></tr>
            </table>
        </form>
        <form method="POST" action="{{ route('legacy.support.orders.destroy', $order) }}" class="kp-msg">
            @csrf
            @method('DELETE')
            <label><input type="checkbox" name="confirm" value="1" required> {{ __('legacy_support.classic.delete_entry') }}</label>
            <button type="submit" class="kp-danger">{{ __('legacy_support.classic.delete') }}</button>
        </form>
    @elseif ($form === 'customer')
        <form method="POST" action="{{ $action }}">
            @csrf
            @method('PUT')
            @include('legacy.classic.support._order_hidden', ['order' => $order, 'except' => ['kunde']])
            <table class="eintrag">
                <tr>
                    <td class="kp-label"><label for="kp-kunde">{{ __('legacy_support.classic.label.customer') }}</label></td>
                    <td>
                        <select id="kp-kunde" name="kunde" size="5" required>
                            @foreach ($customers as $customer)
                                @php $sqid = $customerSqid((int) $customer->id); @endphp
                                <option value="{{ $sqid }}" @selected((string) old('kunde', $selectedCustomer) === $sqid)>{{ $customer->kunde }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.date') }}</td><td>{{ \App\Legacy\Support\LegacyClock::format($order->datum) }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.orderer') }}</td><td>{{ $order->kname }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.phone') }}</td><td>{{ $order->telefon }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.object') }}</td><td>{{ $order->objekt }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.order_text') }}</td><td class="kp-pre">{{ $order->auftrag }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.appointment') }}</td><td>{{ \App\Legacy\Support\LegacyClock::format($order->appointmentAt()) }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.mechanic') }}</td><td>{{ $order->wer }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.status') }}</td><td>{{ $statusOptions[(int) $order->status] ?? $order->status }}</td></tr>
                <tr><td>&nbsp;</td><td><button type="submit">{{ __('legacy_support.classic.save') }}</button></td></tr>
            </table>
        </form>
    @else
        <form method="POST" action="{{ $action }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="kunde" value="{{ $selectedCustomer }}">
            <table class="eintrag">
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.number') }}</td><td>{{ $order->id }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.customer') }}</td><td>{{ $order->customer?->kunde ?? __('legacy_support.customer_unknown', ['id' => $order->kunde]) }}</td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.date') }}</td><td>{{ \App\Legacy\Support\LegacyClock::format($order->datum) }}</td></tr>
                <tr>
                    <td class="kp-label"><label for="kp-kname">{{ __('legacy_support.classic.label.orderer') }}</label></td>
                    <td>
                        @if ($order->isOfficeOrder())
                            {{ $order->kname }}
                        @else
                            <input id="kp-kname" type="text" name="kname" maxlength="30" value="{{ old('kname', $order->kname) }}">
                        @endif
                    </td>
                </tr>
                <tr><td class="kp-label"><label for="kp-telefon">{{ __('legacy_support.classic.label.phone') }}</label></td><td><input id="kp-telefon" type="text" name="telefon" maxlength="30" value="{{ old('telefon', $order->telefon) }}"></td></tr>
                <tr><td class="kp-label"><label for="kp-objekt">{{ __('legacy_support.classic.label.object') }}</label></td><td><input id="kp-objekt" type="text" name="objekt" maxlength="255" value="{{ old('objekt', $order->objekt) }}"></td></tr>
                <tr><td class="kp-label"><label for="kp-auftrag">{{ __('legacy_support.classic.label.order_text') }}</label></td><td><textarea id="kp-auftrag" name="auftrag" rows="3" required>{{ old('auftrag', $order->auftrag) }}</textarea></td></tr>
                <tr><td class="kp-label">{{ __('legacy_support.classic.label.appointment_input') }}</td><td>@include('legacy.classic.support._appointment_inputs', ['order' => $order])</td></tr>
                <tr>
                    <td class="kp-label"><label for="kp-wer">{{ __('legacy_support.classic.label.mechanic') }}</label></td>
                    <td>
                        <select id="kp-wer" name="wer">
                            <option value="">{{ __('legacy_support.classic.nobody') }}</option>
                            @foreach ($mechanics as $mechanic)
                                <option value="{{ $mechanic }}" @selected((string) old('wer', $order->wer) === $mechanic)>{{ $mechanic }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
                <tr><td class="kp-label"><label for="kp-antwort">{{ __('legacy_support.classic.label.reply') }}</label></td><td><textarea id="kp-antwort" name="antwort" rows="3">{{ old('antwort', $order->antwort) }}</textarea></td></tr>
                <tr><td class="kp-label"><label for="kp-intern">{{ __('legacy_support.classic.label.internal') }}</label></td><td><textarea id="kp-intern" name="intern" rows="3">{{ old('intern', $order->intern) }}</textarea></td></tr>
                <tr><td class="kp-label"><label for="kp-dauer">{{ __('legacy_support.classic.label.duration') }}</label></td><td><input id="kp-dauer" type="text" name="dauer" maxlength="255" value="{{ old('dauer', $order->dauer) }}"></td></tr>
                <tr>
                    <td class="kp-label"><label for="kp-status">{{ __('legacy_support.classic.label.status') }}</label></td>
                    <td>
                        <select id="kp-status" name="status">
                            @foreach ($statusOptions as $statusValue => $statusLabel)
                                <option value="{{ $statusValue }}" @selected((int) old('status', $order->status) === $statusValue)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
                <tr><td>&nbsp;</td><td><button type="submit">{{ __('legacy_support.classic.save') }}</button></td></tr>
            </table>
        </form>
    @endif
@endsection
