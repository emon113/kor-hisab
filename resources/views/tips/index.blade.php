@use('App\Support\Money')
<x-layout :title="__('Pay less tax, legally')">
    <div class="page-head reveal">
        <div>
            <h1>{{ __('Pay less tax, legally') }}</h1>
            <p>{{ __('Every saving here is a choice the Income Tax Act gives you, not a way around it. The calculator checks each one against your own numbers.') }}</p>
        </div>
        <a href="{{ route('home') }}" class="btn btn-primary">{{ __('Check them on my numbers') }}</a>
    </div>

    @if ($personal && $personal['tips'])
        <section class="sec reveal" style="--i:1" aria-labelledby="h-for-you">
            <div class="sec-head">
                <div>
                    <h2 id="h-for-you">{{ __('For you') }}</h2>
                    <p>{{ __('From your salary profile: :gross a year, tax :tax in :year.', ['gross' => Money::bdt($personal['gross']), 'tax' => Money::bdt($personal['tax']), 'year' => $personal['year']]) }}</p>
                </div>
            </div>
            <div class="tips">
                @foreach ($personal['tips'] as $i => $tip)
                    <article class="tip kind-{{ $tip['kind'] }} reveal" style="--i:{{ min($i, 8) }}">
                        <header>
                            <span class="tip-icon"><x-icon :name="$tip['icon']" size="18" /></span>
                            <span class="tip-kind">{{ $kinds[$tip['kind']] }}</span>
                            <span class="tip-saving">{{ __(':amount less', ['amount' => Money::bdt($tip['saving'])]) }}</span>
                        </header>
                        <h3>{{ $tip['title'] }}</h3>
                        <p>{{ $tip['about'] }}</p>
                        <small>{{ $tip['law'] }}</small>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @foreach ($groups as $kind => $tips)
        <section class="sec reveal" style="--i:{{ $loop->iteration + 1 }}" aria-labelledby="h-{{ $kind }}">
            <div class="sec-head"><h2 id="h-{{ $kind }}">{{ $kinds[$kind] }}</h2></div>
            <div class="tips">
                @foreach ($tips as $tip)
                    <article class="tip kind-{{ $kind }}">
                        <header><span class="tip-icon"><x-icon :name="$tip['icon']" size="18" /></span></header>
                        <h3>{{ $tip['title'] }}</h3>
                        <p>{{ $tip['about'] }}</p>
                        <small>{{ $tip['law'] }}</small>
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach

    <p class="callout warn" style="margin-top:8px">{{ __('Rules change with each budget. Confirm the details with NBR or a tax practitioner before acting on a large decision.') }}</p>
</x-layout>
