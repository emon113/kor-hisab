<?php

namespace App\Http\Controllers;

use App\Http\Requests\CertificateDataRequest;
use App\Models\SalaryCertificate;
use App\Services\SalaryCertificate\CertificateForm;
use App\Services\SalaryCertificate\CertificateReader;
use App\Services\SalaryCertificate\CertificateReport;
use App\Support\Money;
use App\Support\TaxOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    public const MAX_KB = 10240;

    public const MIMES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    public function __construct(private readonly CertificateReader $reader, private readonly CertificateReport $reports) {}

    public function index(Request $request): View
    {
        Money::useGrouping($request->cookie('kh_grouping'));
        $certificates = $request->user()->salaryCertificates()->latest()->get()
            ->map(function (SalaryCertificate $c) {
                $summary = null;
                if ($c->status === SalaryCertificate::CONFIRMED) {
                    $s = $this->reports->build($c->data)['report']['summary'];
                    $summary = ['gross' => $s['gross'], 'tax' => $s['liability'], 'payable' => $s['payable']];
                }

                return ['certificate' => $c, 'summary' => $summary];
            });

        return view('certificates.index', [
            'certificates' => $certificates,
            'reading' => $this->reader->enabled(),
            'maxMb' => self::MAX_KB / 1024,
        ]);
    }

    /** Upload, store privately, read, then go to the review step. */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', self::MIMES), 'max:'.self::MAX_KB],
        ]);
        $file = $request->file('file');
        $user = $request->user();

        $path = $file->storeAs("certificates/{$user->id}", Str::uuid().'.'.strtolower($file->extension()), SalaryCertificate::DISK);
        $certificate = $user->salaryCertificates()->create([
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
        $this->reader->read($certificate);

        $url = route('certificates.show', $certificate);

        return $request->expectsJson() ? response()->json(['redirect' => $url]) : redirect()->to($url);
    }

    /** The review step: the document beside the figures read from it. */
    public function show(Request $request, SalaryCertificate $certificate): View
    {
        $this->authorizeOwner($request, $certificate);
        Money::useGrouping($request->cookie('kh_grouping'));

        $components = collect(config('salary_certificate.components'))->map(fn ($c, $k) => ['key' => $k, 'label' => __($c['label'])])->values()->all();

        return view('certificates.review', [
            'certificate' => $certificate,
            'boot' => [
                'data' => CertificateForm::defaults($certificate, $request->user()),
                'found' => $certificate->data ? [] : ($certificate->extracted['found'] ?? []),
                'evidence' => $certificate->data ? [] : ($certificate->extracted['evidence'] ?? []),
                'certificateTotal' => $certificate->extracted['gross_total'] ?? null,
                'options' => [
                    'years' => TaxOptions::years(),
                    'categories' => TaxOptions::categories(),
                    'instruments' => TaxOptions::instruments(),
                    'perks' => TaxOptions::perks(),
                    'filing' => TaxOptions::filing(),
                    'components' => $components,
                ],
                'routes' => [
                    'save' => route('certificates.update', $certificate),
                    'calculate' => route('calculate'),
                ],
            ],
        ]);
    }

    /** The original file, only for its owner, never cached by shared caches. */
    public function file(Request $request, SalaryCertificate $certificate): StreamedResponse
    {
        $this->authorizeOwner($request, $certificate);

        return Storage::disk(SalaryCertificate::DISK)->response($certificate->path, $certificate->original_name, [
            'Content-Type' => $certificate->mime,
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function update(CertificateDataRequest $request, SalaryCertificate $certificate): JsonResponse|RedirectResponse
    {
        $this->authorizeOwner($request, $certificate);

        $certificate->update([
            'data' => CertificateForm::complete($request->validated()),
            'status' => SalaryCertificate::CONFIRMED,
            'confirmed_at' => now(),
        ]);
        $url = route('certificates.report', $certificate);

        return $request->expectsJson() ? response()->json(['redirect' => $url]) : redirect()->to($url);
    }

    public function report(Request $request, SalaryCertificate $certificate): View|RedirectResponse
    {
        $this->authorizeOwner($request, $certificate);
        if ($certificate->status !== SalaryCertificate::CONFIRMED) {
            return redirect()->route('certificates.show', $certificate)->with('status', __('Check the figures first; the report is made from what you confirm.'));
        }
        Money::useGrouping($request->cookie('kh_grouping'));

        return view('certificates.report', [
            'certificate' => $certificate,
            'r' => $this->reports->build($certificate->data, $certificate->extracted),
        ]);
    }

    /** Read the file again, for example after the OCR service was unavailable. */
    public function reread(Request $request, SalaryCertificate $certificate): RedirectResponse
    {
        $this->authorizeOwner($request, $certificate);
        $this->reader->read($certificate);

        return redirect()->route('certificates.show', $certificate)
            ->with('status', $certificate->status === SalaryCertificate::READ ? __('Read again. Check the figures.') : $certificate->ocr_error);
    }

    public function destroy(Request $request, SalaryCertificate $certificate): RedirectResponse
    {
        $this->authorizeOwner($request, $certificate);
        $certificate->delete();

        return redirect()->route('certificates.index')->with('status', __('Certificate and its file deleted.'));
    }

    private function authorizeOwner(Request $request, SalaryCertificate $certificate): void
    {
        abort_unless($certificate->belongsToUser($request->user()), 404);
    }
}
