<?php

namespace App\Http\Controllers\Tenant;

use App\Models\HelpArticle;
use Illuminate\Http\Request;
use App\Services\NotificationService;
use App\Services\Tenant\UserAccessService;
use Illuminate\Support\Facades\Auth;

class HelpController extends Controller
{
    protected NotificationService $notificationService;
    protected UserAccessService $userAccessService;
    // Use trait to handle
    use \App\Traits\LogsUserActivity;

    public function __construct(
        NotificationService $notificationService, UserAccessService $userAccessService)
    {
        // only super-user can manage
        $this->middleware(['auth', 'role:super-user'])->only(['create', 'store', 'edit', 'update', 'destroy']);

        $this->notificationService = $notificationService;
        $this->userAccessService = $userAccessService;
    }
  
  /**
  * Display a listing of the help articles.
  */
  public function index()
  {
    $articles = HelpArticle::where('is_active', true)
    ->orderBy('updated_at', 'desc')
    ->get();
    return view('help.index', compact('articles'));
  }
  
  /**
  * Show the form for creating a new help article.
  */
  public function create()
  {
    return view('help.create');
  }
  
  /**
  * Store a newly created help article in storage.
  */
  public function store(Request $request)
  {
    try {
      $request->validate([
        'title' => 'required|string|max:255',
        'slug' => 'required|string|max:255|unique:help_articles,slug',
        'content' => 'required|string',
        'is_active' => 'required|boolean',
      ]);
      $article = HelpArticle::create([
        'title' => $request->title,
        'slug' => $request->slug,
        'content' => $request->content,
        'is_active' => $request->is_active,
        'created_by' => Auth::id(),
      ]);

      // log activity and create notification
      $this->logUserActivityAndNotification(
          'create',
          'help_articles',
          $article->id,
          'Created Help Article: ' . $article->title,
          'Help Article "' . $article->title . '" has been created successfully.'
      );
      return redirect()->route('master-data.help.index')->with('success', 'Help article created successfully.');
    } catch (\Exception $e) {
      return redirect()->back()->withErrors('Failed to create help article.');
    }
  }
  
  /**
  * Show the form for editing the specified help article.
  */
  public function edit($id)
  {
    try {
      $article = HelpArticle::findOrFail($id);
      return view('help.edit', compact('article'));
    } catch (\Exception $e) {
      return redirect()->back()->withErrors('Failed to retrieve help article.');
    }
  }
  
  /**
  * Update the specified help article in storage.
  */
  public function update(Request $request, $id)
  {
    try {
      $article = HelpArticle::findOrFail($id);
      $request->validate([
        'title' => 'required|string|max:255',
        'slug' => 'required|string|max:255|unique:help_articles,slug,' . $article->id,
        'content' => 'required|string',
        'is_active' => 'required|boolean',
      ]);
      $article->update([
        'title' => $request->title,
        'slug' => $request->slug,
        'content' => $request->content,
        'is_active' => $request->is_active,
      ]);
      // log activity and create notification
      $this->logUserActivityAndNotification(
          'update',
          'help_articles',
          $article->id,
          'Updated Help Article: ' . $article->title,
          'Help Article "' . $article->title . '" has been updated successfully.'
      );
      return redirect()->route('master-data.help.index')->with('success', 'Help article updated successfully.');
    } catch (\Exception $e) {
      return redirect()->back()->withErrors('Failed to update help article.');
    }
  }
  
  /**
  * Remove the specified help article from storage.
  */
  public function destroy($id)
  {
    try {
      $article = HelpArticle::findOrFail($id);
      $article->delete();
      
      // log activity and create notification
      $this->logUserActivityAndNotification(
        'delete',
        'help_articles',
        $article->id,
        'Deleted Help Article: ' . $article->title,
        'Help Article "' . $article->title . '" has been deleted successfully.'
      );

      return redirect()->route('master-data.help.index')->with('success', 'Help article deleted successfully.');
    } catch (\Exception $e) {
      return redirect()->back()->withErrors('Failed to delete help article.');
    }
  }
}
