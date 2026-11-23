# Tests

Three layers, cheapest first. A bug should be caught by the lowest layer that
can see it.

| Layer                | Runner                                    | Where                  | What it is for                                                                  |
| -------------------- | ----------------------------------------- | ---------------------- | ------------------------------------------------------------------------------- |
| TS unit              | Vitest (`vp test`, ships with Vite+)      | `tests/ts/`            | Store logic in Node: validation, fetch/nonce contract, what a form shows, views |
| PHP integration      | PHPUnit 9.6 + WordPress test suite        | `tests/php/`           | REST endpoints, services, hooks, against a real WordPress and MySQL             |
| PHP regression table | plain PHP                                 | `tests/url-policy.php` | UrlPolicy against the PHP version's URL parser                                  |
| E2E                  | Playwright (Edge locally, Chromium in CI) | `tests/e2e/`           | The journeys in a real browser, against a running site                          |

The **contract fixture** `fixtures/gateway-state.json` ties the two sides
together. It holds the shape (keys and value types) of the state PHP hydrates
for the Gateway store:

- `php/Gateway/StateContractTest.php` checks PHP's output against it.
- `ts/gateway/contract.test.ts` checks the TS-typed sample state against it.

If you change a field on one side only, one of them fails. After a deliberate
change, update `inc/gateway/Assets/types.ts` and regenerate the fixture with
`UPDATE_FIXTURES=1`.

## Running

```sh
pnpm test        # all of it
pnpm test:ts     # Vitest (vp test watch: watch mode)
pnpm test:php    # PHPUnit
pnpm test:url    # the URL-policy table
pnpm test:e2e    # Playwright, against a running site (not part of `pnpm test`)
```

CI runs Vitest in the build job, the PHP tests in the `php-integration` job
(a MySQL service and a downloaded WordPress core), and the E2E specs in the
`e2e` job, against a fresh WordPress whose theme is the build's `production/`
package. Deploying needs all three to pass.

## PHP integration tests: setup

1. **Its own Composer project.** Run `composer install --working-dir=tests/php`.
   The theme's `vendor/` is committed and deployed, so test tooling must never
   go there. `/tests/` is excluded from the package (`.distignore`), and
   `tests/php/vendor/` is gitignored.
2. **A throwaway database.** The WordPress test suite **drops and recreates
   every `wptests_` table** in its database, so it must never be a site's
   database. `wp-tests-config.php` refuses any database whose name doesn't
   contain `test`. Locally it is `starwish_tests` on Local's MySQL.
3. **Settings.** These come from env vars, or locally from the gitignored
   `tests/php/.env.local.php`:

   ```php
   <?php
   return [
       'WP_TESTS_ABSPATH'     => 'D:/…/app/public/', // a WordPress core
       'WP_TESTS_DB_NAME'     => 'starwish_tests',
       'WP_TESTS_DB_USER'     => 'root',
       'WP_TESTS_DB_PASSWORD' => '…',
       'WP_TESTS_DB_HOST'     => '127.0.0.1:10114',
   ];
   ```

4. **A PHP with mysqli.** The bootstrap stops with a clear message if it's
   missing. On this machine Herd's PHP has it; `C:\tools\php85` doesn't.

Each test runs in a transaction that is rolled back. Users, options and
rate-limit transients never outlive a test, so tests can register accounts
and reset passwords for real. Mail goes to the suite's `MockPHPMailer`. The
bootstrap turns off `EmailPolicy`'s DNS probe (`starwishx/email_dns_check`),
so nothing needs the network.

## E2E tests: setup

1. **A running site.** Locally that's `https://starwish.local`. Playwright
   uses the installed Edge (`channel: "msedge"`), so there's nothing to
   download.
2. **Settings.** These come from env vars (`E2E_BASE_URL`, `E2E_WP_PATH`,
   `E2E_PHP`, `E2E_PHP_ARGS`, `E2E_CHANNEL`), or locally from the gitignored
   `tests/e2e/.env.local.json`:

   ```json
   {
     "baseURL": "https://starwish.local",
     "wpPath": "D:/…/app/public",
     "php": "C:/Users/…/herd/bin/php85/php.exe",
     "phpArgs": ["-d", "mysqli.default_port=10114"]
   }
   ```

   `wpPath` and `php` are for the test-data helper below. Local's
   `wp-config.php` says `localhost`, so the PHP needs the MySQL port.

