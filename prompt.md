# Member View — Reusable AI Development Prompt

Design contract for the **Member View** WordPress plugin. Hand this to an AI assistant for any change (build, feature, fix, refactor) so results stay consistent.

## How to use this

1. Paste the **"Prompt template"** below into a fresh AI session.
2. Fill in the `<describe the change>` blank.
3. Point the AI at the `member-view/` folder. Once code exists, read it before changing — don't regenerate from scratch.

---

## Prompt template

```
You are building/extending a WordPress plugin called "Member View". If code already exists in that folder, read it before changing — do not regenerate files from scratch.

CHANGE REQUESTED: <describe the change>

Preserve these rules unless the change explicitly asks to alter them:

## Access levels

### Visitors
Not logged in, OR logged in holding only this plugin's "Visitor" role (a WP role). Both get the same access. The single access check is member_view_is_community(): it returns true only when the user is both logged in AND holds a non-Visitor role on the current site — so logged-out and Visitor-role users both fail it. (Callers may also short-circuit on !is_user_logged_in() as defense-in-depth, but the helper already covers it; a bare is_user_logged_in()===true must never grant access on its own.)

The Visitor role supports a "Request access" form (see Login modal); an Administrator then reviews and promotes.

#### Access provided
Only the landing page — rendered in the site's normal theme (header, nav, widgets, footer), designed like any other page. Every other front-end URL redirects here. Same-domain links on it open the login modal (with the exceptions below) instead of navigating.

**Landing-page enforcement:**
- The gate is !member_view_is_community(), applied across every entry point (not just page loads): front-end via template_redirect (compare the requested page to the configured landing page ID), REST via rest_pre_dispatch/rest_authentication_errors, and wp-admin via admin_init. Each uses the same helper, so the rule stays in one place.
- Capture the local requested URL as a return-to param (e.g. redirect_to) for post-login redirect. Validate with wp_validate_redirect() against an internal allow-list.
- Exempt: wp-login.php + core auth/password-reset, the AJAX/REST endpoint(s) the modal and "Request access" use, static assets, and XML sitemaps/RSS/Atom feeds (for crawlers). Do NOT exempt ordinary pages/posts, search, or archives.
- wp-admin: fine for Community, but redirect Visitor-role users away too (via the admin_init gate above) — otherwise they reach profile.php etc. as authenticated users.
- Feeds are crawlable but must not leak gated content: expose titles/excerpts/links only, never full post content. (The gate is defeated if /feed/ serves the article body to anyone.)
- The feed/sitemap exemption is by URL, not user-agent — bots hitting ordinary pages get redirected like anyone else. No user-agent sniffing.
- REST (wp-json) is not exempted wholesale — only the specific modal/"Request access" endpoints.
- Never redirect a Community user. (Admin preview mode below only affects the previewing admin's own session.)
- Guard against redirect loops: the landing page and every exempt URL must never trigger the redirect.

**Login modal:**
- Same-domain links (via home_url()/site_url(), not a hardcoded domain) open the modal instead of navigating. External links unaffected.
- Interception exceptions — do NOT hijack: on-page fragment links (href "#..." or same URL + fragment); the Sign in button and any element wired to open/close the modal; auth/request-access URLs (wp-login.php, password reset, the plugin's own login/request endpoints); and any element carrying an explicit opt-out marker (e.g. a data-attribute or CSS class) so admins can whitelist specific links.
- The landing page always renders an explicit persistent trigger to open the modal (e.g. a "Sign in" button), in case the page has no internal links. Keep the label neutral ("Sign in" / "Access") rather than "Log in", since a Visitor-role user is already logged in.
- Applies to Visitor-role users too — show the modal, don't special-case them out.
- The modal offers:
    (a) a real login form (WP's own auth — wp_login_form() or AJAX against core), and
    (b) a "Request access" form: collects name/email, creates a Visitor-role account that is inactive until email-verified (WP sends a set-password/verify email — blocks fake addresses, gives real credentials), and notifies an Administrator. Protect the form against bots (nonce + honeypot). Not self-service registration — accounts stay gated as Visitor until an admin promotes them.
- On login, redirect to the captured return-to URL, else the landing page.
- Front-end script (enqueued only for logged-out/Visitor users) intercepts same-domain anchor clicks. Keep the modal accessible: keyboard-operable, focus trap, Escape to close.

### Community
Logged in with any role other than Visitor (Administrator, Editor, Author, Contributor, Subscriber, or custom).

#### Access provided
Standard WordPress access — this plugin adds/removes no Community capabilities.

## Admin functionality

### Configurable landing page
- Settings screen with a page dropdown; store the page's post ID in one option (e.g. member_view_landing_page_id), not a slug/URL, so it survives renames.
- If unset, fail open (don't redirect) and show a page whose only content is a login prompt — never a loop or 404.

### Admin preview of the visitor experience
- A capability-gated toggle (e.g. admin-bar item, settings-level users) simulates the visitor redirect for the admin's own session — no logout needed.
- Only affects that session; never weakens the gate or is reachable by non-privileged users.
- Make preview state obvious and trivially exitable (e.g. an "Exit preview" control).

### User administration pages
- Add two sortable columns to the Users list: "Date Created" (user_registered) and "Last Login" — not tracked by core, so capture it on wp_login (writing member_view_last_login user meta). Never-logged-in or pre-tracking users show "Never"/"—", not blank or a fake date.

### Uninstall behavior
- uninstall.php has no UI, so capture the choice in advance: an admin setting (e.g. member_view_uninstall_visitor_action) = keep / reassign to Subscriber / delete.
- If unset, default to non-destructive (leave the role and Visitor accounts untouched).
- Also remove this plugin's own footprint: its options and the member_view_last_login user meta. On multisite, clean each site.

## Multisite support
- Works on single-site and multisite — nothing is network-activate-only. A per-site Activate must not loop every site (only Network Activate does).
- The landing page setting is per-site (pages are per-site) — no network-wide landing page.
- The Visitor role is provisioned on the main site and every subsite with only the 'read' capability (nothing else): add_role() at activation (looping via get_sites()+switch_to_blog() only when network-activated with $network_wide true; a per-site Activate provisions only the current site), and via wp_initialize_site for new subsites.
- The added user columns are per-site (like wp-admin/users.php itself); Last Login is captured per-site.
- member_view_is_community() checks the user's role on the CURRENT site, not network login state — a user logged in but with no role on this site is a Visitor here, even if Community elsewhere on the network.

## Conventions
- Plugin identity (header defaults): Name "Member View", text domain "member-view", License GPLv2+, Requires WP 6.0+, Requires PHP 7.4+. Author left to fill in.
- Prefix everything member_view_ — functions, hooks, options, meta keys, roles. Wrap user-facing strings for i18n with the "member-view" text domain.
- Bump the plugin Version header and note changes in readme.txt for any behavioral change.

## Distribution & updates
- Self-hosted (not wordpress.org). Ship a GitHub-releases-based auto-updater using only wp_remote_get() + core update filters (pre_set_site_transient_update_plugins, plugins_api, upgrader_source_selection) — no bundled update library. readme.txt is informal docs, not the strict .org format.

## Security baseline
- Nonce every form and AJAX/REST handler; capability-check with the narrowest cap that fits (never manage_options as a stand-in).
- Escape all output (esc_html/esc_attr/esc_url), sanitize all input (sanitize_text_field/sanitize_email/esc_url_raw).

## General
Ask before changing: the Visitor/Community model, Visitor being treated identically to a logged-out visitor, the "every non-landing-page URL redirects" rule, "Request access" vs self-service registration, or making the landing page setting network-wide.
```

