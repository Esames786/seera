<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function index(Request $request): View
    {
        $shifts = Shift::withCount('assignments')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return view('admin.hr.shifts.index', [
            'shifts' => $shifts,
            'totalShifts' => Shift::count(),
            'activeShifts' => Shift::where('status', 'active')->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.hr.shifts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $shift = Shift::create($this->validated($request));

        ActivityLog::record($request, 'HR', 'Created shift', $shift->name);

        return redirect()->route('admin.hr.shifts.index')
            ->with('status', 'Shift "'.$shift->name.'" created successfully.');
    }

    public function edit(Shift $shift): View
    {
        return view('admin.hr.shifts.edit', ['shift' => $shift]);
    }

    public function update(Request $request, Shift $shift): RedirectResponse
    {
        $shift->update($this->validated($request, $shift));

        ActivityLog::record($request, 'HR', 'Updated shift', $shift->name);

        return redirect()->route('admin.hr.shifts.index')
            ->with('status', 'Shift "'.$shift->name.'" updated successfully.');
    }

    public function destroy(Request $request, Shift $shift): RedirectResponse
    {
        if ($shift->attendanceRecords()->exists()) {
            return back()->withErrors(['shift' => 'This shift is already used by attendance records.']);
        }

        $name = $shift->name;
        $shift->delete();

        ActivityLog::record($request, 'HR', 'Deleted shift', $name);

        return redirect()->route('admin.hr.shifts.index')
            ->with('status', 'Shift "'.$name.'" deleted successfully.');
    }

    private function validated(Request $request, ?Shift $shift = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'unique:shifts,code'.($shift ? ','.$shift->id : '')],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'overtime_after_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
