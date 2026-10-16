(function () {
    'use strict';
    function init() {
        const root = document.querySelector('.crm-history');
        if (!root) return;
        const department = root.querySelector('[data-history-department]');
        department.addEventListener('change', function () {
            root.querySelector('[data-history-employee]').disabled = true;
            department.form.requestSubmit();
        });
        const dialog = root.querySelector('[data-history-download]');
        root.querySelector('[data-open-history-download]').addEventListener('click', () => dialog.showModal());
        dialog.querySelectorAll('[data-close-history-download]').forEach(button => button.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('click', event => { if (event.target === dialog && (event.clientX < dialog.getBoundingClientRect().left || event.clientX > dialog.getBoundingClientRect().right || event.clientY < dialog.getBoundingClientRect().top || event.clientY > dialog.getBoundingClientRect().bottom)) dialog.close(); });
        dialog.addEventListener('change', function () {
            const form = dialog.querySelector('form');
            const all = form.elements.scope.value === 'all';
            form.elements.employee_id.disabled = all;
            root.querySelector('[data-export-employee]').hidden = all;
            const both = form.elements.dataset.querySelector('[value="both"]');
            both.disabled = form.elements.format.value === 'csv';
            if (both.disabled && form.elements.dataset.value === 'both') form.elements.dataset.value = 'kpi';
        });
        const payload = root.querySelector('[data-history-rows]');
        if (!payload) return;
        const rows = JSON.parse(payload.textContent);
        const ns = 'http://www.w3.org/2000/svg';
        function node(tag, attributes, text) {
            const element = document.createElementNS(ns, tag);
            Object.entries(attributes || {}).forEach(([key, value]) => element.setAttribute(key, value));
            if (text !== undefined) element.textContent = text;
            return element;
        }
        function draw(container) {
            const target = container.dataset.historyChart === 'target';
            const width = Math.max(240, container.clientWidth), height = 270;
            const left = 50, right = 18, top = 28, bottom = 50;
            const plotWidth = width - left - right, plotHeight = height - top - bottom;
            const observed = rows.flatMap(row => target ? [row.target, row.achieved] : [row.score]).filter(value => value !== null);
            const maximum = target ? Math.max(1, Math.ceil(Math.max(0, ...observed) / 100000)) : 5;
            const svg = node('svg', {viewBox: `0 0 ${width} ${height}`, role: 'img', 'aria-label': target ? 'Monthly target and achieved sales, INR lakh' : 'Monthly KPI score out of five'});
            svg.appendChild(node('title', {}, target ? 'Target and achieved sales' : 'KPI history'));
            const detail = document.createElement('p'); detail.className = 'hp-chart-detail';
            detail.setAttribute('aria-live', 'polite');
            container.replaceChildren(svg, detail);
            const x = index => left + plotWidth * (index + 0.5) / rows.length;
            const y = value => top + plotHeight - (target ? value / 100000 : value) / maximum * plotHeight;
            const ticks = target ? 4 : 5;
            for (let i = 0; i <= ticks; i++) {
                const amount = maximum * i / ticks, yy = top + plotHeight - i / ticks * plotHeight;
                svg.appendChild(node('line', {x1: left, x2: width-right, y1: yy, y2: yy, stroke: 'var(--hp-line)', 'stroke-width': 1}));
                svg.appendChild(node('text', {x: left-10, y: yy+4, 'text-anchor': 'end'}, Number.isInteger(amount) ? amount : amount.toFixed(1)));
            }
            svg.appendChild(node('text', {x: left, y: 13}, target ? 'INR lakh' : 'KPI / 5'));
            const stride = Math.ceil(rows.length / Math.max(1, Math.min(width < 480 ? 4 : 12, Math.floor(plotWidth / 76))));
            let path = '', connected = false;
            rows.forEach((row, index) => {
                const xx = x(index);
                if (index % stride === 0 || (index === rows.length-1 && index % stride > 0 && index % stride >= stride/2)) svg.appendChild(node('text', {x: xx, y: height-26, 'text-anchor': 'middle'}, row.label.replace(/\d{4}$/, year => year.slice(-2))));
                if (target && row.target !== null) {
                    const bar = Math.min(18, plotWidth / rows.length * 0.3);
                    [row.target, row.achieved].forEach((value, series) => {
                        if (value === null) return;
                        const rect = node('rect', {x: xx+(series ? 1 : -bar-1), y: y(value), width: bar, height: Math.max(1,top+plotHeight-y(value)), rx: 2, fill: series ? (row.achievement >= 100 ? 'var(--hp-green)' : 'var(--hp-blue)') : 'var(--hp-navy)', opacity: series ? 1 : 0.22});
                        rect.appendChild(node('title', {}, row.label + ': ' + (series ? 'Achieved' : 'Target') + ' INR ' + Number(value).toLocaleString('en-IN')));
                        svg.appendChild(rect);
                        if (series && row.achievement !== null && (plotWidth/rows.length >= 32 || index % stride === 0)) svg.appendChild(node('text', {x:xx+1+bar/2,y:Math.max(16,y(value)-6),'text-anchor':'middle',style:row.achievement>=100?'fill:var(--hp-green)':''}, Math.round(row.achievement)+'%'));
                    });
                } else if (!target) {
                    path += (connected ? 'L' : 'M') + xx + ',' + y(row.score ?? 0) + ' '; connected = true;
                } else {
                    svg.appendChild(node('text', {x:xx,y:top+plotHeight+12,'text-anchor':'middle'}, '-'));
                }
            });
            if (!target) {
                svg.appendChild(node('path', {d: path, fill: 'none', stroke: 'var(--hp-navy)', 'stroke-width': 2.5}));
                rows.forEach((row, index) => {
                    const missing = row.score === null;
                    const dot = node('circle', {cx:x(index),cy:y(row.score ?? 0),r:missing?3.5:5,fill:missing?'var(--hp-surface)':'var(--hp-navy)',stroke:missing?'var(--hp-muted)':'var(--hp-surface)','stroke-width':1.5});
                    dot.appendChild(node('title',{},row.label+': '+(missing?'Not scored':row.score+'/5')));
                    svg.appendChild(dot);
                    if (!missing && index % stride === 0) svg.appendChild(node('text',{x:x(index),y:y(row.score)-12,'text-anchor':'middle'},row.score));
                });
            }
            svg.appendChild(node('text', {x: left+plotWidth/2, y: height-5, 'text-anchor': 'middle'}, 'Month'));
            const hit = node('rect', {x: left, y: top, width: plotWidth, height: plotHeight, fill: 'transparent'});
            function show(event) {
                const bounds = svg.getBoundingClientRect();
                const index = Math.max(0, Math.min(rows.length-1, Math.floor((event.clientX-bounds.left-left)/plotWidth*rows.length)));
                const row = rows[index];
                detail.textContent = row.label + ' - ' + (target ? row.target === null ? 'Target not assigned' : 'Target INR ' + Number(row.target).toLocaleString('en-IN') + ' | Achieved INR ' + Number(row.achieved).toLocaleString('en-IN') + ' | ' + (row.achievement === null ? 'N/A' : row.achievement + '%') : (row.score === null ? 'Not available' : row.score + '/5') + ' | ' + row.source);
            }
            hit.addEventListener('pointermove', show); hit.addEventListener('pointerdown', show); svg.appendChild(hit);
            if (!observed.length) detail.textContent = 'No recorded KPI scores for this period.';
        }
        const charts = Array.from(root.querySelectorAll('[data-history-chart]'));
        const observer = new ResizeObserver(entries => entries.forEach(entry => draw(entry.target)));
        charts.forEach(chart => { draw(chart); observer.observe(chart); });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
