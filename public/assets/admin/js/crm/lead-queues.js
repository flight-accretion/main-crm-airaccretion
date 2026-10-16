document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-lead-queue]');
    if (!root) return;
    const table = root.querySelector('[data-queue-table]');
    const rows = Array.from(table.querySelectorAll('[data-queue-row]'));
    const search = root.querySelector('[data-queue-search]');
    const size = root.querySelector('[data-queue-size]');
    const pager = root.querySelector('[data-queue-pages]');
    const searchable = new Map(rows.map(row => [row, row.textContent.toLocaleLowerCase()]));
    let page = 1, tab = 'all', sortColumn = null, descending = false, matching = rows;
    const mobileSort = root.querySelector('[data-queue-sort-select]');
    root.querySelectorAll('[data-queue-sort]').forEach(button => {
        for (const direction of ['asc','desc']) {
            const option = document.createElement('option'); option.value = button.dataset.queueSort+':'+direction;
            option.textContent = button.textContent.trim()+' ('+(direction === 'asc' ? 'ascending' : 'descending')+')'; mobileSort.append(option);
        }
    });
    const render = () => {
        const term = search.value.trim().toLocaleLowerCase();
        matching = rows.filter(row => (tab === 'all' || row.dataset.bucket === tab) && searchable.get(row).includes(term));
        if (sortColumn !== null) matching.sort((a,b) => {
            const first = a.cells[sortColumn], second = b.cells[sortColumn];
            const x = first.dataset.order ?? first.textContent.trim(), y = second.dataset.order ?? second.textContent.trim();
            const result = x !== '' && y !== '' && Number.isFinite(Number(x)) && Number.isFinite(Number(y)) ? Number(x)-Number(y) : x.localeCompare(y, undefined, {numeric:true});
            return descending ? -result : result;
        });
        const limit = size.value === 'all' ? Math.max(1, matching.length) : Number(size.value);
        const pages = Math.max(1, Math.ceil(matching.length / limit)); page = Math.min(page,pages);
        const start = (page-1)*limit;
        rows.forEach(row => { row.hidden = true; });
        matching.slice(start,start+limit).forEach(row => { row.hidden = false; table.tBodies[0].append(row); });
        root.querySelector('[data-queue-count]').textContent = `Showing ${matching.length ? start+1 : 0}-${Math.min(start+limit,matching.length)} of ${matching.length} entries`;
        root.querySelector('[data-queue-empty]').hidden = matching.length !== 0;
        pager.replaceChildren();
        const button = (label, target, disabled = false, current = false) => {
            const item = document.createElement('button'); item.type='button'; item.textContent=label; item.disabled=disabled;
            item.setAttribute('aria-label', /^\d+$/.test(label) ? `Page ${label}` : label);
            if(current) item.setAttribute('aria-current','page');
            item.addEventListener('click',()=>{page=target;render();}); pager.append(item);
        };
        button('Previous',page-1,page===1);
        const shown = [...new Set([1,...Array.from({length:5},(_,i)=>page+i-2).filter(n=>n>1&&n<pages),pages])].sort((a,b)=>a-b);
        let previous=0; shown.forEach(n=>{if(previous && n-previous>1){const gap=document.createElement('span');gap.textContent='...';pager.append(gap);}button(String(n),n,false,n===page);previous=n;});
        button('Next',page+1,page===pages);
    };
    search.addEventListener('input',()=>{page=1;render();}); size.addEventListener('change',()=>{page=1;render();});
    mobileSort.addEventListener('change',()=>{
        const [column,direction]=mobileSort.value.split(':');sortColumn=column === ''?null:Number(column);descending=direction==='desc';page=1;
        table.querySelectorAll('th').forEach(th=>th.removeAttribute('aria-sort'));render();
    });
    root.querySelectorAll('[data-queue-tab]').forEach(button=>button.addEventListener('click',()=>{
        tab=button.dataset.queueTab;page=1;
        root.querySelectorAll('[data-queue-tab]').forEach(item=>item.setAttribute('aria-pressed',String(item===button)));render();
    }));
    root.querySelectorAll('[data-queue-sort]').forEach(button=>button.addEventListener('click',()=>{
        const column=Number(button.dataset.queueSort);descending=sortColumn===column?!descending:false;sortColumn=column;
        mobileSort.value=column+':'+(descending?'desc':'asc');
        table.querySelectorAll('th').forEach(th=>th.removeAttribute('aria-sort'));
        button.closest('th').setAttribute('aria-sort',descending?'descending':'ascending');render();
    }));
    root.querySelector('[data-export-dnp]')?.addEventListener('click',event=>{
        if (!matching.length) return;
        const url=new URL(event.currentTarget.dataset.exportDnp,location.origin);
        const params=new URLSearchParams(new FormData(root.querySelector('#filter-form')));
        for(const [key,value] of params) if(value) url.searchParams.set(key,value);
        url.searchParams.set('ids',matching.map(row=>row.dataset.id).join(','));location.assign(url.href);
    });
    root.querySelector('[data-export-followups]')?.addEventListener('click',()=>{
        const escape = value => {let text=String(value).trim();if(/^[=+@\-\t\r]/.test(text))text="'"+text;return '"'+text.replaceAll('"','""')+'"';};
        const headings=Array.from(table.tHead.rows[0].cells).slice(0,-1).map(cell=>cell.textContent.trim());
        const lines=[headings,...matching.map(row=>Array.from(row.cells).slice(0,-1).map(cell=>cell.textContent.trim()))];
        const blob=new Blob(['\ufeff'+lines.map(line=>line.map(escape).join(',')).join('\r\n')],{type:'text/csv;charset=utf-8'});
        const link=document.createElement('a'),url=URL.createObjectURL(blob);link.href=url;link.download='follow-ups.csv';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
    });
    render();
});
