(() => {
    const root = document.querySelector('.lead-detail');
    if (!root) return;
    const main = root.querySelector('#ld-main');
    const side = root.querySelector('#ld-side');
    const contact = [...root.querySelectorAll('.box')].find(box => box.querySelector('h5')?.textContent.trim() === 'Contact details');
    if (contact) {
        contact.id = 'ld-contact';
        contact.querySelectorAll('.space-y-2').forEach(field => {
            const value = field.querySelector('p')?.textContent.trim();
            if (!value || value === 'N/A') field.hidden = true;
        });
        side.append(contact);
        const completeness = root.querySelector('#ld-completeness');
        if (completeness) contact.querySelector('.box-body').append(completeness);
    }
    ['ld-services', 'ld-notes', 'ld-payments', 'ld-audit'].forEach(id => {
        const section = root.querySelector('#' + id);
        if (section) main.append(section);
    });
    const owner = root.querySelector('#ld-owner');
    if (owner) side.append(owner);
    root.querySelectorAll('#ld-payments table').forEach(table => {
        table.classList.remove('table-datatable', 'nowrap', 'responsive');
        const headings = [...table.querySelectorAll('th')].map(th => th.textContent.trim());
        table.querySelectorAll('tbody tr').forEach(row => [...row.children].forEach((td, i) => td.dataset.label = headings[i]));
    });
    root.classList.add('ld-ready');
})();
