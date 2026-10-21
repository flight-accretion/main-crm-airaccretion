(() => {
    const dialog = document.getElementById('rq-manage-dialog');
    if (!dialog) return;
    const body = dialog.querySelector('.rq-modal-body');
    let opener;
    let previousOverflow;
    document.querySelectorAll('[data-rq-manage]').forEach(button => {
        button.addEventListener('click', () => {
            const template = document.getElementById(button.dataset.rqManage);
            if (!template || dialog.open) return;
            opener = button;
            body.replaceChildren(template.content.cloneNode(true));
            previousOverflow = document.body.style.overflow;
            dialog.showModal();
            document.body.style.overflow = 'hidden';
            dialog.querySelector('.rq-modal-close').focus();
        });
    });
    dialog.querySelector('.rq-modal-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        const bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right
            || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
    });
    dialog.addEventListener('close', () => {
        document.body.style.overflow = previousOverflow;
        body.replaceChildren();
        opener?.focus();
    });
})();
