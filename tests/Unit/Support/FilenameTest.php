<?php
/*
 * Created on   : Thu Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FilenameTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Support;

use App\Support\Filename;
use PHPUnit\Framework\TestCase;

class FilenameTest extends TestCase {
    public function test_strips_directory_components(): void {
        $this->assertSame('passwd', Filename::sanitize('../../etc/passwd'));
        $this->assertSame('report.pdf', Filename::sanitize('/var/tmp/report.pdf'));
    }

    public function test_replaces_control_chars_and_backslashes(): void {
        $this->assertSame('a_b_c.txt', Filename::sanitize("a\x00b\\c.txt"));
        $this->assertSame('tab_name.csv', Filename::sanitize("tab\tname.csv"));
    }

    public function test_keeps_umlauts_spaces_and_dots(): void {
        $this->assertSame('Bericht Müller & Söhne (final)..pdf', Filename::sanitize('Bericht Müller & Söhne (final)..pdf'));
    }

    public function test_limits_to_255_characters(): void {
        $name = str_repeat('ä', 300) . '.txt';

        $this->assertSame(255, mb_strlen(Filename::sanitize($name)));
    }
}
