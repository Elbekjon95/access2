/**
 * ACCSESS — 3D Earth & Flight Visualizer
 * Ultra-Realistic Three.js 3D Globe with Local NASA Textures & Neon Flight Arcs
 */

let earthRenderer, earthScene, earthCamera, earth, orbitControls;
window.earthInitialized = false;
let currentRouteObjects = [];
let borderGroup = null;
let fillGroup = null;
let countryFeatures = null;
let countryFillCanvas = null;
let countryFillCtx = null;
let countryFillTexture = null;
let countryFillMesh = null;
let cloudsMesh = null;

const airportCoordCache = new Map();
let airportJsonCache = null;
let flightCurve = null;
let flightPlane = null;
let flightAnimActive = false;
let flightAnimSpeed = 0.05; // route completion fraction per second (sokin va silliq parvoz)
let flightAnimLastTs = 0;
const FLIGHT_PLANE_SCALE = 2.2;
const FLIGHT_MODEL_HEADING_OFFSET = 0;
let flightHeadingFixQuat = null;
let starsParticleSystem = null;

function getApiBase() {
  if (typeof window !== "undefined" && window.API_BASE) {
    return window.API_BASE;
  }
  return "";
}

function joinBasePath(base, path) {
  const b = String(base || "");
  const p = String(path || "").replace(/^\/+/, "");
  if (!b) return p;
  return b.endsWith("/") ? `${b}${p}` : `${b}/${p}`;
}

function getBaseCandidates() {
  const raw = [getApiBase(), "", "../", "/"];
  const seen = new Set();
  const out = [];
  raw.forEach((base) => {
    const key = String(base || "");
    if (seen.has(key)) return;
    seen.add(key);
    out.push(key);
  });
  return out;
}

/**
 * Procedural Starfield (No external image dependency)
 */
function createStarfield() {
  const starCount = 1500;
  const starGeo = new THREE.BufferGeometry();
  const positions = new Float32Array(starCount * 3);
  const colors = new Float32Array(starCount * 3);

  for (let i = 0; i < starCount; i++) {
    const radius = 50 + Math.random() * 80;
    const u = Math.random();
    const v = Math.random();
    const theta = 2 * Math.PI * u;
    const phi = Math.acos(2 * v - 1);

    positions[i * 3] = radius * Math.sin(phi) * Math.cos(theta);
    positions[i * 3 + 1] = radius * Math.cos(phi);
    positions[i * 3 + 2] = radius * Math.sin(phi) * Math.sin(theta);

    const tint = Math.random();
    if (tint > 0.8) {
      colors[i * 3] = 0.4; colors[i * 3 + 1] = 0.8; colors[i * 3 + 2] = 1.0;
    } else if (tint > 0.6) {
      colors[i * 3] = 1.0; colors[i * 3 + 1] = 0.9; colors[i * 3 + 2] = 0.7;
    } else {
      colors[i * 3] = 0.9; colors[i * 3 + 1] = 0.95; colors[i * 3 + 2] = 1.0;
    }
  }

  starGeo.setAttribute("position", new THREE.BufferAttribute(positions, 3));
  starGeo.setAttribute("color", new THREE.BufferAttribute(colors, 3));

  const starMat = new THREE.PointsMaterial({
    size: 0.7,
    vertexColors: true,
    transparent: true,
    opacity: 0.8,
    sizeAttenuation: true,
  });

  return new THREE.Points(starGeo, starMat);
}

/**
 * Initialize 3D Earth Canvas
 */
