<div class="msg91-composer" data-msg91-composer data-preview-url="{{ $previewUrl }}" data-store-url="{{ $storeUrl }}" data-records-url="{{ $recordsUrl }}" hidden>
    <div class="msg91-composer__backdrop" data-msg91-close></div>
    <section class="msg91-composer__dialog" role="dialog" aria-modal="true" aria-labelledby="msg91-composer-title">
        <header class="msg91-composer__header">
            <div>
                <p class="msg91-eyebrow">MSG91 WhatsApp</p>
                <h2 id="msg91-composer-title">Bulk WhatsApp</h2>
                <p>Only approved templates for your verified integrated number are available.</p>
            </div>
            <button type="button" class="msg91-composer__close" aria-label="Close" data-msg91-close>×</button>
        </header>
        <form data-msg91-form>
            <div class="msg91-composer__summary" data-msg91-summary>Select one or more eligible leads to continue.</div>
            <label>Campaign name
                <input name="name" maxlength="191" required placeholder="e.g. October Follow-up Campaign">
            </label>
            <label>Approved WhatsApp template
                <select name="template_id" required disabled><option value="">Select recipients first</option></select>
            </label>
            <div class="msg91-composer__preview" data-msg91-preview hidden>
                <strong>Personalization preview</strong>
                <dl data-msg91-variables></dl>
            </div>
            <p class="msg91-composer__note">Campaigns are queued after confirmation. “Accepted” means MSG91 received the request; it does not mean delivery.</p>
            <p class="msg91-composer__error" role="alert" data-msg91-error hidden></p>
            <footer>
                <button type="button" class="msg91-button msg91-button--secondary" data-msg91-close>Cancel</button>
                <button type="submit" class="msg91-button" data-msg91-submit disabled>Queue campaign</button>
            </footer>
        </form>
    </section>
</div>
