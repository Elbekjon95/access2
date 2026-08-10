import { state } from "./config.js";
window.state = state;
import { initHologram } from "./hologram.js";
import { startCamera } from "./camera.js";
import { initLanguageSelector, loadVoices } from "./language.js";
// map.js o'rniga navigation.js dagi AirportNavigation ishlatiladi
// window.airportNav = new AirportNavigation("map-canvas"); // Bu index.php da navigation.js yuklanganidan keyin amalga oshiriladi

import {
  showModal,
  hideModal,
  loadFlightsToTable,
  initFlightsTabs,
  setComplaintStatus,
  resetComplaintPreview,
} from "./ui.js";
import {
  startRecording,
  stopRecording,
  stopAssistantVoice,
  toggleAssistantVoice,
  startComplaintRecording,
  stopComplaintRecording,
} from "./voice.js?v=2";
import { initWeather } from "./weather.js";

const mapViewState = {
  scale: 1,
  panX: 0,
  panY: 0,
};

function applyMapTransform() {
  // CSS transform o'rniga navigation.js ning ichki renderidan foydalanamiz
  // Bu yerda faqat kerak bo'lsa canvas elementining o'zini markazlashtirish mantiqi qolishi mumkin
}

function initMapPanZoom() {
    // CSS-ga asoslangan Pan/Zoom hozircha o'chirildi, chunki u navigation.js bilan ziddiyatga kelmoqda.
    // Navigatsiya paytida navigation.js avtomatik "Follow" (kamera kuzatish) funksiyasini bajaradi.
}

function resetMapView() {
  if (window.airportNav) {
    if (typeof window.airportNav.resizeCanvasToContainer === "function") {
      window.airportNav.resizeCanvasToContainer();
    }
    window.airportNav.resetZoom();
    window.airportNav.path = [];
    window.airportNav.isAnimatingPath = false;
    window.airportNav.needsRender = true;
    
    // Manual state ham reset
    mapViewState.scale = 1;
    mapViewState.panX = 0;
    mapViewState.panY = 0;
    applyMapTransform();
  }
}
window.resetMapView = resetMapView;

// Inactivity Reset (5 minut) va Cache tozalash
let inactivityTimer;
const INACTIVITY_TIMEOUT = 5 * 60 * 1000; // 5 daqiqa

function resetInactivityTimer() {
    if (inactivityTimer) clearTimeout(inactivityTimer);
    inactivityTimer = setTimeout(() => {
        console.log("5 daqiqa faoliyat yo'q. Cache tozalanmoqda va sahifa yangilanmoqda...");
        clearBrowserCache();
    }, INACTIVITY_TIMEOUT);
}

function clearBrowserCache() {
    // Barcha cache'ni tozalash (til sozlamalarini ham)
    localStorage.clear();
    sessionStorage.clear();
    
    // Hard reload (cache'siz yangilash)
    window.location.reload(true);
}

// Barcha interaksiyalarni kuzatish
['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(name => {
    document.addEventListener(name, resetInactivityTimer, true);
});

// Dastlabki ishga tushirish
resetInactivityTimer();

setInterval(() => {
  const now = new Date();
  const timeDisplay = document.getElementById("time-display");
  if (timeDisplay)
    timeDisplay.innerText = now.toLocaleTimeString("uz-UZ", { hour12: false });
}, 1000);

