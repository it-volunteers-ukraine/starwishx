# Tests

Three layers, cheapest first. A bug should be caught by the lowest layer that
can see it.

| Layer                | Runner                               | Where                  | What it is for                                                                  |
| -------------------- | ------------------------------------ | ---------------------- | ------------------------------------------------------------------------------- |
| TS unit              | Vitest (`vp test`, ships with Vite+) | `tests/ts/`            | Store logic in Node: validation, fetch/nonce contract, what a form shows, views |
| PHP integration      | PHPUnit 9.6 + WordPress test suite   | `tests/php/`           | REST endpoints, services, hooks, against a real WordPress and MySQL             |
| PHP regression table | plain PHP                            | `tests/url-policy.php` | UrlPolicy against the PHP version's URL parser                                  |
| E2E (next)           | Playwright (installed Edge)          | `tests/e2e/`           | A few journeys in a real browser                                                |

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
```

CI runs Vitest in the build job and the PHP tests in the `php-integration`
job, which has a MySQL service and a downloaded WordPress core. Deploying needs
both jobs to pass.

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

## Writing tests

- **PHP:** extend `Gateway\GatewayTestCase`.
  - `rest()` calls an endpoint as the store does (JSON body plus a `wp_rest`
    nonce).
  - `user()` makes an activated user with a known password.
  - `mailTo()` reads what was "sent".
  - `fromIp()` sets the client IP for the rate limits.
- **TS:** import the store modules directly, not `*-store.ts`, because those
  register the store as a side effect.
  - `tests/ts/setup.ts` mocks `@wordpress/interactivity` with
    `support/interactivity.ts`. Put the state in `fake.stores` and the clicked
    element in `fake.ref`.
  - `support/dom.ts` provides `window.location` and `history`, plus the
    element classes the stores check with `instanceof`.
