<?php

namespace App\Filament\Resources\ContactSettingResource\Pages;

use App\Filament\Resources\ContactSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContactSettings extends ListRecords
{
    protected static string $resource = ContactSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
