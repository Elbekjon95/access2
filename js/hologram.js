import { state } from "./config.js";

export function initHologram() {
  const container = document.getElementById("hologram-container");
  if (!container) return;

  state.scene = new THREE.Scene();
  state.globeCamera = new THREE.PerspectiveCamera(
    75,
    window.innerWidth / window.innerHeight,
    0.1,
    1000,
  );
  state.globeCamera.position.z = 250;

  state.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  state.renderer.setSize(window.innerWidth, window.innerHeight);
  state.renderer.setPixelRatio(window.devicePixelRatio);
  container.appendChild(state.renderer.domElement);

  const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
  state.scene.add(ambientLight);

  const pointLight = new THREE.PointLight(0xd5a107, 1);
  pointLight.position.set(50, 50, 50);
  state.scene.add(pointLight);

  const loader = new THREE.TextureLoader();
  loader.load("img/logo_hologram.png", function (texture) {
    createHologramFromTexture(texture);
  });

  window.addEventListener("resize", onWindowResize, false);
  window.addEventListener("orientationchange", () => {
    setTimeout(onWindowResize, 200);
  }, false);
  updateHologramScale();
  animate();
}

export function updateHologramScale() {
  if (!state.globeCamera || !state.renderer) return;
  const w = window.innerWidth;
  const h = window.innerHeight;
  const aspect = w / h;

  // Ekran nisbati (aspect ratio) ga qarab 3D hologram o'lchami va kamera masofasini avtomatik moslashtirish
  if (aspect < 0.6) {
    // Tor Mobil Telefonlar (Vertikal)
    state.globeCamera.position.z = 250 * (1.15 / aspect);
    state.globeCamera.position.y = 15;
  } else if (aspect < 1.0) {
    // Planshet va Vertikal Kiosklar
    state.globeCamera.position.z = 250 * (1.1 / aspect);
    state.globeCamera.position.y = 10;
  } else if (aspect < 1.4) {
    // Kvadrat / Kichik Noutbuk
    state.globeCamera.position.z = 250 * (1.2 / aspect);
    state.globeCamera.position.y = 0;
  } else {
    // Keng Desktop Ekranlar
    state.globeCamera.position.z = 250;
    state.globeCamera.position.y = 0;
  }

  state.globeCamera.aspect = aspect;
  state.globeCamera.updateProjectionMatrix();
  state.renderer.setSize(w, h);
}

function createHologramFromTexture(texture) {
  const width = 200;
  const height = 200;
  const particles = 150000;
  const geometry = new THREE.BufferGeometry();
  const positions = new Float32Array(particles * 3);
  const colors = new Float32Array(particles * 3);

  const canvas = document.createElement("canvas");
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext("2d");
  ctx.drawImage(texture.image, 0, 0, width, height);
  const imgData = ctx.getImageData(0, 0, width, height).data;

  let validParticles = 0;
  for (let i = 0; i < particles; i++) {
    let x = Math.random() * width;
    let y = Math.random() * height;

    const idx = (Math.floor(y) * width + Math.floor(x)) * 4;
    if (imgData[idx + 3] > 80) {
      positions[validParticles * 3] = (x - width / 2) * 1.5;
      positions[validParticles * 3 + 1] = -(y - height / 2) * 1.5;
      positions[validParticles * 3 + 2] = (Math.random() - 0.5) * 5;

      colors[validParticles * 3] = 0.835;
      colors[validParticles * 3 + 1] = 0.631;
      colors[validParticles * 3 + 2] = 0.027;

      validParticles++;
    }
  }

  const finalPositions = positions.slice(0, validParticles * 3);
  geometry.setAttribute(
    "position",
    new THREE.BufferAttribute(finalPositions, 3),
  );
  geometry.setAttribute(
    "color",
    new THREE.BufferAttribute(colors.slice(0, validParticles * 3), 3),
  );

  geometry.userData = { originalPositions: new Float32Array(finalPositions) };

  const material = new THREE.PointsMaterial({
    size: 0.7,
    vertexColors: true,
    transparent: true,
    opacity: 0.9,
    blending: THREE.AdditiveBlending,
  });

  state.particleSystem = new THREE.Points(geometry, material);
  state.scene.add(state.particleSystem);
  updateHologramScale();
}

function animate() {
  requestAnimationFrame(animate);

  if (window.pauseHologram) return; // CPU/GPU tejash
  if (state.particleSystem) {
    const time = Date.now() * 0.001;
    state.particleSystem.rotation.y = Math.sin(time * 0.3) * (Math.PI / 8);

    const posAttr = state.particleSystem.geometry.attributes.position;
    const original = state.particleSystem.geometry.userData.originalPositions;

    let intensity = 0;
    let isSpeaking = false;

    if (
      state.outputAnalyser &&
      state.currentAudio &&
      !state.currentAudio.paused
    ) {
      state.outputAnalyser.getByteFrequencyData(state.outputDataArray);
      let sum = 0;
      for (let i = 0; i < state.outputDataArray.length; i++)
        sum += state.outputDataArray[i];
      intensity = sum / state.outputDataArray.length;
      isSpeaking = true;
    } else if (window.speechSynthesis && window.speechSynthesis.speaking) {
      intensity = 40 + Math.sin(time * 10) * 20;
      isSpeaking = true;
    }

    const scatterMultiplier = isSpeaking ? 1 : 0.1;

    for (let i = 0; i < posAttr.array.length; i += 3) {
      const idx = i / 3;
      let audioFactor = 0;
      if (
        state.outputAnalyser &&
        state.outputDataArray &&
        state.currentAudio &&
        !state.currentAudio.paused
      ) {
        const freqIdx = idx % state.outputDataArray.length;
        audioFactor = state.outputDataArray[freqIdx] / 255;
      } else if (isSpeaking) {
        audioFactor =
          (0.5 + Math.sin(time * 5 + idx * 0.1) * 0.5) * (intensity / 100);
      }

      const scatter = audioFactor * 40 * scatterMultiplier;
      const wave = Math.sin(time * 2 + idx * 0.1) * 2;

      posAttr.array[i] =
        original[i] + (Math.random() - 0.5) * scatter + wave * 0.2;
      posAttr.array[i + 1] =
        original[i + 1] + (Math.random() - 0.5) * scatter + wave * 0.2;
      posAttr.array[i + 2] =
        original[i + 2] +
        (Math.random() - 0.5) * scatter +
        Math.sin(time + idx) * 2;
    }
    posAttr.needsUpdate = true;
  }

  if (state.renderer && state.scene && state.globeCamera) {
    state.renderer.render(state.scene, state.globeCamera);
  }
}

function onWindowResize() {
  updateHologramScale();
}
