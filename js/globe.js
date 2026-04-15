import { state } from "./config.js";
import { normalizeIataCode, enhanceTextWithLinks } from "./utils.js";
import { renderEarthRouteActions } from "./api.js";
import { typewriterEffect, showModal, hideModal } from "./ui.js";

export function showEarthRoute(originCode, destCode, routeData = null) {
  const modal = document.getElementById("earth-modal");
  if (!modal) {
    console.error("рџЊЌ ERROR: earth-modal element NOT FOUND!");
    return;
  }

  const from = normalizeIataCode(originCode);
  const to = normalizeIataCode(destCode);
  if (!from || !to) {
    console.error(
      "Earth route skipped: invalid airport codes",
      originCode,
      destCode,
    );
    return;
  }

  showModal(modal);
  const openAndRenderEarth = () => {
    // Assuming initEarth, resizeEarth, showFlightRoute are globally loaded from 3D Earth script
    if (!window.earthInitialized && typeof window.initEarth === "function") {
      window.initEarth("earth-container");
    } else if (typeof window.resizeEarth === "function") {
      window.resizeEarth();
    }

    if (typeof window.showFlightRoute === "function") {
      window.showFlightRoute(from, to);
    }
  };

  requestAnimationFrame(() => {
    requestAnimationFrame(openAndRenderEarth);
  });

  const terminalText = document.getElementById("terminal-text");
  const assistantText = document.getElementById("assistant-text");

  if (terminalText && assistantText) {
    const flightInfo =
      (routeData && routeData.reply) ||
      (state.lastAssistantResponseData &&
        state.lastAssistantResponseData.reply) ||
      assistantText.innerText;
    const enhancedInfo = enhanceTextWithLinks(flightInfo);
    typewriterEffect(terminalText, enhancedInfo);
    renderEarthRouteActions(to, flightInfo);
  }

  const closeBtn = modal.querySelector(".close-modal");
  if (closeBtn) {
    closeBtn.onclick = () => {
      hideModal(modal);
      if (typeof window.clearRoute === "function") window.clearRoute();
    };
  }

  modal.onclick = (e) => {
    if (e.target === modal) {
      hideModal(modal);
      if (typeof window.clearRoute === "function") window.clearRoute();
    }
  };
}
