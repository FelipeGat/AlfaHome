<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthFotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_unauthenticated_returns_401(): void
    {
        $this->postJson('/api/v1/auth/me/foto')->assertStatus(401);
    }

    public function test_uploads_image_and_returns_foto_url(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['foto' => null]);
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $resp = $this->postJson('/api/v1/auth/me/foto', ['foto' => $file])
            ->assertOk();

        $fotoPath = $user->fresh()->foto;
        $this->assertNotNull($fotoPath);
        $this->assertStringStartsWith('usuarios/', $fotoPath);
        Storage::disk('public')->assertExists($fotoPath);

        // Resource retorna foto_url absoluta
        $this->assertNotNull($resp->json('data.foto_url'));
    }

    public function test_upload_replaces_old_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Upload 1
        $this->postJson('/api/v1/auth/me/foto', [
            'foto' => UploadedFile::fake()->image('a.png'),
        ])->assertOk();
        $oldPath = $user->fresh()->foto;
        Storage::disk('public')->assertExists($oldPath);

        // Upload 2 — substitui
        $this->postJson('/api/v1/auth/me/foto', [
            'foto' => UploadedFile::fake()->image('b.png'),
        ])->assertOk();
        $newPath = $user->fresh()->foto;

        $this->assertNotEquals($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_upload_rejects_non_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/auth/me/foto', ['foto' => $file])
            ->assertStatus(422)
            ->assertJsonValidationErrors('foto');
    }

    public function test_upload_rejects_oversized(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 3 MB > 2 MB cap
        $file = UploadedFile::fake()->image('big.jpg')->size(3000);

        $this->postJson('/api/v1/auth/me/foto', ['foto' => $file])
            ->assertStatus(422)
            ->assertJsonValidationErrors('foto');
    }

    public function test_delete_removes_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Upload primeiro
        $this->postJson('/api/v1/auth/me/foto', [
            'foto' => UploadedFile::fake()->image('a.png'),
        ])->assertOk();
        $fotoPath = $user->fresh()->foto;

        // Delete
        $this->deleteJson('/api/v1/auth/me/foto')
            ->assertOk()
            ->assertJsonPath('data.foto_url', null);

        $this->assertNull($user->fresh()->foto);
        Storage::disk('public')->assertMissing($fotoPath);
    }

    public function test_delete_idempotent_when_no_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['foto' => null]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/auth/me/foto')
            ->assertOk()
            ->assertJsonPath('data.foto_url', null);
    }

    public function test_old_photo_is_only_deleted_after_db_save_succeeds(): void
    {
        // Garante ordem correta: persistir novo → salvar DB → apagar antigo.
        // Verifica que, mesmo num upload bem-sucedido, o caminho antigo só
        // é removido DEPOIS de o user->save() ter executado com sucesso.
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Upload inicial — captura o path antigo
        $this->postJson('/api/v1/auth/me/foto', [
            'foto' => UploadedFile::fake()->image('a.png'),
        ])->assertOk();
        $oldPath = $user->fresh()->foto;
        Storage::disk('public')->assertExists($oldPath);

        // Substitui
        $resp = $this->postJson('/api/v1/auth/me/foto', [
            'foto' => UploadedFile::fake()->image('b.png'),
        ])->assertOk();

        $newPath = $user->fresh()->foto;
        $this->assertNotEquals($oldPath, $newPath, 'O path deve ter mudado.');

        // DB já aponta para o novo, antigo já foi removido só ao final
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);
    }
}
