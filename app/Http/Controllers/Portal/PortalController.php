<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;

class PortalController extends Controller
{
    /**
     * Display the portal home page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('portal.home');
    }
}