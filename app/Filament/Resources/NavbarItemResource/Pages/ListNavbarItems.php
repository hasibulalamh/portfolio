<?php

namespace App\Filament\Resources\NavbarItemResource\Pages;

use App\Filament\Resources\NavbarItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNavbarItems extends ListRecords
{
    protected static string $resource = NavbarItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