function initEarth(containerId) {
  if (window.earthInitialized) return;

  const container = document.getElementById(containerId);
  if (!container) {
    console.error("[EARTH] Container not found:", containerId);
    return;
  }

  const w = container.clientWidth || container.offsetWidth || 800;
  const h = container.clientHeight || container.offsetHeight || 600;

  earthRenderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: "high-performance" });
  earthRenderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  earthRenderer.setSize(w, h);

  earthScene = new THREE.Scene();
  const aspect = w / h;
  earthCamera = new THREE.PerspectiveCamera(45, aspect, 0.1, 1000);
  earthCamera.position.set(1.3, 0.5, 1.3);

  orbitControls = new THREE.OrbitControls(earthCamera, earthRenderer.domElement);
  orbitControls.enableDamping = true;
  orbitControls.dampingFactor = 0.06;
  orbitControls.rotateSpeed = 0.7;
  orbitControls.zoomSpeed = 0.9;
  orbitControls.minDistance = 0.65;
  orbitControls.maxDistance = 3.5;
  orbitControls.autoRotate = true;
  orbitControls.autoRotateSpeed = 0.35;

  // Balanced Professional Lighting
  const ambientLight = new THREE.AmbientLight(0xffffff, 0.55);
  earthScene.add(ambientLight);

  const sunLight = new THREE.DirectionalLight(0xffffff, 0.9);
  sunLight.position.set(5, 3, 5);
  earthScene.add(sunLight);

  const fillLight = new THREE.DirectionalLight(0x00c6ff, 0.35);
  fillLight.position.set(-5, -2, -4);
  earthScene.add(fillLight);

  // Starfield
  starsParticleSystem = createStarfield();
  earthScene.add(starsParticleSystem);

  // Earth Globe Group
  earth = new THREE.Group();
  earthScene.add(earth);

  // Surface Mesh with Local NASA Texture
  const earthRadius = 0.5;
  const earthGeo = new THREE.SphereGeometry(earthRadius, 64, 64);

  const textureLoader = new THREE.TextureLoader();
  const apiBase = (window.location.pathname.includes("/admin/")) ? "../" : "";
  const mapPath = `${apiBase}img/earth_map.jpg`;
  const specPath = `${apiBase}img/earth_specular.jpg`;
  const cloudsPath = `${apiBase}img/earth_clouds.png`;

  const earthMat = new THREE.MeshPhongMaterial({
    color: 0xffffff,
    specular: new THREE.Color(0x223344),
    shininess: 15,
  });

  textureLoader.load(mapPath, (texture) => {
    texture.minFilter = THREE.LinearFilter;
    earthMat.map = texture;
    earthMat.needsUpdate = true;
    console.log("[EARTH] Local texture loaded successfully:", mapPath);
  }, undefined, (err) => {
    console.warn("[EARTH] Local texture load failed, trying fallback:", err);
    // Fallback to unpkg CDN if needed
    textureLoader.load("https://unpkg.com/three-globe@2.31.0/example/img/earth-blue-marble.jpg", (fallbackTex) => {
      earthMat.map = fallbackTex;
      earthMat.needsUpdate = true;
    });
  });

  textureLoader.load(specPath, (specTex) => {
    earthMat.specularMap = specTex;
    earthMat.needsUpdate = true;
  });

  const surface = new THREE.Mesh(earthGeo, earthMat);
  surface.name = "surface";
  earth.add(surface);

  // Dynamic Realistic Atmosphere / Cloud Layer
  const cloudsGeo = new THREE.SphereGeometry(earthRadius * 1.006, 64, 64);
  const cloudsMat = new THREE.MeshPhongMaterial({
    transparent: true,
    opacity: 0.38,
    blending: THREE.NormalBlending,
    depthWrite: false,
  });

  textureLoader.load(cloudsPath, (cloudsTex) => {
    cloudsMat.map = cloudsTex;
    cloudsMat.needsUpdate = true;
  });

  cloudsMesh = new THREE.Mesh(cloudsGeo, cloudsMat);
  cloudsMesh.name = "clouds";
  earth.add(cloudsMesh);

  container.innerHTML = "";
  container.appendChild(earthRenderer.domElement);

  loadCountryBorders();
  loadAirplaneModel();

  window.earthInitialized = true;
  animateEarth();

  setTimeout(() => {
    if (typeof window.resizeEarth === "function") window.resizeEarth();
  }, 250);
}

// Globus o'lchamini konteynerga moslashtirish
window.resizeEarth = function () {
  const container = document.getElementById("earth-container") || document.getElementById("earth-canvas");
  if (!container || !earthRenderer || !earthCamera) return;
  const w = container.clientWidth || container.offsetWidth || 800;
  const h = container.clientHeight || container.offsetHeight || 600;
  if (w === 0 || h === 0) return;
  earthRenderer.setSize(w, h);
  earthCamera.aspect = w / h;
  earthCamera.updateProjectionMatrix();
};

function latLongToVector3(latitude, longitude, radius, height) {
  const phi = (latitude * Math.PI) / 180;
  const theta = ((longitude - 180) * Math.PI) / 180;
  const r = radius + (height || 0);

  const x = -r * Math.cos(phi) * Math.cos(theta);
  const y = r * Math.sin(phi);
  const z = r * Math.cos(phi) * Math.sin(theta);

  return new THREE.Vector3(x, y, z);
}

