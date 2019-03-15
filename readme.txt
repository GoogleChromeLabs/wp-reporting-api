=== Reporting API ===

Contributors:      google, flixos90, westonruter
Requires at least: 4.7
Tested up to:      5.1
Requires PHP:      5.6
Stable tag:        0.1.1
License:           GNU General Public License v2 (or later)
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              reporting, api

WordPress plugin for receiving browser reports via a Reporting API endpoint.

== Description ==

As [noted on the Google Developers blog](https://developers.google.com/web/updates/2018/09/reportingapi):

> The Reporting API defines a new HTTP header, `Report-To`, that gives web developers a way to **specify server endpoints** for the browser to send warnings and errors to. Browser-generated warnings like CSP violations, Feature Policy violations, deprecations, browser interventions, and network errors are some of the things that can be collected using the Reporting API.

This plugin provides a storage mechanism and endpoint for browser reports according to the Reporting API spec in WordPress, as well as an admin interface for browsing these reports. It also provides an API for sending the `Report-To` response headers.

As the Reporting API specification is still evolving and at an early stage, the plugin reflects that and is currently an experimental prototype, to demonstrate how Reporting API can be used in WordPress.

= Did you know? =

There is also a new specification called Feature Policy which will integrate with the Reporting API specification. There is a [WordPress plugin for Feature Policy](https://wordpress.org/plugins/feature-policy/) as well.

== Installation ==

1. Upload the entire `reporting-api` folder to the `/wp-content/plugins/` directory or download it through the WordPress backend.
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= Which browsers support the Reporting API specification? =

The Reporting API standard is quite bleeding-edge, so support is currently still limited. The only browser supporting it at the moment is Chrome.

= Where should I submit my support request? =

Note that this is an experimental plugin, so support is limited and volunteer-driven. For regular support requests, please use the [wordpress.org support forums](https://wordpress.org/support/plugin/reporting-api). If you have a technical issue with the plugin where you already have more insight on how to fix it, you can also [open an issue on Github instead](https://github.com/GoogleChromeLabs/wp-reporting-api/issues).

= How can I contribute to the plugin? =

If you have some ideas to improve the plugin or to solve a bug, feel free to raise an issue or submit a pull request in the [Github repository for the plugin](https://github.com/GoogleChromeLabs/wp-reporting-api). Please stick to the [contributing guidelines](https://github.com/GoogleChromeLabs/wp-reporting-api/blob/master/CONTRIBUTING.md).

You can also contribute to the plugin by translating it. Simply visit [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/reporting-api) to get started.

== Screenshots ==

1. Reports admin screen with list view
2. Reports admin screen with excerpt view
3. Reports admin screen, filtered by a specific report type

== Changelog ==

= 0.1.1 =

* Add polyfill to support report requests using the old `application/csp-report` specification, parsing it into a proper `application/reports+json` format.
* Ensure the usage of `Content-Security-Policy-Report-Only` header does not cause side effects.
* Prime report log data caches for report queries to significantly reduce number of SQL requests when querying multiple reports.

= 0.1.0 =

* Initial release