**Test data.** `support/wp.php` is a CLI-only helper that boots the site's
WordPress and makes what a spec needs:

- `wp.user(name)` makes an activated user, or an unactivated one with
  `{ inactive: true }`;
- `wp.resetKey(login)` returns a real key, as the reset mail would carry it;
- `wp.clearLimits()` clears the rate-limit counters.

The rules:

- Users are always `e2e-<run>-<name>`. The helper refuses any other login.
  The global setup and teardown delete every `e2e-` user, so a crashed run
  leaves nothing behind.
- **No mail is ever sent.** Keys come from the helper, and the lost-password
  form is only given unknown logins. A successful registration sends mail, so
  it's tested by PHPUnit (`RegisterTest`), not here.
- One worker: the specs share the database and the rate-limit counters for
  the browser's IP. Each test still gets its own browser context, so cookies
  stay separate.

**CI.** `support/install.php` installs a throwaway WordPress, and refuses a
database without `test` in its name. It sets up the plugins, the theme,
pretty permalinks and then the theme's managed pages. `support/router.php`
routes PHP's built-in server. The job also adds Secure Custom Fields, because
`footer.php` reads ACF fields.

## Writing tests

- **PHP:** extend `Gateway\GatewayTestCase`.
  - `rest()` calls an endpoint as the store does (JSON body plus a `wp_rest`
    nonce).
  - `assertFieldError($response, $field)` checks an error the form shows
    under a field: the status (422 by default), the code and
    `data.field_errors[$field]`, which is what the store routes inline.
  - `user()` makes an activated user with a known password.
  - `mailTo()` and `onlyMailTo()` read what was "sent".
  - `queryOf($url)` decodes a link's query after parsing it with PHP 8.5's
    WHATWG URL parser, so a link that isn't a valid URL fails there.
  - `fromIp()` sets the client IP for the rate limits.
  - `HardeningTest` holds the language-level guard rails:
    - ignoring a check's result warns (`#[\NoDiscard]`), and `(void)`
      marks a deliberate discard;
    - passwords and reset keys are `#[\SensitiveParameter]`.
  - `FormsTest` reads each form's markup with `Dom\HTMLDocument` and checks
    what the store relies on: `novalidate`, the submit handler, and that every
    `data-field` names a string field of the state and has a label.
  - Tests of shared code (`inc/shared`) go in `tests/php/Shared/`.
- **TS:** import the store modules directly, not `*-store.ts`, because those
  register the store as a side effect.
  - For a form's state while its request is still open, answer `fetch`
    later: `Promise.withResolvers()` (see `pendingFetch()` in
    `submit.test.ts`).
  - The tests type-check in the stores' own environment (browser lib, no
    Node types). The contract fixture is imported as a JSON module.
  - `tests/ts/setup.ts` mocks `@wordpress/interactivity` with
    `support/interactivity.ts`. Put the state in `fake.stores` and the clicked
    element in `fake.ref`.
  - `support/dom.ts` provides `window.location` and `history`, plus the
    element classes the stores check with `instanceof`.
- **E2E:** import `test` and `expect` from `tests/e2e/support/fixtures.ts`.
  - `gateway` is a page object:
    - `goto(view)` opens a view and waits for hydration;
    - `type()`, `submit()`, `fieldError()` and `banner()` drive and read a
      form;
    - `state(s => …)` reads the store's state in the page;
    - `requestsTo(route)` counts the REST calls made.
  - `submit()` calls `requestSubmit()`, the path Enter and the submit
    button take. The forms are `novalidate`, so the store's own checks run
    rather than the browser's constraint validation; without it, an empty
    required field would stop the submit, and the specs would fail.
  - Assert structure (which form shows, whether an error slot is filled,
    URLs, requests), never translated text: the same specs run on the local
    site and on plain WordPress in CI.
  - A test fails on any page error or Interactivity API warning.
