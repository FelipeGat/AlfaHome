<?php

namespace Tests\Feature\Correcoes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Constitution VII: o usuário lê português, nunca chave de tradução crua. */
class MensagensEmPortuguesTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_errado_explica_em_portugues(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'senha-errada'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
    }

    public function test_muitas_tentativas_de_login_explica_em_portugues(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'errada']);
        }

        $erro = session('errors')?->first('email');
        $resposta = $this->post('/login', ['email' => $user->email, 'password' => 'errada']);
        $mensagem = session('errors')->first('email');

        $this->assertStringStartsWith('Muitas tentativas de acesso. Tente novamente em', $mensagem);
        $this->assertStringNotContainsString('auth.', $mensagem);
    }

    public function test_validacao_de_formulario_sai_em_portugues(): void
    {
        $this->post('/register', ['name' => '', 'email' => 'nao-e-email', 'password' => '123', 'password_confirmation' => '456'])
            ->assertSessionHasErrors([
                'name'     => 'O campo nome é obrigatório.',
                'email'    => 'O campo e-mail deve ser um endereço de e-mail válido.',
                'password' => 'O campo senha não confere com a confirmação.',
            ]);
    }

    public function test_recuperacao_de_senha_responde_em_portugues(): void
    {
        $this->post('/forgot-password', ['email' => 'ninguem@exemplo.com'])
            ->assertSessionHasErrors(['email' => 'Não encontramos um usuário com esse e-mail.']);
    }

    public function test_nenhuma_mensagem_padrao_ficou_sem_traducao(): void
    {
        foreach (['auth.failed', 'auth.throttle', 'auth.password', 'validation.required', 'validation.email', 'validation.numeric', 'validation.date', 'validation.unique', 'validation.max.string', 'validation.min.numeric', 'passwords.sent', 'passwords.user', 'passwords.token', 'passwords.reset', 'passwords.throttled', 'pagination.next', 'pagination.previous'] as $chave) {
            $this->assertNotSame($chave, __($chave), "Sem tradução: {$chave}");
        }
        $this->assertSame('pt_BR', app()->getLocale());
    }
}
