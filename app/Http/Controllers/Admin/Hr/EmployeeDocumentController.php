<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $documents = EmployeeDocument::with('employee')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('document_number', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($e) => $e->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")));
            })
            ->when($request->filled('employee'), fn ($q) => $q->where('employee_id', $request->integer('employee')))
            ->when($request->filled('type'), fn ($q) => $q->where('document_type', $request->string('type')))
            ->when($request->filled('validity'), function ($query) use ($request) {
                $today = now()->startOfDay();
                match ($request->string('validity')->toString()) {
                    'expired' => $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', $today),
                    'expiring' => $query->whereNotNull('expiry_date')
                        ->whereDate('expiry_date', '>=', $today)
                        ->whereDate('expiry_date', '<=', $today->copy()->addDays(60)),
                    'valid' => $query->whereNotNull('expiry_date')->whereDate('expiry_date', '>', $today->copy()->addDays(60)),
                    default => $query,
                };
            })
            ->orderBy('expiry_date')
            ->paginate(10)
            ->withQueryString();

        $today = now()->startOfDay();

        return view('admin.hr.documents.index', [
            'documents' => $documents,
            'totalDocuments' => EmployeeDocument::count(),
            'expiredDocuments' => EmployeeDocument::whereNotNull('expiry_date')->whereDate('expiry_date', '<', $today)->count(),
            'expiringDocuments' => EmployeeDocument::whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', $today->copy()->addDays(60))
                ->count(),
            'validDocuments' => EmployeeDocument::whereNotNull('expiry_date')->whereDate('expiry_date', '>', $today->copy()->addDays(60))->count(),
        ] + $this->formOptions());
    }

    public function download(EmployeeDocument $document): StreamedResponse
    {
        abort_unless($document->file_path, 404);

        if (Storage::disk('local')->exists($document->file_path)) {
            return Storage::disk('local')->download($document->file_path);
        }

        // Existing deployments may still have files written by the former public-disk workflow.
        abort_unless(Storage::disk('public')->exists($document->file_path), 404);

        return Storage::disk('public')->download($document->file_path);
    }

    private function formOptions(): array
    {
        return [
            'employees' => Employee::orderBy('employee_code')->get(),
            'documentTypes' => EmployeeDocument::TYPES,
        ];
    }
}
