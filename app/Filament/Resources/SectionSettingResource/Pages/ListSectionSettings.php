<?php

namespace App\Filament\Resources\SectionSettingResource\Pages;

use App\Filament\Resources\SectionSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSectionSettings extends ListRecords
{
    protected static string $resource = SectionSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
