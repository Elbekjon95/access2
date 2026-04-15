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
  // Avval til tanlash modalni ko'rsatish
  showLanguageModal();
  
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


// Til tanlash modal
export function showLanguageModal() {
  const modal = document.getElementById('language-modal');
  const grid = document.getElementById('language-grid');
  
  if (!modal || !grid) return;
  
  // Agar til allaqachon tanlangan bo'lsa, modalni ko'rsatmaymiz
  const savedLang = localStorage.getItem('kiosk_lang');
  if (savedLang && savedLang !== 'auto') {
    modal.classList.add('hide');
    return;
  }
  
  // Tillar ro'yxati
  const languages = [
    { code: 'uz', name: "O'zbek", flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#1eb6ff"/><rect y="7" width="30" height="6" fill="#ffffff"/><rect y="13" width="30" height="7" fill="#1eb53a"/><rect y="6.5" width="30" height="1" fill="#ce1126"/><rect y="12.5" width="30" height="1" fill="#ce1126"/></svg>' },
    { code: 'ru', name: 'Русский', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#ffffff"/><rect y="7" width="30" height="6" fill="#0039a6"/><rect y="13" width="30" height="7" fill="#d52b1e"/></svg>' },
    { code: 'en', name: 'English', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#012169"/><rect y="8" width="30" height="4" fill="#ffffff"/><rect x="13" width="4" height="20" fill="#ffffff"/><rect y="9" width="30" height="2" fill="#c8102e"/><rect x="14" width="2" height="20" fill="#c8102e"/></svg>' },
    { code: 'es', name: 'Español', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#c60b1e"/><rect y="5" width="30" height="10" fill="#ffc400"/></svg>' },
    { code: 'zh', name: '中文', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#de2910"/><circle cx="6" cy="6" r="3" fill="#ffde00"/></svg>' },
    { code: 'hi', name: 'हिन्दी', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#ff9933"/><rect y="6.7" width="30" height="6.6" fill="#ffffff"/><rect y="13.3" width="30" height="6.7" fill="#138808"/><circle cx="15" cy="10" r="2.2" fill="#000088"/></svg>' },
    { code: 'ar', name: 'العربية', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#006c35"/><rect x="6" y="9" width="18" height="2" fill="#ffffff"/></svg>' },
    { code: 'bn', name: 'বাংলা', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#006a4e"/><circle cx="13" cy="10" r="5" fill="#f42a41"/></svg>' },
    { code: 'pt', name: 'Português', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="12" height="20" fill="#006600"/><rect x="12" width="18" height="20" fill="#ff0000"/></svg>' },
    { code: 'ja', name: '日本語', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#ffffff"/><circle cx="15" cy="10" r="5" fill="#bc002d"/></svg>' },
    { code: 'de', name: 'Deutsch', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#000000"/><rect y="6.7" width="30" height="6.6" fill="#dd0000"/><rect y="13.3" width="30" height="6.7" fill="#ffce00"/></svg>' },
    { code: 'fr', name: 'Français', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="10" height="20" fill="#002395"/><rect x="10" width="10" height="20" fill="#ffffff"/><rect x="20" width="10" height="20" fill="#ed2939"/></svg>' },
    { code: 'it', name: 'Italiano', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="10" height="20" fill="#009246"/><rect x="10" width="10" height="20" fill="#ffffff"/><rect x="20" width="10" height="20" fill="#ce2b37"/></svg>' },
    { code: 'ko', name: '한국어', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#ffffff"/><circle cx="15" cy="10" r="5" fill="#c60c30"/></svg>' },
    { code: 'tr', name: 'Türkçe', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#e30a17"/><circle cx="12" cy="10" r="5" fill="#ffffff"/><circle cx="13.5" cy="10" r="4" fill="#e30a17"/></svg>' },
    { code: 'ur', name: 'اردو', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#01411c"/><rect width="6" height="20" fill="#ffffff"/><circle cx="17" cy="10" r="4" fill="#ffffff"/></svg>' },
    { code: 'tg', name: 'Тоҷикӣ', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#d81e05"/><rect y="6.7" width="30" height="6.6" fill="#ffffff"/><rect y="13.3" width="30" height="6.7" fill="#006600"/></svg>' },
    { code: 'ky', name: 'Кыргызча', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#e8112d"/><circle cx="15" cy="10" r="4.5" fill="#ffcc00"/></svg>' },
    { code: 'kk', name: 'Қазақша', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#00a3dd"/><circle cx="15" cy="10" r="4.5" fill="#ffcc00"/></svg>' },
    { code: 'tk', name: 'Türkmençe', flag: '<svg width="60" height="40" viewBox="0 0 30 20"><rect width="30" height="20" fill="#007a3d"/><rect width="7" height="20" fill="#c8102e"/></svg>' }
  ];
  
  // Grid'ni to'ldirish
  grid.innerHTML = '';
  languages.forEach(lang => {
    const card = document.createElement('div');
    card.className = 'language-option-card';
    card.innerHTML = `
      <div class="flag-icon">${lang.flag}</div>
      <div class="lang-name">${lang.name}</div>
    `;
    
    card.addEventListener('click', () => {
      selectLanguage(lang.code);
      modal.classList.add('hide');
    });
    
    grid.appendChild(card);
  });
  
  // Modalni ko'rsatish
  modal.classList.remove('hide');
}

function selectLanguage(code) {
  localStorage.setItem('kiosk_lang', code);
  
  // Dropdown'ni yangilash
  const dropdown = document.getElementById('lang-dropdown');
  if (dropdown) {
    dropdown.dataset.value = code;
    const option = dropdown.querySelector(`[data-value="${code}"]`);
    if (option) {
      const labelSlot = dropdown.querySelector('.lang-label');
      const flagSlot = dropdown.querySelector('.lang-flag');
      if (labelSlot) labelSlot.textContent = option.dataset.label || option.textContent.trim();
      if (flagSlot) {
        const flag = option.querySelector('.flag-icon');
        if (flag) flagSlot.innerHTML = flag.innerHTML;
      }
    }
  }
  
  console.log('[LANG] Til tanlandi:', code);
}
