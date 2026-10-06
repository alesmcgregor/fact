<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#101210">
    <title>{{ __('app.brand') }} — {{ __('app.eyebrow') }}</title>
    <meta name="description" content="{{ __('app.intro') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="site-shell" data-fact-app data-locale="{{ app()->getLocale() }}"
     data-create-url="{{ route('api.analyses.store') }}"
     data-show-template="{{ route('api.analyses.show', ['analysis' => '__ID__']) }}"
     data-messages="{{ json_encode(collect(__('app'))->only(['loading_title','loading_body','stage_queued','stage_validating_url','stage_fetching_content','stage_extracting_content','stage_searching_evidence','stage_evaluating_evidence','stage_generating_report','stage_complete','stage_failed','error_network','error_processing','validation_url','failure_provider_not_configured']), JSON_HEX_APOS | JSON_HEX_QUOT) }}">
    <header class="topbar">
        <a class="wordmark" href="{{ route('home') }}" aria-label="FACT home"><span class="mark">F</span><span>FACT<span class="wordmark-dot">.</span></span></a>
        <form class="language-form" method="post" action="{{ route('language.update') }}" data-language-form>
            @csrf
            <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
            <label class="sr-only" for="locale">{{ __('app.language') }}</label>
            <select id="locale" name="locale">
                @foreach (config('fact.locales') as $code => $name)
                    <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $name }}</option>
                @endforeach
            </select>
            <span class="select-chevron" aria-hidden="true">⌄</span>
        </form>
    </header>

    <main>
        <section class="hero" id="check">
            <div class="hero-orbit orbit-one"></div><div class="hero-orbit orbit-two"></div>
            <div class="hero-content">
                <div class="eyebrow"><span class="eyebrow-line"></span>{{ __('app.eyebrow') }}</div>
                <h1>{{ __('app.headline') }}</h1>
                <p class="hero-intro">{{ __('app.intro') }}</p>
                <form id="analysis-form" class="url-form" data-analysis-form>
                    <label class="sr-only" for="source-url">{{ __('app.input_label') }}</label>
                    <span class="url-icon" aria-hidden="true">↗</span>
                    <input id="source-url" name="url" type="url" inputmode="url" autocomplete="url" maxlength="2048" value="{{ is_string(request('url')) ? request('url') : '' }}" placeholder="{{ __('app.input_placeholder') }}" required>
                    <button class="check-button" type="submit"><span>{{ __('app.check_button') }}</span><span aria-hidden="true">↗</span></button>
                </form>
                <div class="analysis-options">
                    <label><input form="analysis-form" type="checkbox" name="refresh" @checked(request()->boolean('refresh'))> {{ __('app.refresh_analysis') }}</label>
                    <label><input form="analysis-form" type="checkbox" name="include_perspectives"> {{ __('app.include_perspectives') }}</label>
                    <p>{{ __('app.reuse_note') }}</p>
                    <details><summary>{{ __('app.supplied_transcript') }}</summary><p>{{ __('app.supplied_transcript_note') }}</p><textarea form="analysis-form" name="transcript" rows="5" minlength="40" maxlength="12000" aria-label="{{ __('app.supplied_transcript') }}"></textarea></details>
                </div>
                <p class="form-note"><span class="lock-icon" aria-hidden="true">◈</span>{{ __('app.public_only') }}</p>
                <p class="form-note">{{ __('app.youtube_transcript_note') }}</p>
                @if (config('fact.analysis_driver') !== 'openai')
                    <p class="preview-disclosure" role="status">{{ __('app.preview_mode') }}</p>
                @endif
                @error('url')<p class="inline-error">{{ $message }}</p>@enderror
                <div class="analysis-panel" data-analysis-panel hidden aria-live="polite">
                    <div class="analysis-heading"><span class="spinner" aria-hidden="true"></span><div><strong data-analysis-title>{{ __('app.loading_title') }}</strong><p data-analysis-message>{{ __('app.loading_body') }}</p></div></div>
                    <ol class="stage-list" data-stage-list></ol>
                    <div class="analysis-error" data-analysis-error hidden></div>
                </div>
            </div>
            <div class="hero-footnote"><span class="live-dot"></span>{{ __('app.trust_title') }}</div>
        </section>

        <section class="principles section-wrap">
            <div class="section-kicker">01 <span></span> {{ __('app.how_title') }}</div>
            <div class="principle-grid">
                <article class="principle-card"><div class="card-index">A</div><div class="mini-glyph glyph-claims">≋</div><h2>{{ __('app.step_claims') }}</h2><p>{{ __('app.step_claims_body') }}</p></article>
                <article class="principle-card"><div class="card-index">B</div><div class="mini-glyph glyph-sources">⌕</div><h2>{{ __('app.step_sources') }}</h2><p>{{ __('app.step_sources_body') }}</p></article>
                <article class="principle-card"><div class="card-index">C</div><div class="mini-glyph glyph-context">◌</div><h2>{{ __('app.step_context') }}</h2><p>{{ __('app.step_context_body') }}</p></article>
            </div>
        </section>

        <section class="registry section-wrap">
            <div class="section-kicker">02 <span></span> {{ __('app.registry_title') }}</div>
            <div class="registry-panel"><div><h2>{{ __('app.registry_title') }}</h2><p>{{ __('app.registry_body') }}</p></div>
                <div class="country-list"><span class="country-chip">{{ __('app.global_sources') }}</span>
                @foreach ($countries as $country)
                    <div class="country-chip"><span class="country-flag">{{ $country->flag }}</span><span>{{ __('app.country_'.$country->code) }}</span><span class="country-code">{{ $country->code }}</span></div>
                @endforeach
                </div>
            </div>
        </section>
    </main>

    <footer class="footer section-wrap"><a class="wordmark footer-mark" href="{{ route('home') }}"><span class="mark">F</span><span>FACT<span class="wordmark-dot">.</span></span></a><p>{{ __('app.footer_line') }}</p><div class="footer-meta"><span>{{ __('app.footer_method') }}</span><span class="footer-separator">/</span><span>{{ __('app.footer_privacy') }}</span><span class="footer-copy">© {{ now()->year }} FACT</span></div></footer>
</div>
</body>
</html>
