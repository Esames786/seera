<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EquipmentAsset;
use App\Models\EquipmentDocument;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

/** Private uploads share the parent transaction; failed saves remove newly written files. */
class RecordUploads
{
    private array $created = [];

    private array $replaced = [];

    public function transaction(callable $save, int $attempts = 1): mixed
    {
        $this->created = [];
        $this->replaced = [];
        $attempt = 0;
        try {
            return DB::transaction(function () use ($save, &$attempt) {
                // Equipment's existing deadlock retry can re-enter the save closure.
                // Retire only files from its rolled-back attempt, never a previous successful save.
                if ($attempt++ > 0) {
                    Storage::disk('local')->delete($this->created);
                }
                $this->created = [];
                $this->replaced = [];
                $result = $save();
                $old = $this->replaced;
                DB::afterCommit(fn () => Storage::disk('local')->delete($old));

                return $result;
            }, $attempts);
        } catch (Throwable $error) {
            Storage::disk('local')->delete($this->created);
            throw $error;
        }
    }

    public static function validatePhoto(Request $request): void
    {
        $request->validate([
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=6000,max_height=6000'],
            'remove_profile_photo' => ['nullable', 'boolean'],
        ]);
    }

    public function photo(Request $request, Employee|User $owner): void
    {
        if (! $request->hasFile('profile_photo') && ! $request->boolean('remove_profile_photo')) {
            return;
        }
        $owner->refresh(); // The parent write holds its row lock; retire the latest saved image, not a stale route binding.
        $old = $owner->profile_photo_path;
        $path = $request->hasFile('profile_photo') ? $this->store($request->file('profile_photo'), 'profile-photos') : null;
        $owner->forceFill(['profile_photo_path' => $path])->save();
        if ($old) {
            $this->replaced[] = $old;
        }
        ActivityLog::record($request, $owner instanceof Employee ? 'HR' : 'Users', 'Updated profile photo', '['.class_basename($owner).' #'.$owner->id.']');
    }

    public static function validateDocuments(Request $request, bool $equipment = false): void
    {
        $request->validate([
            'new_documents' => ['nullable', 'array', 'max:10'],
            'new_documents.*.file' => ['nullable', 'required_with:new_documents.*.document_number,new_documents.*.issue_date,new_documents.*.expiry_date', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'new_documents.*.document_type' => ['nullable', 'string', 'max:80', ...($equipment ? [Rule::in(array_keys(EquipmentDocument::TYPES))] : [])],
            'new_documents.*.document_number' => ['nullable', 'string', $equipment ? 'max:80' : 'max:100'],
            'new_documents.*.issue_date' => ['nullable', 'date'],
            'new_documents.*.expiry_date' => ['nullable', 'date'],
        ]);
    }

    public function documents(Request $request, Customer|Supplier|EquipmentAsset $owner): void
    {
        $indices = array_unique(array_merge(array_keys($request->input('new_documents', [])), array_keys($request->allFiles()['new_documents'] ?? [])));
        foreach ($indices as $i) {
            $file = $request->file('new_documents.'.$i.'.file');
            if (! $file) {
                continue;
            }
            if ($owner instanceof EquipmentAsset) {
                abort_unless($request->user()->hasPermission('Equipment', 'create'), 403);
            }
            $type = $request->input('new_documents.'.$i.'.document_type') ?: 'other';
            $path = $this->store($file, $owner instanceof EquipmentAsset ? 'equipment-documents' : 'party-documents');
            $owner->documents()->create([
                'document_type' => $type,
                'document_number' => $request->input('new_documents.'.$i.'.document_number'),
                'issue_date' => $request->input('new_documents.'.$i.'.issue_date'),
                'expiry_date' => $request->input('new_documents.'.$i.'.expiry_date'),
                'original_filename' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 191),
                'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $request->user()->id,
            ]);
            ActivityLog::record($request, $owner instanceof EquipmentAsset ? 'Equipment' : ($owner instanceof Customer ? 'Customers' : 'Suppliers'), 'Uploaded document', '['.class_basename($owner).' #'.$owner->id.']');
        }
    }

    private function store($file, string $folder): string
    {
        $path = $file->store($folder, 'local');
        if (! $path) {
            throw new RuntimeException('The private file could not be saved. Please try again.');
        }
        $this->created[] = $path;

        return $path;
    }
}