async function loadCountryBorders() {
  if (!earth || borderGroup) return;
  if (typeof topojson === "undefined") {
    console.warn("[EARTH] TopoJSON not available; country borders disabled.");
    return;
  }

  borderGroup = new THREE.Group();
  earth.add(borderGroup);
  fillGroup = new THREE.Group();
  earth.add(fillGroup);
  initCountryFillOverlay();

  try {
    const res = await fetch("https://unpkg.com/world-atlas@2/countries-110m.json");
    if (!res.ok) throw new Error("Failed to fetch world atlas");
    const topo = await res.json();
    const geo = topojson.feature(topo, topo.objects.countries);
    countryFeatures = geo.features || [];

    const positions = [];
    const radius = 0.502;
    const height = 0.001;

    const addRing = (ring) => {
      for (let i = 0; i < ring.length - 1; i++) {
        const p1 = ring[i];
        const p2 = ring[i + 1];
        const v1 = latLongToVector3(p1[1], p1[0], radius, height);
        const v2 = latLongToVector3(p2[1], p2[0], radius, height);
        positions.push(v1.x, v1.y, v1.z, v2.x, v2.y, v2.z);
      }
    };

    geo.features.forEach((feature) => {
      const geom = feature.geometry;
      if (!geom) return;
      if (geom.type === "Polygon") {
        geom.coordinates.forEach(addRing);
      } else if (geom.type === "MultiPolygon") {
        geom.coordinates.forEach((poly) => poly.forEach(addRing));
      }
    });

    const borderGeo = new THREE.BufferGeometry();
    borderGeo.setAttribute("position", new THREE.Float32BufferAttribute(positions, 3));
    const borderMat = new THREE.LineBasicMaterial({
      color: 0x00d2ff,
      transparent: true,
      opacity: 0.32,
      blending: THREE.AdditiveBlending,
    });
    const borders = new THREE.LineSegments(borderGeo, borderMat);
    borderGroup.add(borders);
  } catch (err) {
    console.warn("[EARTH] Country borders load failed:", err);
  }
}

function initCountryFillOverlay() {
  if (!earth || countryFillMesh) return;
  countryFillCanvas = document.createElement("canvas");
  countryFillCanvas.width = 2048;
  countryFillCanvas.height = 1024;
  countryFillCtx = countryFillCanvas.getContext("2d");
  countryFillTexture = new THREE.CanvasTexture(countryFillCanvas);
  countryFillTexture.minFilter = THREE.LinearFilter;
  countryFillTexture.magFilter = THREE.LinearFilter;

  const fillMat = new THREE.MeshBasicMaterial({
    map: countryFillTexture,
    transparent: true,
    opacity: 0.75,
    depthWrite: false,
    depthTest: false,
  });
  const geo = new THREE.SphereGeometry(0.503, 48, 48);
  countryFillMesh = new THREE.Mesh(geo, fillMat);
  countryFillMesh.renderOrder = 5;
  earth.add(countryFillMesh);
}

function clearCountryFills() {
  if (countryFillCtx && countryFillCanvas) {
    countryFillCtx.clearRect(0, 0, countryFillCanvas.width, countryFillCanvas.height);
    if (countryFillTexture) countryFillTexture.needsUpdate = true;
  }
}

function pointInRing(point, ring) {
  let inside = false;
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const xi = ring[i][0];
    const yi = ring[i][1];
    const xj = ring[j][0];
    const yj = ring[j][1];
    const intersect =
      yi > point[1] !== yj > point[1] &&
      point[0] < ((xj - xi) * (point[1] - yi)) / (yj - yi + 0.0) + xi;
    if (intersect) inside = !inside;
  }
  return inside;
}

function pointInPolygon(point, polygon) {
  if (!polygon || polygon.length === 0) return false;
  if (!pointInRing(point, polygon[0])) return false;
  for (let i = 1; i < polygon.length; i++) {
    if (pointInRing(point, polygon[i])) return false;
  }
  return true;
}

