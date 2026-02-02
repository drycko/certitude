<?php

namespace App\Http\Controllers\Tenant\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Tenant\GrowerUser;
use App\Models\Tenant\Grower;
use App\Models\Tenant\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class GrowerAssignUserController extends Controller
{
  protected NotificationService $notificationService;
  // Log user activities and notifications
  use \App\Traits\LogsUserActivity;
  
  public function __construct(NotificationService $notificationService)
  {
    $this->middleware(['auth', 'permission:assign growers']);
    
    $this->notificationService = $notificationService;
  }
  
  /**
  * Display a listing of the resource.
  */
  public function index(Request $request)
  {
    $query = Grower::with('commodities', 'growerFbos')->orderBy('name');

    // Search functionality
    if ($request->filled('search')) {
        $searchTerm = $request->input('search');
        $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'like', '%' . $searchTerm . '%')
              ->orWhere('grower_number', 'like', '%' . $searchTerm . '%');
        });
    }

    // filter to set the number of items per page
    $filters['per_page'] = $request->input('per_page', 15);
    // if all
    if ($filters['per_page'] == -1) {
        $filters['per_page'] = Grower::count();
        // remove page parameter to avoid issues
        $request->query->remove('page');
    }

    // filter by fbo
    if ($request->filled('fbo_id')) {
        $fboId = $request->input('fbo_id');
        $query->whereHas('growerFbos', function ($q) use ($fboId) {
            $q->where('fbo_id', $fboId);
        });
    }

    // filter by commodity
    if ($request->filled('commodity_id')) {
        $commodityId = $request->input('commodity_id');
        $query->whereHas('commodities', function ($q) use ($commodityId) {
            $q->where('commodity_id', $commodityId);
        });
    }

    $growers = $query->paginate($filters['per_page']);

    // Get filter options
    $fbos = \App\Models\Tenant\Fbo::orderBy('name')->get();
    $commodities = \App\Models\Tenant\Commodity::orderBy('sort_order')->orderBy('name')->get();
    $users = \App\Models\Tenant\User::orderBy('name')->get();
    return view('grower-assign.index', compact('growers', 'users', 'fbos', 'commodities'));
  }
  
  /**
  * Show the form for creating a new resource.
  */
  public function create()
  {
    //
  }
  
  /**
  * Store a newly created resource in storage.
  */
  public function store(Request $request)
  {
    //
  }
  
  /**
  * Display the specified resource.
  */
  public function show(GrowerUser $growerUser)
  {
    //
  }
  
  /**
  * Show the form for editing the specified resource.
  */
  public function edit(Grower $grower)
  {
    $users = \App\Models\Tenant\User::orderBy('name')->get();
    // Show the form for editing the specified resource.
    return view('grower-assign.user-assign', compact('grower', 'users'));
  }
  
  /**
  * Update the specified resource in storage.
  */
  public function update(Request $request, GrowerUser $growerUser)
  {
    //
  }
  
  /**
  * Remove the specified resource from storage.
  */
  public function destroy(GrowerUser $growerUser)
  {
    //
  }
}
