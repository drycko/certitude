<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
  /**
   * The URIs that should be excluded from CSRF verification.
   *
   * @var array<int, string>
   */
  protected $except = [
    'tg/paygate/*',
    'tg/payfast/*',
    '*/tg/paygate/*',
    '*/tg/payfast/*',
    'http://*/tg/paygate/*',
    'http://*/tg/payfast/*',
    'https://*/tg/paygate/*',
    'https://*/tg/payfast/*',
    // Central/Portal PayFast routes
    'p/payfast/*',
    '*/p/payfast/*',
    'http://*/p/payfast/*',
    'https://*/p/payfast/*',
  ];
}