<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class SectionSetting extends Model
{
    protected $fillable = [
        'model_name',
        'section_name',
        'component_name',
        'section_id',
        'is_enabled',
        'order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    /**
     * Get all enabled sections with their data
     */
    public static function getEnabledSectionsWithData()
    {
        $sections = self::where('is_enabled', true)
            ->orderBy('order')
            ->get();

        $result = [];

        foreach ($sections as $section) {
            $hasData = self::checkModelHasData($section->model_name);

            if ($hasData) {
                $result[] = [
                    'component' => $section->component_name,
                    'section_id' => $section->section_id,
                ];
            }
        }

        return $result;
    }

    /**
     * Check if a model has active data
     */
    private static function checkModelHasData($modelName)
    {
        $modelClass = "App\\Models\\{$modelName}";

        if (!class_exists($modelClass)) {
            return false;
        }

        try {
            // Single-record models (end with 'Setting')
            if (str_ends_with($modelName, 'Setting')) {
                return $modelClass::where('is_active', true)->exists();
            }

            // Multi-record models
            return $modelClass::where('is_active', true)->count() > 0;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Auto-discover and sync sections from components
     */
    public static function syncAvailableSections()
    {
        $componentsPath = config('sections.component_path');

        if (!File::exists($componentsPath)) {
            return;
        }

        $componentFiles = File::files($componentsPath);
        $mappings = config('sections.mappings', []);
        $order = 1;

        foreach ($componentFiles as $file) {
            $componentName = $file->getFilenameWithoutExtension();

            // Skip non-section components
            if (in_array($componentName, ['index', 'Layout'])) {
                continue;
            }

            // Get model name from config
            $modelName = $mappings[$componentName] ?? null;

            if (!$modelName) {
                continue; // Skip if not in config
            }

            // Check if model exists
            if (!class_exists("App\\Models\\{$modelName}")) {
                continue;
            }

            // Create or update section
            self::updateOrCreate(
                ['component_name' => $componentName],
                [
                    'model_name' => $modelName,
                    'section_name' => $componentName,
                    'section_id' => strtolower($componentName),
                    'order' => config('sections.order.' . $componentName, $order++),
                    'is_enabled' => true,
                ]
            );
        }
    }
}
