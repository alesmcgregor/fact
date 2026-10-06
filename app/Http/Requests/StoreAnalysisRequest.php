<?php

namespace App\Http\Requests;

use App\Exceptions\InvalidPublicUrlException;
use App\Services\Content\PublicUrlPolicy;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        $fail(__('app.validation_url'));

                        return;
                    }

                    try {
                        app(PublicUrlPolicy::class)->parse(trim($value));
                    } catch (InvalidPublicUrlException) {
                        $fail(__('app.validation_url'));
                    }
                },
            ],
            'locale' => ['required', 'string', Rule::in(array_keys(config('fact.locales')))],
            'refresh' => ['sometimes', 'boolean'],
            'include_perspectives' => ['sometimes', 'boolean'],
            'transcript' => ['nullable', 'string', 'min:40', 'max:12000'],
        ];
    }
}
