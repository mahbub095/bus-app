<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\RedirectsToAdminTab;

/**
 * Base for admin panel controllers (Blade forms + tab redirects).
 */
abstract class BaseAdminController extends Controller
{
    use RedirectsToAdminTab;
}
