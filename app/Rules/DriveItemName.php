<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class DriveItemName implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (in_array(trim($value), ['.', '..'], true)) {
            $fail(__('This name is not allowed.'));

            return;
        }

        if (preg_match('/[\/\\\\\x00-\x1F\x7F]/', $value)) {
            $fail(__('The name may not contain slashes or control characters.'));
        }
    }
}
