<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DockerArtifactsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate für die Container-Distribution (Vollscan 2026-08-23, G9 /
 * MVP-720): Die Docker-Artefakte müssen vorhanden sein, die Pflicht-ENV der
 * App (APP_KEY, APP_URL, DB_*) in .env.docker.example auftauchen, jeder
 * Schlüssel der Vorlage einen Leser haben, der Entrypoint darf keine
 * org-editierbaren Kataloge seeden (J3) und keinen view:cache bauen
 * (Projektregel), und der Queue-Worker-Timeout muss unter retry_after liegen.
 */
class DockerArtifactsTest extends TestCase {
    use ScansSourceTree;

    private const ARTIFACTS = [
        'Dockerfile',
        '.dockerignore',
        'compose.yml',
        '.env.docker.example',
        'deploy/docker/nginx.conf',
        'deploy/docker/entrypoint.sh',
        'deploy/docker/healthcheck.sh',
        'deploy/docker/php.ini',
        'deploy/docker/php-fpm.conf',
        'docs/on-premise-docker.md',
    ];

    /** Pflicht-ENV mit Betriebscharakter (Heuristik): ohne sie startet kein Container sinnvoll. */
    private const REQUIRED_KEY_PATTERN = '/^(APP_KEY|APP_URL|APP_ENV|DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)$/';

    /** @var array<string, string> Schlüssel → wo er außerhalb von config/ gelesen wird */
    private const READ_ELSEWHERE = [
        'ERROR_TOOLKIT_LOG_CHANNEL' => 'vendor/dschuppelius/php-error-toolkit/config/error-toolkit.php',
        'TZ' => 'Betriebssystem/Container (tzdata)',
        'MARIADB_DATABASE' => 'compose.yml db-Service (mariadb-Image)',
        'MARIADB_USER' => 'compose.yml db-Service (mariadb-Image)',
        'MARIADB_PASSWORD' => 'compose.yml db-Service (mariadb-Image)',
        'MARIADB_ROOT_PASSWORD' => 'compose.yml db-Service (mariadb-Image)',
        'WD_AUTO_MIGRATE' => 'deploy/docker/entrypoint.sh',
        'WD_DB_WAIT_SECONDS' => 'deploy/docker/entrypoint.sh',
        'WD_HTTP_PORT' => 'compose.yml (Port-Mapping web)',
    ];

    public function test_container_artifacts_exist(): void {
        $root = $this->repoRoot();
        $missing = array_values(array_filter(self::ARTIFACTS, static fn (string $path): bool => ! is_file($root . '/' . $path)));

        $this->assertSame([], $missing, "Container-Artefakte fehlen (MVP-720):\n" . implode("\n", $missing));
    }

    public function test_required_env_keys_are_in_docker_example(): void {
        $required = [];
        foreach ($this->phpFiles('config') as $file) {
            preg_match_all('/env\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]/', (string) file_get_contents($file), $found);
            foreach ($found[1] as $key) {
                if (preg_match(self::REQUIRED_KEY_PATTERN, $key) === 1) {
                    $required[$key] = true;
                }
            }
        }
        ksort($required);

        $missing = array_values(array_diff(array_keys($required), $this->dockerExampleKeys()));

        $this->assertSame([], $missing, ".env.docker.example fehlen Pflicht-Schlüssel aus config/*.php:\n" . implode("\n", $missing));
    }

    public function test_every_docker_example_key_has_a_reader(): void {
        $readers = [];
        $files = array_merge($this->phpFiles('config'), $this->filesUnder('app/Plugins', '/^config\.php$/'), $this->phpFiles('bootstrap'));
        foreach ($files as $file) {
            preg_match_all('/env\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]/', (string) file_get_contents($file), $found);
            foreach ($found[1] as $key) {
                $readers[$key] = true;
            }
        }

        $orphans = array_values(array_filter(
            $this->dockerExampleKeys(),
            static fn (string $key): bool => ! isset($readers[$key]) && ! isset(self::READ_ELSEWHERE[$key]),
        ));

        $this->assertSame([], $orphans, ".env.docker.example enthält Schlüssel ohne Leser (falscher Name oder verwaist):\n"
            . implode("\n", $orphans) . "\n\nSchlüssel korrigieren oder — wenn compose/Entrypoint/Paket ihn liest — in READ_ELSEWHERE eintragen.");
    }

