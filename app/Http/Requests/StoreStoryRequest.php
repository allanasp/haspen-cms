<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Store story form request validation.
 */
class StoreStoryRequest extends FormRequest
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
            'story' => 'required|array',
            'story.name' => 'required|string|max:255',
            'story.slug' => 'sometimes|string|max:255|regex:/^[a-z0-9-\/]+$/',
            'story.content' => 'sometimes|array',
            'story.status' => 'sometimes|string|in:draft,in_review,published,scheduled,archived',
            'story.parent_id' => 'sometimes|nullable|string|exists:stories,uuid',
            'story.meta_title' => 'sometimes|nullable|string|max:255',
            'story.meta_description' => 'sometimes|nullable|string|max:500',
            'story.publish_at' => 'sometimes|nullable|date|after:now',
            'story.language' => 'sometimes|nullable|string|size:2',
            'story.translation_group_id' => 'sometimes|nullable|string|exists:stories,uuid'
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
            'story.required' => 'Story data is required',
            'story.name.required' => 'Story name is required',
            'story.name.max' => 'Story name cannot exceed 255 characters',
            'story.slug.regex' => 'Slug can only contain lowercase letters, numbers, hyphens, and forward slashes',
            'story.status.in' => 'Invalid story status',
            'story.parent_id.exists' => 'Parent story does not exist',
            'story.meta_title.max' => 'Meta title cannot exceed 255 characters',
            'story.meta_description.max' => 'Meta description cannot exceed 500 characters',
            'story.publish_at.after' => 'Publish date must be in the future',
            'story.language.size' => 'Language code must be exactly 2 characters',
            'story.translation_group_id.exists' => 'Translation group does not exist'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('story.name') && !$this->has('story.slug')) {
            $this->merge([
                'story' => array_merge($this->input('story', []), [
                    'slug' => str($this->input('story.name'))->slug()->toString()
                ])
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $space = $this->get('current_space');
            
            if ($space && $this->has('story.slug')) {
                // Check slug uniqueness within space
                $existing = \App\Models\Story::where('space_id', $space->id)
                    ->where('slug', $this->input('story.slug'))
                    ->exists();

                if ($existing) {
                    $validator->errors()->add('story.slug', 'This slug already exists in the space');
                }
            }

            // Validate parent story belongs to same space
            if ($this->has('story.parent_id') && $space) {
                $parent = \App\Models\Story::where('uuid', $this->input('story.parent_id'))
                    ->where('space_id', $space->id)
                    ->first();

                if (!$parent) {
                    $validator->errors()->add('story.parent_id', 'Parent story must belong to the same space');
                }
            }
        });
    }
}