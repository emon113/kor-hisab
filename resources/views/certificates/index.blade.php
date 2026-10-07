@use('App\Support\Money')
@use('App\Models\SalaryCertificate')
<x-layout :title="__('Salary certificate report')">
    @push('scripts')
        <script src="{{ asset('js/certificates.js') }}?v={{ @filemtime(public_path('js/certificates.js')) }}"></script>
        <script>
            document.addEventListener('click', (e) => {
                const del = e.target.closest('[data-confirm]');
                if (del && !confirm(del.dataset.confirm)) e.preventDefault();
            });
        </script>
    @endpush

    <div class="page-head reveal">
        <div>
            <h1>{{ __('Salary certificate report') }}</h1>
            <p>{{ __('Upload the salary certificate your employer gives you for the year. We read the figures, you check them, and you get a full tax report with charts, the legal ways to pay less, and where every figure goes on your return.') }}</p>
        </div>
    </div>

    <div class="split cert-layout">
        <section class="panel uploader reveal" style="--i:1"
                 x-data="certificateUpload(@js(['maxBytes' => $maxMb * 1048576, 'maxLabel' => Money::digits((string) $maxMb).' MB', 'routes' => ['store' => route('certificates.store')]]))">
            <ol class="stepper" aria-label="{{ __('Steps') }}">
                <li :class="{ on: true, done: stage === 'uploading' || stage === 'reading' }"><span>{{ Money::digits('1') }}</span> {{ __('Upload') }}</li>
                <li :class="{ on: stage === 'reading' }"><span>{{ Money::digits('2') }}</span> {{ __('Read') }}</li>
                <li><span>{{ Money::digits('3') }}</span> {{ __('Check and get the report') }}</li>
            </ol>

            <label class="dropzone" :class="{ dragging, filled: file }"
                   @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)"
                   x-show="stage === 'idle' || stage === 'picked'">
                <input type="file" class="sr-only" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf" @change="pick($event.target.files[0])">
                <template x-if="!file">
                    <div class="dropzone-empty">
                        <span class="dropzone-icon"><x-icon name="upload" size="26" /></span>
                        <b>{{ __('Drop the certificate here, or choose a file') }}</b>
                        <small>{{ __('Photo (JPG, PNG) or PDF, up to :size. A clear, flat photo reads best.', ['size' => Money::digits((string) $maxMb).' MB']) }}</small>
                    </div>
                </template>
                <template x-if="file">
                    <div class="dropzone-file">
                        <img x-show="preview" :src="preview" alt="">
                        <span x-show="isPdf" class="pdf-badge"><x-icon name="guide" size="28" /> PDF</span>
                        <div><b x-text="file.name"></b><small x-text="sizeLabel"></small></div>
                    </div>
                </template>
            </label>

            <div class="upload-progress" x-show="stage === 'uploading' || stage === 'reading'" x-transition.opacity>
                <div class="scan" aria-hidden="true"><img x-show="preview" :src="preview" alt=""><span x-show="isPdf"><x-icon name="guide" size="40" /></span><i class="scan-line"></i></div>
                <p x-show="stage === 'uploading'" x-text="KH.t('Uploading… :pct%', { pct: KH.num(progress) })"></p>
                <p x-show="stage === 'reading'">{{ __('Reading the certificate… this takes a few seconds.') }}</p>
                <div class="meter"><i :style="'width:' + (stage === 'reading' ? 100 : progress) + '%'" :class="{ indeterminate: stage === 'reading' }"></i></div>
            </div>

            <p class="error" x-show="error" x-text="error" role="alert"></p>

            <div class="uploader-actions" x-show="stage === 'picked'">
                <button type="button" class="btn btn-quiet" @click="reset()">{{ __('Choose another') }}</button>
                <button type="button" class="btn btn-primary" @click="upload()"><x-icon name="upload" size="18" /> {{ __('Upload and read') }}</button>
            </div>

            <p class="privacy-note">
                <x-icon name="lock" size="14" />
                @if ($reading)
                    {{ __('Your file is stored privately with your account and only you can open it. To read it, the file is sent to the OCR.space service; you can delete it any time.') }}
                @else
                    {{ __('Your file is stored privately with your account. Automatic reading is off on this server, so you will type the figures in.') }}
                @endif
            </p>
        </section>

        <aside class="panel cert-help reveal" style="--i:2">
            <h3>{{ __('What to upload') }}</h3>
            <ul class="checklist" style="font-size:var(--t-sm)">
                <li>{{ __('The yearly salary certificate from HR, for July to June.') }}</li>
                <li>{{ __('It should show basic pay, allowances, bonuses and the tax deducted at source.') }}</li>
                <li>{{ __('Photograph it flat, in good light, with the whole page in view.') }}</li>
            </ul>
        </aside>
    </div>

    @if ($certificates->isNotEmpty())
        <section class="sec reveal" style="--i:3" aria-labelledby="h-mine">
            <div class="sec-head"><h2 id="h-mine">{{ __('Your certificates') }}</h2></div>
            <ul class="cert-list">
                @foreach ($certificates as $row)
                    @php
                        $c = $row['certificate'];
                        [$tone, $label] = match ($c->status) {
                            SalaryCertificate::CONFIRMED => ['good', __('Report ready')],
                            SalaryCertificate::READ => ['watch', __('Check the figures')],
                            default => ['info', __('Enter the figures')],
                        };
                    @endphp
                    <li class="cert-item">
                        <span class="cert-thumb"><x-icon :name="$c->isPdf() ? 'guide' : 'image'" size="20" /></span>
                        <div class="calc-title">
                            <a href="{{ $c->status === SalaryCertificate::CONFIRMED ? route('certificates.report', $c) : route('certificates.show', $c) }}">{{ $c->title() }}</a>
                            <span>{{ __('Uploaded :when', ['when' => Money::digits($c->created_at->diffForHumans())]) }} · <span class="pill tone-{{ $tone }}">{{ $label }}</span></span>
                        </div>
                        @if ($row['summary'])
                            <dl class="calc-fig"><dt>{{ __('Salary for tax') }}</dt><dd>{{ Money::bdt($row['summary']['gross']) }}</dd></dl>
                            <dl class="calc-fig"><dt>{{ __('Tax for the year') }}</dt><dd class="tone-cost">{{ Money::bdt($row['summary']['tax']) }}</dd></dl>
                            <dl class="calc-fig">
                                <dt>{{ $row['summary']['payable'] >= 0 ? __('Left to pay') : __('Refund due') }}</dt>
                                <dd class="{{ $row['summary']['payable'] > 0 ? 'tone-cost' : 'tone-good' }}">{{ Money::bdt(abs($row['summary']['payable'])) }}</dd>
                            </dl>
                        @else
                            <span></span><span></span><span></span>
                        @endif
                        <div class="calc-actions">
                            <a href="{{ route('certificates.show', $c) }}" class="icon-btn" title="{{ __('Edit figures') }}" aria-label="{{ __('Edit figures') }}"><x-icon name="settings" size="16" /></a>
                            <form method="POST" action="{{ route('certificates.destroy', $c) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="icon-btn" title="{{ __('Delete') }}" aria-label="{{ __('Delete :title', ['title' => $c->title()]) }}" data-confirm="{{ __('Delete this certificate and its file? This can’t be undone.') }}"><x-icon name="trash" size="16" /></button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layout>
