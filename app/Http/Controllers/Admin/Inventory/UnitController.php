<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(Request $request): View
    {
        $units = Unit::withCount('items')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return view('admin.inventory.units.index', [
            'units' => $units,
            'totalUnits' => Unit::count(),
            'activeUnits' => Unit::where('status', 'active')->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.inventory.units.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $unit = Unit::create($this->validated($request));

        ActivityLog::record($request, 'Inventory', 'Created unit', $unit->code.' - '.$unit->name);

        return redirect()->route('admin.inventory.units.index')
            ->with('status', 'Unit "'.$unit->name.'" created successfully.');
    }

    public function edit(Unit $unit): View
    {
        return view('admin.inventory.units.edit', ['unit' => $unit]);
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $unit->update($this->validated($request, $unit));

        ActivityLog::record($request, 'Inventory', 'Updated unit', $unit->code.' - '.$unit->name);

        return redirect()->route('admin.inventory.units.index')
            ->with('status', 'Unit "'.$unit->name.'" updated successfully.');
    }

    public function destroy(Request $request, Unit $unit): RedirectResponse
    {
        if ($unit->items()->exists()) {
            return back()->withErrors(['unit' => 'This unit is already used by items.']);
        }

        $label = $unit->code.' - '.$unit->name;
        $unit->delete();

        ActivityLog::record($request, 'Inventory', 'Deleted unit', $label);

        return redirect()->route('admin.inventory.units.index')
            ->with('status', 'Unit "'.$unit->name.'" deleted successfully.');
    }

    private function validated(Request $request, ?Unit $unit = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:units,code'.($unit ? ','.$unit->id : '')],
            'name' => ['required', 'string', 'max:100'],
            'allows_decimal' => ['nullable', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $data['allows_decimal'] = $request->boolean('allows_decimal');

        return $data;
    }
}
