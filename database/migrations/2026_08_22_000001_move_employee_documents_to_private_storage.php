<?php

use App\Models\EmployeeDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        EmployeeDocument::whereNotNull('file_path')->orderBy('id')->each(function (EmployeeDocument $document) {
            $path = $document->file_path;
            if (Storage::disk('local')->exists($path) || ! Storage::disk('public')->exists($path)) {
                return;
            }

            $contents = Storage::disk('public')->get($path);
            if (Storage::disk('local')->put($path, $contents)) {
                Storage::disk('public')->delete($path);
            }
        });
    }

    public function down(): void
    {
        // Sensitive HR documents deliberately remain private on rollback.
    }
};