document.addEventListener("DOMContentLoaded", () => {
  // Sahifa yuklanganda cache tozalash (manual refresh uchun)
  const isManualRefresh = performance.navigation.type === 1;
  if (isManualRefresh) {
    console.log("Manual refresh aniqlandi. Cache va til sozlamalari tozalanmoqda...");
    localStorage.clear();
    sessionStorage.clear();
  }
  
  // Mikrofon va kamera ruxsatini so'rash
  requestMediaPermissions();
  
  initFlightsTabs();
  initHologram(); // LOGO SHU YERDA ISHGA TUSHADI
  startCamera(); // Kamera faqat rasm olish uchun
  initLanguageSelector();
  loadVoices();
  initMapPanZoom();
  initWeather();

  // AirportNavigation initialize
  if (typeof AirportNavigation !== "undefined") {
    window.airportNav = new AirportNavigation("map-canvas");
    // Xarita rasmini yuklash (api/map_settings.php orqali)
    fetch("api/map_settings.php")
      .then((res) => res.json())
      .then((data) => {
        let finalPath = (data && data.path) ? data.path : "img/airport_map.jpg";
        // Agar yo'l ../ bilan boshlansa, uni olib tashlaymiz
        finalPath = finalPath.replace(/^\.\.\//, '');
        console.log('[MAIN] Loading map from:', finalPath);
        return window.airportNav.loadMap(finalPath);
      })
      .catch((err) => {
        console.error('[MAIN] Map settings fetch error:', err);
        return window.airportNav.loadMap("img/airport_map.jpg");
      });

    // Nuqtalarni yuklash
    fetch("api/scanner.php")
      .then((res) => res.json())
      .then((nodes) => {
        if (!Array.isArray(nodes)) {
            console.error("[MAIN] Scanner DB Error or invalid data:", nodes);
            nodes = []; // Fallback to empty array to prevent crash
        }
        console.log('[MAIN] Loaded', nodes.length, 'map points');
        if (window.airportNav && typeof window.airportNav.setNodes === "function") {
          window.airportNav.setNodes(nodes);
          fillSidePanels(nodes); // Yon panellarni to'ldirish
        }
      })
      .catch((err) => console.error("[MAIN] Scanner error:", err));
    // Xarita boshqaruv tugmalari (Floating controls)
    const btnZoomIn = document.getElementById("nav-btn-zoom-in");
    const btnZoomOut = document.getElementById("nav-btn-zoom-out");
    const btnCenter = document.getElementById("nav-btn-center");

    if (btnZoomIn) {
      btnZoomIn.onclick = (e) => {
        e.stopPropagation();
        if (window.airportNav) {
          window.airportNav.scale = Math.min(window.airportNav.maxScale, window.airportNav.scale * 1.3);
          window.airportNav.userHasPanned = true;
          window.airportNav.needsRender = true;
        }
      };
    }
    if (btnZoomOut) {
      btnZoomOut.onclick = (e) => {
        e.stopPropagation();
        if (window.airportNav) {
          window.airportNav.scale = Math.max(window.airportNav.minScale, window.airportNav.scale * 0.77);
          window.airportNav.userHasPanned = true;
          window.airportNav.needsRender = true;
        }
      };
    }
    if (btnCenter) {
      btnCenter.onclick = (e) => {
        e.stopPropagation();
        if (window.airportNav) {
          window.airportNav.centerMap();
        }
      };
    }
  }

  const mapModal = document.getElementById("map-modal");
  const flightsModal = document.getElementById("flights-modal");
  const btnMap = document.getElementById("btn-map");
  const btnFlights = document.getElementById("btn-flights");
  const btnVoice = document.getElementById("btn-voice");

  if (btnMap)
    btnMap.onclick = () => {
      showModal(mapModal);
      if (window.airportNav) {
        window.airportNav.resizeCanvasToContainer();
        window.airportNav.centerMap();
      }
    };
  if (btnFlights)
    btnFlights.onclick = () => {
      loadFlightsToTable();
      showModal(flightsModal);
    };

  if (btnVoice) {
    btnVoice.onclick = () =>
      state.isRecording ? stopRecording() : startRecording();
  }

  const btnStopVoice = document.getElementById("btn-stop-voice");
  if (btnStopVoice) btnStopVoice.onclick = stopAssistantVoice;

  const btnPauseVoice = document.getElementById("btn-pause-voice");
  if (btnPauseVoice) btnPauseVoice.onclick = toggleAssistantVoice;

  // Telefon tugmasi (kelajakda operatorga qo'ng'iroq)
  const btnCall = document.getElementById("btn-call");
  if (btnCall) {
    btnCall.onclick = () => {
      // Kelajakda tel:// yoki WebRTC orqali qo'ng'iroq qilish mumkin
      alert("Operatorga qo'ng'iroq: +998 78 140-28-77\n(Kanselyariya)");
    };
  }

  const btnComplaint = document.getElementById("btn-complaint");
  if (btnComplaint)
    btnComplaint.onclick = () => {
      showModal("complaint-modal");
      resetComplaintPreview();
      setComplaintStatus("Yozuv tugagach shikoyat avtomatik yuboriladi.");
    };

  const btnComplaintRecord = document.getElementById("btn-complaint-record");
  if (btnComplaintRecord) {
    btnComplaintRecord.onclick = () => {
      if (state.isComplaintRecording) stopComplaintRecording();
      else startComplaintRecording();
    };
  }

  document.querySelectorAll(".close-modal").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      const modal = e.target.closest(".modal");
      if (modal) hideModal(modal);
      
      if (state.isComplaintRecording) {
        stopComplaintRecording();
      }
      if (window.airportNav && typeof window.airportNav.resetZoom === "function") {
        window.airportNav.resetZoom();
      }
    });
  });

  const btnCloseQR = document.getElementById("close-qr");
  if (btnCloseQR) {
    btnCloseQR.onclick = () => {
      if (typeof window.hideQR === "function") window.hideQR();
    };
  }
});

/**
 * Xarita yon panellarini nuqtalar bilan to'ldirish
 */
