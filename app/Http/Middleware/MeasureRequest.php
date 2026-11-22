<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeasureRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Platform\Organization;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};
use Symfony\Component\HttpFoundation\Response;

/**
 * Misst Dauer, Abfragezahl und Datenbankzeit einer Anfrage. Langsame Anfragen
 * landen im Log (Route statt URL, keine Nutzerdaten); auf Wunsch trägt die
 * Antwort `Server-Timing`.
 */
class MeasureRequest {
    private const string TALLY = 'performance.tally';

    public function handle(Request $request, Closure $next): Response {
        $threshold = (int) config('performance.slow_request_ms', 0);
        $timing = (bool) config('performance.server_timing', false);
        if ($threshold <= 0 && ! $timing) {
            return $next($request);
        }

        $tally = $this->tally();
        $queries = $tally['count'];
        $queryMs = $tally['ms'];
        $start = (float) $request->server('REQUEST_TIME_FLOAT', microtime(true));

        $response = $next($request);

        $ms = (microtime(true) - $start) * 1000;
        $queries = $tally['count'] - $queries;
        $queryMs = $tally['ms'] - $queryMs;

        if ($timing && $request->user() !== null) {
            $response->headers->set('Server-Timing', sprintf('app;dur=%.1f, db;dur=%.1f;desc="%d"', $ms, $queryMs, $queries));
        }

        if ($threshold > 0 && $ms >= $threshold) {
            $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
            $route = $request->route();
            $channel = config('performance.log_channel');
            $log = is_string($channel) && $channel !== '' ? Log::channel($channel) : Log::getFacadeRoot();
            $log->warning('Langsame Anfrage', [
                'method' => $request->getMethod(),
                'route' => $route?->getName() ?? $route?->uri(),
                'status' => $response->getStatusCode(),
                'ms' => (int) round($ms),
                'queries' => $queries,
                'query_ms' => (int) round($queryMs),
                'memory_mb' => (int) round(memory_get_peak_usage(true) / 1048576),
                'organization_id' => $organization instanceof Organization ? $organization->id : null,
            ]);
        }

        return $response;
    }

    /**
     * Ein Zähler je Anwendungsinstanz, damit mehrere Anfragen (Tests, Worker) nicht je einen Listener anmelden.
     *
     * @return \ArrayObject<string, int|float>
     */
    private function tally(): \ArrayObject {
        if (app()->bound(self::TALLY)) {
            return app(self::TALLY);
        }

        /** @var \ArrayObject<string, int|float> $tally */
        $tally = new \ArrayObject(['count' => 0, 'ms' => 0.0]);
        app()->instance(self::TALLY, $tally);
        DB::listen(static function (QueryExecuted $query) use ($tally): void {
            $tally['count']++;
            $tally['ms'] += (float) $query->time;
        });

        return $tally;
    }
}
