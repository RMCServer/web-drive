<?php

namespace App\Http\Requests\Drive;

use App\Http\Requests\Drive\Concerns\ResolvesDriveFolder;
use App\Models\DriveItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MoveDriveItemRequest extends FormRequest
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
            'destination' => $this->folderRules(),
        ];
    }

    /**
     * Get the folder the item is moved into, or null for the root.
     */
    public function destinationFolder(): ?DriveItem
    {
        return $this->validatedFolder('destination');
    }
}
