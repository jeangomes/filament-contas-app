<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Trava: teste nunca roda fora do SQLite em memória.
     *
     * Com a configuração em cache (`optimize`/`config:cache`), o Laravel
     * ignora o phpunit.xml e o .env.testing e conecta no MySQL real — e o
     * `RefreshDatabase` faz `migrate:fresh` nele. A checagem fica aqui, e não
     * no `setUp()`, porque `createApplication()` roda antes dos traits: o
     * teste aborta antes de qualquer migração tocar o banco.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $conexao = $app['config']->get('database.default');
        $banco = $app['config']->get("database.connections.$conexao.database");

        if ($conexao !== 'sqlite' || $banco !== ':memory:') {
            throw new RuntimeException(sprintf(
                'Testes abortados: o banco configurado é "%s" (%s), não SQLite em memória. '
                . 'Provável configuração em cache — rode `php artisan optimize:clear` antes de testar.',
                $conexao,
                is_string($banco) ? $banco : '?',
            ));
        }

        return $app;
    }
}
