<?php

namespace App\Filament\Resources\HeroSettingResource\Pages;

use App\Filament\Resources\HeroSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHeroSetting extends EditRecord
{
    protected static string $resource = HeroSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
