<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\View\PanelsRenderHook;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('/')
            ->login()
            ->brandName('Contas Casinha V3')
            ->sidebarWidth('15rem')
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                // Cor principal: Vinho / Bordô
                'primary' => [
                    50  => '#fdf2f4',
                    100 => '#fbe5e8',
                    200 => '#f7ced5',
                    300 => '#f0a8b5',
                    400 => '#e4768c',
                    500 => '#d34b67',
                    600 => '#bd314e',
                    700 => '#9e223b', // Tom base vibrante
                    800 => '#852035', // Tom dominante do Vinho
                    900 => '#711f31',
                    950 => '#410c17', // Tom profundo/fechado
                ],

                // Destaques / Avisos: Dourado / Amarelo Ouro
                'warning' => [
                    50  => '#fefce8',
                    100 => '#fef9c3',
                    200 => '#fef08a',
                    300 => '#fde047',
                    400 => '#facc15',
                    500 => '#eab308',
                    600 => '#ca8a04',
                    700 => '#a16207', // Tom base do Ouro/Bronze
                    800 => '#854d0e',
                    900 => '#713f12',
                    950 => '#422006',
                ],

                // Ações de Sucesso / Confirmações: Esmeralda Escura (Equilíbrio elegante)
                'success' => Color::Emerald,

                // Alerta / Destruição: Vermelho Quente (Energia de Fogo)
                'danger' => Color::Rose,

                // Informações: Âmbar / Laranja Queimado
                'info' => Color::Amber,

                // Tons neutros: Grafite / Slate (Representando o fundo misterioso de Escorpião)
                'gray' => Color::Slate,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => app()->isLocal()
                    ? '<style>
                body.fi-body,
                .fi-layout {
                    background-color: #ffedd5 !important;
                }
            </style>'
                    : '',
            )
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
