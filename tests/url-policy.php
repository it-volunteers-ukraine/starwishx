<?php

/**
 * Regression table for Shared\Policy\UrlPolicy.
 *
 * UrlPolicy's parser ships with PHP, so a PHP update can change what it accepts
 * without anything in the theme changing. This is what notices. Plain PHP, no
 * test framework:
 *
 *   pnpm test    (php tests/url-policy.php; also runs in CI)
 *
 * File: tests/url-policy.php
 */

declare(strict_types=1);

use Shared\Policy\UrlPolicy;

// The two WordPress pieces UrlPolicy touches.
final class WP_Error
{
    public function __construct(public string $code = '', public string $message = '') {}
}

function __(string $text, string $domain = 'default'): string
{
    return $text;
}

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../inc/shared/Policy/UrlPolicy.php';

/** What a field keeps: typed => stored. */
$accepted = [
    // Empty stays empty: whether a field is required is the caller's concern.
    '' => '',
    '   ' => '',

    // Everyday input, stored exactly as before.
    'google.com' => 'https://google.com',
    'google.com:8080' => 'https://google.com:8080',
    '//example.com' => 'https://example.com',
    '  https://example.com  ' => 'https://example.com',
    'http://example.com' => 'http://example.com',
    'https://example.com' => 'https://example.com',
    'https://example.com/' => 'https://example.com/',
    'https://example.com/a?b=1#c' => 'https://example.com/a?b=1#c',
    'https://example.com:443/' => 'https://example.com/',
    'http://example.com:80/x' => 'http://example.com/x',
    'HTTPS://EXAMPLE.COM/Path' => 'https://example.com/Path',
    'www.example.com/path with space' => 'https://www.example.com/path%20with%20space',
    'https://example.com/a(b)c' => 'https://example.com/a(b)c',
    'https://example.com/~user/' => 'https://example.com/~user/',
    'https://example.com/?q=a+b&r=c%20d' => 'https://example.com/?q=a+b&r=c%20d',
    'https://example.com/#/spa/route' => 'https://example.com/#/spa/route',
    'localhost' => 'https://localhost',
    'https://localhost:3000' => 'https://localhost:3000',
    'https://intranet/path' => 'https://intranet/path',
    'https://example.com.' => 'https://example.com.',
    'https://a.b' => 'https://a.b',
    't.me/channel' => 'https://t.me/channel',
    'forms.gle/AbC123' => 'https://forms.gle/AbC123',
    'bit.ly/3xYz' => 'https://bit.ly/3xYz',
    'facebook.com/groups/1234' => 'https://facebook.com/groups/1234',
    'instagram.com/user.name_1/' => 'https://instagram.com/user.name_1/',
    'youtube.com/watch?v=abc_DEF-123&t=1s' => 'https://youtube.com/watch?v=abc_DEF-123&t=1s',
    'docs.google.com/forms/d/e/1FAIpQLS/viewform?usp=sf_link' => 'https://docs.google.com/forms/d/e/1FAIpQLS/viewform?usp=sf_link',
    'https://diia.gov.ua/services' => 'https://diia.gov.ua/services',

    // Non-ASCII paths are percent-encoded, international hosts become punycode.
    'https://example.com/шлях' => 'https://example.com/%D1%88%D0%BB%D1%8F%D1%85',
    'https://uk.wikipedia.org/wiki/Україна' => 'https://uk.wikipedia.org/wiki/%D0%A3%D0%BA%D1%80%D0%B0%D1%97%D0%BD%D0%B0',
    'https://приклад.укр' => 'https://xn--80aikifvh.xn--j1amh',
    'приклад.укр' => 'https://xn--80aikifvh.xn--j1amh',
    'приклад.укр/новини?x=1' => 'https://xn--80aikifvh.xn--j1amh/%D0%BD%D0%BE%D0%B2%D0%B8%D0%BD%D0%B8?x=1',
    'ПРИКЛАД.УКР' => 'https://xn--80aikifvh.xn--j1amh',
    'україна.укр' => 'https://xn--80aa2annq7l.xn--j1amh',
    'їжак.укр' => 'https://xn--80aln7i.xn--j1amh',
    'ґанок.укр' => 'https://xn--80auie62b.xn--j1amh',
    'єдність.укр' => 'https://xn--d1asme4c2bq.xn--j1amh',
    'пример.рф' => 'https://xn--e1afmkfd.xn--p1ai',
    'xn--80aikifvh.xn--j1amh' => 'https://xn--80aikifvh.xn--j1amh',
    'bücher.de' => 'https://xn--bcher-kva.de',
    'straße.de' => 'https://xn--strae-oqa.de',
    'faß.de' => 'https://xn--fa-hia.de',
    'ελληνικός.gr' => 'https://xn--qxaegecap6byf.gr',
    'μουσείο.ελ' => 'https://xn--kxalumcvn.xn--qxam',
    'مثال.موقع' => 'https://xn--mgbh0fb.xn--4gbrim',
    'דוגמה.ישראל' => 'https://xn--6dbbec0c.xn--4dbrk0ce',
    '例子.中国' => 'https://xn--fsqu00a.xn--fiqs8s',
    'ドメイン名例.jp' => 'https://xn--eckwd4c7cu47r2wf.jp',
    '도메인.한국' => 'https://xn--hq1bm8jm9l.xn--3e0b707e',

    // Joiners that belong in the word: the check for invisible characters
    // must not catch these.
    "ශ්\u{200D}රී.ලංකා" => 'https://xn--10cl1a0b660p.xn--fzc2c9e2c',
    "نامه\u{200C}ای.ایران" => 'https://xn--mgba3gch31f060k.xn--mgba3a4f16a',

    // A character the IDNA rules ignore: dropped, so the real domain is stored.
    "https://exa\u{34F}mple.com" => 'https://example.com',

    // The same URL to a browser, spelled the way the parser spells it. These
    // are the only accepted rows league/uri (used until November 2026) stored
    // differently.
    'https://example.com?x=1' => 'https://example.com/?x=1',
    'https://example.com/a/../b' => 'https://example.com/b',
    'https://example.com/privacy/.' => 'https://example.com/privacy/',
    'https://example.com/?a[]=1' => 'https://example.com/?a[]=1',
];

