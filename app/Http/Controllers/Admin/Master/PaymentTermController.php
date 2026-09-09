<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PaymentTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Supplier payment terms (CR-17). Normally created from the "+ New" dialog on
 * the supplier form; this small screen is the maintenance list.
 */
class PaymentTermController extends Controller
{
    public function index(): View
    {
        return view('admin.master.payment-terms.index', [
            'terms' => PaymentTerm::withCount('suppliers')->orderBy('days')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $term = PaymentTerm::create($this->validated($request));

        ActivityLog::record($request, 'Suppliers', 'Created payment term', $term->label());

        if ($request->wantsJson()) {
            return response()->json(['id' => $term->id, 'label' => $term->label(), 'days' => $term->days], 201);
        }

        return redirect()->route('admin.master.payment-terms.index')
            ->with('status', 'Payment term "'.$term->label().'" created.');
    }

    public function update(Request $request, PaymentTerm $payment_term): RedirectResponse
    {
        $payment_term->update($this->validated($request, $payment_term));

        // Suppliers keep the readable name in step with the term.
        $payment_term->suppliers()->update(['payment_terms' => $payment_term->name]);

        ActivityLog::record($request, 'Suppliers', 'Updated payment term', $payment_term->label());

        return redirect()->route('admin.master.payment-terms.index')
            ->with('status', 'Payment term "'.$payment_term->label().'" updated.');
    }

    public function destroy(Request $request, PaymentTerm $payment_term): RedirectResponse
    {
        if ($payment_term->suppliers()->exists()) {
            return back()->withErrors(['term' => 'Suppliers still use this payment term. Mark it inactive instead.']);
        }

        $label = $payment_term->label();
        $payment_term->delete();

        ActivityLog::record($request, 'Suppliers', 'Deleted payment term', $label);

        return redirect()->route('admin.master.payment-terms.index')
            ->with('status', 'Payment term "'.$label.'" deleted.');
    }

    private function validated(Request $request, ?PaymentTerm $term = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:payment_terms,name'.($term ? ','.$term->id : '')],
            'days' => ['required', 'integer', 'min:0', 'max:365'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ], [
            'name.unique' => 'That payment term already exists; pick it from the list.',
            'days.max' => 'Payment terms longer than a year are not supported.',
        ]);

        $data['status'] = $data['status'] ?? 'active';

        return $data;
    }
}
