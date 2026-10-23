# Service Catalog Design

The Services list and Add/Edit Service pages use the shared Kimi tokens and scoped service CSS/JavaScript. The existing service routes, validation, pricing accessors, product storage, extra-service pivot sync, cache invalidation, save redirects and role permissions remain in place. No migration or new pricing/status policy is introduced.

## Usage

- Summary counts come from the CRM catalog. Filters opens the initially collapsed name/status/product form; products are a dropdown, not a strip of tabs. Filters use GET requests. The rows selector defaults to 10, and previous/next controls paginate the matching rendered results. Missing-price rows appear first without changing their stored prices or activation eligibility.
- View Service opens a read-only popup with product, prices/fees, description, registration note, terms, timestamps and attached extra services. Escape, Close and outside-click dismiss it. The standalone details URL remains usable, including opening the link in a new tab. Read failures clear stale content and display an error.
- Every row always has Activate or Deactivate as appropriate, with confirmation before the existing PATCH action. Delete remains visible only to Super Admin, as before. Mutations reload the current filtered page to keep counts and status labels in sync.
- Edit opens the existing edit route. Set price opens the same route and focuses the amount field. It is not an independent pricing workflow.
- Add/Edit retain the original field names and rich-text Quill terms editor. If Quill fails to load, a textarea remains available. Search hides unmatched add-ons without dropping checked selections; their prices still come from the extra-service master.
- The preview shows gross service amount, fees, original amount and the total with selected add-ons. It does not store a second amount or automatically charge add-ons. Save keeps the existing redirect (new service returns to Add Service).

## Differences From the Reference

The sample-data banner, invented terms templates and unsupported note-variable chips are not included. There is no new View on form/website action: View Service is the popup. Zero prices are highlighted, not blocked. Extra-service master prices cannot be edited from this form. The single existing Save action is retained instead of adding an unsupported Save and add another variant. On mobile the preview stacks below the form and table rows become labelled cards; the reference screenshot's horizontal clipping is not reproduced.

## Verification

`ServicesDesignTest` uses an isolated SQLite database to exercise dynamic counts/filtering, popup and standalone views, create/update and pivot links, validation repopulation, zero-price compatibility, vendor-only extra rejection, role access and status/delete contracts.

Render synthetic browser fixtures:

```bash
php tests/Fixtures/services/render.php /absolute/output/path
node tests/Browser/ServicesDesign.standalone.cjs /absolute/output/path
```

The browser suite mocks requests and checks 1440/1024/768/390/320px layouts, modal success/error/close, pagination, mutation identity/cancel/error, fee/add-on calculations, real form-click submission, Quill payload and edit preselection. It never calls live CRM endpoints. Set PLAYWRIGHT_MODULE and CHROME_PATH when using a different runtime installation.

Include the new partials, CSS and JavaScript in the release. The controller retains the existing product-filter integration with ProductController::serviceProductIds; include the associated Product work when preparing this shared dirty checkout for release. Refresh compiled views through the normal deployment process. No code is deployed automatically by these checks.
