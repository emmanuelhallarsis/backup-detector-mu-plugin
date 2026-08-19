# Forbidden Plugins Guard (Backup Detector)

An mu-plugin that maintains a blocklist of plugins (by slug, with an optional
display-name fallback) and enforces one of two policies — **warn** or
**block** — at install time and at activation time, with admin-facing
notices explaining why.

Ships as an mu-plugin so it can't be casually deactivated from wp-admin the
way a normal plugin can.

## How it works

- `warn` — install/activation proceeds. A persistent (session-scoped, not
  permanently dismissible) notice appears on the Plugins page for as long as
  the plugin is active.
- `block` — install is aborted before the files land in
  `wp-content/plugins/` (via `upgrader_source_selection` returning a
  `WP_Error`), and activation is aborted via `deactivate_plugins()` +
  `wp_die()`. This covers repo search-install, zip upload, install-by-URL,
  and WP-CLI (all route through `Plugin_Upgrader`), plus plugins already on
  disk (e.g. dropped in via SFTP).

Mode is **per blocklist entry**, not a single global switch — one entry can
warn while another blocks.

## Editing the blocklist

Edit `inc/blocklist.php`. Each entry is keyed by plugin slug:

```php
'duplicator' => array(
    'name'   => 'Duplicator',              // optional, used as a fallback match
    'mode'   => 'warn',                    // 'warn' or 'block'
    'reason' => 'Unsupported by hosting team — use at your own risk.',
),
```

No settings UI is required to change policy — just edit the array.

## File layout

```
forbidden-plugins-guard.php              ← thin loader, autoloaded by WP from mu-plugins/
forbidden-plugins-guard/
├── inc/
│   ├── class-forbidden-plugins-guard.php  ← hook registration
│   ├── blocklist.php                      ← the actual list + modes (config)
│   ├── admin-notices.php                  ← notice rendering
│   └── functions.php                      ← shared helpers (lookup, scanning)
└── README.md
```

WordPress only autoloads top-level files in `mu-plugins/`, not
subdirectories, so `forbidden-plugins-guard.php`'s only job is to require
the real plugin from its subfolder — mirroring Pantheon's own
`loader.php` → `pantheon-mu-plugin/pantheon.php` pattern.

## Scope

Everything this plugin does is tied to plugin install/activation
transactions or the Plugins list page itself:

- `upgrader_source_selection` / `activate_plugin` only fire during an
  actual install or activation attempt (repo search-install, zip upload,
  install-by-URL, WP-CLI, or activating something already on disk).
- Notices and the row badge only render on the Plugins screen
  (`plugins` / `plugins-network`) — nowhere else in wp-admin, and never on
  the public site.

No audit logging, no settings page — just enforcement + Plugins-page
notices, by design.

## Known limitations

- Slug detection at install time relies on the extracted zip's top-level
  folder name (or, as a fallback, the `Plugin Name` header of a `.php` file
  inside it), since the plugin doesn't have a final slug on disk yet at the
  point `upgrader_source_selection` fires.
- No JSON/CSV import, audit trail, or per-admin persistent notice
  dismissal — see the task list's stretch goals if these become
  priorities.