---

## Architecture map

| File | Responsibility |
|---|---|
| `member-view/member-view.php` | Plugin header, constants (incl. `MEMBER_VIEW_ROLE`), requires, activation + multisite provisioning loop, `wp_initialize_site` hook, textdomain |
| `member-view/includes/helpers.php` | `member_view_is_community()`, `member_view_should_gate()`, `member_view_is_previewing()`, landing-page-id + per-site last-login meta-key helpers |
| `member-view/includes/roles.php` | `member_view_provision_site()` — Visitor role with only `read` |
| `member-view/includes/gate.php` | Gate at every entry point: `template_redirect` (front-end + redirect_to capture), `admin_init` (wp-admin), `rest_pre_dispatch` (REST), feed excerpt-only filter |
| `member-view/includes/modal.php` | Enqueue for gated users, footer modal markup + persistent trigger, AJAX login (`wp_signon`) + request-access (account create, verify email, admin notify, honeypot) |
| `member-view/includes/settings.php` | Settings → Member View: landing page dropdown, uninstall action, GitHub repo; "set a landing page" admin notice |
| `member-view/includes/preview.php` | Preview-as-Visitor admin-bar toggle + exit banner (admin-post handlers) |
| `member-view/includes/users-columns.php` | Date Created + Last Login columns (sortable), `wp_login` tracking |
| `member-view/includes/updater.php` | GitHub-releases updater (dormant until a repo is configured) |
| `member-view/assets/js/member-view.js` | Same-domain link interception (with exceptions), accessible modal, AJAX |
| `member-view/assets/css/member-view.css` | Modal + trigger styling |
| `member-view/uninstall.php` | Retention-aware cleanup (Visitor accounts per chosen action; options + meta), per-site on multisite |
| `member-view/readme.txt` | User docs + changelog |

## Original build spec

The original request lives in version control / the initiating conversation. This file is the forward-looking source of truth for how the plugin should work — not a historical record.
