<?php

namespace App\Filament\Resources\NavbarItemResource\Pages;

use App\Filament\Resources\NavbarItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNavbarItem extends EditRecord
{
    protected static string $resource = NavbarItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
