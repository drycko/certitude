<?php

namespace App\Services\Tenant;

use App\Models\Tenant\User;
use App\Models\Tenant\SummaryLink;
use App\Models\Tenant\SummaryLinkType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SummaryService
{
  /**
  * Get Summary links accessible to user based on roles and groups
  */
  public function getAccessibleSummaryLinks(User $user): Builder
  {
    $query = SummaryLink::where('is_active', true);
    
    // Admin and super-user roles can see all (active or not)
    if ($user->hasRole(['admin', 'super-admin', 'super-user'])) {
      return $query = SummaryLink::query();
    }
    // If user has 'manage all powerbi reports' permission, return all
    // if ($user->hasAnyPermission(['manage all powerbi reports', 'manage powerbi reports'])) {
    //   return $query;
    // }
    
    
    $query->where(function (Builder $q) use ($user) {
      
      // Grower-specific access
      if ($user->growers && $user->growers->isNotEmpty() && $user->hasAnyPermission('view powerbi reports')) {
        $growerIds = $user->growers->pluck('id')->toArray();
        $q->orWhereIn('grower_id', $growerIds);
      }
      // // Reports accessible by summary link type through user groups
      // if ($user->hasAnyPermission('view powerbi reports')) {
      //   $userGroups = $user->activeUserGroups;
      //   // Collect all summary link type IDs from user's groups
      //   $summaryLinkTypeIds = $userGroups
      //   ->flatMap(fn($group) => $group->summaryLinkTypes->pluck('id'))
      //   ->unique()
      //   ->toArray();
        
      //   if (!empty($summaryLinkTypeIds)) {
      //     $q->orWhereHas('summaryLinkType', function (Builder $cq) use ($summaryLinkTypeIds) {
      //       $cq->whereIn('id', $summaryLinkTypeIds);
      //     });
      //   }
      // }
      
      // // Report by user groups
      // $this->addSummaryGroupBasedAccess($q, $user);
      
      // Reports uploaded by user
      if ($user->hasAnyPermission('view powerbi reports')) {
        $q->orWhere('added_by', $user->id);
      }
    });
    
    return $query;
  }
  
  /**
  * Add group-based summary report access to query
  */
  private function addSummaryGroupBasedAccess(Builder $query, User $user): void
  {
    // Assuming $user->activeUserGroups returns groups user belongs to
    foreach ($user->activeUserGroups as $group) {
      // Get permissions for this group via pivot table
      $groupPermissions = $group->permissions->pluck('name')->toArray(); // using Spatie
      
      // Check if group has summary report related permissions
      $summaryReportPermissions = array_filter($groupPermissions, function ($permission) {
        return str_contains($permission, 'summary report');
      });
      
      if (!empty($summaryReportPermissions)) {
        // Example: restrict by summary link type
        // You might add more logic here based on your business rules
        $query->orWhere(function (Builder $sq) use ($summaryReportPermissions) {
          $sq->whereHas('summaryLinkType', function (Builder $cq) use ($summaryReportPermissions) {
            $cq->whereIn('name', $summaryReportPermissions);
          });
        });
      }
    }
  }
  
  /**
  * Get filtered summary links based on search criteria
  */
  public function getFilteredSummaryLinks(User $user, array $filters = []): Builder
  {
    $query = $this->getAccessibleSummaryLinks($user);
    
    // Apply search filters
    if (!empty($filters['search'])) {
      $search = $filters['search'];
      $query->where(function ($q) use ($search) {
        $q->where('name', 'like', "%{$search}%")
        ->orWhere('description', 'like', "%{$search}%");
      });
    }
    
    if (isset($filters['summary_link_type_id'])) {
      $query->where('summary_link_type_id', $filters['summary_link_type_id']);
    }
    
    if (isset($filters['link_source'])) {
      $query->where('link_source', $filters['link_source']);
    }
    
    // Sorting
    $sortBy = $filters['sort_by'] ?? 'sort_order';
    $sortDirection = $filters['sort_direction'] ?? 'asc';
    
    $query->orderBy($sortBy, $sortDirection);
    
    return $query;
  }

  /**
   * Get filtered trashed summary links based on search criteria
   */
  public function getFilteredTrashedSummaryLinks(array $filters = []): Builder
  {
    $query = SummaryLink::onlyTrashed();
    
    // Apply search filters
    if (!empty($filters['search'])) {
      $search = $filters['search'];
      $query->where(function ($q) use ($search) {
        $q->where('name', 'like', "%{$search}%")
        ->orWhere('description', 'like', "%{$search}%");
      });
    }
    
    if (isset($filters['summary_link_type_id'])) {
      $query->where('summary_link_type_id', $filters['summary_link_type_id']);
    }
    
    if (isset($filters['link_source'])) {
      $query->where('link_source', $filters['link_source']);
    }
    
    // Sorting
    $sortBy = $filters['sort_by'] ?? 'deleted_at';
    $sortDirection = $filters['sort_direction'] ?? 'desc';
    
    $query->orderBy($sortBy, $sortDirection);
    
    return $query;
  }
  
  /**
  * Get Summary links accessible by user (with filters and sorting)
  */
  public function getLinksForUser(User $user, array $filters = [], array $sort = []): Collection
  {
    $query = SummaryLink::where('is_active', true);
    // ->where(function ($query) use ($user) {
    //   // Company-specific links
    //   if ($user->company_id) {
    //     $query->where('company_id', $user->company_id);
    //   }
    //   // Global links (no company restriction)
    //   $query->orWhereNull('company_id');

    // });
    
    // Further restrict by Spatie permissions
    $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();
    if (!in_array('view powerbi reports', $userPermissions)) {
      // User lacks permission to view any powerbi reports
      return collect();
    }

    // Example: Only allow links of types the user has permission for, or based on their groups
    $userGroups = $user->activeUserGroups;
    // Collect all summary link type IDs from user's groups
    // $summaryLinkTypeIds = $userGroups->summaryLinkTypes->pluck('id')->toArray();
    $summaryLinkTypeIds = $userGroups
      ->flatMap(fn($group) => $group->summaryLinkTypes->pluck('id'))
      ->unique()
      ->toArray(); // CORRECT
    if (!empty($summaryLinkTypeIds)) {
      $query->whereIn('summary_link_type_id', $summaryLinkTypeIds);
    }
    
    // Optionally, include group-based access logic
    $this->addSummaryGroupBasedAccess($query, $user);
    
    // Apply filters
    if (!empty($filters)) {
      foreach ($filters as $field => $value) {
        $query->where($field, $value);
      }
    }
    
    // Apply sorting
    if (!empty($sort)) {
      foreach ($sort as $field => $direction) {
        $query->orderBy($field, $direction);
      }
    } else {
      $query->orderBy('sort_order')->orderBy('name');
    }
    
    return $query->get();
  }
  
  /**
  * Get embed URL with filters for user
  */
  public function getEmbedUrl(SummaryLink $link, User $user): string
  {
    $embedUrl = $link->url;
    
    // Add user-specific filters based on role
    if ($user->isGrowerRole() && $user->grower_number) {
      $embedUrl = $this->addUrlParameter($embedUrl, 'grower', $user->grower_number);
    }
    
    if ($user->isCustomerRole() || $user->isGrowerRole()) {
      // Add commodity filters
      $commodityCodes = $user->commodities->pluck('code')->toArray();
      if (!empty($commodityCodes)) {
        $embedUrl = $this->addUrlParameter($embedUrl, 'commodities', implode(',', $commodityCodes));
      }
    }
    
    if ($user->isDoleRole()) {
      // Default filter for Dole users - Quality Reports
      $embedUrl = $this->addUrlParameter($embedUrl, 'filter', 'quality_reports');
    }
    
    return $embedUrl;
  }
  
  /**
  * Generate obfuscated link ID
  */
  public function generateObfuscatedId(SummaryLink $link): string
  {
    // Avoid exposing app key
    return base64_encode($link->id . ':' . $link->created_at->timestamp . ':' . \Illuminate\Support\Str::random(8));
  }
  
  /**
  * Decode obfuscated link ID
  */
  public function decodeObfuscatedId(string $obfuscatedId): ?int
  {
    try {
      $decoded = base64_decode($obfuscatedId);
      $parts = explode(':', $decoded);
      
      if (count($parts) >= 2) {
        return (int) $parts[0];
      }
    } catch (\Throwable $e) {
      // Invalid obfuscated ID
    }
    
    return null;
  }
  
  /**
  * Check if user can access Summary link
  * (Uses user groups and Spatie permissions, not role_access field)
  */
  public function canUserAccessLink(User $user, SummaryLink $link): bool
  {
    // Link must be active when user is not creator
    if (!$link->is_active && $link->created_by !== $user->id) {
      return false;
    }
    
    // Company access: if link is global OR matches user's company
    $hasCompanyAccess = is_null($link->company_id) || $link->company_id === $user->company_id;
    
    // Permission access: user must have permission to view powerbi reports
    $hasPermission = $user->hasPermissionTo('view powerbi reports');
    
    // Group access: check if any of user's active groups grant access to this link type
    $hasGroupAccess = false;
    if (method_exists($user, 'activeUserGroups')) {
      foreach ($user->activeUserGroups as $group) {
        // Check permissions for the group (assuming Spatie relation)
        if ($group->permissions->contains('name', 'view powerbi reports')) {
          // Optionally check for more restrictions, e.g. by link type
          if (
            isset($link->summary_link_type_id) &&
            $group->summaryLinkTypes &&
            $group->summaryLinkTypes->contains('id', $link->summary_link_type_id)
            ) {
              $hasGroupAccess = true;
              break;
            }
            // If no link type filtering, group permission is enough
            $hasGroupAccess = true;
            break;
          }
        }
      }
      
    \Log::info('User ' . $user->id . ' hasPermissionTo view powerbi reports: ' . ($hasPermission ? 'true' : 'false') . ', hasGroupAccess: ' . ($hasGroupAccess ? 'true' : 'false') . ', hasCompanyAccess: ' . ($hasCompanyAccess ? 'true' : 'false'));
      
      // Final access: permission OR group access, and company access
      return ($hasPermission || $hasGroupAccess) || $hasCompanyAccess;
    }
    
    /**
    * Add parameter to URL
    */
    private function addUrlParameter(string $url, string $key, string $value): string
    {
      $separator = strpos($url, '?') !== false ? '&' : '?';
      return $url . $separator . $key . '=' . urlencode($value);
    }
    
    /**
    * Get dashboard configuration for embedding
    */
    public function getDashboardConfig(SummaryLink $link): array
    {
      return [
        'width' => config('powerbi.default_width', '100%'),
        'height' => config('powerbi.default_height', '600px'),
        'enable_filters' => config('powerbi.enable_filters', true),
        'enable_export' => config('powerbi.enable_export', false),
        'enable_print' => config('powerbi.enable_print', false),
      ];
    }
  }