<?php

namespace App\Http\Requests\Drive;

use App\Http\Requests\Drive\Concerns\ResolvesDriveFolder;
use App\Models\DriveItem;
use App\Rules\DriveItemName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFolderRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', new DriveItemName],
            'parent' => $this->folderRules(),
        ];
    }

    /**
     * Get the folder the new folder is created in, or null for the root.
     */
    public function parentFolder(): ?DriveItem
    {
        return $this->validatedFolder('parent');
    }
}
