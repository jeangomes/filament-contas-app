<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;

class Results extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.results';
    protected static ?string $title = 'Consolidado';

    /*public $count = 1;

    public function increment()
    {
        $this->count++;
    }

    public function decrement()
    {
        $this->count--;
    }*/
}
