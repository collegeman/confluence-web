=== Ollie Pro ===
Contributors:      patrickposner, mmcalister, buildwithollie
Tags:              ollie, onboarding
Requires at least: 6.3
Tested up to:      6.7
Stable tag:        1.2.9
Requires PHP:      7.4
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html

Adds the Ollie Pro pattern library and Ollie Pro Dashboard to the Ollie block theme.

== Description ==

Ollie Pro is a premium plugin that adds even more WordPress patterns to your site via a cloud pattern library with an intuitive and powerful interface.

== Installation ==

You can install the Ollie Pro plugin by going to Plugins → Add New Plugin → Upload Plugin and uploading the ollie-pro.zip file.

== Frequently Asked Questions ==

= How do I use this plugin? =

Once installed and activated, go to Appearance → Ollie to active your Ollie Pro account and unlock pro features. Visit the Ollie Docs for detailed video tutorials and articles about Ollie and Ollie Pro. https://olliewp.com/docs

== Changelog ==

= 1.2.9 =

* added full-text search support (content + title)
* added boolean search support (AND, OR, NOT)
* added the ability to authenticate via wp-config.php (OLLIE_EMAIL + OLLIE_PASSWORD)
* reduced number of NPM packages used for better efficiency

= 1.2.8 =

* fixed full page pattern insertion (with replaceBlocks API)

= 1.2.7 =

* fixed image uploads in Classic Editor
* ability to use the Ollie Pattern Block when editing pages in site editor mode

= 1.2.6 =

* automatically create pattern directory if it doesn't exist when using a child theme

= 1.2.5 =

* fixed downloading and saving patterns in child theme context
* improved category output when downloading themes including ollie namespace
* added support for downloading dynamic pattern versions
* improved downloaded patterns state management

= 1.2.4 =

* fixed customizer image upload when using Ollie Pro
* fixed theme.json overwrites when using child theme with modified theme.json
* improved activation handling and improved query checkup
* default search requests to be lowercase (ongoing improvement)

= 1.2.3 =

* improved asset loading for onboarding and pattern block
* fixed pattern block appearance in site editor context (including template views)

= 1.2.2 =

* account: fallback solution for heavily cached environments (Siteground, InstaWP, WP Engine..)
* implemented nested filtering (downloaded and favorites) inside categories and collections
* improved search behavior (no results) when in collection or category view
* compatibility with WP 6.6

= 1.2.1 =

* improved image loading styles
* fixed search (no results) while in collection or category view

= 1.2 =

* refactored release process
* replaced iframes with automatically generated screenshots for grid view
* fixed permission issue with favorites
* fix hover issue when using Firefox
* removed pattern guide from modal
* fix for line wrapping (headings) + site logo width
* added clear sort button
* improved context logic for quick search (less API requests)

= 1.1 =

* improved login/logout flow
* fixed namespace for patterns when using onboarding
* added quick links to plugin in Plugins settings page
* added logic to show content based on login status
* merged Ollie Account and Dashboard for better UX
* customized wording and UI based on login status
* limited the number of pages for selecting the homepage to parent and max 25 pages
* fixed issue where account area wasn't showing up when account is cancelled
* improved search: No results message + dynamic placeholder based on context (collection, category..)

= 1.0 =

* Initial release
