<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubscriptionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\Reselling\PeriodStatus;
use App\Http\Controllers\Controller;
use App\Models\{Customer, User};
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Services\Reselling\Register\PeriodPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Portal-Sicht „meine Abos" (Feature 152, Prozesse 6): der Kunde sieht den
 * Bestand seiner Abos und der Abos seiner Endkunden (Fremdkunden) — Produkt,
 * Halter, Menge, Laufzeit, Rhythmus und die nächste Periode. Bewusst NUR
 * Bestand: keine Preise, keine Einkaufsdaten, keine Rechnungsbezüge; der
 * Periodenstatus ist neutral formuliert (offen/berechnet/nicht berechnet).
 * Harte Grenze: Organisation des Portalkontos + `ResaleSubscription::forCustomer`.
 */
class SubscriptionController extends Controller {
    public function __construct(private readonly PeriodPlanner $planner) {}

    public function index(): View {
        $user = $this->portalUser();
        $customer = $this->portalCustomer($user);
        $today = ResalePeriod::today();

        $subscriptions = $this->visible($user, $customer, $today)
            ->with(['customer:id,name', 'foreignCustomer:id,name', 'article:id,number,name', 'lexofficeArticle:id,name,article_number', 'parent'])
            ->orderBy('label')
            ->orderBy('starts_on')
            ->paginate(25);

        $nextPeriods = [];
        foreach ($subscriptions as $subscription) {
            $nextPeriods[$subscription->id] = $this->nextPeriodStart($subscription, $today);
        }

        return view('customer.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'nextPeriods' => $nextPeriods,
            'today' => $today,
        ]);
    }

    public function show(ResaleSubscription $subscription): View {
        $user = $this->portalUser();
        $customer = $this->portalCustomer($user);
        $today = ResalePeriod::today();

        // Leak-Schutz: außerhalb der Kundensicht existiert das Abo für dieses Portalkonto nicht.
        abort_unless($this->visible($user, $customer, $today)->whereKey($subscription->getKey())->exists(), 404);

        $subscription->load(['customer:id,name', 'foreignCustomer:id,name', 'article:id,number,name', 'lexofficeArticle:id,name,article_number', 'parent', 'periods']);

        return view('customer.subscriptions.show', [
            'subscription' => $subscription,
            'nextPeriod' => $this->nextPeriodStart($subscription, $today),
            'periodStatusLabels' => $this->periodStatusLabels(),
            'today' => $today,
        ]);
    }

    /**
     * Sichtbarer Bestand: Abos des Kunden und seiner Endkunden in der eigenen
     * Organisation, aktiv/gekündigt/abgelöst sowie beendete der letzten zwölf Monate.
     *
     * @return Builder<ResaleSubscription>
     */
    private function visible(User $user, Customer $customer, CarbonImmutable $today): Builder {
        return ResaleSubscription::query()
            ->where('organization_id', (int) $user->organization_id)
            ->forCustomer($customer)
            ->visibleInPortal($today);
    }

    /**
     * Beginn der nächsten geplanten Periode nach dem Stichtag (null: keine
     * mehr — gekündigt mit Ende, abgelöst, beendet). Die Planung sieht nur
     * HORIZON_DAYS voraus; damit auch ein Jahresrhythmus seine nächste Periode
     * zeigt, wird der Stichtag um ein Jahr vorgezogen (nur Horizont, kein Ende).
     */
    private function nextPeriodStart(ResaleSubscription $subscription, CarbonImmutable $today): ?CarbonImmutable {
        if (! $subscription->status->isPlanning()) {
            return null;
        }
        foreach ($this->planner->plan($subscription, $today->addYearNoOverflow()) as $slot) {
            if ($slot['starts_on']->greaterThan($today)) {
                return $slot['starts_on'];
            }
        }

        return null;
    }

    /**
     * Neutrale Periodenlabels fürs Portal: interne Zustände (teilweise,
     * strittig, verzichtet mit Grund) werden nicht 1:1 nach außen getragen.
     *
     * @return array<string, string>
     */
    private function periodStatusLabels(): array {
        $labels = [];
        foreach (PeriodStatus::cases() as $status) {
            $labels[$status->value] = (string) __('resale_portal.period_status.' . $status->value);
        }

        return $labels;
    }

    private function portalUser(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_if($user->customer_id === null, 403);

        return $user;
    }

    private function portalCustomer(User $user): Customer {
        $customer = $user->customer;
        abort_unless($customer instanceof Customer && (int) $customer->organization_id === (int) $user->organization_id, 403);

        return $customer;
    }
}
