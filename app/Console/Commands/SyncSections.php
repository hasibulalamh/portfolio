<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SectionSetting;

class SyncSections extends Command
{
    protected $signature = 'sections:sync {--force : Force sync even if no changes}';
    protected $description = 'Sync available homepage sections from components';

    public function handle()
    {
        $this->info(' Syncing homepage sections...');
        $this->newLine();

        // Sync sections
        SectionSetting::syncAvailableSections();

        $this->info('✓ Sections synced successfully!');
        $this->newLine();

        // Show current status
        $sections = SectionSetting::orderBy('order')->get();

        if ($sections->isEmpty()) {
            $this->warn('⚠ No sections found.');
            $this->info('Make sure:');
            $this->line('  1. Components exist in resources/js/Components/Home/');
            $this->line('  2. Models are defined in config/sections.php');
            return Command::FAILURE;
        }

        $this->table(
            ['Order', 'Section', 'Component', 'Model', 'Enabled', 'Has Data'],
            $sections->map(function($section) {
                $modelClass = "App\\Models\\{$section->model_name}";
                $hasData = '?';

                if (class_exists($modelClass)) {
                    try {
                        if (str_ends_with($section->model_name, 'Setting')) {
                            $count = $modelClass::where('is_active', true)->count();
                        } else {
                            $count = $modelClass::where('is_active', true)->count();
                        }
                        $hasData = $count > 0 ? "✓ ({$count})" : '✗ (0)';
                    } catch (\Exception $e) {
                        $hasData = 'Error';
                    }
                }

                return [
                    $section->order,
                    $section->section_name,
                    $section->component_name . '.vue',
                    $section->model_name,
                    $section->is_enabled ? '<fg=green>✓ Yes</>' : '<fg=red>✗ No</>',
                    $hasData,
                ];
            })
        );

        $this->newLine();
        $this->info(' Manage sections: /admin/section-settings');

        return Command::SUCCESS;
    }
}
