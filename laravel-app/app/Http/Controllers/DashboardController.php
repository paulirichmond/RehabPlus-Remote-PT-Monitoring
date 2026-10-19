<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardOverviewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The home dashboard. Reports & Analytics lives in ReportController. */
class DashboardController extends Controller
{
    public function __construct(private DashboardOverviewService $overview)
    {
    }

    public function index(Request $request): View
    {
        return view('dashboard.index', $this->overview->overview($request->user()));
    }
}
