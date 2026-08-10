import { state } from "./config.js";
import { getSelectedLanguage, resolveTtsLanguage } from "./language.js";
import {
  showModal,
  hideModal,
  setComplaintStatus,
  resetComplaintPreview,
} from "./ui.js";
import { speakText } from "./voice.js?v=2";
import { showEarthRoute } from "./globe.js";
import { enhanceTextWithLinks } from "./utils.js";
import { autoCapture } from "./camera.js";
import {
  parseCounterBounds,
  extractCounterRangeFromQuery,
  extractCounterRangeFromFlight,
  replyMentionsCounterForFlight,
  normalizeFlightNo,
  expandDestinationCodes,
  extractDestinationCodeFromTo,
} from "./utils.js";

export function setEarthRouteActionsPlaceholder(text) {
  const actionsWrap = document.getElementById("earth-route-actions");
  if (actionsWrap) {
    actionsWrap.innerHTML = `<div style="opacity: 0.5; font-style: italic; padding: 10px; font-size: 0.9em; color: var(--secondary-blue); text-shadow: 0 0 5px rgba(0, 198, 255, 0.4);">[SYSTEM] ${text}</div>`;
  }
}

export function showEarthRouteActionNote(text) {
  const terminal = document.getElementById("terminal-text");
  if (terminal) {
    const note = document.createElement("div");
    note.style.borderTop = "1px solid rgba(0,255,0,0.2)";
    note.style.marginTop = "10px";
    note.style.paddingTop = "8px";
    note.style.fontSize = "0.85em";
    note.style.color = "#00ff00";
    note.style.fontFamily = "'Courier New', monospace";
    note.innerHTML = `<span style="opacity: 0.7;">>> SYSTEM_NOTE:</span> ${text}`;
    terminal.appendChild(note);
    terminal.scrollTop = terminal.scrollHeight;
  }
}

export function isNavigationQuery(text) {
  const msg = String(text || "").toLowerCase();
  
  // Navigatsiya uchun maxsus so'rovlar (qayerda, yo'l ko'rsat va h.k.)
  const isWhereQuery = /(qayerda|qaerda|qaysi joyda|qayerga|joylashgan|boriladi|yo'nalish|yo'l|yol|borsam|bursand|borish)/.test(msg);
  
  // Maxsus joy isimlarini tekshirish (faqat joy nomining o'zi aytilsa ham navigatsiya deb hisoblash uchun)
  const isLocationMention = /(hojatxona|tualet|wc|reception|zina|eskavator|chiqish|kirish|masjid|mosque|mesjid|prayer|musalla|namoz|namaz|mescit|cami|mezquita|mesquita|moschee|moschea|мечет|мечеть|مسجد|lounge|business)/.test(msg) ||
    /(stoyka|стойк|counter|check[-\s]?in|checkin|registr|регистрац|стойku|стойka)/.test(msg) ||
    /(cip[-\s]?zona|cip\s+zone|vip[-\s]?zal|бизнес[-\s]?зал|лаунж|вип|vi\s*ip[-\s]?zal|bi\s*ay\s*pi[-\s]?zal|si\s*ay\s*pi\s*zona)/.test(msg);

  // "ma'lumot" so'zi bo'lsa, u faqat "qayerda" bilan kelsa navigatsiya deb hisoblanadi
  if (msg.includes("ma'lumot") || msg.includes("malumot") || msg.includes("info")) {
      return isWhereQuery; // Agarda "ma'lumot" qayerda deb so'ralgan bo'lsa - true, aks holda false
  }

  return isWhereQuery || isLocationMention;
}

