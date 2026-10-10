<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\Customer\IntakeStatus;
use App\Enums\CustomerPortal\PortalCapability;
use App\Enums\Invoicing\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer\CustomerIntake;
use App\Models\Diary\{DiaryEntry, OpenIssue};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Reselling\ResaleSubscription;
use App\Models\Time\TimeEntry;
use App\Modules\ModuleRegistry;
use App\Services\Customer\Intake\CustomerIntakeStages;
use App\Services\CustomerPortal\Contracts\PortalNoticeSource;
use App\Services\CustomerPortal\PortalVisibility;
use App\Support\MorphMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{Auth, Log};
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller {
    public function __invoke(PortalVisibility $visibility, ModuleRegistry $modules, CustomerIntakeStages $stages): View {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        $customerId = (int) $user->customer_id;
        $customer = $user->customer;

        // Kacheln/Zähler nur für freigegebene Bereiche (MVP-511) — nicht
        // Freigegebenes wird weder gezählt noch verlinkt.
        $stats = [];
        if ($customer !== null && $visibility->allows($customer, PortalCapability::Diary)) {
            $stats['diary'] = DiaryEntry::query()->where('customer_id', $customerId)->count();
        }
        if ($customer !== null && $visibility->timeDetail($customer)->showsEntries()) {
            $timeQuery = TimeEntry::query()
                ->whereHas('project', fn($q) => $q->where('customer_id', $customerId));
            if ($visibility->timeScope($customer) === PortalVisibility::TIME_SCOPE_PUBLISHED) {
                $timeQuery->whereNotNull('customer_visible_at');
            }
            $stats['time_entries'] = $timeQuery->count();
        }
        if ($customer !== null && $visibility->allows($customer, PortalCapability::Invoices)) {
            // Wie die Liste: Entwürfe sind interne Arbeitsstände (MVP-1019).
            $stats['invoices'] = Invoice::query()->where('customer_id', $customerId)->where('status', '!=', InvoiceStatus::Draft)->count();
        }
        if ($customer !== null && $visibility->allows($customer, PortalCapability::OpenIssues)) {
            $stats['open_issues'] = OpenIssue::query()
                ->where('visibility', \App\Enums\OpenIssue\OpenIssueVisibility::Customer->value)
                ->whereNull('closed_at')
                ->where(function (Builder $q) use ($customerId): void {
                    $q->where(function (Builder $sub) use ($customerId): void {
                        $sub->where('subject_type', MorphMap::alias(\App\Models\Customer\Customer::class))
                            ->where('subject_id', $customerId);
                    })
                        ->orWhere(function (Builder $sub) use ($customerId): void {
                            $sub->where('subject_type', MorphMap::alias(DiaryEntry::class))
                                ->whereIn('subject_id', DiaryEntry::query()
                                    ->where('customer_id', $customerId)
                                    ->select('id'));
                        })
                        ->orWhere(function (Builder $sub) use ($customerId): void {
                            $sub->where('subject_type', MorphMap::alias(Project::class))
                                ->whereIn('subject_id', Project::query()
                                    ->where('customer_id', $customerId)
                                    ->select('id'));
                        });
                })
                ->count();
        }
        if ($customer !== null && $visibility->allows($customer, PortalCapability::Subscriptions)) {
            // Bestand wie in „meine Abos": Kunde + Endkunden, Org-Grenze des Portalkontos.
            $stats['subscriptions'] = ResaleSubscription::query()
                ->where('organization_id', (int) $user->organization_id)
                ->forCustomer($customer)
                ->visibleInPortal()
                ->count();
        }

        if ($customer !== null && $visibility->allows($customer, PortalCapability::Intakes)) {
            // Laufende Vorgänge und die, bei denen der Kunde am Zug ist (MVP-1075).
            $intakes = CustomerIntake::query()->ofPortalUser($user)
                ->whereIn('status', [...array_map(static fn (IntakeStatus $s): string => $s->value, IntakeStatus::open()), IntakeStatus::HandedOver->value])
                ->with('quote')
                ->latest('id')
                ->limit(200)
                ->get();
            $stats['intakes'] = $intakes->filter(fn (CustomerIntake $intake): bool => $intake->status->isOpen())->count();
            $stats['intakes_action'] = $intakes->filter(fn (CustomerIntake $intake): bool => $stages->for($intake)->actionRequired)->count();
        }

        // Hinweise der Module (MVP-915, z. B. Krisenmitteilungen); eine fehlerhafte Quelle fällt einzeln aus.
        $notices = [];
        $organization = $user->organization;
        if ($customer !== null && $organization !== null) {
            foreach ($modules->extensions(PortalNoticeSource::class) as $class) {
                try {
                    /** @var PortalNoticeSource $source */
                    $source = app($class);
                    array_push($notices, ...$source->portalNotices($organization, $customer));
                } catch (Throwable $e) {
                    Log::warning('portal notice source failed', ['source' => $class, 'organization_id' => $organization->id, 'error' => $e->getMessage()]);
                }
            }
        }

        return view('customer.dashboard', [
            'user' => $user,
            'customer' => $customer,
            'stats' => $stats,
            'notices' => $notices,
        ]);
    }
}