function findCountryByPoint(lon, lat) {
  if (!countryFeatures) return null;
  const point = [lon, lat];
  for (let i = 0; i < countryFeatures.length; i++) {
    const geom = countryFeatures[i].geometry;
    if (!geom) continue;
    if (geom.type === "Polygon") {
      if (pointInPolygon(point, geom.coordinates)) return countryFeatures[i];
    } else if (geom.type === "MultiPolygon") {
      for (let j = 0; j < geom.coordinates.length; j++) {
        if (pointInPolygon(point, geom.coordinates[j])) return countryFeatures[i];
      }
    }
  }
  return null;
}

function drawCountryFillCanvas(feature, colorHex, alpha = 0.5) {
  if (!countryFillCtx || !countryFillCanvas || !feature || !feature.geometry) return;
  const ctx = countryFillCtx;
  const w = countryFillCanvas.width;
  const h = countryFillCanvas.height;

  const lonToX = (lon) => ((lon + 180) / 360) * w;
  const latToY = (lat) => ((90 - lat) / 180) * h;

  const drawRing = (ring) => {
    if (!ring || ring.length === 0) return;
    let prevX = null;
    let started = false;
    ctx.beginPath();
    for (let i = 0; i < ring.length; i++) {
      const lon = ring[i][0];
      const lat = ring[i][1];
      const x = lonToX(lon);
      const y = latToY(lat);
      if (prevX !== null && Math.abs(x - prevX) > w * 0.5) {
        ctx.closePath();
        ctx.fill();
        ctx.beginPath();
        started = false;
      }
      if (!started) {
        ctx.moveTo(x, y);
        started = true;
      } else {
        ctx.lineTo(x, y);
      }
      prevX = x;
    }
    ctx.closePath();
    ctx.fill();
  };

  ctx.save();
  ctx.fillStyle = colorHex;
  ctx.globalAlpha = alpha;

  const geom = feature.geometry;
  if (geom.type === "Polygon") {
    geom.coordinates.forEach(drawRing);
  } else if (geom.type === "MultiPolygon") {
    geom.coordinates.forEach((poly) => poly.forEach(drawRing));
  }
  ctx.restore();

  if (countryFillTexture) countryFillTexture.needsUpdate = true;
}

function highlightCountriesByPoints(origin, dest) {
  if (!origin || !dest) return;
  clearCountryFills();

  const originFeature = findCountryByPoint(origin.lon, origin.lat);
  const destFeature = findCountryByPoint(dest.lon, dest.lat);

  if (originFeature) drawCountryFillCanvas(originFeature, "#00ff88", 0.45);
  if (destFeature && destFeature !== originFeature) {
    drawCountryFillCanvas(destFeature, "#ff3366", 0.45);
  }
}

/**
 * Modern Pulsing 3D Pin / Marker for Airports (Ixcham va nafis)
 */
function placeMarker(latitude, longitude, colorHex, labelText, isOrigin = false) {
  const group = new THREE.Group();
  const basePos = latLongToVector3(latitude, longitude, 0.5, 0.002);
  group.position.copy(basePos);

  // Center beacon sphere (Ixcham)
  const sphereGeo = new THREE.SphereGeometry(0.005, 16, 16);
  const sphereMat = new THREE.MeshBasicMaterial({ color: colorHex });
  const beacon = new THREE.Mesh(sphereGeo, sphereMat);
  group.add(beacon);

  // Outer glowing ring (Ixcham)
  const ringGeo = new THREE.RingGeometry(0.007, 0.012, 32);
  const ringMat = new THREE.MeshBasicMaterial({
    color: colorHex,
    side: THREE.DoubleSide,
    transparent: true,
    opacity: 0.85,
    blending: THREE.AdditiveBlending,
  });
  const ring = new THREE.Mesh(ringGeo, ringMat);
  ring.lookAt(basePos.clone().multiplyScalar(2));
  group.add(ring);

  // Vertical light column (Ixcham)
  const columnGeo = new THREE.CylinderGeometry(0.0006, 0.0015, 0.025, 8);
  const columnMat = new THREE.MeshBasicMaterial({
    color: colorHex,
    transparent: true,
    opacity: 0.65,
    blending: THREE.AdditiveBlending,
  });
  const column = new THREE.Mesh(columnGeo, columnMat);
  column.position.copy(basePos.clone().normalize().multiplyScalar(0.012));
  column.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), basePos.clone().normalize());
  group.add(column);

  earth.add(group);
  currentRouteObjects.push(group);
  return group;
}

