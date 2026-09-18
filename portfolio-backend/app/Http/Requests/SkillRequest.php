<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The admin panel supplies this from the selected category rather
            // than a form field, but it still has to reference a real row.
            'skill_category_id' => ['required', 'integer', 'exists:skill_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            // A Simple Icons slug picked in the admin panel, e.g. "laravel".
            // Lowercase alphanumerics and dashes is exactly the slug alphabet
            // that library uses, so anything else is a bad write.
            'icon_slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'logo_type' => ['nullable', 'string', 'in:library,custom'],
            'logo_url' => ['nullable', 'url', 'max:2048', 'required_if:logo_type,custom'],
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'skill_category_id.required' => 'A skill category is required.',
            'skill_category_id.exists' => 'That skill category does not exist.',
            'icon_slug.regex' => 'That is not a valid technology icon.',
        ];
    }
}
