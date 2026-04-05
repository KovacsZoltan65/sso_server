<?php

namespace App\Http\Requests\Admin;

use App\Support\OAuth\RememberedConsentRevocationReasons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RevokeUserClientConsentRequest extends FormRequest
{
    /**
     * @return array<int, string>
     */
    public static function supportedReasons(): array
    {
        return RememberedConsentRevocationReasons::adminSelectable();
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'revocation_reason' => ['required', 'string', Rule::in(self::supportedReasons())],
        ];
    }
}