export function findLocalNavTarget(text) {
  const msg = String(text || "").toLowerCase();
  const nodes = (window.airportNav && window.airportNav.nodes) || [];
  
  const normalize = (t) => String(t || "").toLowerCase().replace(/[^a-z0-9\s]/g, " ").replace(/\s+/g, " ").trim();
  const msgNorm = normalize(msg);

  // 1. Stoyka (Counter range matching: 1-10, 11-20, 21-30, 31-40)
  const counterRange = extractCounterRangeFromQuery(msg);
  if (counterRange) {
    const counterNode = findMapNodeForCounterRange(counterRange);
    if (counterNode) return counterNode;
    return { type: "counter_range", counterRange };
  }

  // 2. Direct name match within msg
  const byName = nodes.find(n => {
      const nodeName = normalize(n.name);
      return nodeName.length > 2 && (msgNorm.includes(nodeName) || nodeName.includes(msgNorm));
  });
  if (byName) return byName;

  // 3. Type & Keyword matches
  if (/(hojatxona|tualet|wc|toilet|restroom|bathroom|туалет|санузел)/.test(msg)) {
      return nodes.find(n => n.type === 'toilet' || n.name.toLowerCase().includes('hojatxona') || n.name.toLowerCase().includes('toilet'));
  }
  if (/(masjid|mosque|prayer|namoz|namaz|мечеть|молельная)/.test(msg)) {
      return nodes.find(n => n.type === 'mosque' || n.name.toLowerCase().includes('masjid') || n.name.toLowerCase().includes('namoz'));
  }
  if (/(anor)/.test(msg)) {
      return nodes.find(n => n.name.toLowerCase().includes('anor'));
  }
  if (/(anjir)/.test(msg)) {
      return nodes.find(n => n.name.toLowerCase().includes('anjir'));
  }
  if (/(cip|vip|lounge|business|zal|вип|лаунж|бизнес)/.test(msg)) {
      return nodes.find(n => n.type === 'cip' || n.type === 'vip' || n.name.toLowerCase().includes('cip') || n.name.toLowerCase().includes('vip'));
  }
  if (/(kafe|cafe|food|ovqat|qahva|coffee|restoran|restaurant|кафе|ресторан)/.test(msg)) {
      return nodes.find(n => n.type === 'cafe' || n.type === 'restaurant' || n.name.toLowerCase().includes('kafe') || n.name.toLowerCase().includes('restoran'));
  }
  if (/(duty|free|magazin|shop|do\'kon|dokon|дюти|магазин)/.test(msg)) {
       return nodes.find(n => n.name.toLowerCase().includes('duty') || n.name.toLowerCase().includes('free') || n.type === 'shop');
  }
  if (/(tibbiyot|medpunkt|doktor|vrach|shifokor|first\s*aid|medical|медпункт|аптека)/.test(msg)) {
      return nodes.find(n => n.type === 'medical' || n.name.toLowerCase().includes('tibbiyot') || n.name.toLowerCase().includes('medpunkt'));
  }
  if (/(info|ma'lumot|malumot|reception|spravka|справка)/.test(msg)) {
      return nodes.find(n => n.type === 'reception' || n.name.toLowerCase().includes('info') || n.name.toLowerCase().includes('axborot'));
  }
  if (/(bagaj|yuk|baggage|luggage|багаж)/.test(msg)) {
      return nodes.find(n => n.type === 'baggage' || n.name.toLowerCase().includes('bagaj'));
  }
  if (/(darvoza|gate|выход)/.test(msg)) {
      const m = msg.match(/(?:darvoza|gate|выход)\s*([a-z]?\d+)/i);
      if (m && m[1]) {
          const gCode = m[1].toUpperCase();
          const gNode = nodes.find(n => n.type === 'gate' && n.name.toUpperCase().includes(gCode));
          if (gNode) return gNode;
      }
      return nodes.find(n => n.type === 'gate');
  }

  return null;
}

let flightsCache = null;
let lastCacheTime = 0;

/**
 * Reyslar ro'yxatini keshlagan holda yuklash (30 sekund muddat bilan)
 */
export async function getFlightsListCached() {
  const now = Date.now();
  if (flightsCache && (now - lastCacheTime < 30000)) {
    return flightsCache;
  }
  try {
    const res = await fetch("api/flights.php");
    flightsCache = await res.json();
    lastCacheTime = now;
    return flightsCache;
  } catch (e) {
    console.error("Flights fetch error:", e);
    return [];
  }
}

export function pickFlightsForEarthPanel(flights, destinationCode, replyText) {
  const targetCodes = expandDestinationCodes(destinationCode);
  const destFlights = (flights || []).filter((f) => {
    const toCode = extractDestinationCodeFromTo(f?.to);
    return targetCodes.includes(toCode);
  });

  const replyNorm = normalizeFlightNo(replyText);
  let selected = destFlights.filter((f) =>
    replyNorm.includes(normalizeFlightNo(f.flight_no)),
  );
  if (!selected.length) selected = destFlights;

  const unique = [];
  const seen = new Set();
  selected.forEach((f) => {
    const key = `${normalizeFlightNo(f.flight_no)}|${String(f.time || "")}`;
    if (seen.has(key)) return;
    seen.add(key);
    unique.push(f);
  });
  return unique.slice(0, 6);
}

export async function renderEarthRouteActions(destinationCode, replyText) {
  const actionsWrap = document.getElementById("earth-route-actions");
  if (!actionsWrap) return;

  setEarthRouteActionsPlaceholder("Stoyka yo'nalishlari yuklanmoqda...");
  try {
    const flights = await getFlightsListCached();
    const selected = pickFlightsForEarthPanel(
      flights,
      destinationCode,
      replyText,
    );
    const withCounters = selected
      .map((f) => ({
        flight: f,
        counterRange: extractCounterRangeFromFlight(f),
      }))
      .filter(
        (x) =>
          (!!x.counterRange &&
            replyMentionsCounterForFlight(
              replyText,
              x.flight && x.flight.flight_no,
              x.counterRange,
            )) ||
          (replyText && replyText.includes(x.counterRange)),
      );

    if (!withCounters.length) {
      actionsWrap.innerHTML = "";
      return;
    }

    actionsWrap.innerHTML = "";
    withCounters.forEach(({ flight, counterRange }) => {
      const btn = document.createElement("button");
      btn.className = "earth-route-btn";
      btn.type = "button";
      btn.innerHTML = `<strong>${flight.flight_no}</strong> - <span class="counter">${counterRange}</span> stoykaga yo'nalish`;
      btn.onclick = () =>
        routeToCheckinCounter(counterRange, flight.flight_no, flight.gate);
      actionsWrap.appendChild(btn);
    });
    showEarthRouteActionNote(
      "Stoyka tugmasini bosing - ichki xaritada yo'nalish chiziladi.",
    );
  } catch (e) {
    console.error("Earth route actions error:", e);
    setEarthRouteActionsPlaceholder("Stoyka tugmalarini yuklab bo'lmadi.");
  }
}

function findMapNodeForCounterRange(counterRange) {
  const nodes = (window.airportNav && window.airportNav.nodes) || [];
  if (!nodes.length) return null;

  const toNorm = (v) =>
    String(v || "")
      .toLowerCase()
      .replace(/[^\p{L}\p{N}\s-]/gu, " ")
      .replace(/\s+/g, " ")
      .trim();
  const target = parseCounterBounds(counterRange);
  if (!target) return null;
  const t1 = target.lo;
  const t2 = target.hi;
  const kioskX = Number(window.airportNav?.kioskPos?.x || 0);
  const kioskY = Number(window.airportNav?.kioskPos?.y || 0);
  const isCounterNode = (normName, type) =>
    /(stoyka|стойк|counter|check[-\s]?in|checkin|registr|регистрац|desk)/i.test(
      normName,
    ) || /check|counter|stoyka|стойka/i.test(String(type || ""));

  let best = null;
  let bestScore = -1;
  let bestDist = Infinity;
  for (const node of nodes) {
    const name = String(node?.name || "");
    const norm = toNorm(name);
    const nodeRange = parseCounterBounds(name);
    const counterNode = isCounterNode(norm, node?.type);
    let score = 0;

    if (!counterNode && !nodeRange) continue;
    if (counterNode) score += 30;

    if (nodeRange) {
      const n1 = nodeRange.lo;
      const n2 = nodeRange.hi;
      const exact = n1 === t1 && n2 === t2;
      if (exact) score += 360;

      const overlap = Math.max(0, Math.min(t2, n2) - Math.max(t1, n1) + 1);
      if (overlap > 0) {
        score += 220 + overlap * 4;
        // Agar t1 va t2 bitta raqam bo'lsa (masalan 29) va u n1-n2 ichida bo'lsa, juda katta ball beramiz
        if (t1 === t2 && t1 >= n1 && t1 <= n2) {
            score += 500; 
        }
      } else {
        const gap = n1 > t2 ? n1 - t2 : t1 - n2;
        score -= Math.min(140, Math.max(0, gap) * 5);
      }

      if (t1 === t2 && n1 === n2 && n1 === t1) score += 120;
      if (t1 === t2 && n1 <= t1 && n2 >= t1) score += 100;
      if (norm.includes(`${t1}-${t2}`)) score += 40;
      if (t1 === t2) {
        const one = String(t1);
        const numWord = new RegExp(`(?:^|\\s)${one}(?:\\s|$)`);
        if (numWord.test(norm)) score += 30;
      }
    } else if (t1 === t2) {
      const one = String(t1);
      const numWord = new RegExp(`(?:^|\\s)${one}(?:\\s|$)`);
      if (numWord.test(norm)) score += 45;
    }

    const dx = Number(node?.pos_x || 0) - kioskX;
    const dy = Number(node?.pos_y || 0) - kioskY;
    const dist = Math.hypot(dx, dy);

    if (score > bestScore || (score === bestScore && dist < bestDist)) {
      bestScore = score;
      bestDist = dist;
      best = node;
    }
  }

  return bestScore >= 120 ? best : null;
}

export function routeToCheckinCounter(counterRange, flightNo, gateCode = "") {
  showModal("map-modal");
  const mapModal = document.getElementById("map-modal");
  if (mapModal) mapModal.style.zIndex = "180";
  
  window.NAV_CAMERA_FOLLOW = true;
  window.NAV_CAMERA_ZOOM = 2; // Stoykaga borishda yaqinroq ko'rsatish

  if (typeof window.resetMapView === "function") window.resetMapView();

  const directNode = findMapNodeForCounterRange(counterRange);
  const norm = (v) =>
    String(v || "")
      .toLowerCase()
      .replace(/[^\p{L}\p{N}\s-]/gu, " ")
      .replace(/\s+/g, " ")
      .trim();
  const nodes = (window.airportNav && window.airportNav.nodes) || [];

  const resolveExistingTarget = (candidateList) => {
    const cands = (candidateList || []).map((c) => norm(c)).filter(Boolean);
    if (!cands.length) return null;
    for (const n of nodes) {
      const name = String(n && n.name ? n.name : "");
      const nn = norm(name);
      if (!nn) continue;
      if (cands.includes(nn)) return name;
    }
    for (const n of nodes) {
      const name = String(n && n.name ? n.name : "");
      const nn = norm(name);
      if (!nn) continue;
      if (cands.some((c) => nn.includes(c) || c.includes(nn))) return name;
    }
    return null;
  };

  const bounds = parseCounterBounds(counterRange);
  const singleCounter =
    bounds && bounds.lo === bounds.hi ? String(bounds.lo) : null;

  const candidates = directNode
    ? [directNode.name]
    : [
        `CHECK-IN ${counterRange}`,
        `CHECK IN ${counterRange}`,
        `CHECKIN ${counterRange}`,
        `COUNTER ${counterRange}`,
        `STOYKA ${counterRange}`,
        `${counterRange} STOYKA`,
        ...(singleCounter
          ? [
              `CHECK-IN ${singleCounter}`,
              `CHECK IN ${singleCounter}`,
              `CHECKIN ${singleCounter}`,
              `COUNTER ${singleCounter}`,
              `STOYKA ${singleCounter}`,
              `${singleCounter} STOYKA`,
            ]
          : []),
      ];

  let found = null;
  const targetName =
    resolveExistingTarget(candidates) || (directNode ? directNode.name : null);

  if (targetName && window.airportNav) {
    if (directNode && typeof window.airportNav.navigateTo === 'function') {
      found = directNode;
      window.airportNav.navigateTo(directNode.pos_x, directNode.pos_y, directNode.name);
    } else {
      found = window.airportNav.findPath(targetName);
    }
  }

  if (!found && gateCode && window.airportNav) {
    const gate = String(gateCode).trim().toUpperCase();
    const gateCandidates = [`${gate}`, `GATE ${gate}`, `DARVOZA ${gate}`];
    const gateTarget = resolveExistingTarget(gateCandidates);
    if (gateTarget) {
      found = window.airportNav.findPath(gateTarget);
    } else {
      found = window.airportNav.findPath(gate);
    }
    if (found) {
      showEarthRouteActionNote(
        `Stoyka ${counterRange} topilmadi, ${gate} darvozaga yo'nalish ko'rsatildi.`,
      );
    }
  }

  if (!found) {
    const info = `Stoyka ${counterRange} (${flightNo}) uchun xaritada nuqta topilmadi.`;
    console.warn(info);
    showEarthRouteActionNote(info);
  }
}

export function showQR(name) {
  const container = document.getElementById("qr-container");
  const img = document.getElementById("qr-image");
  const label = document.getElementById("qr-label");
  if (!container || !img) return;

  const qrMap = {
    'Cargo': 'Cargo (Yuk) xizmati',
    'CIP': 'CIP zali xizmatlari',
    'FASTTRACK': 'Fast Track xizmati',
    'Helicopters': 'Vertolyot xizmatlari',
    'SILK': 'Silkavia reyslari'
  };

  img.src = `img/QR/${name}.png`;
  if (label) label.innerText = qrMap[name] || name;
  
  container.classList.remove("hide");
  
  // 30 soniyadan keyin avtomatik yopish (ixtiyoriy)
  if (window._qrTimeout) clearTimeout(window._qrTimeout);
  window._qrTimeout = setTimeout(() => hideQR(), 30000);
}

export function hideQR() {
  const container = document.getElementById("qr-container");
  if (container) container.classList.add("hide");
  if (window._qrTimeout) clearTimeout(window._qrTimeout);
}

window.hideQR = hideQR;

// Make it available globally for inline links
window.routeToCheckinCounter = routeToCheckinCounter;
window.handleInlineRoute = routeToCheckinCounter;

export async function sendMessage(message) {
  if (!message) return;
  
  if (typeof window.hideQR === "function") window.hideQR();

  state.lastUserMessage = message;
  const assistantTextElement = document.getElementById("assistant-text");
  
  if (assistantTextElement) {
    assistantTextElement.innerHTML = `<div style="opacity: 0.6; font-size: 0.9em; margin-bottom: 1rem; border-left: 3px solid var(--secondary-blue); padding-left: 1rem;">🎤 Siz: "${message}"</div><div>...</div>`;
  }
  
  autoCapture(true);

  try {
    const lang = getSelectedLanguage();
    const response = await fetch("api/chat.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ message: message, language: lang }),
    });

    const responseText = await response.text();
    const cleanedResponseText = responseText.replace(/^\uFEFF/, "");
    let data;
    try {
      data = JSON.parse(cleanedResponseText);
    } catch (e) {
      console.error("JSON Error:", responseText);
      if (assistantTextElement)
        assistantTextElement.innerText =
          "Serverdan noto'g'ri javob keldi. Admin bilan bog'laning.";
      return;
    }

    if (data.reply) {
      state.lastAssistantResponseData = data;

      if (assistantTextElement) {
        // SYSTEM_NOTE larni ajratib, pastga ko'chirish
        const sysNotes = [];
        const cleanReply = data.reply.replace(/>>\s*SYSTEM[_\s]?NOTE\s*:([^\n]*)/gi, (_, note) => {
          sysNotes.push(note.trim());
          return '';
        }).trim();

        const enhancedReply = enhanceTextWithLinks(cleanReply);
        const noteHtml = sysNotes.length
          ? `<div style="margin-top:1rem;padding:0.5rem 0.75rem;border-left:3px solid #4a9eff;opacity:0.65;font-size:0.82em;color:#8ecfff;">${sysNotes.map(n => `⚙ ${n}`).join('<br>')}</div>`
          : '';
        assistantTextElement.innerHTML = `<div style="opacity: 0.6; font-size: 0.9em; margin-bottom: 1rem; border-left: 3px solid var(--secondary-blue); padding-left: 1rem;">🎤 Siz: "${message}"</div><div>${enhancedReply}</div>${noteHtml}`;
      }

      const ttsLang = resolveTtsLanguage(
        data.language || lang || "uz",
        data.reply,
      );
      speakText(data.reply, ttsLang);

      // 3D Globe — origin + destination bo'lsa DOIM birinchi ochiladi
      // (data.location bo'lsa ham, globe orqali stoykaga yo'nalish beriladi)
      if (data.show_earth_route && data.origin && data.destination) {
        setTimeout(() => {
          showEarthRoute(data.origin, data.destination, data);
        }, 1000);
      }

      if (data.qr) {
        showQR(data.qr);
      } else {
        hideQR();
      }

      // Harita — agar lokatsiya aniqlangan bo'lsa yoki navigatsiya so'rovi bo'lsa
      if (data.location && !data.origin && !data.destination) {
        showModal("map-modal");
        const loc = data.location;
        console.log("[NAV] Triggering map navigation to:", loc);
        setTimeout(() => {
          if (window.airportNav) {
            window.airportNav.resizeCanvasToContainer();
            const navTarget = findLocalNavTarget(loc);
            if (navTarget && navTarget.pos_x && typeof window.airportNav.navigateTo === 'function') {
              window.airportNav.navigateTo(navTarget.pos_x, navTarget.pos_y, navTarget.name || loc);
            } else {
              window.airportNav.findPath(loc);
            }
          }
        }, 200);
      } else if (isNavigationQuery(state.lastUserMessage)) {
        const target = findLocalNavTarget(state.lastUserMessage);
        if (target) {
          showModal("map-modal");
          console.log("[NAV] Triggering local navigation from query:", target);
          setTimeout(() => {
            if (target && typeof target === "object" && target.counterRange) {
              routeToCheckinCounter(target.counterRange, "LOCAL");
            } else if (window.airportNav) {
              window.airportNav.resizeCanvasToContainer();
              if (target.pos_x && typeof window.airportNav.navigateTo === 'function') {
                window.airportNav.navigateTo(target.pos_x, target.pos_y, target.name || target);
              } else {
                window.airportNav.findPath(target.name || target);
              }
            }
          }, 200);
        }
      }
    } else if (data.error) {
      if (assistantTextElement)
        assistantTextElement.innerText = "Xato: " + data.error;
    } else {
      if (assistantTextElement)
        assistantTextElement.innerText = "Noma'lum xatolik.";
    }
  } catch (err) {
    console.error(err);
    const assistantTextElement = document.getElementById("assistant-text");
    if (assistantTextElement)
      assistantTextElement.innerText = "Server bilan ulanishda xato.";
  }
}

function getComplaintAudioFilename(blob) {
  const type = String((blob && blob.type) || "").toLowerCase();
  if (type.includes("ogg")) return "complaint.ogg";
  if (type.includes("wav")) return "complaint.wav";
  if (type.includes("mpeg") || type.includes("mp3")) return "complaint.mp3";
  return "complaint.webm";
}

export async function submitVoiceComplaint(blob) {
  const name = (document.getElementById("comp-name") || {}).value || "";
  const contact = (document.getElementById("comp-contact") || {}).value || "";
  if (!blob || blob.size <= 0) {
    setComplaintStatus("Audio yozuv bo'sh. Qayta urinib ko'ring.", true);
    return;
  }

  const formData = new FormData();
  formData.append("name", name);
  formData.append("contact", contact);
  formData.append("message", "Voice complaint submitted");
  formData.append("audio", blob, getComplaintAudioFilename(blob));

  setComplaintStatus("Shikoyat yuborilmoqda...");
  try {
    const res = await fetch("api/complaint.php", {
      method: "POST",
      body: formData,
    });
    const data = await res.json();
    if (data && data.success) {
      if (data.telegram_sent) {
        const via = data.telegram_transport
          ? ` (${data.telegram_transport})`
          : "";
        setComplaintStatus(`Shikoyat Telegramga yuborildi${via}.`);
      } else {
        const reason = data.telegram_error
          ? ` Sabab: ${data.telegram_error}`
          : "";
        setComplaintStatus(
          `Shikoyat saqlandi, Telegramga yuborilmadi.${reason}`,
          true,
        );
      }
      setTimeout(() => {
        hideModal("complaint-modal");
        resetComplaintPreview();
      }, 1200);
    } else {
      setComplaintStatus(
        (data && data.error) || "Shikoyatni yuborib bo'lmadi.",
        true,
      );
    }
  } catch (e) {
    console.error("Complaint send error:", e);
    setComplaintStatus("Shikoyat yuborishda xatolik yuz berdi.", true);
  }
}
