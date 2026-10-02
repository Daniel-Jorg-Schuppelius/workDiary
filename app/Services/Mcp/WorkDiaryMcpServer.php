<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkDiaryMcpServer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Modules\ModuleRegistry;
use App\Services\Mcp\Contracts\McpTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\{Instructions, Name, Version};
use Laravel\Mcp\Server\Tool;

/**
 * MCP-Server für KI-Assistenten (MVP-1063): Werkzeuge kommen aus den
 * Manifesten ({@see McpTool}); Schreiben legt nur Entwürfe an (MVP-1064),
 * Ausstellen und Versenden bleiben in workDiary.
 */
#[Name('workDiary')]
#[Version('1.0.0')]
#[Instructions('Zugriff auf die Daten einer Organisation in workDiary: Kunden, Projekte, Aufträge, Angebote, Rechnungen, offene Posten und Zeiten, Termine und Kennzahlen. Kennungen sind kurze Zeichenketten (Sqids). Beträge sind Dezimalzahlen mit Punkt. Schreibende Werkzeuge legen nur Entwürfe an, die ein Mensch in workDiary prüft und ausstellt.')]
class WorkDiaryMcpServer extends Server {
    protected function boot(): void {
        $tools = [];
        foreach (app(ModuleRegistry::class)->extensions(McpTool::class) as $class) {
            if (is_a($class, Tool::class, true)) {
                $tools[] = $class;
            }
        }
        $this->tools = $tools;
    }
}