/** Refused with the "valid URL" error, by reason. */
$rejected = [
    'not http or https' => [
        'ftp://example.com',
        'file:///etc/passwd',
        'ws://example.com',
        'javascript:alert(1)',
        'data:text/html,x',
        'mailto:foo@bar.com',
        'tel:+380441234567',
    ],
    'userinfo' => [
        'https://google.com@evil.com',
        'https://user:pw@example.com',
        'https://@example.com',
        'https://:@example.com',
    ],
    'an IP, in any spelling' => [
        '192.168.1.1',
        'https://192.168.1.1',
        'https://[::1]/',
        'https://[2001:db8::1]:8080/',
        'https://127.1',
        'https://2130706433',
        'https://0x7f.0.0.1',
        'https://0177.0.0.1',
        'https://1.2.3',
    ],
    'not a domain' => [
        'https://',
        'https://-example.com',
        'https://example-.com',
        'https://exa_mple.com',
        'https://example..com',
        'https://.example.com',
        'https://exam ple.com',
        'https://example.123',
        'https://256.1.1.1',
        'https://1.2.3.4.5',
        'https://xn--a.com',
        'https://' . str_repeat('a', 64) . '.com',
    ],
    'port' => [
        'https://example.com:99999',
        'https://example.com:abc',
    ],
    'the wrong number of slashes' => [
        'https:///example.com',
        'https:////example.com',
    ],
    'control characters and backslashes' => [
        "https://exa\tmple.com",
        "https://example.com\n.evil.com",
        "https://example.com\r\nX: y",
        "https://example.com/a\nb",
        'https:\\\\evil.com',
        'https://evil.com\\@example.com',
        'https://example.com\\.evil.com',
        'https://example.com/a\\b',
    ],
    'a percent-encoded host' => [
        'https://%65xample.com',
        'https://exa%2Emple.com',
        'https://%e2%98%83',
        'https://a%C2%ADb/',
    ],
    'invisible or confusable characters in the host' => [
        "https://exa\u{200C}mple.com",
        "https://exa\u{200D}mple.com",
        "https://\u{200D}.example",
        "https://look\u{180E}out.net",
        "https://exa\u{202E}mple.com",
        "https://example.com\u{2100}.evil.com",
        "https://a\u{2020}--/x",
        "https://ab--c.\u{DF}/x",
        "https://\u{64A}a/x",
        "https://look\u{5BE}out.net",
    ],
];

$failures = [];
$show = static fn(mixed $value): string => $value instanceof WP_Error
    ? '(rejected)'
    : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

foreach ($accepted as $typed => $stored) {
    $typed = (string) $typed;
    $result = UrlPolicy::validate($typed);
    if ($result !== $stored) {
        $failures[] = "{$show($typed)} should store {$show($stored)}, got {$show($result)}";
    }
    // Saving a form again must not change a stored URL.
    if ($stored !== '' && UrlPolicy::validate($stored) !== $stored) {
        $failures[] = "{$show($stored)} changes when validated again";
    }
}

foreach ($rejected as $reason => $urls) {
    foreach ($urls as $typed) {
        $result = UrlPolicy::validate($typed);
        if (!$result instanceof WP_Error) {
            $failures[] = "{$show($typed)} should be rejected ({$reason}), got {$show($result)}";
        }
    }
}

if (UrlPolicy::validate(null) !== '') {
    $failures[] = 'null should be valid and store an empty string';
}

$total = count($accepted) + array_sum(array_map('count', $rejected)) + 1;
foreach ($failures as $failure) {
    echo "FAIL {$failure}\n";
}
printf("url-policy: %d cases, %d failed\n", $total, count($failures));
exit($failures ? 1 : 0);
