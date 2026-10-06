const app = document.querySelector('[data-fact-app]');

document.querySelector('[data-language-form] select')?.addEventListener('change', (event) => {
    event.currentTarget.form.requestSubmit();
});

if (app) {
    const form = app.querySelector('[data-analysis-form]');
    const panel = app.querySelector('[data-analysis-panel]');
    const stageList = app.querySelector('[data-stage-list]');
    const title = app.querySelector('[data-analysis-title]');
    const message = app.querySelector('[data-analysis-message]');
    const error = app.querySelector('[data-analysis-error]');
    const messages = JSON.parse(app.dataset.messages || '{}');
    const stages = ['queued', 'validating_url', 'fetching_content', 'extracting_content', 'searching_evidence', 'evaluating_evidence', 'generating_report', 'complete'];

    const renderStages = (currentStage, events = []) => {
        const eventStages = new Set(events.map((event) => event.stage));
        const currentIndex = Math.max(0, stages.indexOf(currentStage));
        stageList.replaceChildren(...stages.slice(0, currentStage === 'failed' ? currentIndex + 1 : currentIndex + 1).map((stage, index) => {
            const item = document.createElement('li');
            item.textContent = messages[`stage_${stage}`] || stage;
            if (eventStages.has(stage) || index < currentIndex) item.classList.add('is-done');
            if (index === currentIndex) item.classList.add('is-current');
            return item;
        }));
    };

    const poll = async (id) => {
        const endpoint = app.dataset.showTemplate.replace('__ID__', encodeURIComponent(id));
        for (;;) {
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) throw new Error(messages.error_network || 'The request could not be completed.');
            const payload = await response.json();
            const analysis = payload.data;
            renderStages(analysis.stage, analysis.events || []);
            if (analysis.status === 'completed') {
                window.location.assign(analysis.report_url);
                return;
            }
            if (analysis.status === 'failed') {
                title.textContent = messages.stage_failed;
                message.textContent = analysis.failure_message || messages.error_processing;
                if (app.querySelector('.spinner')) app.querySelector('.spinner').hidden = true;
                form.querySelector('button[type="submit"]').disabled = false;
                return;
            }
            await new Promise((resolve) => window.setTimeout(resolve, 1600));
        }
    };

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        error.hidden = true;
        error.textContent = '';
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        if (app.querySelector('.spinner')) app.querySelector('.spinner').hidden = false;
        panel.hidden = false;
        panel.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'nearest' });
        renderStages('queued');

        try {
            const response = await fetch(app.dataset.createUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ url: form.elements.url.value.trim(), locale: app.dataset.locale,
                    refresh: form.elements.refresh.checked, include_perspectives: form.elements.include_perspectives.checked,
                    transcript: form.elements.transcript.value.trim() || null }),
            });
            const payload = await response.json();
            if (!response.ok) {
                const messageText = Object.values(payload?.errors || {})[0]?.[0] || payload?.message || messages.error_network;
                throw new Error(messageText || messages.error_network);
            }
            const analysis = payload.data;
            title.textContent = messages.loading_title;
            message.textContent = messages.loading_body;
            await poll(analysis.id);
        } catch (requestError) {
            error.textContent = requestError.message || messages.error_network;
            error.hidden = false;
            button.disabled = false;
        }
    });
}

document.querySelectorAll('[data-share-url]').forEach((button) => {
    button.addEventListener('click', async () => {
        const url = button.dataset.shareUrl;
        const label = button.textContent.trim();
        try {
            if (navigator.share) await navigator.share({ title: document.title, url });
            else {
                await navigator.clipboard.writeText(url);
                button.textContent = button.dataset.copied || '✓ Copied';
                window.setTimeout(() => { button.textContent = label; }, 2200);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                button.textContent = button.dataset.copyFailed || 'Copy unavailable';
                window.setTimeout(() => { button.textContent = label; }, 2200);
            }
        }
    });
});
