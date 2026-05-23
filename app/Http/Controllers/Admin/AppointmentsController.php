<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;

class AppointmentsController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $appointments = Appointment::with(['user', 'specialist'])
            ->when($search, fn($q) => $q->where('booking_reference', 'like', "%{$search}%"))
            ->when($status, fn($q) => $q->where('status', $status))
            ->latest()->paginate(PAGINATION_COUNT);
        return view('admin.appointments.index', compact('appointments', 'search', 'status'));
    }

    public function show(Appointment $appointment)
    {
        $appointment->load('user', 'specialist', 'child', 'review');
        return view('admin.appointments.show', compact('appointment'));
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $data = $request->validate(['status' => 'required|in:pending_payment,confirmed,upcoming,completed,canceled,missed']);
        $appointment->update(['status' => $data['status']]);
        return back()->with('success', 'Status updated.');
    }
}
