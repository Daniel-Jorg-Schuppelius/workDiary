<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditSecretRedactionRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use SplFileInfo;
use Tests\TestCase;

/**
 * Gate zu S-21 (Sicherheitsscan 2026-08-23).
 *
 * `audit_logs` ist append-only und hash-verkettet: was einmal darin steht,
 * bleibt zehn Jahre stehen und lässt sich nur über den dokumentierten
 * Ausnahmeweg (`audit:redact`) wieder entfernen. Ein Feld, das in der eigenen
 * Tabelle verschlüsselt liegt, darf deshalb nicht als Wert ins Protokoll
 * wandern — sonst hebt das Protokoll die Verschlüsselung wieder auf, und zwar
 * dauerhaft.
 *
 * {@see \App\Models\Concerns\Auditable::auditRedact()} leitet das automatisch
 * ab. Dieser Test hält fest, dass niemand die Ableitung stillschweigend
 * aushebelt — etwa durch ein Override, das die eigenen Geheimfelder vergisst.
 */
class AuditSecretRedactionRuleTest extends TestCase {
    /** Namensmuster für Geheimnisse, die kein Cast als solche ausweist. */
    private const SECRET_PATTERN = '/(_token|_secret|api_key|license_key|private_key|_password)$/';

    public function test_verschluesselte_und_geheime_felder_stehen_nie_als_wert_im_protokoll(): void {
        $violations = [];
        $checked = 0;

        foreach ($this->auditableModels() as $class) {
            try {
                $model = new $class();
            } catch (\Throwable) {
                continue;
            }

            if (! $model instanceof Model) {
                continue;
            }

            $secrets = [];
            foreach ($model->getCasts() as $column => $cast) {
                if (is_string($cast) && str_starts_with($cast, 'encrypted')) {
                    $secrets[] = $column;
                }
            }
            foreach ($model->getFillable() as $column) {
                if (preg_match(self::SECRET_PATTERN, $column) === 1) {
                    $secrets[] = $column;
                }
            }

            if ($secrets === []) {
                continue;
            }

            $checked++;

            $method = new ReflectionMethod($model, 'auditRedact');
            $method->setAccessible(true);
            /** @var array<int, string> $redacted */
            $redacted = $method->invoke($model);

            $open = array_values(array_diff(array_unique($secrets), $redacted, $model->getHidden()));
            if ($open !== []) {
                $violations[] = class_basename($class) . ': ' . implode(', ', $open);
            }
        }

        $this->assertGreaterThan(20, $checked, 'Die Modellsuche hat offenbar nichts gefunden — der Test prüft nichts.');

        $this->assertSame([], $violations, sprintf(
            "Diese Felder landen als Wert im Audit-Protokoll, obwohl sie verschlüsselt oder geheim sind:\n  %s\n"
            . 'Abhilfe: encrypted-Cast setzen (dann greift die Ableitung) oder auditRedact() erweitern.',
            implode("\n  ", $violations),
        ));
    }

    /** @return list<class-string> */
    private function auditableModels(): array {
        $classes = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Models')));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace([app_path('Models') . '/', '/', '.php'], ['', '\\', ''], $file->getPathname());
            $class = 'App\\Models\\' . $relative;

            if (! class_exists($class)) {
                continue;
            }

            // Über die tatsächlich verwendeten Traits statt über den Quelltext:
            // `use Auditable;` steht in vielen Modellen gruppiert in einer
            // Zeile mit anderen Traits (z. B. Asset) und wäre per Textsuche
            // durchgerutscht — genau die Modelle, um die es hier geht.
            if (in_array(Auditable::class, class_uses_recursive($class), true)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
