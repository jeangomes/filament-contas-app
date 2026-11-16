<?php

namespace Tests\Feature;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * O painel não tem build de front (sem Vite/Tailwind): o CSS próprio é um
 * arquivo puro registrado via FilamentAsset.
 */
final class CssDoPainelTest extends TestCase
{
    #[Test]
    public function o_css_do_painel_esta_registrado(): void
    {
        $ids = array_map(
            static fn (Css $css): string => $css->getId(),
            FilamentAsset::getStyles(['app']),
        );

        $this->assertContains('admin', $ids);
        $this->assertStringContainsString('/css/app/admin.css', FilamentAsset::getStyleHref('admin'));
    }

    #[Test]
    public function o_css_e_puro_sem_diretivas_do_tailwind(): void
    {
        // Sem build, `@apply`/`@import 'tailwindcss'` chegariam ao navegador
        // como texto e seriam ignorados em silêncio.
        $css = (string) file_get_contents(resource_path('css/admin.css'));

        $this->assertDoesNotMatchRegularExpression('/@(apply|tailwind|source|import|theme)\b/', $css);
    }

    #[Test]
    public function a_classe_de_destaque_usada_nas_tabelas_existe_no_css(): void
    {
        // CreditCardBillsTable e TransactionsRelationManager aplicam
        // `my-bg-primary` via extraCellAttributes.
        $this->assertStringContainsString(
            '.my-bg-primary',
            (string) file_get_contents(resource_path('css/admin.css')),
        );
    }
}
