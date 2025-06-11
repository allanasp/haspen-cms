<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Store asset form request validation.
 */
class StoreAssetRequest extends FormRequest
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
            'file' => 'required|file|max:10240', // 10MB max
            'title' => 'sometimes|nullable|string|max:255',
            'alt' => 'sometimes|nullable|string|max:255',
            'folder' => 'sometimes|nullable|string|max:255|regex:/^[a-zA-Z0-9\/_-]+$/'
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
            'file.required' => 'File is required',
            'file.file' => 'Uploaded file is invalid',
            'file.max' => 'File size cannot exceed 10MB',
            'title.max' => 'Title cannot exceed 255 characters',
            'alt.max' => 'Alt text cannot exceed 255 characters',
            'folder.regex' => 'Folder path can only contain letters, numbers, hyphens, underscores, and forward slashes'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->hasFile('file')) {
                $file = $this->file('file');
                $space = $this->get('current_space');

                // Check file type restrictions
                $allowedMimes = [
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
                    'application/pdf', 'text/plain', 'application/json',
                    'video/mp4', 'video/webm', 'video/ogg',
                    'audio/mpeg', 'audio/wav', 'audio/ogg'
                ];

                if (!in_array($file->getMimeType(), $allowedMimes)) {
                    $validator->errors()->add('file', 'File type not allowed');
                }

                // Check space-specific limits
                if ($space) {
                    $maxSize = $space->getResourceLimit('max_asset_size', 10 * 1024 * 1024); // 10MB default
                    
                    if ($file->getSize() > $maxSize) {
                        $validator->errors()->add('file', "File size exceeds limit of " . ($maxSize / 1024 / 1024) . "MB");
                    }

                    // Check asset count limits
                    $assetCount = \App\Models\Asset::where('space_id', $space->id)->count();
                    $assetLimit = $space->getResourceLimit('asset_limit', 1000);
                    
                    if ($assetCount >= $assetLimit) {
                        $validator->errors()->add('file', "Asset limit of {$assetLimit} reached");
                    }
                }

                // Image-specific validation
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    try {
                        $imageInfo = getimagesize($file->getRealPath());
                        if ($imageInfo === false) {
                            $validator->errors()->add('file', 'Invalid image file');
                        } else {
                            // Check image dimensions if needed
                            $maxWidth = 5000; // 5000px max width
                            $maxHeight = 5000; // 5000px max height
                            
                            if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
                                $validator->errors()->add('file', "Image dimensions cannot exceed {$maxWidth}x{$maxHeight} pixels");
                            }
                        }
                    } catch (\Exception $e) {
                        $validator->errors()->add('file', 'Unable to process image file');
                    }
                }
            }
        });
    }
}