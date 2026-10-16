document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.crm-ops-work');
    if (!page) return;
    const date = page.querySelector('[name=date_filter]');
    const from = page.querySelector('[name=from_date]');
    const to = page.querySelector('[name=to_date]');
    const update = () => {
        const custom = date.value === 'custom';
        for (const field of [from, to]) {
            field.closest('div').hidden = !custom;
            field.disabled = !custom;
        }
    };
    date.addEventListener('change', update); update();
    const wrapper = page.querySelector('#operations-work-details');
    const body = page.querySelector('[data-work-details-body]');
    const title = page.querySelector('#operations-work-details-title');
    const cards = [...page.querySelectorAll('[data-work-select]')];
    const appliedFilters = JSON.parse(page.dataset.workFilters);
    let request, generation = 0, selected;
    async function loadDetails(url, focus = false) {
        request?.abort(); request = new AbortController(); const current = ++generation;
        wrapper.hidden = false; body.textContent = 'Loading cases...'; body.setAttribute('aria-busy','true');
        if (focus) {
            title.focus({preventScroll:true});
            wrapper.scrollIntoView({behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth',block:'start'});
        }
        try {
            const response = await fetch(url,{signal:request.signal,credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
            if (!response.ok) throw new Error('Unable to load case details. Please select the card again.');
            const result = await response.json();
            if (current !== generation) return;
            if (!result.success || typeof result.html !== 'string') throw new Error('Unable to load case details.');
            title.textContent = result.title; body.innerHTML = result.html;
        } catch (error) {
            if (current === generation && error.name !== 'AbortError') body.textContent = error.message;
        } finally { if (current === generation) body.removeAttribute('aria-busy'); }
    }
    cards.forEach(card => card.addEventListener('click', () => {
        selected = card;
        cards.forEach(item=>item.setAttribute('aria-pressed',String(item===card)));
        const url = new URL(page.dataset.workDetailsUrl,location.origin);
        for (const [key,value] of Object.entries(appliedFilters)) if (value != null) url.searchParams.set(key,value);
        url.searchParams.set('work_status',card.dataset.workSelect);
        if (card.dataset.workCategory) url.searchParams.set('work_type',card.dataset.workCategory);
        title.textContent = 'Case details'; loadDetails(url,true);
    }));
    body.addEventListener('click',event=>{
        const link = event.target.closest('[data-work-page]'); if (!link) return;
        event.preventDefault(); loadDetails(link.href,true);
    });
    page.querySelector('[data-close-work-details]').addEventListener('click',()=>{
        request?.abort(); generation++; wrapper.hidden = true; body.removeAttribute('aria-busy');
        cards.forEach(card=>card.setAttribute('aria-pressed','false')); selected?.focus({preventScroll:true});
    });
});
