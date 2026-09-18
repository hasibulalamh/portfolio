<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiShowcaseRequest extends FormRequest
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
            // The name of a lucide-react icon, e.g. "Zap" or "Database".
            //
            // No longer required: the admin panel now picks a real brand logo
            // via `icon_slug`, and a showcase whose subject is a brand has no
            // sensible lucide name to supply. Rows may carry either, both, or
            // neither — the frontend renders the slug first and falls back to
            // the lucide name, then to a neutral icon.
            'icon_name' => ['nullable', 'string', 'max:255'],
            // A Simple Icons slug picked in the admin panel, e.g. "laravel".
            'icon_slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'logo_type' => ['nullable', 'string', 'in:library,custom'],
            'logo_url' => ['nullable', 'url', 'max:2048', 'required_if:logo_type,custom'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Blank rows are already dropped by the admin page before submit.
            'endpoints' => ['nullable', 'array'],
            'endpoints.*' => ['string', 'max:500'],
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'icon_slug.regex' => 'That is not a valid technology icon.',
        ];
    }
}
