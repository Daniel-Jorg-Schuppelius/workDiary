<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Plugins\PluginManager;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PluginController extends Controller
{
    public function index(Request $request, PluginManager $manager): View
    {
        abort_unless((bool) $request->user()?->isAdmin(), 403);

        return view('admin.plugins.index', [
            'plugins' => $manager->all(),
        ]);
    }
}
