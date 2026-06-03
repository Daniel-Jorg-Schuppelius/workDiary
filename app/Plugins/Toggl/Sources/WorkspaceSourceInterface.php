<?php
/*
 * Created on   : Tue Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkspaceSourceInterface.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Sources;

/**
 * Quellen-Abstraktion für genau einen Toggl-Workspace. Entkoppelt den
 * {@see \App\Plugins\Toggl\TogglExportImporter} von der konkreten Herkunft der
 * Daten: ein Workspace-Export-Ordner ({@see FolderWorkspaceSource}) oder die
 * Toggl-API ({@see ApiWorkspaceSource}). Beide Implementierungen liefern die
 * Stammdaten im identischen Format wie der {@see TogglWorkspaceReader}.
 */
interface WorkspaceSourceInterface {
    /**
     * Toggl-Clients des Workspaces.
     *
     * @return array<int, array{id: int, name: string, archived: bool}>
     */
    public function clients(): array;

    /**
     * Toggl-Projekte des Workspaces (inkl. Client-Bezug und Metadaten).
     *
     * @return array<int, array{id: int, name: string, client_id: ?int, client_name: ?string, color: ?string, billable: bool, active: bool, start_date: ?string}>
     */
    public function projects(): array;

    /**
     * Workspace-Benutzer (für die Benutzer-Zuordnung per E-Mail).
     *
     * @return array<int, array{email: string, name: string, timezone: ?string}>
     */
    public function users(): array;

    /**
     * Alle Zeiteinträge des Workspaces, normalisiert.
     *
     * @return array<int, TogglEntry>
     */
    public function entries(): array;
}