function fillSidePanels(processedNodes) {
    const listServices = document.getElementById("list-services");
    const listGates = document.getElementById("list-gates");
    if (!listServices || !listGates) return;

    listServices.innerHTML = "";
    listGates.innerHTML = "";
    
    // Alifbo bo'yicha tartiblaymiz
    const sortedNodes = [...processedNodes].sort((a, b) => (a.name || "").localeCompare(b.name || ""));

    sortedNodes.forEach(node => {
        if (node.type === "kiosk_start") return; 

        const item = document.createElement("div");
        item.className = "panel-item";
        
        let icon = "fa-map-marker-alt";
        const nLower = (node.name || "").toLowerCase();
        const tLower = (node.type || "").toLowerCase();

        if (tLower === "toilet" || nLower.includes("hojatxona") || nLower.includes("toilet")) icon = "fa-restroom";
        else if (tLower === "cafe" || tLower === "restaurant" || nLower.includes("kafe") || nLower.includes("restoran")) icon = "fa-utensils";
        else if (tLower === "counter" || nLower.includes("stoyka") || /^\d+-\d+/.test(nLower)) icon = "fa-id-card";
        else if (tLower === "gate" || nLower.includes("darvoza") || nLower.includes("gate")) icon = "fa-plane-departure";
        else if (tLower === "mosque" || nLower.includes("masjid") || nLower.includes("namoz")) icon = "fa-mosque";
        else if (tLower === "shop" || nLower.includes("duty") || nLower.includes("shop")) icon = "fa-shopping-cart";
        else if (tLower === "cip" || tLower === "vip" || nLower.includes("cip") || nLower.includes("vip") || nLower.includes("anor") || nLower.includes("anjir")) icon = "fa-couch";
        else if (tLower === "medical" || nLower.includes("tibbiyot") || nLower.includes("medpunkt")) icon = "fa-briefcase-medical";
        else if (tLower === "reception" || tLower === "info" || nLower.includes("info") || nLower.includes("axborot")) icon = "fa-info-circle";
        else if (tLower === "baggage" || nLower.includes("bagaj")) icon = "fa-suitcase";

        item.innerHTML = `<i class="fas ${icon}"></i><span>${node.name}</span>`;
        
        item.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            const px = Number(node.pos_x);
            const py = Number(node.pos_y);
            
            console.log("[UI] Clicked:", node.name, "| Coords:", px, py);
            
            item.style.background = "rgba(0, 198, 255, 0.3)";
            setTimeout(() => { item.style.background = ""; }, 200);

            if (window.airportNav && typeof window.airportNav.navigateTo === 'function') {
                window.airportNav.navigateTo(px, py, node.name);
            } else if (window.airportNav) {
                window.airportNav.findPath(node.name);
            }
        };

        const isGate = (tLower === "gate" || nLower.includes("gate") || nLower.includes("darvoza")) && !nLower.includes("stoyka") && !/^\d+-\d+/.test(nLower);

        if (isGate) {
            listGates.appendChild(item);
        } else {
            listServices.appendChild(item);
        }
    });
    console.log("[UI] Side panels filled with", sortedNodes.length, "nodes");
}



// Mikrofon va kamera ruxsatini so'rash
async function requestMediaPermissions() {
    // MUHIM: getUserMedia faqat HTTPS yoki localhost da ishlaydi
    const isSecure = location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
    if (!isSecure) {
        console.error('[PERMISSIONS] ❌ Sayt HTTP orqali ochilgan! Mikrofon va kamera faqat HTTPS da ishlaydi. Nginx SSL konfiguratsiyasini tekshiring.');
        return;
    }

    // navigator.mediaDevices mavjudligini tekshirish
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        console.error('[PERMISSIONS] ❌ navigator.mediaDevices mavjud emas. Brauzer yoki HTTPS muammosi.');
        return;
    }

    try {
        console.log('[PERMISSIONS] Mikrofon va kamera ruxsatini so\'ramoqda...');

        // Mikrofon ruxsati
        const audioStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        console.log('[PERMISSIONS] ✅ Mikrofon ruxsati berildi');
        audioStream.getTracks().forEach(track => track.stop());

        // Kamera ruxsati
        const videoStream = await navigator.mediaDevices.getUserMedia({ video: true });
        console.log('[PERMISSIONS] ✅ Kamera ruxsati berildi');
        videoStream.getTracks().forEach(track => track.stop());

    } catch (err) {
        console.warn('[PERMISSIONS] ⚠️ Media ruxsatlari rad etildi:', err);
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            console.warn('[PERMISSIONS] Foydalanuvchi ruxsat bermadi');
        } else if (err.name === 'NotFoundError') {
            console.warn('[PERMISSIONS] Mikrofon yoki kamera qurilmasi topilmadi');
        }
    }
}
