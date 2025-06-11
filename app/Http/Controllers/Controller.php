<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Traits\HasApiResponses;

/**
 * @psalm-suppress UnusedClass
 */
abstract class Controller
{
    use HasApiResponses;
}
