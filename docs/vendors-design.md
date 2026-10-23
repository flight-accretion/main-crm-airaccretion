# Vendor List Design

The Vendors list uses shared Kimi tokens with separate Vendor, Contact, Email, Location, Status and Actions columns. It does not add vendor types or follow-up actions. The four summary values come from the existing vendors table: total, active, inactive and no email. Inactive replaces the sample mockup's open-follow-up metric.

Filters is collapsed on every page load, including filtered results. Click it to open the existing name, email, product, service and status GET filters, plus the already-supported general search. The Active badge indicates applied filters while collapsed. Reset clears the filters. Server pagination is retained, with the existing 20-row vendor default and a Rows selector. Services and Service Addresses separately default to 10 rows; Services product tabs have been replaced with the Product dropdown inside Filters.

## Existing Flows

- Add vendor opens the existing add form below the list. All fields, phone formatting, uploads, product/service/extra-service selection and backend validation remain.
- View and Edit keep their existing offcanvas forms and endpoints, including bank details, profile image, location and linked catalog records.
- Every vendor has Activate or Deactivate, using the existing confirmation and PATCH route. Cancel does not submit.
- The conflicting delayed popup reopen handler was bounded to the active popup. Record-load responses are checked against the current record/request; close aborts pending loads, and failed edit loads leave Save disabled.
- No migrations, role changes, notifications, new vendor classification or follow-up workflows were introduced.

## Verification

`VendorsDesignTest` covers rendered columns/actions, real summary values, filters, create/update persistence, validation, status changes, modal data and Operations/Sales access. The combined catalog/lead regression suite passed 72 tests with 569 assertions.

Render synthetic fixtures with `php tests/Fixtures/vendors/render.php /absolute/output/path`. The browser harness is `node tests/Browser/VendorsDesign.standalone.cjs /absolute/output/path`. Supply the CRM's public jQuery 3.6.1, Select2 4.1.0-rc.0 and intl-tel-input 25.3.1 JS/CSS dependencies in the output folder, named jquery.min.js, select2.min.js, select2.min.css, intlTelInput.min.js and intlTelInput.css. VENDOR_DEPS_DIR/JQUERY_PATH can point to an existing dependency cache. PLAYWRIGHT_MODULE/CHROME_PATH can select the test runtime.

All browser mutations are intercepted. The harness checks 1440/1024/768/390/320px layouts, filters/reset, correct status target/cancellation, View/Edit, read failures, edit validation, Add submission and horizontal overflow. Full shared-header renders of Vendors, Services and Service Addresses also passed using the local PostgreSQL database inside a read-only transaction. This is not a certification of unrelated CRM workflows or production delivery.
