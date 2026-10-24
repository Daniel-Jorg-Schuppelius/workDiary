<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeSeafileServer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Support;

use GuzzleHttp\Psr7\Response as Psr7Response;
use Psr\Http\Message\RequestInterface;

/**
 * Kleine Seafile-Instanz für Tests: Bibliotheken mit Dateien und Ordnern,
 * Datei- und Ordner-IDs als Inhalts-Hash (wie Seafile), `oid`/„uptodate“,
 * Down- und Upload-Links des Dateiservers, Anlegen und Löschen.
 */
trait FakeSeafileServer {
    /** @var array<string, array{name: string, encrypted: bool, permission?: string, own?: bool}> */
    protected array $seafileRepos = [];

    /** @var array<string, array<string, array{content: string, mtime: int}>> Bibliothek → Pfad → Datei */
    protected array $seafileFiles = [];

    /** @var array<string, array<string, true>> Bibliothek → ausdrücklich angelegte Ordner */
    protected array $seafileDirs = [];

    protected function seafileServe(string $base, string $email = 'erika@example.test'): FakePluginHttp {
        return FakePluginHttp::fake([
            $base . '/*' => fn (RequestInterface $request): Psr7Response => $this->seafileRespond($base, $email, $request),
        ]);
    }

    protected function seafileFile(string $repo, string $path, string $content, int $mtime = 1_760_000_000): void {
        $this->seafileFiles[$repo]['/' . trim($path, '/')] = ['content' => $content, 'mtime' => $mtime];
    }

    private function seafileRespond(string $base, string $email, RequestInterface $request): Psr7Response {
        $path = substr($request->getUri()->getPath(), strlen((string) parse_url($base, PHP_URL_PATH)));
        parse_str($request->getUri()->getQuery(), $query);
        $p = '/' . trim((string) ($query['p'] ?? '/'), '/');
        $method = $request->getMethod();

        if ($path === '/api2/account/info/') {
            return FakePluginHttp::response(['email' => $email, 'contact_email' => 'kontakt@example.test', 'name' => 'Erika Muster', 'total' => -2, 'usage' => 1234]);
        }
        if ($path === '/api2/server-info/') {
            return FakePluginHttp::response(['version' => '11.0.12']);
        }
        if ($path === '/api2/repos/') {
            $rows = [];
            foreach ($this->seafileRepos as $id => $repo) {
                // Gruppenfreigaben listet Seafile zusätzlich — dieselbe ID erscheint ein zweites Mal, nach den eigenen.
                $first = ['id' => $id, 'name' => $repo['name'], 'encrypted' => $repo['encrypted'], 'permission' => $repo['permission'] ?? 'rw'];
                $rows[] = $first + ['type' => ($repo['own'] ?? true) ? 'repo' : 'srepo'];
                $rows[] = $first + ['type' => 'grepo'];
            }

            return FakePluginHttp::response($rows);
        }
        if (preg_match('#^/seafhttp/files/([A-Za-z0-9_-]+)/#', $path, $m) === 1) {
            [$repo, $file] = explode('|', (string) base64_decode(strtr($m[1], '-_', '+/')), 2);

            return isset($this->seafileFiles[$repo][$file])
                ? new Psr7Response(200, ['Content-Type' => 'application/octet-stream'], $this->seafileFiles[$repo][$file]['content'])
                : FakePluginHttp::response(['error' => 'not found'], 404);
        }
        if (preg_match('#^/seafhttp/upload-api/([0-9a-f-]{36})$#', $path, $m) === 1 && $method === 'POST') {
            return $this->seafileUpload($m[1], $request);
        }
        if (preg_match('#^/api/v2\.1/repos/([0-9a-f-]{36})/file/$#', $path, $m) === 1 && $method === 'DELETE') {
            // Dokumentierte Dateilöschung: fehlende Datei → 200, nur fehlende Bibliothek → 404.
            if (! isset($this->seafileRepos[$m[1]])) {
                return FakePluginHttp::response(['error_msg' => 'Library not found.'], 404);
            }
            unset($this->seafileFiles[$m[1]][$p]);

            return FakePluginHttp::response(['success' => true]);
        }
        if (preg_match('#^/api2/repos/([0-9a-f-]{36})(/.*)$#', $path, $m) !== 1 || ! isset($this->seafileRepos[$m[1]])) {
            return FakePluginHttp::response(['error_msg' => 'Library not found.'], 404);
        }
        [$repo, $action] = [$m[1], $m[2]];

        return match (true) {
            $action === '/' && $method === 'GET' => FakePluginHttp::response(['id' => $repo, 'name' => $this->seafileRepos[$repo]['name']]),
            $action === '/dir/' && $method === 'GET' => $this->seafileListing($repo, $p, $query),
            $action === '/dir/' && $method === 'POST' => $this->seafileMkdir($repo, $p, $request),
            $action === '/dir/' && $method === 'DELETE' => $this->seafileDelete($repo, $p, true),
            $action === '/file/' => isset($this->seafileFiles[$repo][$p])
                ? FakePluginHttp::response(json_encode($base . '/seafhttp/files/' . rtrim(strtr(base64_encode($repo . '|' . $p), '+/', '-_'), '=') . '/' . rawurlencode(basename($p))) ?: '', 200, ['Content-Type' => 'application/json'])
                : FakePluginHttp::response(['error_msg' => 'File not found'], 404),
            $action === '/file/detail/' => isset($this->seafileFiles[$repo][$p])
                ? FakePluginHttp::response(['name' => basename($p), 'size' => strlen($this->seafileFiles[$repo][$p]['content'])])
                : FakePluginHttp::response(['error_msg' => 'File not found'], 404),
            $action === '/upload-link/' => $this->seafileIsDir($repo, $p)
                ? FakePluginHttp::response(json_encode($base . '/seafhttp/upload-api/' . $repo) ?: '', 200, ['Content-Type' => 'application/json'])
                : FakePluginHttp::response(['error_msg' => 'Folder not found'], 404),
            default => FakePluginHttp::response(['error_msg' => 'unexpected'], 400),
        };
    }

