<?php

namespace App\Http\Requests\Drive;

use App\Http\Requests\Drive\Concerns\ResolvesDriveFolder;
use App\Models\DriveItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFileRequest extends FormRequest
{
    use ResolvesDriveFolder;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.config('drive.max_upload_size')],
            'parent' => $this->folderRules(),
        ];
    }

    /**
     * Get the folder the file is uploaded into, or null for the root.
     */
    public function parentFolder(): ?DriveItem
    {
        return $this->validatedFolder('parent');
    }
}
