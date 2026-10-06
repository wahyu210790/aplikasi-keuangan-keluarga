<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base controller class for the application.
 *
 * It includes the AuthorizesRequests trait which provides the `$this->authorize()`
 * helper used by policies (e.g., in `HouseholdController`).
 */
abstract class Controller
{
    use AuthorizesRequests;
    // You may add other shared controller utilities here.
}
