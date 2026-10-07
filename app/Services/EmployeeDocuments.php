<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Employee photo and CNIC scans. Stored on the private "local" disk
 * (storage/app/private), so they are reachable only through the
 * owner-only document route.
 */
class EmployeeDocuments
{
    private const DISK = 'local';

    /**
     * Applies uploads and "remove" ticks from an employee form.
     */
    public function sync(Employee $employee, Request $request): void
    {
        foreach (Employee::DOCUMENTS as $document => $column) {
            $file = $request->file($document);

            if ($file instanceof UploadedFile) {
                $this->replace($employee, $column, $file->store("employees/{$employee->id}", self::DISK));
            } elseif ($request->boolean("remove_{$document}")) {
                $this->replace($employee, $column, null);
            }
        }

        $employee->save();
    }

    public function deleteAll(Employee $employee): void
    {
        Storage::disk(self::DISK)->deleteDirectory("employees/{$employee->id}");
    }

    public function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    private function replace(Employee $employee, string $column, ?string $path): void
    {
        if ($employee->{$column}) {
            Storage::disk(self::DISK)->delete($employee->{$column});
        }

        $employee->{$column} = $path;
    }
}
