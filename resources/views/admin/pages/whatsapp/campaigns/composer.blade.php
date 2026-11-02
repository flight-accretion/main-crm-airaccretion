<div
    class="msg91-composer"
    data-msg91-composer
    data-preview-url="{{ $previewUrl }}"
    data-store-url="{{ $storeUrl }}"
    data-records-url="{{ $recordsUrl }}"
    hidden
>

    <div
        class="msg91-composer__backdrop"
        data-msg91-close
    ></div>


    <section
        class="msg91-composer__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="msg91-composer-title"
    >

        {{-- HEADER --}}
        <header class="msg91-composer__header">

            <div class="msg91-composer__header-copy">

                <span class="msg91-composer__eyebrow">
                    <i class="ri-whatsapp-line"></i>
                    MSG91 WhatsApp
                </span>

                <h2 id="msg91-composer-title">
                    Bulk WhatsApp
                </h2>

                <p>
                    Send an approved WhatsApp template to selected CRM leads.
                </p>

            </div>


            <button
                type="button"
                class="msg91-composer__close"
                aria-label="Close"
                data-msg91-close
            >
                <i class="ri-close-line"></i>
            </button>

        </header>


        <form data-msg91-form>

            <div class="msg91-composer__body">


                {{-- ELIGIBILITY SUMMARY --}}
                <div
                    class="msg91-composer__summary"
                    data-msg91-summary
                >
                    Select one or more eligible leads to continue.
                </div>



                {{-- CAMPAIGN NAME --}}
                <div class="msg91-composer__field">

                    <label for="msg91-campaign-name">
                        Campaign Name
                    </label>


                    <div class="msg91-composer__input-wrap">

                        <i class="ri-megaphone-line"></i>

                        <input
                            id="msg91-campaign-name"
                            name="name"
                            maxlength="191"
                            required
                            placeholder="e.g. October Follow-up Campaign"
                            autocomplete="off"
                        >

                    </div>

                    <small>
                        Used only to identify this campaign inside CRM.
                    </small>

                </div>



                {{-- TEMPLATE --}}
                <div class="msg91-composer__field">

                    <label for="msg91-template-select">
                        Approved WhatsApp Template
                    </label>


                    <div class="msg91-composer__select-wrap">

                        <i class="ri-file-list-3-line"></i>

                        <select
                            id="msg91-template-select"
                            name="template_id"
                            required
                            disabled
                        >
                            <option value="">
                                Select recipients first
                            </option>
                        </select>

                    </div>

                    <small>
                        Only approved templates available for your active integrated number are shown.
                    </small>

                </div>



                {{-- SELECTED TEMPLATE MESSAGE --}}
                <div
                    class="msg91-composer__message-preview"
                    data-msg91-message-preview
                    hidden
                >
                    <div class="msg91-composer__preview-heading">
                        <div>
                            <span class="msg91-composer__preview-icon">
                                <i class="ri-message-3-line"></i>
                            </span>
                            <div>
                                <strong>Template Message Preview</strong>
                                <small>Sample message using one eligible lead.</small>
                            </div>
                        </div>
                    </div>
                    <p data-msg91-message-body></p>
                </div>



                {{-- PERSONALIZATION --}}
                <div
                    class="msg91-composer__preview"
                    data-msg91-preview
                    hidden
                >

                    <div class="msg91-composer__preview-heading">

                        <div>

                            <span class="msg91-composer__preview-icon">
                                <i class="ri-user-settings-line"></i>
                            </span>


                            <div>

                                <strong>
                                    Personalization Preview
                                </strong>

                                <small>
                                    Only variables used in the selected template are shown.
                                </small>

                            </div>

                        </div>

                    </div>


                    <dl data-msg91-variables></dl>

                </div>



                {{-- INFORMATION --}}
                <div class="msg91-composer__notice">

                    <i class="ri-information-line"></i>

                    <p>
                        Clicking Send creates the campaign and queues eligible recipients for MSG91 delivery.
                        Accepted means MSG91 received the request; it does not mean the message was delivered.
                    </p>

                </div>



                {{-- ERROR --}}
                <p
                    class="msg91-composer__error"
                    role="alert"
                    data-msg91-error
                    hidden
                ></p>

            </div>



            {{-- FOOTER --}}
            <footer class="msg91-composer__footer">

                <button
                    type="button"
                    class="msg91-composer__cancel"
                    data-msg91-close
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="msg91-composer__send"
                    data-msg91-submit
                    disabled
                >
                    <i class="ri-send-plane-fill"></i>

                    <span data-msg91-submit-label>
                        Send
                    </span>
                </button>

            </footer>

        </form>

    </section>

</div>
