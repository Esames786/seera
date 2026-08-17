<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

    public function create(): View
    {
        return view('admin.hr.documents.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $document = EmployeeDocument::create($this->validated($request));

        ActivityLog::record($request, 'HR', 'Created employee document', $document->document_type.' - '.$document->employee->name);

        return redirect()->route('admin.hr.documents.index')
            ->with('status', 'Document saved successfully.');
    }

    public function edit(EmployeeDocument $document): View
    {
        return view('admin.hr.documents.edit', ['document' => $document] + $this->formOptions());
    }

    public function update(Request $request, EmployeeDocument $document): RedirectResponse
    {
        $document->update($this->validated($request));

        ActivityLog::record($request, 'HR', 'Updated employee document', $document->document_type.' - '.$document->employee->name);

        return redirect()->route('admin.hr.documents.index')
            ->with('status', 'Document updated successfully.');
    }

    public function destroy(Request $request, EmployeeDocument $document): RedirectResponse
    {
        $label = $document->document_type.' - '.$document->employee->name;
        $document->delete();

        ActivityLog::record($request, 'HR', 'Deleted employee document', $label);

        return redirect()->route('admin.hr.documents.index')
            ->with('status', 'Document deleted successfully.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'document_type' => ['required', 'string', 'max:100'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'file' => ['nullable', 'file', 'max:5120'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
        ]);

        unset($data['file']);

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('hr-documents', 'public');
        }

        return $data;
    }

    private function formOptions(): array
    {
        return [
            'employees' => Employee::orderBy('employee_code')->get(),
            'documentTypes' => EmployeeDocument::TYPES,
        ];
    }
}
