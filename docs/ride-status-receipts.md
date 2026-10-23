# Ride Status Layout and Receipt Access

- Ride-card timelines start collapsed. The native Timeline summary opens/closes each independently.
- View Lead and Ride Status buttons remain on one aligned row across desktop and mobile. Existing status targets and popup handlers are unchanged.
- Ride Status and Payment Review use same-origin relative URLs for the existing authenticated follow-up file route, avoiding a receipt link being sent to a different host from configured URL generation.
- Vendor Payment client-history responses now include the existing follow-up receipt filename. Client receipt links use that same protected route. Vendor receipt previews normalize stored paths instead of blindly adding a second /storage prefix.
- Existing backend read access already includes Senior Accounts Manager, Accounts Manager and Accounts Executive for payment receipts. No new write privileges, public-file access, role-group changes or database migrations are introduced.

Verification: 27 selected feature tests with 254 assertions passed, including real authenticated receipt view/download requests for all three Accounts roles on other Sales representatives' payment receipts. Anonymous and unrelated Sales users remain denied; nonpayment attachments do not acquire global Accounts access. Browser checks at 1440/1024/768/390/320px verify collapsed toggles, single-line actions and no horizontal overflow. Existing Ride Status stale-response/write-lock checks pass. Receipt URL functions are exercised directly from production templates.

These are local checks. The reported live Unauthorized response has not been reproduced against its exact failing URL; cached production routes, the deployed revision and current login role still require checking if the error persists. No live deployment or real payment mutation was performed.
