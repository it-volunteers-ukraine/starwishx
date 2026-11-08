/**
 * Page translation with Google's website translator
 * (template-parts/control-header-translate.php).
 *
 * Google's script used to load on every page, synchronously, in the middle of
 * the header: the page stalled until Google answered, and the menu jumped once
 * the rest of the header arrived. Most visitors never translate, so the script
 * now loads only when a visitor picks a language, or when Google's own cookie
 * (googtrans=/uk/en) says they already did. Google then translates the page by
 * itself, on this page and the next ones.
 *
 * Pages without the site header (/gateway/) have no flags; nothing runs.
 */

const COOKIE = "googtrans";
const CALLBACK = "starwishTranslateInit";

document.addEventListener("DOMContentLoaded", () => {
  const buttons = [...document.querySelectorAll("[data-translate-lang]")];
  const source = document
    .querySelector("[data-translate-source]")
    ?.getAttribute("data-translate-source");
  if (buttons.length === 0 || !source) return;

  const languages = [
    ...new Set(buttons.map((button) => button.dataset.translateLang)),
  ];

  /** The language Google's cookie asks for, or null for the page's own. */
  const active = () => {
    const pair = document.cookie
      .split("; ")
      .find((cookie) => cookie.startsWith(`${COOKIE}=`));
    const target = pair
      ? decodeURIComponent(pair.slice(COOKIE.length + 1))
          .split("/")
          .pop()
      : "";
    return target && target !== source ? target : null;
  };

  const press = (lang) => {
    for (const button of buttons) {
      const pressed = button.dataset.translateLang === lang;
      button.setAttribute("aria-pressed", String(pressed));
    }
  };

  let requested = false;
  const loadGoogle = () => {
    if (requested) return;
    requested = true;

    const box = document.createElement("div");
    box.id = "google_translate_element";
    document.body.append(box);

    window[CALLBACK] = () =>
      new window.google.translate.TranslateElement(
        {
          pageLanguage: source,
          includedLanguages: languages.join(","),
          autoDisplay: false,
        },
        box.id,
      );

    const script = document.createElement("script");
    script.src = `https://translate.google.com/translate_a/element.js?cb=${CALLBACK}`;
    document.head.append(script);
  };

  /** Google writes its cookie for the host and for the parent domains. */
  const forget = () => {
    const parts = location.hostname.split(".");
    const domains = [""];
    for (let i = 0; i < parts.length - 1; i++) {
      domains.push(`; domain=.${parts.slice(i).join(".")}`);
    }
    for (const domain of domains) {
      document.cookie = `${COOKIE}=; max-age=0; path=/${domain}`;
    }
  };

  const choose = (lang) => {
    // Back to the original: Google can't undo itself in place, a reload can.
    if (lang === source) {
      if (active()) {
        forget();
        location.reload();
      }
      return;
    }

    const combo = document.querySelector("select.goog-te-combo");
    if (combo instanceof HTMLSelectElement) {
      // Already loaded: switch in place, through Google's own (hidden) select.
      combo.value = lang;
      combo.dispatchEvent(new Event("change", { bubbles: true }));
    } else {
      // Google reads the cookie when it starts, and translates by it.
      document.cookie = `${COOKIE}=/${source}/${lang}; path=/`;
      loadGoogle();
    }
    press(lang);
  };

  for (const button of buttons) {
    button.addEventListener("click", () =>
      choose(button.dataset.translateLang),
    );
  }

  const current = active();
  press(current ?? source);
  if (current) loadGoogle();
});
