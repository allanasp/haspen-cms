<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update story form request validation.
 */
class UpdateStoryRequest extends FormRequest
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
            'story.name' => 'sometimes|string|max:255',
            'story.slug' => 'sometimes|string|max:255|regex:/^[a-z0-9-\/]+$/',
            'story.content' => 'sometimes|array',
            'story.status' => 'sometimes|string|in:draft,in_review,published,scheduled,archived',
            'story.parent_id' => 'sometimes|nullable|string|exists:stories,uuid',
            'story.meta_title' => 'sometimes|nullable|string|max:255',
            'story.meta_description' => 'sometimes|nullable|string|max:500',
            'story.publish_at' => 'sometimes|nullable|date',
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
            'story.name.max' => 'Story name cannot exceed 255 characters',
            'story.slug.regex' => 'Slug can only contain lowercase letters, numbers, hyphens, and forward slashes',
            'story.status.in' => 'Invalid story status',
            'story.parent_id.exists' => 'Parent story does not exist',
            'story.meta_title.max' => 'Meta title cannot exceed 255 characters',
            'story.meta_description.max' => 'Meta description cannot exceed 500 characters',
            'story.language.size' => 'Language code must be exactly 2 characters',
            'story.translation_group_id.exists' => 'Translation group does not exist'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $space = $this->get('current_space');
            $storyId = $this->route('storyId');
            
            if ($space && $this->has('story.slug')) {
                // Check slug uniqueness within space (excluding current story)
                $existing = \App\Models\Story::where('space_id', $space->id)
                    ->where('slug', $this->input('story.slug'))
                    ->where('uuid', '!=', $storyId)
                    ->exists();

                if ($existing) {
                    $validator->errors()->add('story.slug', 'This slug already exists in the space');
                }
            }

            // Validate parent story belongs to same space and isn't the current story
            if ($this->has('story.parent_id') && $space) {
                $parentId = $this->input('story.parent_id');
                
                if ($parentId === $storyId) {
                    $validator->errors()->add('story.parent_id', 'A story cannot be its own parent');
                } else {
                    $parent = \App\Models\Story::where('uuid', $parentId)
                        ->where('space_id', $space->id)
                        ->first();

                    if (!$parent) {
                        $validator->errors()->add('story.parent_id', 'Parent story must belong to the same space');
                    }
                }
            }

            // Validate publish_at when status is scheduled
            if ($this->input('story.status') === 'scheduled') {
                if (!$this->has('story.publish_at') || !$this->input('story.publish_at')) {
                    $validator->errors()->add('story.publish_at', 'Publish date is required when status is scheduled');
                } elseif (strtotime($this->input('story.publish_at')) <= time()) {
                    $validator->errors()->add('story.publish_at', 'Scheduled publish date must be in the future');
                }
            }
        });
    }
}