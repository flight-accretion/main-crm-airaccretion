document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.crm-targets'); if (!page) return;
    document.body.classList.add('crm-targets-page');
    const editor = page.querySelector('#target-editor');
    const form = page.querySelector('#target-form');
    const base = page.dataset.baseUrl.replace(/\/$/,'');
    const field = name => form?.elements.namedItem(name);
    const enabled = form?.querySelector('input[type=checkbox][name=incentive_enabled]');
    const allProducts = form?.querySelector('input[type=checkbox][name=incentive_all_products]');
    const productInputs = form ? [...form.querySelectorAll('[name="incentive_product_ids[]"]')] : [];
    const productList = page.querySelector('#target-product-options');
    const productToggle = page.querySelector('#target-product-toggle');
    function closeProducts() { if(productList) productList.hidden=true; productToggle?.setAttribute('aria-expanded','false'); }
    function syncSettings() {
        if(!enabled) return;
        page.querySelector('#target-incentive-fields').hidden=!enabled.checked;
        const role=field('sales_executive_id').selectedOptions[0]?.dataset.role || '';
        const operation=/Operations/.test(role), manager=['Sales Manager','Senior Sales Manager'].includes(role);
        const radios=[...form.querySelectorAll('[name=incentive_basis]')];
        radios.forEach(radio=>{radio.disabled=(operation&&radio.value!=='company')||(!manager&&radio.value==='team');});
        if(operation)field('incentive_basis').value='company';
        else if(field('incentive_basis').value==='team'&&!manager)field('incentive_basis').value='individual';
        page.querySelector('#target-product-field').hidden=field('incentive_basis').value!=='company';
        const teamMode=field('incentive_basis').value==='team';
        page.querySelector('#target-team-rate-field').hidden=!teamMode;
        page.querySelector('#target-multiplier-label').textContent=teamMode?'Manager incentive multiplier on own sales':'Incentive multiplier';
        field('incentive_team_multiplier').required=enabled.checked&&teamMode;
        page.querySelector('#target-team-rate-percent').textContent=((Number(field('incentive_team_multiplier').value)||0)*100).toFixed(2)+'%';
        page.querySelector('#target-rate-percent').textContent=((Number(field('incentive_multiplier').value)||0)*100).toFixed(2)+'%';
        field('incentive_multiplier').required=enabled.checked;
        productToggle.querySelector('span').textContent=allProducts.checked?'All products':(productInputs.filter(input=>input.checked).map(input=>input.parentElement.textContent.trim()).join(', ')||'Select products');
    }
    function showCreate(employee) {
        if(!editor)return;
        form.reset();form.action=base;field('_method').value='POST';page.querySelector('#target-editor-title').textContent='Assign Target';
        form.querySelector('.target-form-error').hidden=true;
        if(employee)field('sales_executive_id').value=employee;
        closeProducts();syncSettings();editor.showModal();
    }
    page.querySelector('#target-assign')?.addEventListener('click',()=>showCreate());
    page.querySelectorAll('[data-assign-person]').forEach(button=>button.onclick=()=>showCreate(button.dataset.assignPerson));
    page.querySelectorAll('[data-open]').forEach(button=>button.onclick=()=>page.querySelector('#'+button.dataset.open)?.showModal());
    page.querySelectorAll('[data-close]').forEach(button=>button.onclick=()=>button.closest('dialog').close());
    if(form){
        form.addEventListener('change',syncSettings);form.addEventListener('input',syncSettings);
        if(allProducts)allProducts.onchange=()=>productInputs.forEach(input=>input.checked=allProducts.checked);
        productInputs.forEach(input=>input.onchange=()=>{allProducts.checked=productInputs.every(product=>product.checked);});
        if(productToggle)productToggle.onclick=()=>{productList.hidden=!productList.hidden;productToggle.setAttribute('aria-expanded',String(!productList.hidden));};
        document.addEventListener('click',event=>{if(!event.target.closest('#target-product-field'))closeProducts();});
        editor.addEventListener('keydown',event=>{if(event.key==='Escape'&&productList&&!productList.hidden){event.preventDefault();event.stopPropagation();closeProducts();productToggle.focus();}});
        form.onsubmit=async event=>{
            if(field('_method').value!=='PUT')return;
            event.preventDefault(); const button=page.querySelector('#target-save'), error=form.querySelector('.target-form-error');
            button.disabled=true;error.hidden=true;
            try {
                const response=await fetch(form.action,{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
                const data=await response.json();
                if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Target could not be saved.');
                location.reload();
            } catch(problem){error.textContent=problem.message;error.hidden=false;button.disabled=false;}
        };
    }
    page.querySelectorAll('[data-edit]').forEach(button=>button.onclick=async()=>{
        if(!editor)return;button.disabled=true;
        try{
            const response=await fetch(button.dataset.edit,{credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
            if(!response.ok)throw new Error('Target could not be loaded.');
            const {target}=await response.json();showCreate();form.action=base+'/'+encodeURIComponent(target.id);field('_method').value='PUT';page.querySelector('#target-editor-title').textContent='Edit Target';
            ['sales_executive_id','year','month','target_amount','description','status'].forEach(name=>field(name).value=target[name]??'');
            if(enabled){enabled.checked=Boolean(target.incentive_enabled);field('incentive_multiplier').value=target.incentive_multiplier??'0.002';field('incentive_team_multiplier').value=target.incentive_team_multiplier??'0.002';field('incentive_basis').value=target.incentive_basis||'individual';allProducts.checked=!(target.incentive_product_ids||[]).length;productInputs.forEach(input=>input.checked=allProducts.checked||(target.incentive_product_ids||[]).includes(input.value));}
            syncSettings();
        }catch(error){const notice=document.createElement('p');notice.className='alert alert-danger';notice.setAttribute('role','alert');notice.textContent=error.message;page.querySelector('.target-heading').after(notice);}
        finally{button.disabled=false;}
    });
    page.querySelectorAll('[data-delete]').forEach(button=>button.onclick=()=>{const modal=page.querySelector('#target-delete-dialog');modal.querySelector('form').action=button.dataset.delete;modal.showModal();});
    const search=page.querySelector('#target-search'), size=page.querySelector('#target-page-size'), rows=[...page.querySelectorAll('[data-target-row]')];let currentPage=1,sortColumn=null,sortDirection=1;
    function renderRows(){
        const filtered=rows.filter(row=>row.textContent.toLowerCase().includes(search.value.trim().toLowerCase()));
        if(sortColumn!==null){filtered.sort((a,b)=>{
            const value=row=>(sortColumn===0?row.cells[0].querySelector('.target-person strong'):row.cells[sortColumn]).textContent.trim();
            const left=value(a),right=value(b);
            if([1,2,3,5].includes(sortColumn)){const amount=text=>Number((text.match(/[\d,.]+/)||['0'])[0].replace(/,/g,''));return (amount(left)-amount(right))*sortDirection;}
            return left.localeCompare(right,undefined,{numeric:true,sensitivity:'base'})*sortDirection;
        });filtered.forEach(row=>row.parentElement.append(row));}
        const limit=size.value==='all'?(filtered.length||1):Number(size.value),pages=Math.max(1,Math.ceil(filtered.length/limit));currentPage=Math.min(pages,currentPage);
        rows.forEach(row=>row.hidden=true);filtered.slice((currentPage-1)*limit,currentPage*limit).forEach(row=>row.hidden=false);
        page.querySelector('#target-empty').hidden=filtered.length>0;
        page.querySelector('#target-record-count').textContent=filtered.length?`${(currentPage-1)*limit+1}-${Math.min(filtered.length,currentPage*limit)} of ${filtered.length}`:'0 records';
        const pagination=page.querySelector('#target-pagination');pagination.replaceChildren();if(pages>1){
            for(const [label,next,disabled] of [['Previous',currentPage-1,currentPage===1],['Next',currentPage+1,currentPage===pages]]){const button=document.createElement('button');button.type='button';button.textContent=label;button.disabled=disabled;button.onclick=()=>{currentPage=next;renderRows();};pagination.append(button);}
        }
    }
    search.oninput=()=>{currentPage=1;renderRows();};size.onchange=()=>{currentPage=1;renderRows();};renderRows();
    page.querySelectorAll('[data-sort-column]').forEach(button=>button.onclick=()=>{const column=Number(button.dataset.sortColumn);sortDirection=sortColumn===column?-sortDirection:1;sortColumn=column;currentPage=1;page.querySelectorAll('th[aria-sort]').forEach(th=>th.setAttribute('aria-sort','none'));button.closest('th').setAttribute('aria-sort',sortDirection===1?'ascending':'descending');renderRows();});
    const nav=page.querySelector('.target-mobile-nav'),more=page.querySelector('#target-mobile-more'),toggle=page.querySelector('#target-more-toggle');
    const sidebar=document.querySelector('.app-sidebar .main-menu'),links=sidebar?[...sidebar.querySelectorAll('a[href]')]:[];
    const authorized=pattern=>links.find(link=>pattern.test(new URL(link.getAttribute('href'),location.href).pathname));
    for(const [key,pattern] of Object.entries({dashboard:/(?:sales-dashboard|operations(?:\/dashboard)?|hr)\/?$/,followup:/(?:upcoming-follow-up|vendor-followups)\/?$/})){
        const link=nav.querySelector(`[data-target-bottom="${key}"]`),source=authorized(pattern);if(source)link.href=source.href;else link.remove();
    }
    nav.querySelectorAll('a[href]').forEach(link=>{if(!links.some(source=>new URL(source.getAttribute('href'),location.href).pathname===new URL(link.href).pathname))link.remove();});
    nav.style.gridTemplateColumns=`repeat(${nav.children.length},minmax(0,1fr))`;
    const bottomPaths=new Set([...nav.querySelectorAll('a[href]')].map(link=>new URL(link.href).pathname));
    function menuItem(item){const anchor=[...item.children].find(child=>child.tagName==='A');if(!anchor)return null;
        const submenu=[...item.children].find(child=>child.tagName==='UL'),label=(anchor.querySelector('.side-menu__label')||anchor).textContent.trim();
        if(submenu){const children=[...submenu.children].map(menuItem).filter(Boolean);if(!children.length)return null;const group=document.createElement('details'),summary=document.createElement('summary');summary.textContent=label;group.append(summary,...children);return group;}
        const url=new URL(anchor.getAttribute('href'),location.href);if(!['http:','https:'].includes(url.protocol)||bottomPaths.has(url.pathname))return null;
        const link=document.createElement('a');link.href=url.href;link.textContent=label;if(anchor.target==='_blank'){link.target='_blank';link.rel='noopener';}return link;}
    if(sidebar)more.replaceChildren(...[...sidebar.children].map(menuItem).filter(Boolean));
    function closeMore(){more.hidden=true;toggle.setAttribute('aria-expanded','false');}
    toggle.onclick=()=>{more.hidden=!more.hidden;toggle.setAttribute('aria-expanded',String(!more.hidden));};
    document.addEventListener('click',event=>{if(!more.contains(event.target)&&!toggle.contains(event.target))closeMore();});
    document.addEventListener('keydown',event=>{if(event.key==='Escape')closeMore();});
    syncSettings();if(editor&&page.dataset.openCreate==='1')editor.showModal();
});
