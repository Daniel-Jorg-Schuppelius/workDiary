<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainDnsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Domain;

use App\Http\Controllers\Controller;
use App\Models\Domain\DomainProjection;
use App\Services\Domain\{DomainActionException, DomainDnsService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/**
 * Nameserver-/DNS-Zonenverwaltung (Feature 083, MVP-389): typisierte Records,
 * getrennter vollständiger Replace vs. additive Änderung, Snapshot und
 * Re-Read-Konfliktanzeige.
 */
class DomainDnsController extends Controller {
    public function read(DomainProjection $domain, DomainDnsService $service): RedirectResponse {
        Gate::authorize('manageDns', $domain);

        $service->readZone($domain->providerConnection(), $domain->external_domain);

        return back()->with('success', __('domain.flash.dns_read'));
    }

    /** Vollständiger Replace der Zone (rrN) mit Snapshot davor. */
    public function replace(Request $request, DomainProjection $domain, DomainDnsService $service): RedirectResponse {
        Gate::authorize('manageDns', $domain);

        $records = $this->validatedRecords($request);

        try {
            $result = $service->replaceZone($domain->providerConnection(), $domain->external_domain, $records, ($request->user() ?? abort(401)));
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            $result['conflict'] ? 'error' : 'success',
            $result['conflict'] ? __('domain.flash.dns_conflict') : __('domain.flash.dns_replaced'),
        );
    }

    /** Additive Änderung (addrrN/delrrN), getrennt vom Replace. */
    public function modify(Request $request, DomainProjection $domain, DomainDnsService $service): RedirectResponse {
        Gate::authorize('manageDns', $domain);

        $add = $this->validatedRecords($request, 'add');
        $delete = $this->validatedRecords($request, 'delete');

        try {
            $service->modifyRecords($domain->providerConnection(), $domain->external_domain, $add, $delete, ($request->user() ?? abort(401)));
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('domain.flash.dns_modified'));
    }

    /**
     * @return list<array{type: string, name: string, ttl: int|null, priority: int|null, content: string}>
     */
    private function validatedRecords(Request $request, string $key = 'records'): array {
        /** @var array<int, mixed> $raw */
        $raw = (array) $request->input($key, []);
        $records = [];
        foreach ($raw as $row) {
            if (! is_array($row) || trim((string) ($row['name'] ?? '')) === '') {
                continue;
            }
            $records[] = [
                'type' => (string) ($row['type'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'ttl' => isset($row['ttl']) && $row['ttl'] !== '' ? (int) $row['ttl'] : null,
                'priority' => isset($row['priority']) && $row['priority'] !== '' ? (int) $row['priority'] : null,
                'content' => (string) ($row['content'] ?? ''),
            ];
        }

        return $records;
    }
}
