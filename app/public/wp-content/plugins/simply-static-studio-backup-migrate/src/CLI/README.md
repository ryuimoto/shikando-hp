# Simply Static Studio Export — WP-CLI Commands

This directory contains the WP-CLI integration for the Simply Static Studio Export plugin. It lets you run the full export flow or any individual export task directly from the command line.

## Prerequisites
- WordPress site with the Simply Static Studio Export plugin activated.
- WP-CLI installed and working for your site (https://wp-cli.org/).
- Run commands from your WordPress installation root (where wp-config.php is located), or pass `--path=...` as needed.

## Command Namespace
- Base: `wp ssse export`

## Available Commands
- Run full export sequentially in the current process:
  - `wp ssse export run`
- Run single tasks individually:
  - `wp ssse export init`
  - `wp ssse export archive`
  - `wp ssse export config`
  - `wp ssse export config_file`
  - `wp ssse export enumerate_content`
  - `wp ssse export enumerate_media`
  - `wp ssse export enumerate_plugins`
  - `wp ssse export enumerate_themes`
  - `wp ssse export enumerate_tables`
  - `wp ssse export content`
  - `wp ssse export media`
  - `wp ssse export plugins`
  - `wp ssse export themes`
  - `wp ssse export database`
  - `wp ssse export database_file`
  - `wp ssse export clean`

## What the commands do
- `run`: Executes all export tasks synchronously in one process, mirroring the UI export order. It clears previous logs/messages, resets export state, and runs each task in order. Errors halt with a non-zero exit status.
- Individual tasks (e.g., `archive`, `content`): Runs just that task. The command sets the export as "running" for consistency, executes the task, then marks it as not running.

## Logging and output
- CLI outputs progress and status lines to your terminal.
- Detailed logs are written to: `wp-content/uploads/simply-static/ssse-debug.txt`.
- Export artifacts (lists, config, database dump, ZIP) are placed in: `wp-content/uploads/simply-static/`.

## Examples
- Run a full export:
  - `wp ssse export run`
- Run a single task for troubleshooting:
  - `wp ssse export enumerate_plugins`
- Run with a specific path:
  - `wp --path=/path/to/wordpress ssse export run`
- Use in a cron job (example):
  - `*/30 * * * * /usr/local/bin/wp --path=/var/www/html ssse export run >> /var/log/ssse-cli.log 2>&1`

## Notes and behavior
- The CLI flow runs synchronously and does not rely on WP-Cron or async requests.
- Task retries: if a task throws a retry exception, the CLI imitates the background process retry behavior up to the task’s max retries; if a task returns `false`, the CLI loops a few times with short delays.
- Exit status: a fatal task error or missing task class results in a non-zero exit (WP-CLI error). Skippable tasks will log a warning and continue.
- The command names map directly to task class names found in `src/Queue/Tasks`.

## Getting help
- Standard WP-CLI help applies. Try:
  - `wp help ssse export`
  - `wp help ssse export run`
  - `wp help ssse export content`

If you have suggestions for additional CLI features (e.g., status checks or partial ranges), feel free to propose them.