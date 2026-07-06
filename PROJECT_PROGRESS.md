# CoderEmbassy Quantity Guard Progress

Last updated: 2026-07-06

## Project Identity

- Plugin name: CoderEmbassy Quantity Guard for WooCommerce
- Current version: 0.1.0
- Free plugin repository: https://github.com/salehST/coderembassy-quantity-guard.git
- Pro plugin repository for future pro work only: https://github.com/salehST/coderembassy-quantity-guard-pro.git
- Workspace path: `C:\Users\User\Desktop\My Plugins\New Plugin\coderembassy-quantity-guard`
- Localhost test plugin path: `C:\laragon\www\plugins\wp-content\plugins\coderembassy-quantity-guard`
- Latest pushed commit: `6db1ca4 Polish Quantity Guard admin dashboard`
- Latest ZIP built: `C:\Users\User\Desktop\My Plugins\New Plugin\releases\coderembassy-quantity-guard-release-20260706-201954\coderembassy-quantity-guard-0.1.0.zip`

## Current Status

- Free plugin implementation is functionally complete for the planned 0.1.0 build.
- User manually tested the core quantity behavior and reported it working.
- Admin dashboard was branded with the CoderEmbassy logo and modernized.
- Dashboard now suppresses unrelated third-party admin notices on the Quantity Guard settings page.
- Source includes only one active logo asset: `admin/images/logo-light.png`.
- GitHub free repo has been pushed through commit `6db1ca4`.
- Working tree was clean after the push and ZIP build, before this progress file was created.

## Completed Phases

- [x] Reviewed `quantity-guard-plan-addendum-gap-fixes.md`
- [x] Reviewed `coderembassy-quantity-guard-codex-plan.md`
- [x] Agreed the addendum gap fixes were needed, especially Store API and Blocks validation
- [x] Built plugin scaffold
- [x] Added WooCommerce settings page under `WooCommerce > Quantity Guard`
- [x] Added global quantity settings
- [x] Added rule engine
- [x] Added product-level quantity rules
- [x] Added variation-level quantity rules
- [x] Added frontend quantity input behavior
- [x] Added message builder with placeholders
- [x] Added classic WooCommerce add-to-cart, cart, and checkout validation
- [x] Added Store API and Cart/Checkout Blocks validation
- [x] Added HPOS and Cart/Checkout Blocks compatibility declarations
- [x] Added rule debugger preview
- [x] Added uninstall cleanup option
- [x] Added release documentation in `readme.txt`
- [x] Fixed settings warning when maximum is lower than minimum
- [x] Fixed variation quantity spinner alignment so it steps `3 -> 6 -> 9`
- [x] Added CoderEmbassy branded admin dashboard
- [x] Suppressed third-party notices on the plugin dashboard
- [x] Built fresh installable ZIP after dashboard polish

## Important Files

- Main plugin file: `coderembassy-quantity-guard.php`
- Settings and dashboard: `includes/class-ceqg-settings.php`
- Admin dashboard CSS: `admin/css/admin.css`
- Logo asset: `admin/images/logo-light.png`
- Rule engine: `includes/class-ceqg-rule-engine.php`
- Product fields: `includes/class-ceqg-product-fields.php`
- Variation fields: `includes/class-ceqg-variation-fields.php`
- Frontend behavior: `includes/class-ceqg-frontend.php`
- Frontend JS: `public/js/frontend.js`
- Classic validation: `includes/class-ceqg-validation.php`
- Store API validation: `includes/class-ceqg-store-api.php`
- Message builder: `includes/class-ceqg-messages.php`
- Debug preview: `includes/class-ceqg-debug.php`
- Uninstall cleanup: `uninstall.php`
- Release readme: `readme.txt`

## Key Behavior Implemented

