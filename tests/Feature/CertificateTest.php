<?php

namespace Tests\Feature;

use App\Models\SalaryCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    private const OCR_URL = 'https://api.ocr.space/parse/image';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.ocr_space.key' => 'test-key', 'services.ocr_space.endpoint' => self::OCR_URL]);
    }

    private function fakeOcr(?string $text = null): void
    {
        $text ??= file_get_contents(base_path('tests/Fixtures/certificates/ocrspace-table.txt'));
        Http::fake([self::OCR_URL => Http::response([
            'ParsedResults' => [['ParsedText' => $text, 'FileParseExitCode' => 1]],
            'OCRExitCode' => 1,
            'IsErroredOnProcessing' => false,
        ])]);
    }

    private function upload(User $user, ?UploadedFile $file = null)
    {
        return $this->actingAs($user)->postJson('/certificates', ['file' => $file ?? UploadedFile::fake()->image('certificate.jpg', 1200, 1600)]);
    }

    private function confirmPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'year' => '2026-27', 'category' => 'general', 'disabled_children' => 0, 'new_taxpayer' => false, 'filing' => 'standard',
            'employer' => 'Acme Bangladesh Limited', 'employee' => 'Rahim Uddin', 'tin' => '123456789012',
            'components' => ['basic' => 720000, 'house_rent' => 360000, 'medical' => 72000, 'conveyance' => 48000, 'festival_bonus' => 120000, 'other_allowances' => 0],
            'perks' => ['employer_pf' => 72000],
            'investments' => ['dps' => 60000, 'provident_fund' => 144000],
            'tds' => 42000,
        ], $overrides);
    }

    /** A field of a multipart request. */
    private function part(HttpRequest $request, string $name): mixed
    {
        return collect($request->data())->firstWhere('name', $name)['contents'] ?? null;
    }

    public function test_certificates_need_an_account(): void
    {
        $this->get('/certificates')->assertRedirect('/login');
        $this->postJson('/certificates')->assertUnauthorized();
    }

    public function test_upload_is_read_and_ready_for_review(): void
    {
        $this->fakeOcr();
        $user = User::factory()->create();

        $url = $this->upload($user)->assertOk()->json('redirect');

        $certificate = SalaryCertificate::sole();
        $this->assertSame(SalaryCertificate::READ, $certificate->status);
        Storage::disk('local')->assertExists($certificate->path);
        $this->assertStringStartsWith("certificates/{$user->id}/", $certificate->path);
        $this->assertSame(route('certificates.show', $certificate), $url);
        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('apikey', 'test-key') && $this->part($r, 'isTable') === 'true');

        $boot = $this->actingAs($user)->get($url)->assertOk()->assertSee('Read 7 figures')->viewData('boot');
        $this->assertSame(720000.0, $boot['data']['components']['basic']);
        $this->assertSame(72000.0, $boot['data']['perks']['employer_pf']);
        $this->assertSame(72000.0, $boot['data']['investments']['provident_fund']);   // employer PF also counts as investment
        $this->assertSame(42000.0, $boot['data']['tds']);
        $this->assertSame('2026-27', $boot['data']['year']);
    }

    public function test_only_images_and_pdfs_up_to_the_limit_are_accepted(): void
    {
        $user = User::factory()->create();

        $this->upload($user, UploadedFile::fake()->create('virus.exe', 10))->assertStatus(422)->assertJsonValidationErrors('file');
        $this->upload($user, UploadedFile::fake()->create('huge.pdf', 11000, 'application/pdf'))->assertStatus(422)->assertJsonValidationErrors('file');
        $this->assertSame(0, SalaryCertificate::count());
    }

    public function test_reading_problems_fall_back_to_manual_entry(): void
    {
        Http::fake([self::OCR_URL => Http::response(['OCRExitCode' => 4, 'IsErroredOnProcessing' => true, 'ErrorMessage' => ['Timed out']])]);
        $user = User::factory()->create();

        $url = $this->upload($user)->assertOk()->json('redirect');

        $certificate = SalaryCertificate::sole();
        $this->assertSame(SalaryCertificate::UNREAD, $certificate->status);
        $this->assertNotEmpty($certificate->ocr_error);
        $this->actingAs($user)->get($url)->assertOk()->assertSee('Try reading again');
    }

    public function test_without_an_api_key_nothing_is_sent(): void
    {
        config(['services.ocr_space.key' => null]);
        Http::fake();
        $user = User::factory()->create();

        $this->upload($user)->assertOk();

        Http::assertNothingSent();
        $this->assertSame(SalaryCertificate::UNREAD, SalaryCertificate::sole()->status);
    }

    public function test_large_pdfs_are_not_sent_and_large_photos_are_shrunk(): void
    {
        $this->fakeOcr();
        $user = User::factory()->create();

        // A real 2 MB PDF (a fake upload only reports its size, the bytes would be empty).
        $pdf = tempnam(sys_get_temp_dir(), 'kh').'.pdf';
        file_put_contents($pdf, "%PDF-1.4\n".str_repeat('0', 2 * 1024 * 1024)."\n%%EOF");
        $this->upload($user, new UploadedFile($pdf, 'scan.pdf', 'application/pdf', null, true))->assertOk();
        Http::assertNothingSent();
        $this->assertStringContainsString('larger than', SalaryCertificate::sole()->ocr_error);

        // A photo of pure noise (it cannot compress) is well over 1 MB; what reaches the OCR service is under it.
        $path = tempnam(sys_get_temp_dir(), 'kh').'.png';
        $img = imagecreatetruecolor(1200, 1200);
        for ($x = 0; $x < 1200; $x++) {
            for ($y = 0; $y < 1200; $y++) {
                imagesetpixel($img, $x, $y, mt_rand(0, 0xFFFFFF));
            }
        }
        imagepng($img, $path);
        $this->assertGreaterThan(1024 * 1024, filesize($path));

        $this->upload($user, new UploadedFile($path, 'photo.png', 'image/png', null, true))->assertOk();
        Http::assertSent(function (HttpRequest $r) {
            $file = collect($r->data())->firstWhere('name', 'file');

            return $file && $file['contents']->getSize() <= 1024 * 1024 && $this->part($r, 'filetype') === 'JPG';
        });
    }

    public function test_files_and_reports_are_private(): void
    {
        $this->fakeOcr();
        $owner = User::factory()->create();
        $this->upload($owner);
        $certificate = SalaryCertificate::sole();
        $other = User::factory()->create();

        $this->actingAs($owner)->get("/certificates/{$certificate->id}/file")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        foreach (['', '/file', '/report'] as $suffix) {
            $this->actingAs($other)->get("/certificates/{$certificate->id}{$suffix}")->assertNotFound();
        }
        $this->actingAs($other)->putJson("/certificates/{$certificate->id}", $this->confirmPayload())->assertNotFound();
        $this->actingAs($other)->delete("/certificates/{$certificate->id}")->assertNotFound();
    }

    public function test_confirmed_figures_make_the_report(): void
    {
        $this->fakeOcr();
        $user = User::factory()->create();
        $this->upload($user);
        $certificate = SalaryCertificate::sole();

        // Before confirming, the report sends you back to the review.
        $this->actingAs($user)->get("/certificates/{$certificate->id}/report")->assertRedirect("/certificates/{$certificate->id}");

        $this->actingAs($user)->putJson("/certificates/{$certificate->id}", $this->confirmPayload(['components' => ['basic' => -1]]))
            ->assertStatus(422)->assertJsonValidationErrors('components.basic');

        $url = $this->actingAs($user)->putJson("/certificates/{$certificate->id}", $this->confirmPayload())->assertOk()->json('redirect');
        $this->assertSame(SalaryCertificate::CONFIRMED, $certificate->fresh()->status);

        $page = $this->actingAs($user)->get($url)->assertOk()
            ->assertSee('Rahim Uddin')
            ->assertSee('৳1,392,000')             // salary for tax
            ->assertSee('••••••••9012', false)     // TIN masked
            ->assertSee('Where each figure goes on your return')
            ->assertSee('Download PDF');
        $r = $page->viewData('r');
        $this->assertSame(1392000.0, $r['gross']);
        $this->assertSame([], $r['checks']);       // matches the certificate's own total
        $this->assertNotEmpty($r['tips']);

        $this->actingAs($user)->get('/certificates')->assertOk()->assertSee('Report ready');
    }

    public function test_a_mismatch_with_the_certificate_total_is_flagged(): void
    {
        $this->fakeOcr();
        $user = User::factory()->create();
        $this->upload($user);
        $certificate = SalaryCertificate::sole();

        $this->actingAs($user)->putJson("/certificates/{$certificate->id}", $this->confirmPayload(['components' => ['medical' => 0]]))->assertOk();

        $this->actingAs($user)->get("/certificates/{$certificate->id}/report")->assertOk()->assertSee('the certificate’s total says', false);
    }

    public function test_deleting_removes_the_file(): void
    {
        $this->fakeOcr();
        $user = User::factory()->create();
        $this->upload($user);
        $certificate = SalaryCertificate::sole();

        $this->actingAs($user)->delete("/certificates/{$certificate->id}")->assertRedirect('/certificates');

        $this->assertModelMissing($certificate);
        Storage::disk('local')->assertMissing($certificate->path);
    }

    public function test_report_renders_in_bangla(): void
    {
        $this->fakeOcr();
        $user = User::factory()->create();
        $this->upload($user);
        $certificate = SalaryCertificate::sole();
        $this->actingAs($user)->putJson("/certificates/{$certificate->id}", $this->confirmPayload())->assertOk();

        $this->withCookie('kh_locale', 'bn')->actingAs($user)->get("/certificates/{$certificate->id}/report")->assertOk()
            ->assertSee('৳১,৩৯২,০০০');
    }
}
