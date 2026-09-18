<?php

namespace App\Http\Requests;

use App\Services\UploadService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Server-side validation for POST /admin/upload.
 *
 * FileUpload.jsx does its own accept/size check before uploading, but that
 * runs in the browser and is trivially bypassed, so the real limits are
 * enforced here per upload type.
 */
class UploadRequest extends FormRequest
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
        $type = $this->uploadType();
        $rules = UploadService::rulesFor($type);

        return [
            'file' => [
                'required',
                'file',
                'mimetypes:'.implode(',', $rules['mimetypes']),
                'max:'.$rules['max_kb'],
            ],
            // Optional for other shared upload callers; the settings logo and
            // favicon controls send their explicit types.
            'type' => ['nullable', 'string', Rule::in(UploadService::types())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $rules = UploadService::rulesFor($this->uploadType());

        return [
            'file.required' => 'No file was provided.',
            'file.mimetypes' => 'Unsupported file type. Allowed: '.implode(', ', $rules['mimetypes']).'.',
            'file.max' => 'File is too large. Maximum size is '.round($rules['max_kb'] / 1024, 1).'MB.',
            'type.in' => 'Unknown upload type.',
        ];
    }

    public function uploadType(): string
    {
        $type = (string) $this->input('type', '');

        return in_array($type, UploadService::types(), true)
            ? $type
            : UploadService::TYPE_GENERIC;
    }
}
