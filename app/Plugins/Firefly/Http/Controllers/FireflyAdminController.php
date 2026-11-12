<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflyAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Firefly\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Audit\AuditLog;
use App\Models\Platform\User;
use App\Plugins\Firefly\Api\FireflyClientFactory;
use App\Plugins\Firefly\Enums\FireflyMatchStatus;
use App\Plugins\Firefly\{FireflyConfig, FireflyPlugin};
use App\Plugins\Firefly\Models\{FireflyConnection, FireflyTransaction};
use App\Plugins\Firefly\Services\FireflySyncService;
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

/**
 * Firefly-III-Seite (MVP-1333/1335): Verbindung prüfen, abgleichen, trennen
 * und die gespiegelten Bewegungen mit ihrer Zuordnung.
 */
class FireflyAdminController extends Controller {
    use ResolvesPluginOrgContext;

    /** Filterwert für Bewegungen ohne eindeutige Zuordnung. */
    public const string OPEN = 'open';

    /** Filterwert für als erledigt markierte Bewegungen (MVP-1628). */
    public const string SETTLED = 'settled';

    public function index(Request $request, FireflySyncService $sync): View {
        $organization = $this->organization($this->admin());
        $search = StringHelper::trimToNull($request->query('q'));
        $match = (string) $request->query('match', '');
        $status = FireflyMatchStatus::tryFrom($match);
        // „offen“ = ohne oder mit mehrdeutiger Zuordnung, wie Kachel und Arbeitsvorrat zählen (MVP-1617).
        $open = $status === null && $match === self::OPEN;
        $settled = $status === null && $match === self::SETTLED;
        $query = FireflyTransaction::query()->where('organization_id', $organization->id)->with(['invoice', 'incomingEinvoice', 'settledBy']);
        if ($search !== null) {
            FireflyTransaction::whereMatching($query, $search);
        }
        if ($status !== null) {
            // Wie der Arbeitsvorrat zählt: erledigte stehen unter „Erledigt“, nicht mehr unter ihrem offenen Stand.
            $query->where('match_status', $status->value)
                ->when(in_array($status->value, FireflyMatchStatus::openValues(), true), fn ($q) => $q->whereNull('settled_at'));
        } elseif ($open) {
            $query->openForReview();
        } elseif ($settled) {
            $query->whereNotNull('settled_at');
        }
        $connection = $sync->connection((int) $organization->id);

        return view('firefly::admin.index', [
            'configured' => FireflyConfig::isConfigured((int) $organization->id),
            'connection' => $connection,
            'connected' => $connection?->status === OAuthConnectionStatus::Active,
            'transactions' => $query->orderByDesc('booked_on')->orderByDesc('id')->paginate(25)->withQueryString(),
            'search' => $search,
            'status' => $status,
            'open' => $open,
            'settled' => $settled,
            'windowDays' => FireflyConfig::resolve((int) $organization->id)['window_days'],
        ]);
    }

    public function verify(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        if (! FireflyConfig::isConfigured((int) $organization->id)) {
            return $this->back()->with('error', (string) __('firefly::firefly.flash.not_configured'));
        }
        $baseUrl = (string) FireflyConfig::resolve((int) $organization->id)['base_url'];
        try {
            $about = app(FireflyClientFactory::class)->for((int) $organization->id)->about();
        } catch (Throwable $e) {
            report($e);

            return $this->back()->with('error', (string) __('firefly::firefly.flash.verify_failed', ['class' => class_basename($e)]));
        }
        $connection = FireflyConnection::query()->firstOrNew(['organization_id' => $organization->id]);
        $key = SelfHostedInstance::key($baseUrl);
        if ($connection->source_id !== null && $connection->source_id !== $key && FireflyTransaction::query()->where('organization_id', $organization->id)->exists()) {
            return $this->back()->with('error', (string) __('firefly::firefly.flash.source_locked'));
        }
        $connection->forceFill([
            'source_id' => $key,
            'source_name' => SelfHostedInstance::name($baseUrl) . ($about['version'] !== null ? ' (Firefly III ' . $about['version'] . ')' : ''),
            'source_url' => $baseUrl,
            'account_name' => $about['email'],
            'status' => OAuthConnectionStatus::Active,
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
            'connected_by' => $admin->id,
            'connected_at' => CarbonImmutable::now(),
            'disconnected_by' => null,
            'disconnected_at' => null,
        ])->save();
        $connection->audit(FireflyPlugin::ID . '.connected', ['source_id' => $key, 'by_user_id' => (int) $admin->id]);

        return $this->back()->with('success', (string) __('firefly::firefly.flash.verified', ['source_name' => (string) $connection->source_name]));
    }

