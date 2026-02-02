<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccessRequest;
use App\Models\User;
use App\Models\Company;
use App\Models\Commodity;
use App\Models\Grower;
use App\Models\UserGroup;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Redirect;

class AccessRequestController extends Controller
{
    protected NotificationService $notificationService;
    // Log user activities and notifications
    use \App\Traits\LogsUserActivity;

    public function __construct(NotificationService $notificationService)
    {
        $this->middleware(['auth', 'permission:view users'])->only(['index', 'show']);
        $this->middleware(['auth', 'permission:create users'])->only(['create', 'store']);
        $this->middleware(['auth', 'permission:edit users'])->only(['edit', 'update']);
        $this->middleware(['auth', 'permission:delete users'])->only(['destroy']);
        
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        $query = AccessRequest::orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        $requests = $query->paginate(20)->withQueryString();

        $companies = Company::where('is_active', true)->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();
        $commodities = Commodity::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $growers = Grower::where('is_active', true)->orderBy('name')->get(); // for grower number selection

        return view('users.access_requests.index', compact('requests', 'companies', 'roles', 'commodities', 'growers'));
    }

    public function updateStatus(Request $request, AccessRequest $accessRequest)
    {
        $request->validate(['status' => 'required|in:pending,notified,approved,denied,notify_failed']);

        // Get the page parameter
        $page = $request->input('page', 1);

        try {
            \DB::beginTransaction();

            $newStatus = $request->get('status');

            if ($newStatus === 'approved') {
                $nameParts = explode(' ', $accessRequest->name, 2);
                // Optionally update accessRequest status if you want to track it
                $accessRequest->status = 'approved';
                $accessRequest->save();
                \DB::commit();

                return redirect()->route('users.create')->with('prefill', [
                    'first_name' => $nameParts[0],
                    'last_name' => $nameParts[1] ?? '',
                    'name' => $accessRequest->name,
                    'email' => $accessRequest->email,
                    'company_id' => $accessRequest->company_id, // use company_id if that's what your form expects
                    'company_name' => $accessRequest->company,
                    'phone' => $accessRequest->phone,
                    'created_at' => $accessRequest->created_at->format('Y-m-d'),
                    'role' => 'customer', // default role
                    'access_request_id' => $accessRequest->id, // pass the id!
                    'return_page' => $page, // pass the page to return to after user creation
                ]);
            } else {
                $accessRequest->status = $newStatus;
                $accessRequest->save();

                \DB::commit();
                if ($accessRequest->status === 'denied') {
                    $this->notificationService->sendAccessRequestDenialNotification($accessRequest);
                }
            }
            return Redirect::back()->with('success', 'Access request status updated.');
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Failed to update access request status: ' . $e->getMessage());
            return Redirect::back()->with('error', 'Failed to update access request status.');
        }
    }

    public function updateStatusOld(Request $request, AccessRequest $accessRequest)
    {
        $request->validate(['status' => 'required|in:pending,notified,approved,denied,notify_failed']);
        try {
            // start transaction
            \DB::beginTransaction();

            $newStatus = $request->get('status');

            if ($newStatus === 'approved') {
                // Additional logic for approval will create user account and approve in UserController after redirect
                // split name into first and last
                $nameParts = explode(' ', $accessRequest->name, 2);
                return redirect()->route('users.create')->with('prefill', [
                    'first_name' => $nameParts[0],
                    'last_name' => $nameParts[1] ?? '',
                    'name' => $accessRequest->name,
                    'email' => $accessRequest->email,
                    'company_name' => $accessRequest->company,
                    'phone' => $accessRequest->phone,
                    'created_at' => $accessRequest->created_at->format('Y-m-d'),
                    'role' => 'customer', // default role
                    
                ]);
            } else {
                // Additional logic for denial can be added here
                $accessRequest->status = $newStatus;
                $accessRequest->save();

                \DB::commit();
                if ($accessRequest->status === 'denied') {
                    // Notify user of denial logic here (email service)
                    $this->notificationService->sendAccessRequestDenialEmail($accessRequest);
                }
            }
            return Redirect::back()->with('success', 'Access request status updated.');
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Failed to update access request status: ' . $e->getMessage());
            return Redirect::back()->with('error', 'Failed to update access request status.');
        }

    }
}