    public function test_entrypoint_seeds_no_editable_catalogs_and_no_view_cache(): void {
        $source = $this->stripComments((string) file_get_contents($this->repoRoot() . '/deploy/docker/entrypoint.sh'));

        // J3: `db:seed` ohne --class würde ActivityCategory/EntryType/… über
        // org-Änderungen fahren. Erlaubt ist nur der globale PermissionsSeeder.
        preg_match_all('/db:seed\b[^\n]*/', $source, $seeds);
        foreach ($seeds[0] as $call) {
            $this->assertMatchesRegularExpression('/--class=PermissionsSeeder\b/', $call, "Entrypoint seedet außerhalb des PermissionsSeeders (J3): {$call}");
        }

        $this->assertDoesNotMatchRegularExpression('/\bview:cache\b/', $source, 'Entrypoint darf keinen view:cache bauen (README Produktions-Checkliste: @forelse-Counter-Bug).');
        $this->assertMatchesRegularExpression('/WD_AUTO_MIGRATE/', $source, 'Migrationen müssen Opt-in über WD_AUTO_MIGRATE bleiben.');
        $this->assertMatchesRegularExpression('/migrate --force/', $source);
    }

    public function test_queue_worker_timeout_stays_below_retry_after(): void {
        $compose = (string) file_get_contents($this->repoRoot() . '/compose.yml');
        $this->assertMatchesRegularExpression('/queue:work"?,?\s*"?--tries=3"?,?\s*"?--timeout=(\d+)/', $compose, 'compose.yml: Queue-Worker mit --tries=3 --timeout=… erwartet.');
        preg_match('/--timeout=(\d+)/', $compose, $m);
        $timeout = (int) $m[1];

        $example = (string) file_get_contents($this->repoRoot() . '/.env.docker.example');
        preg_match('/^DB_QUEUE_RETRY_AFTER=(\d+)/m', $example, $r);
        $this->assertNotEmpty($r, '.env.docker.example muss DB_QUEUE_RETRY_AFTER setzen (config/queue.php).');
        $this->assertGreaterThan($timeout, (int) $r[1], 'DB_QUEUE_RETRY_AFTER muss über dem Worker-Timeout liegen, sonst laufen Jobs doppelt.');
    }

    public function test_dockerfile_runs_non_root_with_volumes_and_healthcheck(): void {
        $dockerfile = $this->stripComments((string) file_get_contents($this->repoRoot() . '/Dockerfile'));

        $this->assertMatchesRegularExpression('/^USER www-data\s*$/m', $dockerfile, 'Runtime-Stage muss als www-data laufen (non-root).');
        $this->assertMatchesRegularExpression('/^VOLUME \[.*storage.*bootstrap\/cache.*\]/m', $dockerfile, 'storage und bootstrap/cache müssen als Volumes deklariert sein.');
        $this->assertMatchesRegularExpression('/^HEALTHCHECK /m', $dockerfile);
        $this->assertMatchesRegularExpression('/deploy\/docker\/nginx\.conf/', $dockerfile, 'Der web-Stage muss deploy/docker/nginx.conf verwenden.');
        $this->assertMatchesRegularExpression('/composer install [^\n]*--no-dev/', $dockerfile);
        $this->assertMatchesRegularExpression('/npm ci\b/', $dockerfile);
        $this->assertMatchesRegularExpression('/npm run build\b/', $dockerfile);
    }

    /** @return list<string> */
    private function dockerExampleKeys(): array {
        $example = (string) file_get_contents($this->repoRoot() . '/.env.docker.example');
        preg_match_all('/^([A-Z][A-Z0-9_]*)=/m', $example, $matches);

        return array_values(array_unique($matches[1]));
    }
}