- Rule priority: variation rule overrides product rule, product rule overrides global rule.
- Global settings support minimum, maximum, step, and default quantity.
- Product and variation rules are whole-rule overrides.
- Product and variation meta is saved with WooCommerce CRUD methods.
- Maximum lower than minimum is normalized safely and shows a warning.
- Default quantity is normalized to respect min, max, and step.
- Offset global min and step combinations save, but show an admin warning for smoother block controls.
- Archive/shop AJAX add-to-cart is disabled for simple products that need non-default quantity rules.
- Product page quantity input respects min, max, step, and default.
- Variation selection updates quantity input and rules correctly.
- Classic add-to-cart, cart update, and checkout validation enforce rules.
- Store API validation protects Cart and Checkout Blocks.
- Admin settings page keeps Quantity Guard notices but hides unrelated plugin banners/notices.

## Testing Already Confirmed By User

- [x] Global rule save works
- [x] Maximum lower than minimum normalizes and shows warning
- [x] Minimum `2`, Step `3`, empty maximum saves and shows the smoother block-controls warning
- [x] Product page displays the quantity rule summary
- [x] Invalid quantity above maximum is blocked
- [x] Spinner now steps correctly for `min 3`, `max 9`, `step 3`: `3 -> 6 -> 9`
- [x] Product-level overrides work
- [x] Variation-level overrides work
- [x] Cart/classic validation works
- [x] Admin dashboard branding and one-logo layout accepted for now

## Verification Commands Used

PHP lint:

```powershell
$php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
rg --files -g "*.php" | ForEach-Object { & $php -l $_ }
```

Frontend JS syntax:

```powershell
node --check public\js\frontend.js
```

Git status:

```powershell
git status --short
```

## Recent Commits

- `6db1ca4` Polish Quantity Guard admin dashboard
- `fe776a8` Keep variation quantity spinner aligned with rules
- `0613de3` Show warnings for adjusted maximum quantities
- `01adaaa` Polish release documentation
- `b242d5c` Add rule debugger preview
- `2b6bcf1` Add Store API quantity validation
- `552b510` Add classic quantity validation
- `2415d31` Add quantity message builder
- `a136447` Add frontend quantity behavior
- `4c8673e` Add variation quantity rules

## Next Tasks

- [ ] Decide whether the first public/free release should stay `0.1.0` or become `1.0.0`.
- [ ] If releasing as `1.0.0`, update:
  - [ ] Plugin header version in `coderembassy-quantity-guard.php`
  - [ ] `CEQG_VERSION`
  - [ ] `readme.txt` stable tag
  - [ ] ZIP filename
- [ ] Regenerate or update `languages/coderembassy-quantity-guard.pot` because the dashboard added new translatable strings.
- [ ] Do one final admin save test after the dashboard polish.
- [ ] Do one final product page quantity test after the dashboard polish, just to ensure no accidental regression.
- [ ] Optionally test Store API directly with invalid and valid quantities.
- [ ] Build final release ZIP after the version decision.
- [ ] If version is final, create a GitHub tag such as `v0.1.0` or `v1.0.0`.
- [ ] Optional: create a GitHub release and attach the ZIP.
- [ ] Optional: add plugin screenshots or banner assets if this will be distributed beyond direct ZIP install.

## Direct Store API Test To Run Later

Use a product or variation with `min 3`, `max 9`, `step 3`.

- [ ] POST invalid quantity `4` to `/wp-json/wc/store/v1/cart/add-item`
- [ ] Expected: HTTP 400 with a `ceqg_` error code and item not added
- [ ] POST valid quantity `6`
- [ ] Expected: item added successfully

## Notes For The Next Chat

- This is the free plugin. Push free work only to `salehST/coderembassy-quantity-guard.git`.
- Do not push free plugin work to the pro repository.
- The user has a localhost WordPress install in Laragon and tests changes at `C:\laragon\www\plugins\wp-content\plugins\coderembassy-quantity-guard`.
- Copying files to the Laragon plugin path requires sandbox escalation, usually with `Copy-Item`.
- GitHub push may require network escalation. If the first push fails, retry once.
- The latest dashboard source uses only `logo-light.png`; `logo-dark.png` is not used in the source package.
- The localhost plugin may still contain old copied files if they were not removed, but the release ZIP source is clean.
- Avoid changing tested quantity logic unless a bug is found. The main remaining work is release readiness.