/**
 * 3D Curved Great-Circle Flight Path
 */
function buildFlightCurve(lat1, lon1, lat2, lon2) {
  const start = latLongToVector3(lat1, lon1, 0.5, 0.006);
  const end = latLongToVector3(lat2, lon2, 0.5, 0.006);

  const mid = new THREE.Vector3().addVectors(start, end).multiplyScalar(0.5);
  const distance = start.distanceTo(end);
  const elevation = Math.min(0.32, Math.max(0.1, distance * 0.38));
  mid.normalize().multiplyScalar(0.5 + elevation);

  return new THREE.QuadraticBezierCurve3(start, mid, end);
}

function drawRoute(lat1, lon1, lat2, lon2) {
  const curve = buildFlightCurve(lat1, lon1, lat2, lon2);

  // 1. Core Glowing Neon Flight Tube (O'ta nozik, nafis va porloq 3D yoy)
  const tubeGeo = new THREE.TubeGeometry(curve, 120, 0.00065, 8, false);
  const tubeMat = new THREE.MeshBasicMaterial({
    color: 0x00f2fe,
    transparent: true,
    opacity: 0.95,
    blending: THREE.AdditiveBlending,
  });
  const tubeMesh = new THREE.Mesh(tubeGeo, tubeMat);
  earth.add(tubeMesh);
  currentRouteObjects.push(tubeMesh);

  // 2. Outer Soft Glowing Aura Tube (Nozik aura)
  const glowGeo = new THREE.TubeGeometry(curve, 120, 0.0016, 8, false);
  const glowMat = new THREE.MeshBasicMaterial({
    color: 0x4facfe,
    transparent: true,
    opacity: 0.35,
    blending: THREE.AdditiveBlending,
  });
  const glowMesh = new THREE.Mesh(glowGeo, glowMat);
  earth.add(glowMesh);
  currentRouteObjects.push(glowMesh);

  return { line: tubeMesh, curve };
}

/**
 * Procedural Stylized Modern Jet Airplane Model
 */
function createFlightPlane() {
  const group = new THREE.Group();

  const bodyMat = new THREE.MeshBasicMaterial({
    color: 0xffffff,
  });
  const wingMat = new THREE.MeshBasicMaterial({
    color: 0x00d2ff,
  });
  const glowMat = new THREE.MeshBasicMaterial({
    color: 0x00ffff,
    transparent: true,
    opacity: 0.8,
  });

  // Nose & Fuselage
  const nose = new THREE.Mesh(new THREE.ConeGeometry(0.0045, 0.016, 12), bodyMat);
  nose.position.set(0, 0.014, 0);
  group.add(nose);

  const body = new THREE.Mesh(new THREE.CylinderGeometry(0.0038, 0.0042, 0.03, 12), bodyMat);
  group.add(body);

  // Main Swept Jet Wings
  const mainWing = new THREE.Mesh(new THREE.BoxGeometry(0.036, 0.0016, 0.012), wingMat);
  mainWing.position.set(0, 0.002, 0);
  group.add(mainWing);

  // Jet Engines
  const engL = new THREE.Mesh(new THREE.CylinderGeometry(0.0018, 0.002, 0.009, 8), glowMat);
  engL.position.set(-0.01, 0.001, -0.002);
  group.add(engL);

  const engR = new THREE.Mesh(new THREE.CylinderGeometry(0.0018, 0.002, 0.009, 8), glowMat);
  engR.position.set(0.01, 0.001, -0.002);
  group.add(engR);

  // Tail Wings & Vertical Stabilizer
  const tailWing = new THREE.Mesh(new THREE.BoxGeometry(0.014, 0.0012, 0.006), wingMat);
  tailWing.position.set(0, -0.015, 0);
  group.add(tailWing);

  const tailFin = new THREE.Mesh(new THREE.BoxGeometry(0.0018, 0.009, 0.006), wingMat);
  tailFin.position.set(0, -0.014, 0.0045);
  group.add(tailFin);

  group.scale.setScalar(2.6);
  return group;
}

let airplaneGltfTemplate = null;
let airplaneLoadingPromise = null;

/**
 * Loyihadagi original 3D samolyot modelini yuklash (models/airplane/scene.gltf)
 */
