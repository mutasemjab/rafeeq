<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlansController extends Controller
{
    public function index()
    {
        $plans = Plan::orderBy('price')->paginate($this->paginationCount());
        return view('admin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.plans.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                    => 'required|string|max:100',
            'type'                    => 'required|in:free,pro',
            'billing_period'          => 'required|in:monthly,yearly,lifetime',
            'price'                   => 'required|numeric|min:0',
            'currency'                => 'required|string|max:10',
            'ai_messages_per_day'     => 'nullable|integer|min:1',
            'max_children'            => 'nullable|integer|min:1',
            'max_documents_per_child' => 'nullable|integer|min:1',
            'has_specialist_access'   => 'boolean',
            'has_voice_mode'          => 'boolean',
            'has_progress_reports'    => 'boolean',
            'is_active'               => 'boolean',
        ]);
        $data['slug']                  = Str::slug($data['name']) . '-' . Str::random(4);
        $data['has_specialist_access'] = $request->boolean('has_specialist_access');
        $data['has_voice_mode']        = $request->boolean('has_voice_mode');
        $data['has_progress_reports']  = $request->boolean('has_progress_reports');
        $data['is_active']             = $request->boolean('is_active');
        Plan::create($data);
        return redirect()->route('admin.plans.index')->with('success', 'Plan created.');
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $request->validate([
            'name'                    => 'required|string|max:100',
            'type'                    => 'required|in:free,pro',
            'billing_period'          => 'required|in:monthly,yearly,lifetime',
            'price'                   => 'required|numeric|min:0',
            'currency'                => 'required|string|max:10',
            'ai_messages_per_day'     => 'nullable|integer|min:1',
            'max_children'            => 'nullable|integer|min:1',
            'max_documents_per_child' => 'nullable|integer|min:1',
            'has_specialist_access'   => 'boolean',
            'has_voice_mode'          => 'boolean',
            'has_progress_reports'    => 'boolean',
            'is_active'               => 'boolean',
        ]);
        $data['has_specialist_access'] = $request->boolean('has_specialist_access');
        $data['has_voice_mode']        = $request->boolean('has_voice_mode');
        $data['has_progress_reports']  = $request->boolean('has_progress_reports');
        $data['is_active']             = $request->boolean('is_active');
        $plan->update($data);
        return redirect()->route('admin.plans.index')->with('success', 'Plan updated.');
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();
        return back()->with('success', 'Plan deleted.');
    }
}
