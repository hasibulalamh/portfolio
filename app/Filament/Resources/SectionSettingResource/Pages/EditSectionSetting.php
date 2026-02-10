<?php

namespace App\Filament\Resources\SectionSettingResource\Pages;

use App\Filament\Resources\SectionSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSectionSetting extends EditRecord
{
    protected static string $resource = SectionSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