function loadAirplaneModel() {
  if (airplaneGltfTemplate) return Promise.resolve(airplaneGltfTemplate);
  if (airplaneLoadingPromise) return airplaneLoadingPromise;

  airplaneLoadingPromise = new Promise((resolve) => {
    if (typeof THREE.GLTFLoader === "undefined") {
      console.warn("[EARTH] THREE.GLTFLoader mavjud emas, protsedural model ishlatiladi.");
      resolve(null);
      return;
    }

    const loader = new THREE.GLTFLoader();
    const modelPaths = ["models/airplane/scene.gltf", "../models/airplane/scene.gltf", "/models/airplane/scene.gltf"];

    const tryLoad = (idx) => {
      if (idx >= modelPaths.length) {
        console.warn("[EARTH] Airplane GLTF model yuklanmadi, protsedural modelga o'tiladi.");
        resolve(null);
        return;
      }
      loader.load(
        modelPaths[idx],
        (gltf) => {
          console.log("[EARTH] Original Airplane 3D GLTF modeli muvaffaqiyatli yuklandi:", modelPaths[idx]);
          const rawModel = gltf.scene;

          // Model o'lchamlarini tekshirish va masshtablash
          const box = new THREE.Box3().setFromObject(rawModel);
          const size = new THREE.Vector3();
          box.getSize(size);
          const maxDim = Math.max(size.x, size.y, size.z) || 1;
          const scaleFactor = 0.048 / maxDim; // Globus yuzasiga mos o'lcham
          rawModel.scale.setScalar(scaleFactor);

          // Model markazini (pivot) to'g'rilash
          const center = new THREE.Vector3();
          box.getCenter(center);
          rawModel.position.sub(center.clone().multiplyScalar(scaleFactor));

          // Samolyot materiallarini tekshirish va metallik/yorqinlik berish
          rawModel.traverse((child) => {
            if (child.isMesh) {
              if (child.material) {
                child.material.metalness = 0.25;
                child.material.roughness = 0.35;
                if (child.material.emissive) {
                  child.material.emissive = new THREE.Color(0x112233);
                }
              }
            }
          });

          // Orientatsiyani to'g'irlash: burni boradigan manzilga (qizil nuqtaga) to'ppa-to'g'ri qaratiladi (180 gradus teskari qilindi)
          const wrapper = new THREE.Group();
          rawModel.rotation.set(0, Math.PI / 2, 0);
          wrapper.add(rawModel);

          airplaneGltfTemplate = wrapper;
          resolve(airplaneGltfTemplate);
        },
        undefined,
        () => {
          tryLoad(idx + 1);
        }
      );
    };

    tryLoad(0);
  });

  return airplaneLoadingPromise;
}

async function startFlightAnimation(curve) {
  if (!curve || !earth) return;
  flightCurve = curve;
  flightAnimProgress = 0;
  flightAnimActive = true;
  flightAnimLastTs = 0;

  if (flightPlane) {
    if (flightPlane.parent) flightPlane.parent.remove(flightPlane);
    flightPlane = null;
  }

  // Original 3D GLTF modelini yuklaymiz
  const gltfModel = await loadAirplaneModel();
  if (gltfModel) {
    flightPlane = gltfModel.clone(true);
  } else {
    flightPlane = createFlightPlane();
  }

  const p0 = curve.getPoint(0);
  flightPlane.position.copy(p0);
  earth.add(flightPlane);
  currentRouteObjects.push(flightPlane);
  console.log("[EARTH] Flight animation started successfully with 3D airplane model");
}

function updateFlightAnimation(deltaSec) {
  if (!flightAnimActive || !flightCurve || !flightPlane) return;

  flightAnimProgress = (flightAnimProgress + deltaSec * flightAnimSpeed) % 1;
  const t = flightAnimProgress;

  const p = flightCurve.getPoint(t);
  const lift = p.clone().normalize().multiplyScalar(0.005);
  flightPlane.position.copy(p).add(lift);

  const tangent = flightCurve.getTangent(t);
  if (tangent.lengthSq() > 1e-9) {
    const forward = tangent.normalize();
    const normal = flightPlane.position.clone().normalize();
    const right = new THREE.Vector3().crossVectors(normal, forward).normalize();
    if (right.lengthSq() > 1e-9) {
      const up = new THREE.Vector3().crossVectors(forward, right).normalize();
      const basis = new THREE.Matrix4().makeBasis(right, up, forward);
      const q = new THREE.Quaternion().setFromRotationMatrix(basis);
      if (!flightHeadingFixQuat) {
        flightHeadingFixQuat = new THREE.Quaternion().setFromAxisAngle(
          new THREE.Vector3(0, 1, 0),
          FLIGHT_MODEL_HEADING_OFFSET
        );
      }
      q.multiply(flightHeadingFixQuat);
      flightPlane.quaternion.copy(q);
    }
  }
}

