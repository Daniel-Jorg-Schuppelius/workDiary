<?php

namespace Tests\Feature\Diag;

use Tests\TestCase;

class DbProbeTest extends TestCase {
    public function test_probe(): void {
        $default = config('database.default');
        fwrite(STDERR, "\nDBPROBE driver=" . config("database.connections.$default.driver")
            . " connection=" . $default
            . " database=" . config("database.connections.$default.database") . "\n");
        $this->assertTrue(true);
    }
}