    /** @param array<string, mixed> $query */
    private function seafileListing(string $repo, string $dir, array $query): Psr7Response {
        if (! $this->seafileIsDir($repo, $dir)) {
            return FakePluginHttp::response(['error_msg' => 'Folder not found'], 404);
        }
        $oid = $this->seafileDirId($repo, $dir);
        if (($query['oid'] ?? null) === $oid) {
            return FakePluginHttp::response(json_encode('uptodate') ?: '', 200, ['Content-Type' => 'application/json', 'oid' => $oid]);
        }
        $entries = [];
        foreach ($this->seafileChildren($repo, $dir) as $name => $child) {
            $entries[] = $child === null
                ? ['type' => 'dir', 'name' => (string) $name, 'id' => $this->seafileDirId($repo, rtrim($dir, '/') . '/' . $name), 'mtime' => 1_760_000_000]
                : ['type' => 'file', 'name' => (string) $name, 'id' => self::seafileFileId($child['content']), 'mtime' => $child['mtime'], 'size' => strlen($child['content'])];
        }

        return FakePluginHttp::response($entries, 200, ['oid' => $oid]);
    }

    private function seafileMkdir(string $repo, string $dir, RequestInterface $request): Psr7Response {
        parse_str((string) $request->getBody(), $form);
        $parent = dirname($dir);
        if (($form['create_parents'] ?? '') === 'true') {
            $accumulated = '';
            foreach (array_filter(explode('/', $dir)) as $segment) {
                $accumulated .= '/' . $segment;
                $this->seafileDirs[$repo][$accumulated] = true;
            }
        } elseif (! $this->seafileIsDir($repo, $parent === '\\' ? '/' : $parent)) {
            return FakePluginHttp::response(['error_msg' => 'Parent folder not found'], 404);
        }
        $this->seafileDirs[$repo][$dir] = true;

        return FakePluginHttp::response('"success"', 201, ['Content-Type' => 'application/json']);
    }