/**
 * Show Flight Route between Origin & Destination
 */
async function showFlightRoute(originCode, destCode) {
  console.log("[EARTH] showFlightRoute:", originCode, "->", destCode);
  try {
    let originKey = String(originCode || "TAS").toUpperCase();
    let destKey = String(destCode || "").toUpperCase();

    // Shahar nomlari xaritasi
    const cityLookup = {
      MOSCOW: "MOW", MOSKVA: "MOW", ISTANBUL: "IST", DUBAI: "DXB",
      DELHI: "DEL", BEIJING: "PEK", SEOUL: "ICN", LONDON: "LHR",
      PARIS: "CDG", ROME: "FCO", FRANKFURT: "FRA", NEWYORK: "JFK",
      ALMATY: "ALA", ASTANA: "NQZ", BISHKEK: "FRU", DUSHANBE: "DYU",
      BAKU: "GYD", TASHKENT: "TAS", SAMARKAND: "SKD", BUKHARA: "BHK",
      URGENCH: "UGC", FERGANA: "FEG", NUKUS: "NCU", NAMANGAN: "NMA",
      TERMEZ: "TMJ", JEDDAH: "JED", MEDINA: "MED", DOHA: "DOH"
    };

    if (cityLookup[destKey]) destKey = cityLookup[destKey];
    if (cityLookup[originKey]) originKey = cityLookup[originKey];

    const airports = await getAirportCoords([originKey, destKey]);

    let origin = airports[originKey] || { lat: 41.2579, lon: 69.2812, name: "Toshkent (TAS)" };
    let dest = airports[destKey];

    // Nom bo'yicha qidiruv (Fallback)
    if (!dest && airportJsonCache) {
      const dLower = String(destCode).toLowerCase();
      for (const [code, info] of Object.entries(airportJsonCache)) {
        if (info.name && info.name.toLowerCase().includes(dLower)) {
          dest = info;
          destKey = code;
          break;
        }
      }
    }

    if (!dest) {
      // Default Moscow fallback if MOW / SVO
      if (destKey.includes("MOS") || destKey.includes("MOW") || destKey.includes("SVO") || destKey.includes("VKO") || destKey.includes("DME")) {
        dest = { lat: 55.7558, lon: 37.6173, name: "Moscow" };
      } else {
        console.error("[EARTH] Destination coordinates not found for:", destCode);
        return;
      }
    }

    clearRoute();

    // Origin: Tashkent (Emerald Green Pulse)
    placeMarker(origin.lat, origin.lon, 0x00ff88, origin.name || "TAS", true);
    // Destination: (Neon Pink/Red Pulse)
    placeMarker(dest.lat, dest.lon, 0xff3366, dest.name || destKey, false);

    // Glowing 3D Curved Route
    const route = drawRoute(origin.lat, origin.lon, dest.lat, dest.lon);
    if (route && route.curve) {
      await startFlightAnimation(route.curve);
    }
    highlightCountriesByPoints(origin, dest);

    // Smooth Camera Focus on Route Midpoint (Faqat ikki nuqta oralig'iga yaqinlashadi)
    const centerLat = (origin.lat + dest.lat) / 2;
    const centerLon = (origin.lon + dest.lon) / 2;
    const distance = latLongToVector3(origin.lat, origin.lon, 0.5).distanceTo(
      latLongToVector3(dest.lat, dest.lon, 0.5)
    );
    // Masofani aynan ikki nuqta ekranda to'liq sig'adigan qilib hisoblaymiz
    const cameraDist = Math.max(0.85, Math.min(1.65, 0.72 + distance * 1.05));
    const targetCamPos = latLongToVector3(centerLat, centerLon, 0.5, cameraDist);

    // Animate camera to target
    animateCameraTo(targetCamPos);
  } catch (error) {
    console.error("[EARTH] Error loading flight route:", error);
  }
}

