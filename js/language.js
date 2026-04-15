export function getSelectedLanguage() {
  const dropdown = document.getElementById("lang-dropdown");
  if (dropdown && dropdown.dataset.value) return dropdown.dataset.value;
  const el = document.getElementById("lang-select");
  return el ? el.value : "auto";
}

export function getSpeechLang(code) {
  const map = {
    uz: "uz-UZ",
    ru: "ru-RU",
    en: "en-GB",
    es: "es-ES",
    zh: "zh-CN",
    hi: "hi-IN",
    ar: "ar-SA",
    bn: "bn-BD",
    pt: "pt-PT",
    ja: "ja-JP",
    de: "de-DE",
    fr: "fr-FR",
    it: "it-IT",
    ko: "ko-KR",
    tr: "tr-TR",
    ur: "ur-PK",
    tg: "tg-TJ",
    ky: "ky-KG",
    kk: "kk-KZ",
    tk: "tk-TM",
  };
  return map[code] || "uz-UZ";
}

export function guessLanguageFromText(text) {
  if (!text) return "";
  const t = String(text);
  if (/[\u0400-\u04FF]/.test(t)) return "ru";
  if (/[\u0600-\u06FF]/.test(t)) return "ar";
  if (/[\u0900-\u097F]/.test(t)) return "hi";
  if (/[\u0980-\u09FF]/.test(t)) return "bn";
  if (/[\u3040-\u30FF]/.test(t)) return "ja";
  if (/[\u4E00-\u9FFF]/.test(t)) return "zh";
  if (/[\uAC00-\uD7AF]/.test(t)) return "ko";
  return "";
}

export function resolveTtsLanguage(preferred, text) {
  // Agar foydalanuvchi tanlagan til va u o'zbek bo'lsa, uni saqlab qolamiz (krill bo'lsa ham)
  if (preferred === "uz") return "uz";
  
  const fromText = guessLanguageFromText(text);
  if (fromText) return fromText;
  if (preferred && preferred !== "auto") return preferred;
  return "uz";
}

export let cachedVoices = [];
export function loadVoices() {
  cachedVoices = window.speechSynthesis
    ? window.speechSynthesis.getVoices()
    : [];
}

if (window.speechSynthesis) {
  window.speechSynthesis.onvoiceschanged = () => loadVoices();
}

export function pickVoice(language) {
  if (!window.speechSynthesis) return null;
  if (!cachedVoices || cachedVoices.length === 0) {
    loadVoices();
  }
  const voices = cachedVoices || [];
  if (voices.length === 0) return null;

  const preferredLangs = {
    en: ["en-GB", "en-US", "en"],
    ru: ["ru-RU", "ru"],
    es: ["es-ES", "es-MX", "es-US", "es"],
    zh: ["zh-CN", "zh-TW", "zh"],
    hi: ["hi-IN", "hi"],
    ar: ["ar-SA", "ar-EG", "ar"],
    bn: ["bn-BD", "bn-IN", "bn"],
    pt: ["pt-PT", "pt-BR", "pt"],
    ja: ["ja-JP", "ja"],
    de: ["de-DE", "de"],
    fr: ["fr-FR", "fr"],
    it: ["it-IT", "it"],
    ko: ["ko-KR", "ko"],
    tr: ["tr-TR", "tr"],
    ur: ["ur-PK", "ur"],
    tg: ["tg-TJ", "tg"],
    ky: ["ky-KG", "ky"],
    kk: ["kk-KZ", "kk"],
    tk: ["tk-TM", "tk"],
  };

  const desired = preferredLangs[language] || [getSpeechLang(language)];
  const normalize = (s) => String(s || "").toLowerCase();
  const scoreVoice = (v) => {
    let score = 0;
    const vLang = normalize(v.lang);
    const vName = normalize(v.name);
    if (desired.some((d) => vLang.startsWith(normalize(d)))) score += 4;
    if (vName.includes("google")) score += 2;
    if (vName.includes("microsoft")) score += 2;
    if (vName.includes("neural") || vName.includes("natural")) score += 2;
    if (!v.localService) score += 1;
    return score;
  };

  let best = voices[0];
  let bestScore = -1;
  voices.forEach((v) => {
    const s = scoreVoice(v);
    if (s > bestScore) {
      bestScore = s;
      best = v;
    }
  });

  return best;
}

export function initLanguageSelector() {
  const dropdown = document.getElementById("lang-dropdown");
  if (!dropdown) return;
  const toggle = dropdown.querySelector(".lang-toggle");
  const options = dropdown.querySelectorAll(".lang-option");
  const flagSlot = dropdown.querySelector(".lang-flag");
  const labelSlot = dropdown.querySelector(".lang-label");

  const selectValue = (value) => {
    const opt = Array.from(options).find((o) => o.dataset.value === value);
    if (!opt) return;
    dropdown.dataset.value = value;
    labelSlot.textContent = opt.dataset.label || opt.textContent.trim();
    const flag = opt.querySelector(".flag-icon");
    if (flag) flagSlot.innerHTML = flag.innerHTML;
    options.forEach((o) =>
      o.setAttribute("aria-selected", o === opt ? "true" : "false"),
    );
    localStorage.setItem("kiosk_lang", value);
  };

  const saved = localStorage.getItem("kiosk_lang") || "auto";
  selectValue(saved);

  toggle.addEventListener("click", () => {
    const isOpen = dropdown.classList.toggle("open");
    toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
  });

  options.forEach((opt) => {
    opt.addEventListener("click", () => {
      selectValue(opt.dataset.value);
      dropdown.classList.remove("open");
      toggle.setAttribute("aria-expanded", "false");
    });
  });

  document.addEventListener("click", (e) => {
    if (!dropdown.contains(e.target)) {
      dropdown.classList.remove("open");
      toggle.setAttribute("aria-expanded", "false");
    }
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      dropdown.classList.remove("open");
      toggle.setAttribute("aria-expanded", "false");
    }
  });
}
