=== RestroPress - Online Food Ordering System ===
Contributors: magnigenie, sagarseth9, kshirod-patel, bibhu1995
Tags: Online ordering, Restaurant Ordering, Food Delivery, Takeaway, Restaurant Menu
Donate link: https://paypal.me/magnigeeks
Requires at least: 4.4
Requires PHP: 5.5
Tested up to: 6.9.4
Stable tag: 3.2.8.6.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
RestroPress is a Food Ordering System for WordPress which will help the restaurant owners to sell their food online.

== Description ==
**Turn your WordPress site into a powerful online food ordering system — no technical skills required.**

RestroPress lets restaurants, cloud kitchens, and food businesses accept **pickup and delivery** orders directly on their own website. Built with simplicity and scalability in mind, it’s the easiest way to start accepting online orders — **without paying commissions** to third-party platforms.

---

## 🎥 Watch RestroPress In Action

[youtube https://www.youtube.com/watch?v=CGVpXYw6JDQ]

[Try Demo](https://demo.restropress.com)

---

## 🍽️ Who Is RestroPress For?

* Local Restaurants and Cafes
* Takeout / Takeaway Businesses
* Bakeries, Pizza Shops, Burger Shops
* Grocery Stores, Florists, Farmers Markets
* Specialty Stores, Fruit & Vegetable Shops
* Laundry Services

---

## 🚩 Problems We Solve

Tired of managing complex or expensive online ordering systems? RestroPress provides:

* Freedom from WooCommerce or other bulky plugins
* A **fully commission-free** solution to reduce operational costs
* Simplified order management with **real-time tracking and notifications**
* Unlimited orders with a **scalable system** that grows with your business
* Streamlined operations — custom menus, automated printing, delivery tools
* Better customer communication via SMS, WhatsApp, and email updates

---

## ✨ Benefits of Choosing RestroPress

* **Completely Free with Unlimited Orders:** No commission, no subscription fees—maximize your profits.
* **Standalone Solution:** Fast, lightweight, and independent from WooCommerce.
* **All-in-One Management:** Manage orders, print tickets automatically, and track delivery efficiently.
* **Boost Customer Engagement:** Automated notifications keep customers informed every step of the way.
* **Built for Growth:** Add locations, customize menus, and integrate premium POS systems like Clover and Square.
* **Dine-In and Delivery Made Easy:** QR code ordering for dine-in and flexible delivery fees based on location.
* **Mobile & Desktop Ready:** Use our apps and desktop POS to stay in control anytime, anywhere.
* **Save Time with Automation:** Auto-print orders, set holiday schedules, and reduce manual work.

---

## 🛠️ Key Features

* Accept unlimited pickup and delivery orders directly from your website
* Customizable menus with addons and modifiers for flexible offerings
* Multiple payment gateways: PayPal, Cash on Delivery, Amazon, and more
* Customer dashboard to view order history and quickly reorder
* Real-time order management with live status updates for admins
* Automatic creation of essential pages (cart, checkout, account) with shortcodes
* Mobile responsive design for seamless ordering on any device
* Email and SMS notifications(extension) to keep customers and staff informed
* Delivery management with flexible fees and zones
* QR code ordering for dine-in customers (via Dine-In extension)
* Integration with popular POS systems (Clover, Square) for smooth payments
* REST API support to build custom integrations and apps

---

## ⚙️ Extensions and Apps

Expand your RestroPress system with powerful add-ons:

* **Driver App:** Manage your delivery team efficiently
* **Customer App:** Smooth, branded ordering experience for customers
* **Order Tracking App:** Real-time order tracking for customers
* **KDS App:** Kitchen Display System to streamline kitchen workflow
* **Dine-In Ordering:** QR code-based table ordering system
* **POS System (Mobile & Desktop):** Unified order management for in-store and online
* **Clover & Square Integrations:** Seamless payment processing with premium POS systems

& More — [Explore all extensions](https://restropress.com/extension/)

---

## 📚 Resources

* [Official Website](https://restropress.com)
* [Documentation](https://docs.restropress.com)
* [Join Our Facebook Group](https://facebook.com/groups/restropress.official)
* [YouTube Channel](https://www.youtube.com/@restropress)
* [Demo Page](https://demo.restropress.com)

---

## 🧩 Build Your Own Custom Solution/Apps

RestroPress supports **REST API** (since version 3.0), allowing developers to build custom apps and integrations.
[Learn more about the REST API](https://docs.restropress.com/docs/rest-api/authentication/)

---

## 🚀 Get Started Today

Take control of your online food ordering with RestroPress — a fast, commission-free, and fully customizable system designed for your business.

👉 [Download Now](https://wordpress.org/plugins/restropress/)
👉 [Explore Pro Features & Extensions](https://restropress.com/extension/)

== Changelog ==

= Version 3.2.8.6.2(2026-04-24) =
* Updated: Plugin version bump to 3.2.8.6.2
* Fixed: Food category drag-and-drop reordering now persists reliably on the admin categories list after refresh.
* Fixed: Prevented addon-category sorting scripts from attaching on food-category taxonomy screens and overriding reorder AJAX actions.
* Improved: Food-category admin ordering flow now consistently loads sortable assets, validates AJAX requests, and applies saved `tax_position` ordering.
* Updated: RestroPress docs links in admin/readme now point to the current docs URL.
* Code Cleanup

= Version 3.2.8.6.1(2026-04-14) =
* Updated: Plugin version bump to 3.2.8.6.1
* Security: Escaped `rpress_receipt` shortcode `error` output to prevent stored XSS payload execution.
* Security: Escaped Orders admin payment-history links generated from query arguments to prevent reflected admin XSS via crafted URLs.
* Code Cleanup

= 3.2.8.6 (2026-04-11) =
* Updated: Version bump
* Fixed: Mobile & tablet layout alignment issues (service buttons, search input, summary row)
* Fixed: Checkout UI alignment (Place Order button, total row consistency)
* Fixed: Mobile UI issues (circular add button, delivery/pickup tabs consistency)
* Fixed: Category navigation and sticky overlap issues across list/grid views
* Fixed: Category navigation targeting and active state sync on mobile
* Fixed: Admin order counts and filters (status, service type, paid state handling)
* Fixed: Order badge/count now excludes deleted/trash records
* Improved: Theme color consistency across service selection and checkout actions
* Improved: Quantity controls styling in item popup
* Improved: Category scroll positioning using dynamic sticky header offsets
* Improved: Frontend asset versioning using filemtime to prevent cache issues
* Misc: Code cleanup and minor improvements

= 3.2.8.5 (2026-04-07) =
* Added: Legacy UI/UX toggle for service selection flow
* Fixed: Service date/time sync issues across modal & checkout
* Improved: Old UI popup styling, layout, and theme color sync
* Improved: REST services endpoint now returns only enabled service types
* Code cleanup

= 3.2.8.4.1 (2026-04-06) =
* Fixed: PayPal redirect handling for safe checkout flow

= 3.2.8.4 (2026-03-28) =
* Fixed: Checkout gateway sync issues
* Security: Hardened DB queries and SQL handling
* Security: Addressed PluginCheck warnings
* Fixed: Mobile cart UI and checkout responsiveness
* Improved: Sticky cart and category navigation UX
* Code cleanup

= 3.2.8.3 (2026-03-26) =
* Fixed: Date/time sync and formatting issues
* Fixed: Add-to-cart behavior for closed store
* Security: CSRF and XSS protections implemented
* Improved: UI enhancements for service summary

= 3.2.8.2 (2026-03-25) =
* Fixed: Grid layout issues on tablets
* Improved: Date-time popup UX
* Code cleanup

= 3.2.8.1 (2026-03-23) =
* Fixed: Delivery validation logic
* Fixed: Modal and overlay issues
* Security: API token generation hardened
* Improved: Elementor compatibility

= 3.2.8 (2026-03-13) =
* Fixed: Service state synchronization issues
* Improved: Service-state handling logic
* Security: Vulnerability fixes
* Code cleanup

= 3.2.7 (2026-03-09) =
* Fixed: Add-on migration issues
* Improved: Safe migration logic
* Code cleanup

= 3.2.6 (2026-03-04) =
* Fixed: Store timing validation issues
* Fixed: PayPal sandbox and checkout issues
* Added: Realtime order tracking & notifications
* Improved: Service-time handling logic
* Code cleanup

= 3.2.5 (2026-02-26) =
* Added: Taxonomy sorting feature
* Fixed: Cart and service switching issues
* Code cleanup

== Upgrade Notice ==

= 3.2.8.6.2 =
Please backup your website before upgrading to the latest version.

== License ==
This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
For full details, please visit http://www.gnu.org/licenses/gpl-2.0.html
For more information and support, visit the [RestroPress website](https://www.restropress.com/).
