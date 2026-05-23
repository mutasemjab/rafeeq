<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionsController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $subscriptions = Subscription::with(['user', 'plan'])
            ->when($search, fn($q) => $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->latest()->paginate(PAGINATION_COUNT);
        return view('admin.subscriptions.index', compact('subscriptions', 'search'));
    }

    public function updateStatus(Request $request, Subscription $subscription)
    {
        $data = $request->validate(['status' => 'required|in:active,canceled,expired,trialing']);
        $subscription->update(['status' => $data['status']]);
        return back()->with('success', 'Subscription updated.');
    }
}
