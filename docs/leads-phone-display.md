# Leads Table Phone Display

- This change only affects the main Leads table presentation. Stored phone numbers, search filters, exports, lead ownership and role permissions are unchanged.
- Possible national Indian numbers are displayed with `+91`. A ten-digit number beginning with `91` is still treated as a local number, not as an existing country prefix. Indian trunk zeroes are normalized for display/dialling.
- Existing international prefixes (`+`, `00`, and recognizable longer country-prefixed digit strings) retain their source display. Dial/copy values use the canonical international number. Extensions are retained.
- Formatting uses the existing installed libphonenumber dependency. Missing/unparseable/impossible numbers retain a non-clickable source label (blank becomes N/A); no new database reads or migrations are needed. This is possible-number detection, not proof that a number is active or reachable.
- At the existing mobile breakpoint (767px and below), a native `tel:` link opens the device dialler. At desktop widths, click or Enter copies the canonical number. Clipboard permission errors use a legacy copy fallback; failed copies show an honest error rather than a success message.
- Event delegation survives table sorting. Accessible action labels update on viewport changes. Phone feedback and link styles are scoped to the Leads page. Asset versions are bumped for existing browsers.

Tests cover national/international formats, trunk prefixes, ambiguous ten-digit prefixes, invalid/blank values, extensions, real rendered cells and unchanged stored data. Browser checks cover HR/Sales views at 1440/1024/390/320px, desktop clipboard success/fallback/failure, keyboard activation, sorting, mobile dial links and light/dark overflow.
