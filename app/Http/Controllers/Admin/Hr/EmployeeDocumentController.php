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
        return $this->stream($document, 'attachment');
    }

    /**
     * The same file shown in the browser instead of downloaded, so the user can
     * confirm the right document is attached (client feedback FR-03). Access
     * still goes through this authenticated route; the files stay private.
     */
    public function view(EmployeeDocument $document): StreamedResponse
    {
        return $this->stream($document, 'inline');
    }

    private function stream(EmployeeDocument $document, string $disposition): StreamedResponse
    {
        abort_unless($document->file_path, 404);

        // Existing deployments may still have files written by the former public-disk workflow.
        $disk = Storage::disk('local')->exists($document->file_path) ? 'local' : 'public';
        abort_unless(Storage::disk($disk)->exists($document->file_path), 404);

        $name = $document->document_type.' - '.basename($document->file_path);

        return $disposition === 'inline'
            ? Storage::disk($disk)->response($document->file_path, $name, ['Content-Disposition' => 'inline; filename="'.$name.'"'])
            : Storage::disk($disk)->download($document->file_path, $name);
    }

    private function formOptions(): array
    {
        return [
            'employees' => Employee::orderBy('employee_code')->get(),
            'documentTypes' => EmployeeDocument::types(),
        ];
    }
}