function animateCameraTo(targetPos) {
  if (!earthCamera || !orbitControls) return;
  // Avto-aylanishni to'liq to'xtatamiz, shunda kamera aynan ikki nuqta oralig'ida qoladi
  orbitControls.autoRotate = false;

  const startPos = earthCamera.position.clone();
  const startTime = performance.now();
  const duration = 1200; // ms

  function step(now) {
    const elapsed = now - startTime;
    const t = Math.min(1, elapsed / duration);
    const ease = 0.5 - Math.cos(t * Math.PI) / 2;

    earthCamera.position.lerpVectors(startPos, targetPos, ease);
    earthCamera.lookAt(0, 0, 0);

    if (t < 1) {
      requestAnimationFrame(step);
    } else {
      // Reys ko'rsatilayotganda avto-aylanmasdan, aynan shu ikki nuqta oralig'ida turadi
      if (orbitControls) orbitControls.autoRotate = false;
    }
  }
  requestAnimationFrame(step);
}

async function getAirportCoords(codes) {
  const uniqueCodes = Array.from(
    new Set((codes || []).map((c) => String(c || "").toUpperCase()))
  ).filter((c) => c.length === 3);

  const missing = uniqueCodes.filter((c) => !airportCoordCache.has(c));
  if (missing.length > 0) {
    let loadedFromApi = false;
    const bases = getBaseCandidates();
    for (const base of bases) {
      try {
        const res = await fetch(joinBasePath(base, "api/airport_coords.php"), {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ codes: missing }),
        });
        if (!res.ok) continue;

        const raw = await res.text();
        if (!raw) continue;

        const data = JSON.parse(raw);
        if (!data || typeof data !== "object" || data.error) continue;

        Object.keys(data).forEach((code) => {
          const key = String(code || "").toUpperCase();
          const row = data[code];
          if (
            key.length === 3 &&
            row &&
            Number.isFinite(Number(row.lat)) &&
            Number.isFinite(Number(row.lon))
          ) {
            airportCoordCache.set(key, {
              lat: Number(row.lat),
              lon: Number(row.lon),
              name: row.name || key,
            });
          }
        });
        loadedFromApi = true;
        break;
      } catch (_) {}
    }
  }

  const stillMissing = uniqueCodes.filter((c) => !airportCoordCache.has(c));
  if (stillMissing.length > 0) {
    await loadAirportJsonCache();
    stillMissing.forEach((code) => {
      if (airportJsonCache && airportJsonCache[code]) {
        airportCoordCache.set(code, airportJsonCache[code]);
      }
    });
  }

  const result = {};
  uniqueCodes.forEach((code) => {
    if (airportCoordCache.has(code)) {
      result[code] = airportCoordCache.get(code);
    }
  });
  return result;
}

async function loadAirportJsonCache() {
  if (airportJsonCache) return;
  const bases = getBaseCandidates();
  for (const base of bases) {
    try {
      const res = await fetch(joinBasePath(base, "data/airport_coordinates.json"));
      if (!res.ok) continue;
      const parsed = await res.json();
      if (!parsed || typeof parsed !== "object") continue;
      airportJsonCache = parsed;
      return;
    } catch (_) {}
  }
  airportJsonCache = null;
}

function clearRoute() {
  flightAnimActive = false;
  flightAnimProgress = 0;
  flightAnimLastTs = 0;
  flightCurve = null;
  currentRouteObjects.forEach((obj) => {
    if (obj.parent) obj.parent.remove(obj);
  });
  currentRouteObjects = [];
  flightPlane = null;
  clearCountryFills();
}

function animateEarth() {
  if (!window.earthInitialized) return;

  requestAnimationFrame(animateEarth);
  if (window.pauseEarth) return;

  const now = performance.now();
  if (!flightAnimLastTs) flightAnimLastTs = now;
  const dt = Math.min(0.05, (now - flightAnimLastTs) / 1000);
  flightAnimLastTs = now;

  updateFlightAnimation(dt);

  // Subtle clouds rotation
  if (cloudsMesh) {
    cloudsMesh.rotation.y += dt * 0.02;
  }

  if (orbitControls) orbitControls.update();
  if (earthRenderer && earthScene && earthCamera) {
    earthRenderer.render(earthScene, earthCamera);
  }
}

// Global scope bindings
window.initEarth = initEarth;
window.showFlightRoute = showFlightRoute;
window.clearRoute = clearRoute;
