<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppendOnlyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Tests\TestCase;

/**
 * Guard-Semantik des AppendOnly-Traits (Konsolidierung A7): UPDATE und
 * DELETE werfen vor jedem DB-Zugriff; die Delete-Ausnahme
 * (appendOnlyAllowsDelete, Retention/Pruning) lässt nur Löschen durch.
 */
final class AppendOnlyTest extends TestCase {
    public function test_updating_throws_with_model_class_in_message(): void {
        $model = new AppendOnlyStrictModel(['name' => 'original']);
        $model->exists = true;
        $model->syncOriginal();
        $model->name = 'manipuliert';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(AppendOnlyStrictModel::class . ' ist append-only und darf nicht geändert werden.');
        $model->save();
    }

    public function test_deleting_throws_with_model_class_in_message(): void {
        $model = new AppendOnlyStrictModel;
        $model->id = 1;
        $model->exists = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(AppendOnlyStrictModel::class . ' ist append-only und darf nicht gelöscht werden.');
        $model->delete();
    }

    public function test_delete_exception_allows_delete_but_still_blocks_update(): void {
        $model = new AppendOnlyPrunableModel;
        $model->id = 1;
        $model->exists = true;

        $this->assertTrue($model->delete());
        $this->assertFalse($model->exists);

        $update = new AppendOnlyPrunableModel(['name' => 'original']);
        $update->exists = true;
        $update->syncOriginal();
        $update->name = 'manipuliert';

        $this->expectException(RuntimeException::class);
        $update->save();
    }
}

/** Testdouble: strikt append-only. */
final class AppendOnlyStrictModel extends Model {
    use AppendOnly;

    protected $table = 'append_only_strict_models';

    protected $guarded = [];
}

/** Testdouble: Delete-Ausnahme (Retention/Pruning). */
final class AppendOnlyPrunableModel extends Model {
    use AppendOnly;

    protected $table = 'append_only_prunable_models';

    protected $guarded = [];

    protected static function appendOnlyAllowsDelete(): bool {
        return true;
    }

    protected function performDeleteOnModel(): void {
        // Kein Schema im Unit-Test: DB-Delete stubben.
        $this->exists = false;
    }
}
