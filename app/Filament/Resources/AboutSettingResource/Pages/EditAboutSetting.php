<?php

namespace App\Filament\Resources\AboutSettingResource\Pages;

use App\Filament\Resources\AboutSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAboutSetting extends EditRecord
{
    protected static string $resource = AboutSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
