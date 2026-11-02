<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ControllerHelperCopyRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate MVP-1391/1398 (Konsolidierungs-Audit 2026-10-23, B1): Controller
 * kopieren keine Querschnitts-Helfer mehr. Es gibt je einen Baustein:
 * `authUser()` (Basis-Controller), `portalUser()` (ResolvesPortalUser),
 * `admin()`/`authorizeBilling()` (AuthorizesOrganizationManagement),
 * `currentOrganization*()`/`assertSameOrganization()`
 * (ResolvesCurrentOrganization). Gemeldet werden Methoden, deren Rumpf nur
 * den angemeldeten Nutzer auflöst oder nur die Organisation eines
 * Datensatzes vergleicht — eigene Rechteprüfungen bleiben erlaubt.
 *
 * Konsolidierungs-Audit 2026-11-02 (kon-12, kon-13): auch die Inline-Form
 * zählt — `abort_unless(… ->canManageBilling() …, 403)` statt
 * `authorizeBilling()` und „Kunde des Portalkontos“ mit eigenem Abbruch
 * statt `portalCustomer()` (nur in Portal-Controllern).
 */
class ControllerHelperCopyRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Pfad → Begründung */
    private const ALLOW_LIST = [
    ];

    private const METHOD = '/^    (?:private|protected|public) function (\w+)\(([^)]*)\): (\??[\\\\\w]+) \{\n(.*?)^    \}$/sm';

    private const RESOLVER = '~^\$(\w+) = (?:Auth::user\(\)|auth\(\)->user\(\)|Auth::guard\(\'customer\'\)->user\(\)); '
        . '(?:abort_(?:unless|if)\((?:\$\1 instanceof User|\$\1->customer_id === null)(?:, \d+)?\); )?'
        . 'return \$\1(?: instanceof User \? \$\1 : (?:null|abort\(\d+\)))?;$~';

    private const SAME_ORG = '~^abort_unless\((?:\(int\) )?\$\w+->organization_id === (?:\(int\) )?[^,;]+, \d+\);$~';

    /** @var array<string, string> Baustein → Inline-Kopie im Quelltext (unabhängig vom Methodenrumpf) */
    private const INLINE = [
        'authorizeBilling()' => '~abort_unless\((?:Auth::user\(\)|auth\(\)->user\(\)|\$request->user\(\)|\$\w+)\??->canManageBilling\(\)(?: \?\? false)?, 403\);~',
        'portalCustomer()' => '~\$\w+ = \$\w+->customer;\s*abort_(?:if|unless)\(\$\w+ (?:=== null|instanceof Customer)[^;]*\);'
            . '|\$\w+->customer \?\? abort\(\d+\)'
            . '|\$\w+->customer instanceof Customer \? \$\w+->customer : abort\(\d+\)~',
    ];

    public function test_controllers_use_the_shared_helpers_instead_of_copies(): void {
        $violations = [];
        $files = array_merge($this->phpFiles('app/Http/Controllers'), $this->phpFiles('app/Plugins'));

        foreach ($files as $file) {
            $relative = $this->relativePath($file);
            if (! str_contains(basename($relative), 'Controller') || str_contains($relative, '/Concerns/')
                || $relative === 'app/Http/Controllers/Controller.php' || $this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }

            $source = $this->stripComments((string) file_get_contents($file));
            preg_match_all(self::METHOD, $source, $methods, PREG_SET_ORDER);
            foreach ($methods as [, $name, $params, $return, $body]) {
                $body = trim((string) preg_replace('/\s+/', ' ', $body));
                $copy = match (true) {
                    in_array($return, ['User', '?User'], true) && $params === '' && preg_match(self::RESOLVER, $body) === 1 => 'authUser()/portalUser()',
                    $return === 'void' && preg_match('/^[\w\\\\]+ \$\w+$/', $params) === 1 && preg_match(self::SAME_ORG, $body) === 1 => 'assertSameOrganization()',
                    default => null,
                };
                if ($copy !== null) {
                    $violations[] = sprintf('%s — %s() statt %s', $relative, $name, $copy);
                }
            }
            foreach (self::INLINE as $helper => $pattern) {
                if ($helper === 'portalCustomer()' && ! str_contains($relative, 'Portal/')) {
                    continue;
                }
                if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) === 1) {
                    $violations[] = sprintf('%s:%d — %s statt %s', $relative, $this->lineOf($source, (int) $match[0][1]), trim((string) preg_replace('/\s+/', ' ', $match[0][0])), $helper);
                }
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Kopierter Controller-Helfer — den gemeinsamen Baustein nutzen:\n\n" . implode("\n", $violations));
    }

    public function test_the_patterns_recognize_the_former_copies(): void {
        $resolver = static fn (string $body): bool => preg_match(self::RESOLVER, $body) === 1;

        $this->assertTrue($resolver('$user = Auth::user(); return $user;'));
        $this->assertTrue($resolver('$user = Auth::user(); abort_unless($user instanceof User, 403); return $user;'));
        $this->assertTrue($resolver('$user = Auth::user(); return $user instanceof User ? $user : null;'));
        $this->assertTrue($resolver('$user = Auth::user(); return $user instanceof User ? $user : abort(401);'));
        $this->assertTrue($resolver("\$user = Auth::guard('customer')->user(); abort_if(\$user->customer_id === null, 404); return \$user;"));
        $this->assertFalse($resolver('$user = Auth::user(); abort_unless($user->isAdmin(), 403); return $user;'));

        $this->assertSame(1, preg_match(self::SAME_ORG, 'abort_unless((int) $rule->organization_id === (int) $this->currentOrganizationOrAbort()->id, 404);'));
        $this->assertSame(1, preg_match(self::SAME_ORG, 'abort_unless($survey->organization_id === $this->orgId(), 404);'));
        $this->assertSame(0, preg_match(self::SAME_ORG, 'abort_unless($item->isOpen(), 422);'));

        $billing = static fn (string $source): bool => preg_match(self::INLINE['authorizeBilling()'], $source) === 1;
        $this->assertTrue($billing('abort_unless(Auth::user()?->canManageBilling() ?? false, 403);'));
        $this->assertTrue($billing('abort_unless($request->user()?->canManageBilling() ?? false, 403);'));
        $this->assertTrue($billing('abort_unless($actor->canManageBilling(), 403);'));
        $this->assertFalse($billing("abort_unless(\$admin->canManageBilling() && \$modules->isActiveFor(\$organization, 'module.finance'), 403);"), 'eigene Kombination mit Modulprüfung');
        $this->assertFalse($billing("'canManage' => \$user->canManageBilling(),"));

        $portal = static fn (string $source): bool => preg_match(self::INLINE['portalCustomer()'], $source) === 1;
        $this->assertTrue($portal('$customer = $user->customer; abort_if($customer === null, 404);'));
        $this->assertTrue($portal("\$customer = \$user->customer;\n        abort_unless(\$customer instanceof Customer && (int) \$customer->organization_id === (int) \$user->organization_id, 403);"));
        $this->assertTrue($portal('$customer = $user->customer ?? abort(404);'));
        $this->assertTrue($portal('return $user instanceof User && $user->customer instanceof Customer ? $user->customer : abort(403);'));
        $this->assertFalse($portal('$assets = $user->customer !== null ? $this->visibility->assetsFor($user->customer) : collect();'), 'optionaler Kunde ohne Abbruch');
    }
}