    public function syncNow(FireflySyncService $sync): RedirectResponse {
        $organization = $this->organization($this->admin());
        if (! $sync->mayRun($organization)) {
            return $this->back()->with('error', (string) __('firefly::firefly.flash.not_ready'));
        }
        try {
            $counters = $sync->run($organization);
        } catch (Throwable $e) {
            report($e);

            return $this->back()->with('error', (string) __('firefly::firefly.flash.sync_failed', ['class' => class_basename($e)]));
        }

        return $this->back()->with('success', (string) __('firefly::firefly.flash.synced', array_map('strval', $counters)));
    }

    /** Trennen: der Abgleich ruht; der Spiegel bleibt bis zum nächsten vollständigen Lauf. */
    public function disconnect(FireflySyncService $sync): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $connection = $sync->connection((int) $organization->id);
        if ($connection !== null) {
            $connection->forceFill(['status' => OAuthConnectionStatus::Disconnected, 'disconnected_by' => $admin->id, 'disconnected_at' => CarbonImmutable::now()])->save();
            $connection->audit(FireflyPlugin::ID . '.disconnected', ['by_user_id' => (int) $admin->id]);
        }

        return $this->back()->with('success', (string) __('firefly::firefly.flash.disconnected'));
    }

    /** Erledigt markieren (MVP-1628): eine Bewegung ohne Beleg mit Begründung aus der Nacharbeit nehmen. */
    public function settleForm(FireflyTransaction $transaction): View {
        $this->ownTransaction($transaction);

        return view('firefly::admin._settle_dialog', ['transaction' => $transaction]);
    }

    public function settle(Request $request, FireflyTransaction $transaction): RedirectResponse {
        $admin = $this->ownTransaction($transaction);
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);
        if ($transaction->isSettled()) {
            return $this->back()->with('error', (string) __('firefly::firefly.settle.already'));
        }

        $transaction->forceFill(['settled_at' => CarbonImmutable::now(), 'settled_by' => $admin->id, 'note' => trim((string) $data['note'])])->save();
        $this->auditTransaction($transaction, 'transaction_settled', ['note' => $transaction->note]);

        return $this->back()->with('success', (string) __('firefly::firefly.settle.done'));
    }

    public function reopen(FireflyTransaction $transaction): RedirectResponse {
        $this->ownTransaction($transaction);
        if (! $transaction->isSettled()) {
            return $this->back();
        }

        $previous = ['settled_at' => $transaction->settled_at?->toIso8601String(), 'settled_by' => $transaction->settled_by, 'note' => $transaction->note];
        $transaction->forceFill(['settled_at' => null, 'settled_by' => null, 'note' => null])->save();
        $this->auditTransaction($transaction, 'transaction_reopened', $previous);

        return $this->back()->with('success', (string) __('firefly::firefly.settle.reopened'));
    }

    private function ownTransaction(FireflyTransaction $transaction): User {
        $admin = $this->admin();
        abort_unless((int) $transaction->organization_id === (int) $this->organization($admin)->id, 404);

        return $admin;
    }

    /** @param  array<string, mixed>  $changes */
    private function auditTransaction(FireflyTransaction $transaction, string $event, array $changes): void {
        AuditLog::query()->create([
            'organization_id' => $transaction->organization_id,
            'user_id' => Auth::id(),
            'event' => FireflyPlugin::ID . '.' . $event,
            'auditable_type' => MorphMap::stableKey(FireflyTransaction::class),
            'auditable_id' => $transaction->id,
            'changes' => $changes,
        ]);
    }

    private function back(): RedirectResponse {
        return redirect()->toList('admin.firefly.index');
    }
}
