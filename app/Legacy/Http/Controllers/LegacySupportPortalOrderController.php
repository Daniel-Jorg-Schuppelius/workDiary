<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacySupportPortalOrderController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Legacy\Http\Concerns\ResolvesLegacySupportCustomer;
use App\Legacy\Models\{LegacySupportCustomer, LegacySupportMailSetting, LegacySupportOrder};
use App\Legacy\Support\LegacyClock;
use App\Support\SortableQuery;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/** Kundensicht des Support-Portals: eigene Aufträge lesen und neue erteilen. */
class LegacySupportPortalOrderController extends Controller {
    use ResolvesLegacySupportCustomer;

    private const int PER_PAGE = 25;

    /** @var array<string, string> */
    private const array SORTS = [
        'datum' => 'datum',
        'name' => 'kname',
        'telefon' => 'telefon',
        'objekt' => 'objekt',
        'termin' => 'termin',
        'status' => 'status',
    ];

    private const string SINGLE_LINE = 'not_regex:/[\x00-\x1F\x7F]/';

    public function index(Request $request): View {
        $customer = $this->supportCustomer($request);

        // Das Altportal ordnet die Liste zuerst nach Status, damit Neues und Offenes oben steht.
        [$sort, $dir] = SortableQuery::resolve($request, self::SORTS, 'status', 'asc');
        $query = LegacySupportOrder::query()->where('kunde', $customer->id)->orderBy(self::SORTS[$sort], $dir);
        if ($sort !== 'datum') {
            $query->orderByDesc('datum');
        }

        return view('legacy.support.portal.index', [
            'customer' => $customer,
            'orders' => $query->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString(),
            'sort' => $sort,
            'dir' => $dir,
            'writeEnabled' => (bool) config('app.legacy_write_enabled'),
        ]);
    }

    /** Ohne Modellbindung: erst die Kundensitzung, dann die Nummer — und nur unter den eigenen Aufträgen. */
    public function show(Request $request, string $order): View {
        $customer = $this->supportCustomer($request);
        // Das Altportal prüfte die Zugehörigkeit nicht; fremde Nummern bleiben hier unsichtbar.
        $order = LegacySupportOrder::query()->where('kunde', $customer->id)->findOrFail((int) $order);

        return view('legacy.support.portal.show', ['customer' => $customer, 'order' => $order]);
    }

    public function create(Request $request): View|RedirectResponse {
        if (! (bool) config('app.legacy_write_enabled')) {
            return redirect()->route('legacy.portal.orders.index');
        }

        return view('legacy.support.portal.create', ['customer' => $this->supportCustomer($request)]);
    }

    public function store(Request $request): RedirectResponse {
        $customer = $this->supportCustomer($request);
        $data = $request->validate([
            'kname' => ['required', 'string', 'max:30', self::SINGLE_LINE],
            'telefon' => ['nullable', 'string', 'max:30', self::SINGLE_LINE],
            'objekt' => ['nullable', 'string', 'max:255', self::SINGLE_LINE],
            'auftrag' => ['required', 'string', 'max:65535'],
        ]);

        $order = LegacySupportOrder::query()->create([
            'kunde' => (int) $customer->id,
            'datum' => LegacyClock::nowString(),
            'kname' => (string) $data['kname'],
            'telefon' => (string) ($data['telefon'] ?? ''),
            'objekt' => (string) ($data['objekt'] ?? ''),
            'auftrag' => (string) $data['auftrag'],
            'termin' => LegacySupportOrder::NO_APPOINTMENT,
            'wer' => '',
            'antwort' => '',
            'intern' => '',
            'dauer' => '',
            'status' => LegacySupportOrder::STATUS_NEW,
        ]);

        $this->notifyOffice($customer, $order);

        return redirect()->route('legacy.portal.orders.index')
            ->with('success', __('legacy_support.portal.order_created', ['number' => $order->id]));
    }

    /** Mail-Hinweis ans Büro, wenn der Schalter an ist — wie im Altportal; ein Fehler hält den Auftrag nicht auf. */
    private function notifyOffice(LegacySupportCustomer $customer, LegacySupportOrder $order): void {
        try {
            $setting = LegacySupportMailSetting::current();
            if (! $setting instanceof LegacySupportMailSetting || ! $setting->isEnabled()) {
                return;
            }

            // Der Hinweis geht ans Büro: Sprache der Installation, nicht die des Kunden.
            $locale = (string) config('app.locale');
            $line = fn(string $key, string $value): string => __($key, [], $locale) . ': ' . ($value !== '' ? $value : '—');
            $body = implode("\n", [
                __('legacy_support.mail.intro', [], $locale),
                '',
                $line('legacy_support.field.number', (string) $order->id),
                $line('legacy_support.field.customer', (string) $customer->kunde),
                $line('legacy_support.field.name', $order->kname),
                $line('legacy_support.field.phone', $order->telefon),
                $line('legacy_support.field.object', $order->objekt),
                '',
                __('legacy_support.field.order_text', [], $locale) . ':',
                $order->auftrag,
            ]);
            $subject = __('legacy_support.mail.subject', ['number' => $order->id, 'customer' => $customer->kunde], $locale);

            Mail::raw($body, function ($message) use ($setting, $subject): void {
                $message->to((string) $setting->email)->subject($subject);
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
