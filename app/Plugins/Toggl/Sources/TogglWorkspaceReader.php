<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglWorkspaceReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Sources;

/**
 * Liest einen einzelnen Toggl-Workspace-Export-Ordner ein, wie ihn der
 * „Export workspace data"-Download erzeugt:
 *
 *   <workspace>/
 *     clients.json            [{id, name, archived, ...}]
 *     projects.json           [{id, name, client_id, client_name, color, billable, active, status, start_date, ...}]
 *     workspace_users.json    [{id, email, name, timezone, role, ...}]
 *     Toggl_time_entries_YYYY-...csv   (Detailbericht-Spalten)
 *
 * Liefert die Stammdaten als assoziative Arrays und die Zeiteinträge bereits
 * als normalisierte {@see TogglEntry}-DTOs (über den {@see TogglCsvParser}).
 */
class TogglWorkspaceReader {
    public function __construct(private readonly TogglCsvParser $csvParser = new TogglCsvParser) {}

    /**
     * Unterordner des Basis-Pfads, die wie ein Workspace-Export aussehen (enthalten projects.json).
     *
     * @return array<int, string>
     */
    public static function detectWorkspaces(string $basePath): array {
        $basePath = rtrim($basePath, '/');
        if (! is_dir($basePath)) {
            return [];
        }

        $items = scandir($basePath);
        if ($items === false) {
            return [];
        }

        $names = [];
        foreach ($items as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $dir = $basePath . '/' . $entry;
            if (is_dir($dir) && is_file($dir . '/projects.json')) {
                $names[] = $entry;
            }
        }
        sort($names);

        return $names;
    }

    /**
     * Toggl-Clients des Workspaces.
     *
     * @return array<int, array{id: int, name: string, archived: bool}>
     */
    public function clients(string $workspacePath): array {
        $out = [];
        foreach ($this->json($workspacePath . '/clients.json') as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => $name,
                'archived' => (bool) ($row['archived'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * Toggl-Projekte des Workspaces (inkl. Client-Bezug und Metadaten).
     *
     * @return array<int, array{id: int, name: string, client_id: ?int, client_name: ?string, color: ?string, billable: bool, active: bool, start_date: ?string}>
     */
    public function projects(string $workspacePath): array {
        $out = [];
        foreach ($this->json($workspacePath . '/projects.json') as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $clientName = isset($row['client_name']) ? trim((string) $row['client_name']) : '';
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => $name,
                'client_id' => isset($row['client_id']) ? (int) $row['client_id'] : null,
                'client_name' => $clientName !== '' ? $clientName : null,
                'color' => isset($row['color']) && trim((string) $row['color']) !== '' ? trim((string) $row['color']) : null,
                'billable' => (bool) ($row['billable'] ?? false),
                'active' => (bool) ($row['active'] ?? true),
                'start_date' => isset($row['start_date']) ? (string) $row['start_date'] : null,
            ];
        }

        return $out;
    }

    /**
     * Workspace-Benutzer (für die Benutzer-Zuordnung per E-Mail).
     *
     * @return array<int, array{email: string, name: string, timezone: ?string}>
     */
    public function users(string $workspacePath): array {
        $out = [];
        foreach ($this->json($workspacePath . '/workspace_users.json') as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $out[] = [
                'email' => $email,
                'name' => trim((string) ($row['name'] ?? $email)),
                'timezone' => isset($row['timezone']) && trim((string) $row['timezone']) !== '' ? trim((string) $row['timezone']) : null,
            ];
        }

        return $out;
    }

    /**
     * Alle Zeiteinträge des Workspaces aus den Jahres-CSVs, normalisiert.
     *
     * @return array<int, TogglEntry>
     */
    public function entries(string $workspacePath): array {
        $files = glob($workspacePath . '/Toggl_time_entries_*.csv') ?: [];
        sort($files);

        $entries = [];
        foreach ($files as $file) {
            $content = (string) file_get_contents($file);
            if (trim($content) === '') {
                continue;
            }
            foreach ($this->csvParser->parse($content) as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function json(string $path): array {
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return \is_array($decoded) ? $decoded : [];
    }
}
