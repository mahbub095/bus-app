<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsToAdminTab;

abstract class Controller
{
    use RedirectsToAdminTab;
}
