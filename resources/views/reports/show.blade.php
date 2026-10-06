<!doctype html>
<html lang="{{ str_replace('_', '-', $analysis->locale) }}" dir="{{ $analysis->locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#101210">
    @php($reportTitle = $analysis->status->value === 'completed' ? __('app.brand').' — '.__('app.summary') : __('app.brand').' — '.__('app.report_not_ready'))
    <title>{{ $reportTitle }}</title>
    <meta name="description" content="{{ Str::limit($analysis->summary ?: __('app.report_not_ready'), 155) }}">
    <meta property="og:type" content="article"><meta property="og:title" content="{{ $reportTitle }}">
    <meta property="og:description" content="{{ Str::limit($analysis->summary ?: __('app.report_not_ready'), 155) }}">
    <meta property="og:url" content="{{ route('reports.show', ['analysis' => $analysis->public_id]) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="site-shell report-shell">
    <header class="topbar"><a class="wordmark" href="{{ route('home') }}"><span class="mark">F</span><span>FACT<span class="wordmark-dot">.</span></span></a><a class="back-link" href="{{ route('home') }}">← {{ __('app.brand') }}</a></header>
    <main class="report-main">
        <div class="report-topline"><div class="eyebrow"><span class="eyebrow-line"></span>{{ __('app.footer_line') }}</div><button class="share-button" type="button" data-share-url="{{ route('reports.show', ['analysis' => $analysis->public_id]) }}" data-copied="✓ {{ __('app.copied') }}" data-copy-failed="{{ __('app.copy_failed') }}"><span aria-hidden="true">↗</span> {{ __('app.share_report') }}</button></div>
        @if ($analysis->demo_mode)<div class="demo-banner"><span class="demo-symbol">i</span><div><strong>{{ __('app.demo_badge') }}</strong><p>{{ __('app.demo_summary') }}</p></div></div>@endif
        @if ($analysis->status->value !== 'completed')
            <section class="report-state"><div class="report-state-mark">{{ $analysis->status->value === 'failed' ? '!' : '…' }}</div><p class="section-kicker">{{ __('app.report_not_ready') }}</p><h1>{{ $analysis->status->value === 'failed' ? __('app.report_failed') : __('app.loading_title') }}</h1><p>{{ $analysis->status->value === 'failed' ? __('app.failure_'.$analysis->failure_code) : __('app.loading_body') }}</p>@if ($analysis->status->value === 'queued' || $analysis->status->value === 'running')<a class="text-link" href="{{ route('home') }}">{{ __('app.brand') }} ←</a>@endif</section>
        @else
            <section class="report-heading"><div class="report-overline">{{ __('app.analyzed_at') }} <time datetime="{{ $analysis->completed_at?->toIso8601String() }}">{{ $analysis->completed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i T') }}</time></div><h1>{{ __('app.summary') }}</h1><p class="report-summary">{{ $analysis->summary }}</p></section>
            <section class="result-overview">
                <div class="verdict-card"><span class="card-label">{{ __('app.evidence_status') }}</span><div class="verdict-status status-{{ $analysis->verification_status }}"><span class="status-symbol" aria-hidden="true">{{ in_array($analysis->verification_status, ['verified','strongly_supported'], true) ? '✓' : ($analysis->verification_status === 'contradicted' ? '!' : '◌') }}</span><span>{{ __('app.status_'.$analysis->verification_status) }}</span></div><p>{{ __('app.score_note') }}</p></div>
                <div class="score-card"><div class="score-head"><span class="card-label">{{ __('app.evidence_score') }}</span><span class="score-value">{{ $analysis->evidence_score ?? '—' }}<small>/100</small></span></div><div class="score-track"><span class="score-fill score-{{ (int) (round(($analysis->evidence_score ?? 0) / 10) * 10) }}"></span></div><p>{{ __('app.score_note') }}</p></div>
            </section>
            <section class="source-preview"><div class="section-kicker">{{ __('app.original_link') }} <span></span></div><a href="{{ $analysis->submitted_url }}" target="_blank" rel="noopener noreferrer">{{ $analysis->content_title ?: $analysis->submitted_url }} <span aria-hidden="true">↗</span></a>@if ($analysis->content_excerpt)<p>{{ Str::limit($analysis->content_excerpt, 420) }}</p>@endif</section>
            @if($analysis->content_metadata)<div class="source-provenance">{{ __('app.content_basis') }}: {{ __('app.transcript_'.($analysis->content_metadata['transcript'] ?? 'none')) }} @if($analysis->content_metadata['author'] ?? null) · {{ $analysis->content_metadata['author'] }} @endif</div>@endif
            @if($analysis->research_context['scope'] ?? null)<section class="research-context"><strong>{{ __('app.research_scope') }}</strong><p>{{ $analysis->research_context['scope'] }}</p>@if($analysis->research_context['countries'] ?? [])<span>{{ implode(' · ', $analysis->research_context['countries']) }}</span>@endif</section>@endif
            <a class="refresh-link" href="{{ route('home', ['url' => $analysis->submitted_url, 'refresh' => 1]) }}">{{ __('app.refresh_analysis') }} ↗</a>
            <section class="claims-section"><div class="section-kicker">{{ __('app.claims') }} <span class="count-pill">{{ $analysis->claims->count() }}</span></div>
                @forelse ($analysis->claims as $claim)
                    <article class="claim-card"><div class="claim-top"><span class="claim-number">{{ str_pad((string) $claim->position, 2, '0', STR_PAD_LEFT) }}</span><span class="classification-pill">{{ __('app.classification_'.$claim->classification) }}</span><span class="claim-verdict status-{{ $claim->verification_status }}">{{ __('app.status_'.$claim->verification_status) }}</span></div><h2>{{ $claim->statement }}</h2>@if ($claim->explanation)<p class="claim-explanation">{{ $claim->explanation }}</p>@endif
                        @if ($claim->evidence->isNotEmpty())<div class="evidence-list"><h3>{{ __('app.evidence') }}</h3><p class="source-summary-note">{{ __('app.source_summary_note') }}</p>@foreach ($claim->evidence as $evidence)<a class="evidence-item" href="{{ $evidence->url }}" target="_blank" rel="noopener noreferrer"><span class="evidence-type">{{ __('app.source_type_'.$evidence->source_type) }}</span><span class="evidence-title">{{ $evidence->title }}</span><span class="evidence-publisher">{{ $evidence->publisher }}@if ($evidence->published_at) · {{ $evidence->published_at->format('Y-m-d') }}@endif</span><span class="evidence-relationship">{{ __('app.'.$evidence->relationship) }}</span>@if ($evidence->summary)<span class="evidence-summary">{{ $evidence->summary }}</span>@endif<span class="evidence-arrow" aria-hidden="true">↗</span></a>@if($evidence->identity_url)<p class="source-provenance"><a href="{{ $evidence->identity_url }}" target="_blank" rel="noopener noreferrer">{{ __('app.publisher_identity') }} ↗</a></p>@endif @endforeach</div>@endif
                    </article>
                @empty
                    <div class="empty-claims"><span>◌</span><p>{{ __('app.no_claims') }}</p></div>
                @endforelse
            </section>
            @if ($analysis->manipulation_level)<section class="signals-section"><div class="section-kicker">{{ __('app.manipulation_signals') }} <span></span></div><div class="signal-level level-{{ $analysis->manipulation_level }}"><span class="signal-mark">◉</span><strong>{{ __('app.signal_'.$analysis->manipulation_level) }}</strong></div><p>{{ __('app.trust_body') }}</p>@if ($analysis->manipulation_signals)<ul class="signal-list">@foreach ($analysis->manipulation_signals as $signal)<li><strong>{{ __('app.signal_'.($signal['code'] ?? 'unsupported_attribution')) }}</strong><span>{{ $signal['detail'] ?? '' }}</span></li>@endforeach</ul>@endif</section>@endif
            @if ($analysis->limitations)<section class="limitations-section"><div class="section-kicker">{{ __('app.limitations') }} <span></span></div><ul>@foreach ($analysis->limitations as $limitation)<li>{{ $limitation }}</li>@endforeach</ul></section>@endif
            <section class="method-note"><span class="method-icon">i</span><p>{{ __('app.score_note') }} {{ __('app.trust_body') }}</p><p>{{ __('app.analyzed_at') }} · {{ $analysis->completed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i T') }}</p></section>
        @endif
    </main>
    <footer class="footer report-footer"><a class="wordmark footer-mark" href="{{ route('home') }}"><span class="mark">F</span><span>FACT<span class="wordmark-dot">.</span></span></a><p>{{ __('app.footer_line') }}</p><div class="footer-meta"><span>{{ __('app.footer_method') }}</span><span class="footer-separator">/</span><span>{{ __('app.footer_privacy') }}</span><span class="footer-copy">© {{ now()->year }} FACT</span></div></footer>
</div>
</body>
</html>

