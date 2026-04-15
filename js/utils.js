export function waitMs(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

export function normalizeIataCode(value) {
  const raw = String(value || "")
    .trim()
    .toUpperCase();
  if (!raw) return "";
  if (/^[A-Z]{3}$/.test(raw)) return raw;
  const m = raw.match(/\(([A-Z]{3})\)/);
  if (m) return m[1];
  const m2 = raw.match(/\b([A-Z]{3})\b/);
  if (m2) return m2[1];
  return raw.slice(0, 3);
}

export function extractDestinationCodeFromTo(toText) {
  const to = String(toText || "").toUpperCase();
  const m = to.match(/\(([A-Z]{3})\)/);
  if (m) return m[1];
  const m2 = to.match(/\b([A-Z]{3})\b/);
  return m2 ? m2[1] : "";
}

export function normalizeFlightNo(value) {
  return String(value || "")
    .toUpperCase()
    .replace(/\s+/g, "")
    .trim();
}

export function parseRangeFromText(text) {
  const s = String(text || "");
  const m = s.match(/\b(\d{1,2})\s*[-–]\s*(\d{1,2})\b/);
  if (!m) return null;
  const a = Number(m[1]);
  const b = Number(m[2]);
  if (!Number.isFinite(a) || !Number.isFinite(b)) return null;
  const lo = Math.min(a, b);
  const hi = Math.max(a, b);
  if (lo < 1 || hi > 99) return null;
  return `${lo}-${hi}`;
}

export function parseCounterBounds(counterRange) {
  const s = String(counterRange || "").trim();
  if (!s) return null;
  const mRange = s.match(/(\d{1,2})\s*[-–—]\s*(\d{1,2})/);
  if (mRange) {
    const a = Number(mRange[1]);
    const b = Number(mRange[2]);
    if (!Number.isFinite(a) || !Number.isFinite(b)) return null;
    const lo = Math.min(a, b);
    const hi = Math.max(a, b);
    if (lo < 1 || hi > 99) return null;
    return { lo, hi };
  }
  const mSingle = s.match(/\b(\d{1,2})\b/);
  if (!mSingle) return null;
  const n = Number(mSingle[1]);
  if (!Number.isFinite(n) || n < 1 || n > 99) return null;
  return { lo: n, hi: n };
}

export function parseCounterNumberFromWords(text) {
  const msg = String(text || "")
    .toLowerCase()
    .replace(/[’`ʻʼ]/g, "'")
    .replace(/ё/g, "е")
    .replace(/[^\p{L}\p{N}\s'-]/gu, " ")
    .replace(/\s+/g, " ")
    .trim();
  if (!msg) return null;
  const tokens = msg.split(" ").filter(Boolean);
  const unitValue = (tok) => {
    if (
      /^(один|одна|одно|перв|два|две|втор|три|трет|четыр|пят|шест|сем|восем|девят)/.test(
        tok,
      )
    ) {
      if (/^(один|одна|одно|перв)/.test(tok)) return 1;
      if (/^(два|две|втор)/.test(tok)) return 2;
      if (/^(три|трет)/.test(tok)) return 3;
      if (/^четыр/.test(tok)) return 4;
      if (/^пят/.test(tok)) return 5;
      if (/^шест/.test(tok)) return 6;
      if (/^сем/.test(tok)) return 7;
      if (/^восем/.test(tok)) return 8;
      if (/^девят/.test(tok)) return 9;
    }
    if (
      /^(bir|bitta|birinchi|ikki|ikkinchi|uch|uchinchi|to'rt|tort|toʻrt|to‘rt|turt|besh|beshinchi|olti|oltinchi|yetti|yettinchi|sakkiz|sakkizinchi|to'qqiz|toqqiz|to‘qqiz|toʻqqiz|toqqizinchi)$/.test(
        tok,
      )
    ) {
      if (/^(bir|bitta|birinchi)$/.test(tok)) return 1;
      if (/^(ikki|ikkinchi)$/.test(tok)) return 2;
      if (/^(uch|uchinchi)$/.test(tok)) return 3;
      if (/^(to'rt|tort|toʻrt|to‘rt|turt|to'rtinchi|tortinchi)$/.test(tok))
        return 4;
      if (/^(besh|beshinchi)$/.test(tok)) return 5;
      if (/^(olti|oltinchi)$/.test(tok)) return 6;
      if (/^(yetti|yettinchi)$/.test(tok)) return 7;
      if (/^(sakkiz|sakkizinchi)$/.test(tok)) return 8;
      if (/^(to'qqiz|toqqiz|to‘qqiz|toʻqqiz|toqqizinchi)$/.test(tok)) return 9;
    }
    return null;
  };
  const tensValue = (tok) => {
    if (/^десят/.test(tok)) return 10;
    if (/^двадцат/.test(tok)) return 20;
    if (/^тридцат/.test(tok)) return 30;
    if (/^сорок/.test(tok)) return 40;
    if (/^пятьдесят|^пятидесят/.test(tok)) return 50;
    if (/^шестьдесят|^шестидесят/.test(tok)) return 60;
    if (/^семьдесят|^семидесят/.test(tok)) return 70;
    if (/^восемьдесят|^восьмидесят/.test(tok)) return 80;
    if (/^девяност/.test(tok)) return 90;
    if (/^(on|o'n|o‘n|oʻn|oninchi)$/.test(tok)) return 10;
    if (/^(yigirma|yigirmanchi)$/.test(tok)) return 20;
    if (/^(ottiz|o'ttiz|o‘ttiz|oʻttiz|o'ttizinchi|ottizinchi)$/.test(tok))
      return 30;
    if (/^(qirq|qirqinchi)$/.test(tok)) return 40;
    if (/^(ellik|elliginchi)$/.test(tok)) return 50;
    if (/^(oltmish|oltmishinchi)$/.test(tok)) return 60;
    if (/^(yetmish|yetmishinchi)$/.test(tok)) return 70;
    if (/^(sakson|saksoninchi)$/.test(tok)) return 80;
    if (/^(toqson|to'qson|to‘qson|toʻqson|toqsoninchi)$/.test(tok)) return 90;
    return null;
  };
  for (let i = 0; i < tokens.length; i++) {
    const tok = tokens[i];
    const directDigit = tok.match(/^(\d{1,2})$/);
    if (directDigit) {
      const n = Number(directDigit[1]);
      if (n >= 1 && n <= 99) return n;
    }
    const t = tensValue(tok);
    if (t) {
      const next = tokens[i + 1] || "";
      const u = unitValue(next);
      if (u) return t + u;
      return t;
    }
    const u = unitValue(tok);
    if (u) return u;
  }
  return null;
}

export function extractCounterRangeFromQuery(text) {
  const msg = String(text || "").toLowerCase();
  const hasCounterIntent =
    /(stoyka|стойк|counter|check[-\s]?in|checkin|registr|регистрац|стойку|стойка)/.test(
      msg,
    ) || /(stoykaga|stoykada|stoykani)/.test(msg);
  if (!hasCounterIntent) return null;
  const explicitRange = parseRangeFromText(msg);
  if (explicitRange) return explicitRange;
  const numberNearCounter =
    msg.match(
      /(?:stoyka|стойк[а-я]*|counter|check[-\s]?in|checkin|регистрац[а-я]*)\s*(\d{1,2})\b/i,
    ) ||
    msg.match(
      /\b(\d{1,2})\s*(?:stoyka|стойк[а-я]*|counter|check[-\s]?in|checkin|регистрац[а-я]*)/i,
    );
  if (numberNearCounter && numberNearCounter[1]) {
    const n = Number(numberNearCounter[1]);
    if (n >= 1 && n <= 99) return `${n}-${n}`;
  }
  const nFromWords = parseCounterNumberFromWords(msg);
  if (nFromWords && nFromWords >= 1 && nFromWords <= 99) {
    return `${nFromWords}-${nFromWords}`;
  }
  return null;
}

export function extractCounterRangeFromFlight(flight) {
  const checkin = String(flight?.checkin_counters || "");
  const status = String(flight?.status || "");
  const fromCheckin = parseRangeFromText(checkin);
  if (fromCheckin) return fromCheckin;
  const statusLike =
    /(on\s*schedule|gate\s*open|check-?in\s*open|departed|delayed|cancelled|vaqtida|по\s*расписанию|отмен|задерж|открыт)/i;
  const fromStatus = parseRangeFromText(status);
  if (fromStatus && (statusLike.test(checkin) || !/\d/.test(checkin))) {
    return fromStatus;
  }
  return null;
}

export function escapeRegExp(value) {
  return String(value || "").replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

export function replyMentionsCounterForFlight(replyText, flightNo, counterRange) {
  const text = String(replyText || "");
  const no = String(flightNo || "").trim();
  const range = String(counterRange || "").trim();
  if (!text || !no || !range) return false;

  const compact = normalizeFlightNo(no);
  if (!compact) return false;

  const flightPattern = compact.split("").map(escapeRegExp).join("\\s*");
  const reFlight = new RegExp(flightPattern, "i");
  const m = reFlight.exec(text);
  if (!m || typeof m.index !== "number") return false;

  // Window size increased for flexibility
  const start = Math.max(0, m.index - 100);
  const end = Math.min(text.length, m.index + String(m[0]).length + 1200);
  const windowText = text.slice(start, end);

  const reCounterWord =
    /(stoyka|стойк|counter|check[-\s]?in|регистрац|ro['’`]?yxat|ro‘yxat|registration|ro'yxat)/i;
  
  const rm = String(range).match(/(\d{1,2})\s*[-–—]\s*(\d{1,2})/);
  if (!rm) {
    // Single number case
    const single = String(range).match(/\b(\d{1,2})\b/);
    if (!single) return false;
    const reSingle = new RegExp(`\\b${single[1]}\\b`);
    return reCounterWord.test(windowText) && reSingle.test(windowText);
  }

  const a = Number(rm[1]);
  const b = Number(rm[2]);
  const lo = Math.min(a, b);
  const hi = Math.max(a, b);

  // Flexible range check (handles 39-40, 39 - 40, etc.)
  const reRange = new RegExp(`\\b${lo}\\s*[-–—]\\s*${hi}\\b`);
  return reCounterWord.test(windowText) && reRange.test(windowText);
}

/**
 * Text ichidagi 'stoyka 39-40' kabi so'zlarni topib, ularni bosiladigan tugmaga aylantiradi
 */
export function enhanceTextWithLinks(text, callbackName = "window.routeToCheckinCounter") {
  if (!text) return "";
  
  // Markdown va boshqa tozalashlar
  let html = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
  
  // Stoyka raqamlarini aniqlash (39-40, 39 - 40, 39 yoki B25-B29 formatlari)
  const pattern = /(stoyka(?:lari)?|check-in|registratsiya|ro'yxatdan o'tish|counter)\s+(\d{1,2}(?:\s*[-–—]\s*\d{1,2})?)/gi;
  
  html = html.replace(pattern, (match, word, range) => {
    return `<span class="inline-stoyka-link" onclick="${callbackName}('${range.replace(/\s+/g, '')}')">${word} <span class="stoyka-tag">${range}</span></span>`;
  });
  
  return html;
}

export function expandDestinationCodes(destCode) {
  const code = normalizeIataCode(destCode);
  const groups = {
    MOW: ["MOW", "SVO", "DME", "VKO"],
    NYC: ["NYC", "JFK", "EWR", "LGA"],
  };
  return groups[code] || [code];
}

export function normalizeTranscription(text) {
  if (!text) return text;
  let t = text.trim().replace(/\s+/g, " ");
  t = t.replace(/\bmalumot\b/gi, "ma'lumot");
  t = t.replace(/\bhaqda\b/gi, "haqida");
  t = t.replace(/\bH\s*Y\s*(\d{2,4})\b/gi, "HY $1");
  t = t.replace(/\bHY\s*[-:]?\s*(\d{2,4})\b/gi, "HY $1");
  
  // Yo'nalish so'zlari
  t = t.replace(/\byunawashi(bu|da|ni)?\b/gi, "yo'nalishi");
  t = t.replace(/\byonalish(i|da|ni)?\b/gi, "yo'nalishi");
  
  // Uchish/Ketish so'zlari
  t = t.replace(/\buchakandai\b/gi, "uchib ketadigan");
  t = t.replace(/\buchakanda\b/gi, "uchib ketadigan");
  t = t.replace(/\buchakan\b/gi, "uchib ketadigan");
  t = t.replace(/\bketish\b/gi, "uchib ketish");
  
  // Kelish so'zlari
  t = t.replace(/\bkelayotgan\b/gi, "kelayotgan");
  t = t.replace(/\bkeladigan\b/gi, "keladigan");
  
  // Reys so'zlari
  t = t.replace(/\breisdara\b/gi, "reyslar");
  t = t.replace(/\breysda\b/gi, "reyslar");
  
  // Shahar so'zlari (Xatoliklarni tuzatish)
  t = t.replace(/\bbudan\b/gi, "Istanbuldan");
  t = t.replace(/\bstambul\b/gi, "Istanbul");
  t = t.replace(/\bmaskva\b/gi, "Moskva");
  
  return t;
}

export function isNavigationQuery(text) {
  const msg = String(text || "").toLowerCase();
  return (
    /(qayerda|qaerda|qaysi joyda|qayerga|joylashgan|boriladi|yo'nalish|yo'l|yol)/.test(
      msg,
    ) ||
    /(hojatxona|tualet|wc|info|ma'lumot|reception|zina|eskavator|chiqish|kirish|masjid|mosque|mesjid|prayer|musalla|namoz|namaz|mescit|cami|mezquita|mesquita|moschee|moschea|мечет|мечеть|مسجد|cip|vip|lounge|business)/.test(
      msg,
    ) ||
    /(stoyka|стойк|counter|check[-\s]?in|checkin|registr|регистрац|стойку|стойка)/.test(
      msg,
    ) ||
    /(cip[-\s]?zona|cip\s+zone|vip[-\s]?zal|бизнес[-\s]?зал|лаунж|вип)/.test(
      msg,
    )
  );
}

// Removed typewriterEffect from utils to avoid duplication with ui.js
