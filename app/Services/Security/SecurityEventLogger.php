<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityEventLogger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Security;

use App\Enums\Security\SecurityEventType;
use CommonToolkit\Generators\CEF\CEFGenerator;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Support\Facades\{Log, Request};

/**
 * Zentrales Security-Event-Log (Feature 096, MVP-443): schreibt jede
 * sicherheitsrelevante Fehlauth als EINE Zeile in den `security`-Kanal —
 * Format-Vertrag mit den fail2ban-Filtern:
 *
 *   [2026-07-21 14:32:11] security.WARNING: auth.failed ip=203.0.113.7 user="…"
 *
 * `ip=` steht immer an erster Kontext-Position (fail2ban-<HOST>-Anker),
 * Werte sind einzeilig und gedeckelt. Optional per SECURITY_LOG_SYSLOG
 * zusätzlich an Syslog (SIEM-Anbindung). Ein Logging-Fehler darf nie den
 * Request brechen.
 */
class SecurityEventLogger {
    /** @param array<string, scalar|null> $context */
    public function log(SecurityEventType $type, array $context = []): void {
        try {
            Log::stack($this->channels())->log($type->level(), $this->line($type, $context));
        } catch (\Throwable $e) {
            Log::warning('security_log_failed', ['event' => $type->value, 'error' => $e->getMessage()]);
        }

        $this->siem($type, $context);

        // MVP-445 hängt hier die selektive Persistenz + Schwellwertzählung an.
        $this->persist($type, $context);

        // Temporäre IP-Sperre (MVP-450, Standard aus); lazy aufgelöst — der Dienst loggt selbst hierher.
        try {
            app(IpBanService::class)->record($type, (string) ($context['ip'] ?? Request::ip() ?? ''));
        } catch (\Throwable $e) {
            Log::warning('security_ip_ban_failed', ['event' => $type->value, 'error' => $e->getMessage()]);
        }
    }

    /** @return list<string> */
    private function channels(): array {
        return (bool) config('logging.security_syslog', false)
            ? ['security', 'syslog']
            : ['security'];
    }

    /** @param array<string, scalar|null> $context */
    private function line(SecurityEventType $type, array $context): string {
        $ip = (string) ($context['ip'] ?? Request::ip() ?? '-');
        unset($context['ip']);

        $parts = [$type->value, 'ip=' . ($ip !== '' ? $ip : '-')];
        foreach ($context as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = $key . '="' . $this->sanitize((string) $value) . '"';
        }

        return implode(' ', $parts);
    }

    /**
     * Strukturierte Zeile für ein SIEM (MVP-452): CEF mit `src`/`suser`/
     * `requestClientApplication`, übrige Angaben in `msg`; oder JSON. Aus,
     * solange `SECURITY_SIEM_FORMAT` nicht gesetzt ist.
     *
     * @param array<string, scalar|null> $context
     */
    private function siem(SecurityEventType $type, array $context): void {
        $format = (string) config('logging.security_siem_format', 'off');
        if (! in_array($format, ['cef', 'json'], true)) {
            return;
        }

        try {
            $ip = (string) ($context['ip'] ?? Request::ip() ?? '');
            $rest = array_filter(array_diff_key($context, ['ip' => true]), static fn ($value): bool => $value !== null && $value !== '');
            if ($format === 'json') {
                $line = JsonHelper::encode(['time' => now()->toIso8601String(), 'event' => $type->value, 'severity' => $type->cefSeverity(), 'ip' => $ip] + $rest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $known = ['user' => 'suser', 'ua' => 'requestClientApplication'];
                $extensions = ['src' => $ip];
                $message = [];
                foreach ($rest as $key => $value) {
                    if (isset($known[$key])) {
                        $extensions[$known[$key]] = $this->sanitize((string) $value);
                    } else {
                        $message[] = $key . '=' . $this->sanitize((string) $value);
                    }
                }
                $extensions['msg'] = implode('; ', $message);
                $line = CEFGenerator::line('WorkDiary', 'WorkDiary', (string) config('app.version', ''), $type->value, $type->value, $type->cefSeverity(), $extensions);
            }
            Log::channel('security_siem')->log($type->level(), $line);
        } catch (\Throwable $e) {
            Log::warning('security_siem_failed', ['event' => $type->value, 'error' => $e->getMessage()]);
        }
    }

    /** Einzeiligkeit + Anker-Integrität der Log-Zeile erzwingen. */
    private function sanitize(string $value): string {
        $value = str_replace(["\r", "\n", '"'], [' ', ' ', "'"], $value);

        return mb_substr($value, 0, 200);
    }

    /** @param array<string, scalar|null> $context */
    private function persist(SecurityEventType $type, array $context): void {
        // MVP-445: security_events-Zeile + Schwellwert-Auswertung — bewusst
        // getrennt, damit das Datei-Log (fail2ban) nie von der DB abhängt.
        if (! $type->persist()) {
            return;
        }

        try {
            SecurityEventRecorder::record($type, $context);
        } catch (\Throwable $e) {
            Log::warning('security_event_persist_failed', ['event' => $type->value, 'error' => $e->getMessage()]);
        }
    }
}
