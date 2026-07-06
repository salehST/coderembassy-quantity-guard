=== CoderEmbassy Quantity Guard for WooCommerce ===
Contributors: coderembassy
Tags: quantity, min quantity, max quantity, woocommerce blocks, quantity step
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Quantity rules for WooCommerce products, variations, Cart and Checkout Blocks, and Store API requests.

== Description ==

CoderEmbassy Quantity Guard helps WooCommerce stores set practical minimum, maximum, default, and step quantity rules without a heavy rule builder.

Built for modern WooCommerce stores, it validates rules in classic cart flows and the WooCommerce Store API used by the Cart block, Checkout block, and Mini-Cart block. It also includes a rule debugger in product admin screens so store owners and agencies can quickly see which rule is active and why.

= Highlights =

* Global quantity rules.
* Product-level quantity rules.
* Variation-level quantity rules.
* Live quantity updates when customers select a variation.
* Friendly customer messages with placeholders.
* Server-side validation for classic WooCommerce cart and checkout.
* Store API validation for Cart block, Checkout block, Mini-Cart block, and direct Store API requests.
* Admin rule debugger showing the active rule source and values.
* HPOS compatible.
* Lightweight: no external calls, no nag screens, no upsell interruptions.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate "CoderEmbassy Quantity Guard for WooCommerce" from the Plugins screen.
3. Go to WooCommerce > Quantity Guard.
4. Configure global rules.
5. Optionally configure product or variation rules from the WooCommerce product editor.

== Frequently Asked Questions ==

= Does it work with the Cart and Checkout blocks? =

Yes. Quantity Guard filters WooCommerce Store API quantity limits and validates Store API cart mutations, so rules apply to the Cart block and Checkout block.

= Does it work with the Mini-Cart block? =

Yes. The Mini-Cart block uses Store API cart data, including the quantity limits provided by Quantity Guard.

= Can customers bypass rules with the REST API or by disabling JavaScript? =

No. JavaScript only improves the customer experience. Rules are also validated server-side in classic WooCommerce hooks and Store API cart requests.

= Does it support HPOS? =

Yes. Quantity Guard declares compatibility with WooCommerce High-Performance Order Storage because it does not modify order storage.

= What happens if my minimum is not a multiple of my step? =

Classic validation supports offset steps such as minimum 2 and step 3, where valid quantities are 2, 5, 8, and so on. WooCommerce Store API `multiple_of` is a plain multiple and cannot fully represent that offset. Quantity Guard still validates the server-side rule as the source of truth and shows an admin notice recommending a minimum that is a multiple of the step for the smoothest block UI.

= Are maximum quantities lifetime purchase limits? =

No. In this free version, maximum quantity means maximum per cart line item.

== Screenshots ==

1. Variation live quantity update on the product page.
2. Rule debugger box in the product editor.
3. Quantity Guard settings page.
4. Invalid quantity blocked in the Checkout block with a friendly message.

== Changelog ==

= 1.0.0 =

* Initial stable release.
* Added global, product, and variation quantity rules.
* Added frontend quantity updates and variation live updates.
* Added classic WooCommerce validation and Store API validation.
* Added rule debugger preview.

== Upgrade Notice ==

= 1.0.0 =

Initial stable release.
