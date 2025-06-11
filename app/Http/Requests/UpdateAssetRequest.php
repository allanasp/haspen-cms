<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update asset form request validation.
 */
class UpdateAssetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|nullable|string|max:255',
            'alt' => 'sometimes|nullable|string|max:255',
            'filename' => 'sometimes|string|max:255|regex:/^[a-zA-Z0-9._-]+$/'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.max' => 'Title cannot exceed 255 characters',
            'alt.max' => 'Alt text cannot exceed 255 characters',
            'filename.max' => 'Filename cannot exceed 255 characters',
            'filename.regex' => 'Filename can only contain letters, numbers, dots, hyphens, and underscores'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $space = $this->get('current_space');
            $assetId = $this->route('assetId');
            
            if ($space && $this->has('filename')) {
                // Check filename uniqueness within space (excluding current asset)
                $existing = \App\Models\Asset::where('space_id', $space->id)
                    ->where('filename', $this->input('filename'))
                    ->where('uuid', '!=', $assetId)
                    ->exists();

                if ($existing) {
                    $validator->errors()->add('filename', 'This filename already exists in the space');
                }
            }
        });
    }
}