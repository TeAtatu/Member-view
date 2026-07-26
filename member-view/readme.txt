=== Member View ===
Contributors:
Tags: membership, access control, login, private site, multisite
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Two-tier access for WordPress: Visitors see only a configurable landing page and a login modal; the logged-in Community gets normal access.

== Description ==

Member View splits your site into two tiers:

* **Visitors** — not logged in, OR logged in holding only the plugin's "Visitor" role. They can reach one page (the configured landing page) and nothing else; every other front-end URL redirects there. Same-domain links on the landing page open a login modal instead of navigating.
* **Community** — any user logged in with a role other than "Visitor" (Administrator, Editor, Author, Contributor, Subscriber, or a custom role). They get standard WordPress access; this plugin neither adds nor removes their capabilities.

The access decision lives in one helper, `member_view_is_community()`, which is true only when the user is logged in AND holds a non-Visitor role on the current site.

= Features =

* **Configurable landing page** — pick any published page (Settings → Member View). Stored by post ID, so it survives renames. If none is set, gating is disabled (fail open) and an admin notice appears.
* **Gate on every entry point** — front-end (`template_redirect`), REST (`rest_pre_dispatch`), and wp-admin (`admin_init`). Feeds and XML sitemaps stay crawlable for SEO, but feeds emit excerpts only so gated content isn't leaked.
* **Login modal** — accessible (keyboard, focus trap, Escape). Offers a real login form (core auth) and a "Request access" form.
* **Request access** — creates an account (with the configurable default sign-up role, Visitor by default), emails the requester a set-password/verify link, and notifies an administrator. Protected by a nonce and honeypot. With the default role, accounts stay gated until an admin promotes them.
* **Configurable default sign-up role** — choose which role "Request access" assigns to new accounts (Settings → Member View). Defaults to Visitor.
* **Preview as Visitor** — admins can experience the gate against their own session (admin-bar toggle) without logging out.
* **User columns** — adds sortable "Date Created" and "Last Login" columns to the Users screen (last login is captured on `wp_login`).
* **Multisite** — works on single-site and multisite. The Visitor role is provisioned on every site (and on new sites as they're created). The landing page setting is per-site.
* **Self-hosted updates** — optional GitHub-releases auto-updater (Settings → Member View). Dormant until a repo is configured.

== Installation ==

1. Upload the `member-view` folder to `/wp-content/plugins/`.
2. Activate the plugin (or Network Activate on multisite).
3. Go to **Settings → Member View** and choose a landing page.

== Frequently Asked Questions ==

= What happens if I don't set a landing page? =
Gating is disabled (the site behaves normally) and an admin notice reminds you to set one. This prevents redirect loops before configuration.

= Can a Visitor-role user do anything? =
No. The Visitor role carries only the `read` capability and is treated exactly like a logged-out user by the gate. It exists so "Request access" can create an account for an admin to review and promote.

= Do search engines still see my content? =
They can crawl XML sitemaps and feeds, but feeds are limited to titles/excerpts/links — the full article body is never served to an unauthenticated request.

== Changelog ==

= 1.1.0 =
* New setting: "Default sign-up role" (Settings → Member View) controls which role new "Request access" accounts get. Defaults to Visitor, so existing behavior is unchanged unless you change it. Administrator is not selectable (and is never honored) since "Request access" is public.
* Updater: added an optional GitHub token field for private repositories, an Update URI header so wordpress.org can't hijack the same slug, and support for WordPress's native per-plugin auto-update toggle.

= 1.0.0 =
* Initial release: two-tier Visitor/Community model, configurable landing page, multi-entry-point gate, accessible login + request-access modal, preview-as-visitor mode, Date Created / Last Login user columns, multisite provisioning, and an optional GitHub-releases updater.
