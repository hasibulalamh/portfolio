<?php

namespace App\Filament\Resources\FooterItemResource\Pages;

use App\Filament\Resources\FooterItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFooterItems extends ListRecords
{
    protected static string $resource = FooterItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