    private function seafileDelete(string $repo, string $target, bool $dir): Psr7Response {
        if ($dir && ! $this->seafileIsDir($repo, $target)) {
            return FakePluginHttp::response(['error_msg' => 'Folder ' . $target . ' not found.'], 404);
        }
        $found = false;
        foreach (array_keys($this->seafileFiles[$repo] ?? []) as $file) {
            if ($dir ? str_starts_with($file, rtrim($target, '/') . '/') : $file === $target) {
                unset($this->seafileFiles[$repo][$file]);
                $found = true;
            }
        }
        foreach (array_keys($this->seafileDirs[$repo] ?? []) as $folder) {
            if ($dir && ($folder === $target || str_starts_with($folder, rtrim($target, '/') . '/'))) {
                unset($this->seafileDirs[$repo][$folder]);
                $found = true;
            }
        }

        return $found ? FakePluginHttp::response('"success"', 200, ['Content-Type' => 'application/json']) : FakePluginHttp::response(['error_msg' => 'not found'], 404);
    }

    /** Multipart des Upload-Links: parent_dir, replace und die Datei. */
    private function seafileUpload(string $repo, RequestInterface $request): Psr7Response {
        preg_match('/boundary=("?)([^";]+)\1/', $request->getHeaderLine('Content-Type'), $b);
        $fields = [];
        $fileName = null;
        foreach (explode('--' . ($b[2] ?? ''), (string) $request->getBody()) as $part) {
            if (preg_match('/name="([^"]+)"(?:; filename="([^"]*)")?.*?\r\n\r\n(.*)\r\n$/s', $part, $m) === 1) {
                $fields[$m[1]] = $m[3];
                if ($m[1] === 'file') {
                    $fileName = $m[2];
                }
            }
        }
        $parent = '/' . trim((string) ($fields['parent_dir'] ?? ''), '/');
        if ($fileName === null || ! $this->seafileIsDir($repo, $parent)) {
            return FakePluginHttp::response(['error' => 'invalid'], 400);
        }
        $this->seafileFile($repo, rtrim($parent, '/') . '/' . $fileName, (string) ($fields['file'] ?? ''));

        return FakePluginHttp::response([['name' => $fileName, 'id' => sha1((string) ($fields['file'] ?? '')), 'size' => strlen((string) ($fields['file'] ?? ''))]]);
    }

    private function seafileIsDir(string $repo, string $dir): bool {
        $dir = '/' . trim($dir, '/');
        if ($dir === '/' || isset($this->seafileDirs[$repo][$dir])) {
            return true;
        }
        foreach (array_keys($this->seafileFiles[$repo] ?? []) as $file) {
            if (str_starts_with($file, $dir . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Direkte Kinder: Name → Datei oder null für Ordner.
     *
     * @return array<string, array{content: string, mtime: int}|null>
     */
    private function seafileChildren(string $repo, string $dir): array {
        $prefix = rtrim('/' . trim($dir, '/'), '/') . '/';
        $children = [];
        $paths = array_merge(array_keys($this->seafileFiles[$repo] ?? []), array_keys($this->seafileDirs[$repo] ?? []));
        foreach ($paths as $path) {
            if (! str_starts_with($path, $prefix) || $path === rtrim($prefix, '/')) {
                continue;
            }
            $rest = substr($path, strlen($prefix));
            $first = explode('/', $rest)[0];
            $children[$first] = str_contains($rest, '/') || isset($this->seafileDirs[$repo][$prefix . $first]) ? null : $this->seafileFiles[$repo][$path];
        }
        ksort($children);

        return $children;
    }

    /** Seafile gibt allen leeren Dateien dieselbe ID aus Nullen. */
    protected static function seafileFileId(string $content): string {
        return $content === '' ? str_repeat('0', 40) : sha1($content);
    }

    /** Ordner-ID als Hash über den Teilbaum — ändert sich, sobald sich darunter etwas ändert. */
    private function seafileDirId(string $repo, string $dir): string {
        $prefix = rtrim('/' . trim($dir, '/'), '/') . '/';
        $tree = [];
        foreach ($this->seafileFiles[$repo] ?? [] as $path => $file) {
            if (str_starts_with($path, $prefix)) {
                $tree[$path] = sha1($file['content']);
            }
        }
        foreach (array_keys($this->seafileDirs[$repo] ?? []) as $folder) {
            if (str_starts_with($folder, $prefix)) {
                $tree[$folder] = 'dir';
            }
        }
        ksort($tree);

        return sha1((string) json_encode($tree));
    }
}
