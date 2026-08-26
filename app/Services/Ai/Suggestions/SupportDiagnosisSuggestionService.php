<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportDiagnosisSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Models\Ai\AiTextSuggestion;
use App\Models\{Organization, User};
use App\Services\Ai\AiInvocationService;
use App\Services\Ai\Dto\{AiTextResult, ExplainRequest};
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Suggestions\Concerns\DecidesSuggestions;
use App\Services\Support\SupportHealthSummary;
use Illuminate\Support\Str;

/**
 * KI-Welle 2 — Support-Diagnose erklären (Feature 148, MVP-732; Feature 041):
 * der Health-Block des Supportberichts wird in eine verständliche Erklärung
 * mit Reihenfolge der Prüfschritte übersetzt.
 *
 * Das ist der Cloud-Pilot der KI-Landkarte, deshalb ist der Datenfluss hier
 * am strengsten: es gehen NUR Fakten mit einem Schlüssel aus
 * {@see self::ALLOWED_CHECKS} in den Prompt (unbekannte Checks fallen
 * komplett weg), und jeder Detailtext wird zusätzlich redigiert
 * ({@see self::redact()}) — keine Pfade, keine Hosts/URLs, keine
 * E-Mail-Adressen, keine IPs, keine Schlüssel. Kundendaten erreichen die
 * Capability konstruktionsbedingt nicht.
 */
class SupportDiagnosisSuggestionService {
    use DecidesSuggestions;

    public const CAPABILITY = 'support.diagnose_explain';

    /**
     * Whitelist der Health-Checks aus {@see \App\Console\Commands\SystemHealthCommand}.
     * Alles, was hier nicht steht, verlässt die Installation nicht.
     *
     * @var list<string>
     */
    public const ALLOWED_CHECKS = [
        'Datenbank',
        'Migrationen',
        'Storage',
        'Queue',
        'APP_KEY',
        'Mail',
        'Lizenz',
        'Backup',
        'Restore-Test',
    ];

    public function __construct(
        private readonly AiInvocationService $invocation,
        private readonly SupportHealthSummary $health,
    ) {}

    /** Erklärt den aktuellen Health-Block des Supportberichts. */
    public function explain(Organization $organization, ?User $user, ?int $connectionId = null): AiTextSuggestion {
        $summary = $this->health->collect();
        if ($summary['available'] !== true) {
            throw new AiException((string) __('ai.error.support_health_unavailable'));
        }

        $facts = self::factsFor($summary);

        $request = new ExplainRequest(
            facts: $facts,
            question: (string) __('ai.support.question'),
            language: app()->getLocale(),
        );

        $result = $this->invocation->invoke($organization, self::CAPABILITY, $request, $connectionId);
        $payload = $result->result;
        if (! $payload instanceof AiTextResult) {
            throw new AiException((string) __('ai.error.unexpected_result_type'));
        }

        return $this->storeProposal(
            (int) $organization->id,
            $organization,
            self::CAPABILITY,
            (string) __('ai.support.source_hint', ['count' => $summary['failed_count']]),
            $payload->text,
            $result,
            $user,
        );
    }

    /**
     * Faktenliste aus der Health-Zusammenfassung — Whitelist + Redaktion.
     *
     * @param  array{available: bool, healthy: bool|null, version: string|null, environment: string|null, checks: list<array{name: string, ok: bool, details: string}>, failed_count: int, error?: string}  $summary
     * @return array<string, scalar|null>
     */
    public static function factsFor(array $summary): array {
        $facts = [
            'version' => $summary['version'] !== null ? self::redact((string) $summary['version']) : null,
            'umgebung' => $summary['environment'] !== null ? self::redact((string) $summary['environment']) : null,
            'gesamtstatus_gesund' => $summary['healthy'],
            'fehlgeschlagene_checks' => $summary['failed_count'],
        ];

        foreach ($summary['checks'] as $check) {
            $name = $check['name'];
            if (! in_array($name, self::ALLOWED_CHECKS, true)) {
                continue; // Unbekannter Check → gar nichts senden.
            }
            $facts['check_' . Str::slug($name, '_')] = $check['ok']
                ? 'ok'
                : 'fehler: ' . self::redact($check['details']);
        }

        return $facts;
    }

    /**
     * Entfernt alles, was eine Installation oder Person identifizieren
     * könnte: E-Mail-Adressen, IPs, URLs/Hosts, absolute Pfade und lange
     * Schlüssel/Hashes. Reihenfolge ist bewusst spezifisch → generisch.
     */
    public static function redact(string $value): string {
        $patterns = [
            '/[\w.+-]+@[\w-]+\.[\w.-]+/u' => '[mail]',
            '#\b[a-z][a-z0-9+.-]*://\S+#iu' => '[url]',
            '/\b\d{1,3}(?:\.\d{1,3}){3}\b/u' => '[ip]',
            '/\b(?:[0-9a-f]{2}:){5}[0-9a-f]{2}\b/iu' => '[mac]',
            '#\b[A-Za-z]:\\\\[^\s"\']*#u' => '[pfad]',
            '#(?<![\w.])/(?:[\w.-]+/){1,}[\w.-]*#u' => '[pfad]',
            '/\b[0-9a-f]{32,}\b/iu' => '[hash]',
            '/\b[A-Za-z0-9_\-]{40,}\b/u' => '[token]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value) ?? $value;
        }

        return Str::limit(trim($value), 200);
    }
}
