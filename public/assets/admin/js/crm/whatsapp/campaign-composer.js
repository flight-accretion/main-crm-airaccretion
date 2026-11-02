(() => {

    const composer =
        document.querySelector(
            '[data-msg91-composer]'
        );

    if (!composer) {
        return;
    }


    const csrf =
        document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;


    const endpoint =
        composer.dataset.previewUrl;


    const storeEndpoint =
        composer.dataset.storeUrl;


    const selected =
        new Set();


    const form =
        composer.querySelector(
            '[data-msg91-form]'
        );


    const summary =
        composer.querySelector(
            '[data-msg91-summary]'
        );


    const template =
        form.elements.template_id;


    const submit =
        composer.querySelector(
            '[data-msg91-submit]'
        );


    const submitLabel =
        composer.querySelector(
            '[data-msg91-submit-label]'
        );


    const error =
        composer.querySelector(
            '[data-msg91-error]'
        );


    const preview =
        composer.querySelector(
            '[data-msg91-preview]'
        );


    const variables =
        composer.querySelector(
            '[data-msg91-variables]'
        );


    const messagePreview =
        composer.querySelector(
            '[data-msg91-message-preview]'
        );


    const messageBody =
        composer.querySelector(
            '[data-msg91-message-body]'
        );


    let previewData =
        null;



    /* =====================================================
       ERROR
       ===================================================== */

    const showError =
        message => {

            error.textContent =
                message || '';

            error.hidden =
                !message;

        };



    /* =====================================================
       TOOLBAR
       ===================================================== */

    const refreshToolbar =
        () => {

            document
                .querySelectorAll(
                    '[data-msg91-selection-count]'
                )
                .forEach(
                    node => {

                        node.textContent =
                            String(
                                selected.size
                            );

                    }
                );


            document
                .querySelectorAll(
                    '[data-msg91-selection-toolbar]'
                )
                .forEach(
                    node => {

                        node.classList.toggle(
                            'is-visible',
                            selected.size > 0
                        );

                    }
                );

        };



    /* =====================================================
       RESET PREVIEW
       ===================================================== */

    const resetPreview =
        () => {

            previewData =
                null;


            template.innerHTML =
                '<option value="">Loading approved templates…</option>';


            template.disabled =
                true;


            submit.disabled =
                true;


            preview.hidden =
                true;


            messagePreview.hidden =
                true;


            messageBody.textContent =
                '';


            variables.replaceChildren();

        };



    /* =====================================================
       FETCH ELIGIBILITY / TEMPLATE PREVIEW
       ===================================================== */

    const fetchPreview =
        async () => {

            resetPreview();

            showError('');


            if (!selected.size) {

                summary.textContent =
                    'Select one or more eligible leads to continue.';

                return;

            }


            summary.textContent =
                'Checking recipient eligibility…';


            try {

                const response =
                    await fetch(
                        endpoint,
                        {
                            method:
                                'POST',

                            headers: {
                                'Content-Type':
                                    'application/json',

                                'Accept':
                                    'application/json',

                                'X-CSRF-TOKEN':
                                    csrf,
                            },

                            body:
                                JSON.stringify(
                                    {
                                        lead_ids:
                                            [
                                                ...selected
                                            ],
                                    }
                                ),
                        }
                    );


                const data =
                    await response.json();


                if (!response.ok) {

                    throw new Error(
                        data.message
                        ||
                        'Could not validate recipients.'
                    );

                }


                previewData =
                    data;


                summary.textContent =
                    `${data.eligible_count} eligible of ${data.selected_count} selected · `
                    +
                    `${data.excluded.duplicates} duplicate · `
                    +
                    `${data.excluded.invalid + data.excluded.suppressed + data.excluded.unauthorized} excluded`;


                template.innerHTML =
                    '<option value="">Select an approved template</option>';


                data.templates.forEach(
                    item => {

                        const option =
                            document.createElement(
                                'option'
                            );


                        option.value =
                            item.id;


                        option.textContent =
                            `${item.name} (${item.language})`;


                        template.append(
                            option
                        );

                    }
                );


                template.disabled =
                    data.templates.length === 0
                    ||
                    data.eligible_count === 0;


            } catch (exception) {

                summary.textContent =
                    'Recipient validation failed.';


                showError(
                    exception.message
                );

            }

        };



    /* =====================================================
       OPEN
       ===================================================== */

    const open =
        () => {

            composer.hidden =
                false;


            document.body.classList.add(
                'msg91-composer-open'
            );


            fetchPreview();

        };



    /* =====================================================
       CLOSE
       ===================================================== */

    const close =
        () => {

            composer.hidden =
                true;


            document.body.classList.remove(
                'msg91-composer-open'
            );

        };



    /* =====================================================
       INDIVIDUAL CHECKBOX
       ===================================================== */

    document.addEventListener(
        'change',
        event => {

            const box =
                event.target.closest(
                    '[data-msg91-lead-checkbox]'
                );


            if (!box) {
                return;
            }


            if (box.checked) {

                selected.add(
                    box.value
                );

            } else {

                selected.delete(
                    box.value
                );

            }


            refreshToolbar();

        }
    );



    /* =====================================================
       SELECT PAGE
       ===================================================== */

    document.addEventListener(
        'change',
        event => {

            const all =
                event.target.closest(
                    '[data-msg91-select-page]'
                );


            if (!all) {
                return;
            }


            document
                .querySelectorAll(
                    '[data-msg91-lead-checkbox]'
                )
                .forEach(
                    box => {

                        if (
                            box
                                .closest('tr')
                                ?.hidden
                        ) {
                            return;
                        }


                        box.checked =
                            all.checked;


                        if (all.checked) {

                            selected.add(
                                box.value
                            );

                        } else {

                            selected.delete(
                                box.value
                            );

                        }

                    }
                );


            refreshToolbar();

        }
    );



    /* =====================================================
       OPEN BUTTON
       ===================================================== */

    document
        .querySelectorAll(
            '[data-msg91-open]'
        )
        .forEach(
            button => {

                button.addEventListener(
                    'click',
                    open
                );

            }
        );



    /* =====================================================
       CLEAR SELECTION
       ===================================================== */

    document
        .querySelectorAll(
            '[data-msg91-clear]'
        )
        .forEach(
            button => {

                button.addEventListener(
                    'click',
                    () => {

                        selected.clear();


                        document
                            .querySelectorAll(
                                '[data-msg91-lead-checkbox]'
                            )
                            .forEach(
                                box => {

                                    box.checked =
                                        false;

                                }
                            );


                        refreshToolbar();

                    }
                );

            }
        );



    /* =====================================================
       CLOSE BUTTONS
       ===================================================== */

    composer
        .querySelectorAll(
            '[data-msg91-close]'
        )
        .forEach(
            button => {

                button.addEventListener(
                    'click',
                    close
                );

            }
        );



    /* =====================================================
       TEMPLATE CHANGE
       ===================================================== */

    template.addEventListener(
        'change',
        () => {

            renderSelectedTemplate();

            submit.disabled =
                !(
                    template.value
                    &&
                    previewData
                        ?.eligible_count
                );

        }
    );



    /* =====================================================
       SELECTED TEMPLATE PREVIEW
       ===================================================== */

    const variableLabels = {
        customer_name: 'Customer Name',
        customer_email: 'Customer Email',
        product_name: 'Product Name',
        service_date: 'Service Date',
        next_followup_date: 'Next Follow-up Date',
        salesperson_name: 'Salesperson Name',
    };


    const selectedTemplate =
        () => previewData?.templates?.find(
            item => item.id === template.value
        );


    const bodyForTemplate =
        item => {

            const component =
                Array.isArray(item?.components)
                    ? item.components.find(
                        candidate => String(candidate?.type || '').toUpperCase() === 'BODY'
                    )
                    : null;

            return String(component?.text || '');

        };


    const templateVariables =
        (item, body) => {

            const placeholders =
                [...new Set(
                    [...body.matchAll(/{{(\d+)}}/g)]
                        .map(match => Number(match[1]))
                )]
                .sort((left, right) => left - right);

            return placeholders.map(index => {

                const schema =
                    item?.variable_schema?.[`body_${index}`]
                    || {};

                return {
                    index,
                    field: schema.field || schema.source || 'customer_name',
                };

            });

        };


    const renderSelectedTemplate =
        () => {

            variables.replaceChildren();
            preview.hidden = true;
            messagePreview.hidden = true;
            messageBody.textContent = '';

            const item = selectedTemplate();
            const body = bodyForTemplate(item);

            if (!item || !body) {
                return;
            }

            const mappedVariables = templateVariables(item, body);
            const sample = previewData?.sample || {};

            messageBody.textContent = body.replace(
                /{{(\d+)}}/g,
                (placeholder, value) => {
                    const mapping = mappedVariables.find(
                        itemVariable => itemVariable.index === Number(value)
                    );

                    return sample[mapping?.field]
                        || placeholder;
                }
            );

            messagePreview.hidden = false;

            mappedVariables.forEach(itemVariable => {

                const dt = document.createElement('dt');
                const dd = document.createElement('dd');
                const label = variableLabels[itemVariable.field]
                    || itemVariable.field.replaceAll('_', ' ');

                dt.textContent = `{{${itemVariable.index}}} · ${label}`;
                dd.textContent = sample[itemVariable.field] || 'Not available';

                variables.append(dt, dd);

            });

            preview.hidden = mappedVariables.length === 0;

        };



    /* =====================================================
       SEND CAMPAIGN
       ===================================================== */

    form.addEventListener(
        'submit',
        async event => {

            event.preventDefault();


            showError('');


            if (
                !template.value
                ||
                !selected.size
            ) {
                return;
            }


            submit.disabled =
                true;


            if (submitLabel) {

                submitLabel.textContent =
                    'Sending…';

            }


            try {

                const response =
                    await fetch(
                        storeEndpoint,
                        {
                            method:
                                'POST',

                            headers: {
                                'Content-Type':
                                    'application/json',

                                'Accept':
                                    'application/json',

                                'X-CSRF-TOKEN':
                                    csrf,
                            },

                            body:
                                JSON.stringify(
                                    {
                                        name:
                                            form.elements
                                                .name
                                                .value,

                                        template_id:
                                            template.value,

                                        lead_ids:
                                            [
                                                ...selected
                                            ],
                                    }
                                ),
                        }
                    );


                const data =
                    await response.json();


                if (!response.ok) {

                    throw new Error(
                        data.message
                        ||
                        'Could not send campaign.'
                    );

                }


                window.location.assign(
                    composer.dataset.recordsUrl
                    +
                    '/'
                    +
                    data.campaign_id
                );

            } catch (exception) {

                showError(
                    exception.message
                );


                submit.disabled =
                    false;


                if (submitLabel) {

                    submitLabel.textContent =
                        'Send';

                }

            }

        }
    );



    /* =====================================================
       ESCAPE
       ===================================================== */

    document.addEventListener(
        'keydown',
        event => {

            if (
                event.key ===
                    'Escape'
                &&
                !composer.hidden
            ) {

                close();

            }

        }
    );

})();
