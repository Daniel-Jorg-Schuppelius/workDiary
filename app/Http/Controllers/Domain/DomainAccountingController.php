<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainAccountingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Domain;

use App\Http\Controllers\Controller;
use App\Models\Domain\DomainAccountingEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Read-only Accounting-Journal (Feature 083, MVP-392). Zeigt Datum, Typ,
 * Beschreibung, Referenz, Beträge und Steuer; erlaubt Zeitraum-/Typfilter.
 * Es wird KEINE steuerliche Rechnung erzeugt. Autorisierung über
 * `can:domain.accounting.view` (Route-Middleware).
 */
class DomainAccountingController extends Controller {
    public function index(Request $request): View {
        $query = DomainAccountingEntry::query()->with(['resellerAccount:id,external_user', 'customer:id,name']);

        if (($from = $request->query('from')) !== null && $from !== '') {
            $query->whereDate('entry_date', '>=', $from);
        }
        if (($to = $request->query('to')) !== null && $to !== '') {
            $query->whereDate('entry_date', '<=', $to);
        }
        if (($type = trim((string) $request->query('type', ''))) !== '') {
            $query->whereLikeEscaped('type', $type);
        }

        $entries = $query->orderByDesc('entry_date')->orderByDesc('id')->paginate(50)->withQueryString();

        return view('domain.accounting.index', [
            'entries' => $entries,
            'filters' => $request->only(['from', 'to', 'type']),
        ]);
    }
}
