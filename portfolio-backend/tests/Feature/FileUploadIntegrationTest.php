<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * File upload flow: a multipart upload reaches the storage disk and returns a
 * URL the admin form can save.
 *
 * Integration, not unit: the contract is that the route, validation, disk
 * selection and URL generation are wired correctly for each upload type.
 */
class FileUploadIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_a_valid_png_upload_returns_a_url(): void
    {
        // Fake both disks: the .env has all R2 credentials present, so the
        // upload service selects the r2 disk — faking only "public" made this
        // test perform a real PutObject against Cloudflare R2.
        Storage::fake('public');
        Storage::fake('r2');
        $this->actAsAdmin();

        $file = UploadedFile::fake()->image('photo.png', 200, 200);

        $response = $this->postJson('/api/admin/upload', [
            'file' => $file,
            'type' => 'project-image',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['data' => ['url']]);
        $this->assertNotEmpty($response->json('data.url'));
    }

    public function test_a_php_file_disguised_as_png_is_rejected(): void
    {
        $this->actAsAdmin();

        // Create a file with .png extension but PHP content
        $tmpFile = tempnam(sys_get_temp_dir(), 'upload-test-');
        file_put_contents($tmpFile, '<?php echo "pwned";');

        $file = new UploadedFile($tmpFile, 'shell.png', 'image/png', null, true);

        $response = $this->postJson('/api/admin/upload', [
            'file' => $file,
            'type' => 'project-image',
        ]);

        $response->assertStatus(422);
        @unlink($tmpFile);
    }

    public function test_an_upload_requires_auth(): void
    {
        $file = UploadedFile::fake()->image('photo.png');

        $this->postJson('/api/admin/upload', [
            'file' => $file,
            'type' => 'project-image',
        ])->assertStatus(401);
    }

    public function test_an_image_upload_rejects_a_pdf(): void
    {
        $this->actAsAdmin();

        $tmpFile = tempnam(sys_get_temp_dir(), 'upload-test-');
        file_put_contents($tmpFile, '%PDF-1.4 fake content');

        $file = new UploadedFile($tmpFile, 'document.pdf', 'application/pdf', null, true);

        $response = $this->postJson('/api/admin/upload', [
            'file' => $file,
            'type' => 'logo',
        ]);

        $response->assertStatus(422);
        @unlink($tmpFile);
    }
}
