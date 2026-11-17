<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;

/**
 * Perfil do usuário logado: nome e troca de senha.
 *
 * O e-mail fica de fora de propósito: `User::canAccessPanel()` libera o painel
 * por e-mail, então trocá-lo aqui tiraria o acesso do próprio usuário.
 *
 * Fica fora de `app/Filament/Pages` para não entrar no `discoverPages()`;
 * é registrada só pelo `->profile()` do painel.
 */
final class EditarPerfil extends EditProfile
{
    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    /**
     * O pai também exige a senha atual quando o e-mail muda, comparando com o
     * campo `email` do form — que aqui não existe. Sem isso a senha atual
     * seria pedida sempre, até para trocar só o nome.
     */
    protected function getCurrentPasswordFormComponent(): Component
    {
        return parent::getCurrentPasswordFormComponent()
            ->visible(fn (Get $get): bool => filled($get('password')));
    }
}
