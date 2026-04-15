
let earthRenderer, earthScene, earthCamera, earth, orbitControls;
window.earthInitialized = false;
let currentRouteObjects = [];
let borderGroup = null;
let holoPointCloud = null;
let fillGroup = null;
let countryFeatures = null;
let originFillMaterial = null;
let destFillMaterial = null;
let countryFillCanvas = null;
let countryFillCtx = null;
let countryFillTexture = null;
let countryFillMesh = null;
const airportCoordCache = new Map();
let airportJsonCache = null;
let flightCurve = null;
let flightPlane = null;
let flightAnimActive = false;
let flightAnimProgress = 0;
let flightAnimSpeed = 0.09; // route completion fraction per second
let flightAnimLastTs = 0;
const FLIGHT_PLANE_SCALE = 1.9;
const FLIGHT_MODEL_TARGET_SIZE = 0.062;
const FLIGHT_MODEL_HEADING_OFFSET = Math.PI / 2;
let flightModelTemplate = null;
let flightModelLoadPromise = null;
let flightHeadingFixQuat = null;
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

function initEarth(containerId) {
  if (window.earthInitialized) return;

  const container = document.getElementById(containerId);
  if (!container) {
    console.error("Earth container not found:", containerId);
    return;
  }

  // Modal animatsiyasi hali tugamagan bo'lishi mumkin — fallback o'lcham
  const w = container.clientWidth || container.offsetWidth || 800;
  const h = container.clientHeight || container.offsetHeight || 600;

  earthRenderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  earthScene = new THREE.Scene();
  let aspect = w / h;
  earthCamera = new THREE.PerspectiveCamera(45, aspect, 0.1, 1500);
  orbitControls = new THREE.OrbitControls(
    earthCamera,
    earthRenderer.domElement,
  );

  let ambientLight = new THREE.AmbientLight(0xffffff, 0.7); // Bright ambient light
  earthScene.add(ambientLight);

  let frontLight = new THREE.DirectionalLight(0xffffff, 0.5);
  frontLight.position.set(5, 3, 5);
  earthScene.add(frontLight);

  let backLight = new THREE.DirectionalLight(0xffffff, 0.3);
  backLight.position.set(-5, -3, -5);
  earthScene.add(backLight);

  earth = createPlanet({
    surface: {
      size: 0.5,
      material: {
        bumpScale: 0.05,
        specular: new THREE.Color("grey"),
        shininess: 10,
      },
      textures: {
        map: "https://s3-us-west-2.amazonaws.com/s.cdpn.io/141228/earthmap1k.jpg",
        bumpMap:
          "https://s3-us-west-2.amazonaws.com/s.cdpn.io/141228/earthbump1k.jpg",
        specularMap:
          "https://s3-us-west-2.amazonaws.com/s.cdpn.io/141228/earthspec1k.jpg",
      },
    },
    atmosphere: {
      size: 0.003,
      material: {
        opacity: 0.8,
      },
      textures: {
        map: "https://s3-us-west-2.amazonaws.com/s.cdpn.io/141228/earthcloudmap.jpg",
        alphaMap:
          "https://s3-us-west-2.amazonaws.com/s.cdpn.io/141228/earthcloudmaptrans.jpg",
      },
      glow: {
        size: 0.02,
        intensity: 0.7,
        fade: 7,
        color: 0x93cfef,
      },
    },
  });

  let galaxyGeometry = new THREE.SphereGeometry(100, 32, 32);
  let galaxyMaterial = new THREE.MeshBasicMaterial({ side: THREE.BackSide });
  let galaxy = new THREE.Mesh(galaxyGeometry, galaxyMaterial);

  let textureLoader = new THREE.TextureLoader();
  textureLoader.crossOrigin = true;
  textureLoader.load(
    "https://s3-us-west-2.amazonaws.com/s.cdpn.io/141228/starfield.png",
    function (texture) {
      galaxyMaterial.map = texture;
      earthScene.add(galaxy);
    },
  );

  earthRenderer.setSize(w, h);
  container.appendChild(earthRenderer.domElement);

  earthCamera.position.set(1.5, 0.5, 1.5);
  earthCamera.lookAt(earthScene.position);
  orbitControls.enableDamping = true;
  orbitControls.dampingFactor = 0.05;

  earthScene.add(earthCamera);
  earthScene.add(earth);

  earth.receiveShadow = true;
  earth.castShadow = true;
  earth.getObjectByName("surface").geometry.center();

  softenSolidEarth();
  addHoloPointCloud();
  loadCountryBorders();

  window.earthInitialized = true;
  animateEarth();

  // Modal to'liq ko'ringandan keyin o'lchamni qayta sozlash
  setTimeout(() => {
    if (typeof window.resizeEarth === "function") window.resizeEarth();
  }, 300);
}

// Globus o'lchamini konteynerga moslashtirish
window.resizeEarth = function () {
  const container = document.getElementById("earth-container");
  if (!container || !earthRenderer || !earthCamera) return;
  const w = container.clientWidth || container.offsetWidth || 800;
  const h = container.clientHeight || container.offsetHeight || 600;
  if (w === 0 || h === 0) return;
  earthRenderer.setSize(w, h);
  earthCamera.aspect = w / h;
  earthCamera.updateProjectionMatrix();
};

let planetProto = {
  sphere: function (size) {
    return new THREE.SphereGeometry(size, 32, 32);
  },
  material: function (options) {
    let material = new THREE.MeshPhongMaterial();
    if (options) {
      for (var property in options) {
        material[property] = options[property];
      }
    }
    return material;
  },
  glowMaterial: function (intensity, fade, color) {
    let glowMaterial = new THREE.ShaderMaterial({
      uniforms: {
        c: { type: "f", value: intensity },
        p: { type: "f", value: fade },
        glowColor: { type: "c", value: new THREE.Color(color) },
        viewVector: { type: "v3", value: earthCamera.position },
      },
      vertexShader: `
                uniform vec3 viewVector;
                uniform float c;
                uniform float p;
                varying float intensity;
                void main() {
                    vec3 vNormal = normalize( normalMatrix * normal );
                    vec3 vNormel = normalize( normalMatrix * viewVector );
                    intensity = pow( c - dot(vNormal, vNormel), p );
                    gl_Position = projectionMatrix * modelViewMatrix * vec4( position, 1.0 );
                }`,
      fragmentShader: `
                uniform vec3 glowColor;
                varying float intensity;
                void main() {
                    vec3 glow = glowColor * intensity;
                    gl_FragColor = vec4( glow, 1.0 );
                }`,
      side: THREE.BackSide,
      blending: THREE.AdditiveBlending,
      transparent: true,
    });
    return glowMaterial;
  },
  texture: function (material, property, uri) {
    let textureLoader = new THREE.TextureLoader();
    textureLoader.crossOrigin = true;
    textureLoader.load(uri, function (texture) {
      material[property] = texture;
      material.needsUpdate = true;
    });
  },
};

function createPlanet(options) {
  let surfaceGeometry = planetProto.sphere(options.surface.size);
  let surfaceMaterial = planetProto.material(options.surface.material);
  let surface = new THREE.Mesh(surfaceGeometry, surfaceMaterial);

  let atmosphereGeometry = planetProto.sphere(
    options.surface.size + options.atmosphere.size,
  );
  let atmosphereMaterialDefaults = {
    side: THREE.DoubleSide,
    transparent: true,
  };
  let atmosphereMaterialOptions = Object.assign(
    atmosphereMaterialDefaults,
    options.atmosphere.material,
  );
  let atmosphereMaterial = planetProto.material(atmosphereMaterialOptions);
  let atmosphere = new THREE.Mesh(atmosphereGeometry, atmosphereMaterial);

  let atmosphericGlowGeometry = planetProto.sphere(
    options.surface.size +
      options.atmosphere.size +
      options.atmosphere.glow.size,
  );
  let atmosphericGlowMaterial = planetProto.glowMaterial(
    options.atmosphere.glow.intensity,
    options.atmosphere.glow.fade,
    options.atmosphere.glow.color,
  );
  let atmosphericGlow = new THREE.Mesh(
    atmosphericGlowGeometry,
    atmosphericGlowMaterial,
  );

  let planet = new THREE.Object3D();
  surface.name = "surface";
  atmosphere.name = "atmosphere";
  atmosphericGlow.name = "atmosphericGlow";
  planet.add(surface);
  planet.add(atmosphere);
  planet.add(atmosphericGlow);

  for (let textureProperty in options.surface.textures) {
    planetProto.texture(
      surfaceMaterial,
      textureProperty,
      options.surface.textures[textureProperty],
    );
  }

  for (let textureProperty in options.atmosphere.textures) {
    planetProto.texture(
      atmosphereMaterial,
      textureProperty,
      options.atmosphere.textures[textureProperty],
    );
  }

  return planet;
}

function latLongToVector3(latitude, longitude, radius, height) {
  let phi = (latitude * Math.PI) / 180;
  let theta = ((longitude - 180) * Math.PI) / 180;

  let x = -(radius + height) * Math.cos(phi) * Math.cos(theta);
  let y = (radius + height) * Math.sin(phi);
  let z = (radius + height) * Math.cos(phi) * Math.sin(theta);

  return new THREE.Vector3(x, y, z);
}

function softenSolidEarth() {
  if (!earth) return;
  const surface = earth.getObjectByName("surface");
  const atmosphere = earth.getObjectByName("atmosphere");
  if (surface && surface.material) {
    surface.material.transparent = false;
    surface.material.opacity = 1;
  }
  if (atmosphere && atmosphere.material) {
    atmosphere.material.transparent = true;
    atmosphere.material.opacity = 0.2;
  }
}

function addHoloPointCloud() {
  if (!earth || holoPointCloud) return;

  const pointCount = 20000;
  const positions = new Float32Array(pointCount * 3);
  const radius = 0.5;

  for (let i = 0; i < pointCount; i++) {
    const u = Math.random();
    const v = Math.random();
    const theta = 2 * Math.PI * u;
    const phi = Math.acos(2 * v - 1);

    const x = Math.sin(phi) * Math.cos(theta);
    const y = Math.cos(phi);
    const z = Math.sin(phi) * Math.sin(theta);

    const jitter = (Math.random() - 0.5) * 0.004;
    const r = radius + jitter;

    positions[i * 3] = x * r;
    positions[i * 3 + 1] = y * r;
    positions[i * 3 + 2] = z * r;
  }

  const geo = new THREE.BufferGeometry();
  geo.setAttribute("position", new THREE.BufferAttribute(positions, 3));

  const mat = new THREE.PointsMaterial({
    color: 0x00ffff,
    size: 0.006,
    sizeAttenuation: true,
    transparent: true,
    opacity: 0.75,
    blending: THREE.AdditiveBlending,
    depthWrite: false,
  });

  holoPointCloud = new THREE.Points(geo, mat);
  earth.add(holoPointCloud);
}


async function loadCountryBorders() {
  if (!earth || borderGroup) return;
  if (typeof topojson === "undefined") {
    console.warn("TopoJSON not available; country borders disabled.");
    return;
  }

  borderGroup = new THREE.Group();
  earth.add(borderGroup);
  fillGroup = new THREE.Group();
  earth.add(fillGroup);
  initCountryFillOverlay();

  try {
    const res = await fetch(
      "https://unpkg.com/world-atlas@2/countries-110m.json",
    );
    if (!res.ok) throw new Error("Failed to fetch world atlas");
    const topo = await res.json();
    const geo = topojson.feature(topo, topo.objects.countries);
    countryFeatures = geo.features || [];

    const positions = [];
    const radius = 0.505;
    const height = 0.002;

    const addRing = (ring) => {
      for (let i = 0; i < ring.length - 1; i++) {
        const p1 = ring[i];
        const p2 = ring[i + 1];
        const v1 = latLongToVector3(p1[1], p1[0], radius, height);
        const v2 = latLongToVector3(p2[1], p2[0], radius, height);
        positions.push(v1.x, v1.y, v1.z, v2.x, v2.y, v2.z);
      }
    };

    originFillMaterial = new THREE.MeshBasicMaterial({
      color: 0x33ff66,
      transparent: true,
      opacity: 0.5,
      side: THREE.DoubleSide,
      depthWrite: false,
    });
    destFillMaterial = new THREE.MeshBasicMaterial({
      color: 0xff3333,
      transparent: true,
      opacity: 0.5,
      side: THREE.DoubleSide,
      depthWrite: false,
    });

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
    borderGeo.setAttribute(
      "position",
      new THREE.Float32BufferAttribute(positions, 3),
    );
    const borderMat = new THREE.LineBasicMaterial({
      color: 0x00ffff,
      transparent: true,
      opacity: 0.55,
      blending: THREE.AdditiveBlending,
    });
    const borders = new THREE.LineSegments(borderGeo, borderMat);
    borderGroup.add(borders);
  } catch (err) {
    console.warn("Country borders load failed:", err);
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
  countryFillTexture.wrapS = THREE.ClampToEdgeWrapping;
  countryFillTexture.wrapT = THREE.ClampToEdgeWrapping;

  const fillMat = new THREE.MeshBasicMaterial({
    map: countryFillTexture,
    transparent: true,
    opacity: 0.85,
    depthWrite: false,
    depthTest: false,
  });
  const geo = new THREE.SphereGeometry(0.507, 64, 64);
  countryFillMesh = new THREE.Mesh(geo, fillMat);
  countryFillMesh.renderOrder = 5;
  earth.add(countryFillMesh);
}

function clearCountryFills() {
  if (countryFillCtx && countryFillCanvas) {
    countryFillCtx.clearRect(
      0,
      0,
      countryFillCanvas.width,
      countryFillCanvas.height,
    );
    if (countryFillTexture) countryFillTexture.needsUpdate = true;
    return;
  }
  if (!fillGroup) return;
  while (fillGroup.children.length > 0) {
    const obj = fillGroup.children.pop();
    if (obj.geometry) obj.geometry.dispose();
    if (obj.material) obj.material.dispose();
  }
}

function addCountryFill(feature, material) {
  if (!feature || !feature.geometry) return;
  if (countryFillCtx && countryFillCanvas) {
    drawCountryFillCanvas(feature, material);
    return;
  }

  const radius = 0.505;
  const height = 0.004;

  const unwrapRing = (ring) => {
    if (!ring || ring.length === 0) return [];
    const out = [];
    let prev = null;
    for (let i = 0; i < ring.length; i++) {
      let lon = ring[i][0];
      const lat = ring[i][1];
      if (prev !== null) {
        while (lon - prev > 180) lon -= 360;
        while (lon - prev < -180) lon += 360;
      }
      out.push([lon, lat]);
      prev = lon;
    }
    return out;
  };

  const normalizeRings = (rings) =>
    (rings || []).map((ring) => unwrapRing(ring));

  const centroidFromRing = (ring) => {
    if (!ring || ring.length === 0) return { lon: 0, lat: 0 };
    let x = 0;
    let y = 0;
    let z = 0;
    const deg2rad = Math.PI / 180;
    ring.forEach((p) => {
      const lon = p[0] * deg2rad;
      const lat = p[1] * deg2rad;
      const clat = Math.cos(lat);
      x += clat * Math.cos(lon);
      y += clat * Math.sin(lon);
      z += Math.sin(lat);
    });
    const len = Math.sqrt(x * x + y * y + z * z) || 1;
    x /= len;
    y /= len;
    z /= len;
    const lat = Math.asin(z) / deg2rad;
    const lon = Math.atan2(y, x) / deg2rad;
    return { lon, lat };
  };

  const wrapToNear = (lon, centerLon) => {
    let v = lon;
    while (v - centerLon > 180) v -= 360;
    while (v - centerLon < -180) v += 360;
    return v;
  };

  const buildFillMesh = (rings) => {
    if (!rings || rings.length === 0) return;
    const normalized = normalizeRings(rings);
    const centroid = centroidFromRing(normalized[0]);
    const centerLon = centroid.lon;
    const centerLat = centroid.lat;
    const cosLat0 = Math.cos((centerLat * Math.PI) / 180);

    const projectRing = (ring) =>
      ring.map((p) => {
        const lon = wrapToNear(p[0], centerLon);
        const lat = p[1];
        return {
          v: new THREE.Vector2((lon - centerLon) * cosLat0, lat - centerLat),
          lon,
          lat,
        };
      });

    const outerProj = projectRing(normalized[0]);
    const holesProj = normalized.slice(1).map((ring) => projectRing(ring));
    const outer = outerProj.map((p) => p.v);
    const holes = holesProj.map((ring) => ring.map((p) => p.v));

    const faces = THREE.ShapeUtils.triangulateShape(outer, holes);
    if (!faces || faces.length === 0) return;

    const verts = outer.concat(...holes);
    const vertsLL = outerProj
      .map((p) => ({ lon: p.lon, lat: p.lat }))
      .concat(
        ...holesProj.map((ring) =>
          ring.map((p) => ({ lon: p.lon, lat: p.lat })),
        ),
      );
    const pos = new Float32Array(faces.length * 9);
    let idx = 0;
    for (let i = 0; i < faces.length; i++) {
      const a = vertsLL[faces[i][0]];
      const b = vertsLL[faces[i][1]];
      const c = vertsLL[faces[i][2]];

      const va = latLongToVector3(a.lat, a.lon, radius, height);
      const vb = latLongToVector3(b.lat, b.lon, radius, height);
      const vc = latLongToVector3(c.lat, c.lon, radius, height);

      pos[idx++] = va.x;
      pos[idx++] = va.y;
      pos[idx++] = va.z;
      pos[idx++] = vb.x;
      pos[idx++] = vb.y;
      pos[idx++] = vb.z;
      pos[idx++] = vc.x;
      pos[idx++] = vc.y;
      pos[idx++] = vc.z;
    }

    const geoFill = new THREE.BufferGeometry();
    geoFill.setAttribute("position", new THREE.BufferAttribute(pos, 3));
    geoFill.computeVertexNormals();
    const mesh = new THREE.Mesh(geoFill, material);
    fillGroup.add(mesh);
  };

  const geom = feature.geometry;
  if (geom.type === "Polygon") {
    buildFillMesh(geom.coordinates);
  } else if (geom.type === "MultiPolygon") {
    geom.coordinates.forEach((poly) => buildFillMesh(poly));
  }
}

function drawCountryFillCanvas(feature, material) {
  if (!countryFillCtx || !countryFillCanvas || !feature || !feature.geometry)
    return;
  const ctx = countryFillCtx;
  const w = countryFillCanvas.width;
  const h = countryFillCanvas.height;

  const color =
    material && material.color ? material.color : new THREE.Color(1, 0, 0);
  const alpha = material && typeof material.opacity === "number" ? material.opacity : 0.65;
  const rgb = color.getStyle();
  const rgba = rgb.replace("rgb(", "rgba(").replace(")", `, ${alpha})`);

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

  const drawPolygon = (rings) => {
    if (!rings || rings.length === 0) return;
    ctx.save();
    ctx.fillStyle = rgba;
    ctx.globalCompositeOperation = "source-over";
    drawRing(rings[0]);

    if (rings.length > 1) {
      ctx.globalCompositeOperation = "destination-out";
      for (let i = 1; i < rings.length; i++) {
        drawRing(rings[i]);
      }
    }
    ctx.restore();
  };

  const geom = feature.geometry;
  if (geom.type === "Polygon") {
    drawPolygon(geom.coordinates);
  } else if (geom.type === "MultiPolygon") {
    geom.coordinates.forEach((poly) => drawPolygon(poly));
  }

  if (countryFillTexture) countryFillTexture.needsUpdate = true;
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
      point[0] <
        ((xj - xi) * (point[1] - yi)) / (yj - yi + 0.0) + xi;
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

function highlightCountriesByPoints(origin, dest) {
  if (!origin || !dest) return;
  if (!countryFeatures || !originFillMaterial || !destFillMaterial) return;
  clearCountryFills();

  const originFeature = findCountryByPoint(origin.lon, origin.lat);
  const destFeature = findCountryByPoint(dest.lon, dest.lat);

  if (originFeature) addCountryFill(originFeature, originFillMaterial);
  if (destFeature && destFeature !== originFeature) {
    addCountryFill(destFeature, destFillMaterial);
  }
}

function placeMarker(latitude, longitude, color, size) {
  let position = latLongToVector3(latitude, longitude, 0.5, 0);
  let markerGeometry = new THREE.SphereGeometry(size || 0.01);
  let markerMaterial = new THREE.MeshLambertMaterial({ color: color });
  let marker = new THREE.Mesh(markerGeometry, markerMaterial);
  marker.position.copy(position);
  earth.getObjectByName("surface").add(marker);
  currentRouteObjects.push(marker);
  return marker;
}

function buildFlightCurve(lat1, lon1, lat2, lon2) {
  let start = latLongToVector3(lat1, lon1, 0.5, 0.005);
  let end = latLongToVector3(lat2, lon2, 0.5, 0.005);

  let mid = new THREE.Vector3().addVectors(start, end).multiplyScalar(0.5);
  let distance = start.distanceTo(end);
  let elevation = distance * 0.3; // Arc height
  mid.normalize().multiplyScalar(0.5 + elevation);

  return new THREE.QuadraticBezierCurve3(start, mid, end);
}

function drawRoute(lat1, lon1, lat2, lon2, color) {
  const curve = buildFlightCurve(lat1, lon1, lat2, lon2);
  let points = curve.getPoints(120);
  let geometry = new THREE.BufferGeometry().setFromPoints(points);
  let material = new THREE.LineBasicMaterial({
    color: color || 0x00ffff,
    linewidth: 4,
    transparent: true,
    opacity: 0.9,
  });

  let line = new THREE.Line(geometry, material);
  earth.add(line);
  currentRouteObjects.push(line);
  return { line, curve };
}

function cloneMaterial(material) {
  if (!material) return material;
  return typeof material.clone === "function" ? material.clone() : material;
}

function cloneGeometry(geometry) {
  if (!geometry) return geometry;
  return typeof geometry.clone === "function" ? geometry.clone() : geometry;
}

function makeObjectResourcesUnique(root) {
  if (!root || typeof root.traverse !== "function") return;
  root.traverse((obj) => {
    if (!obj || !obj.isMesh) return;
    if (obj.geometry) {
      obj.geometry = cloneGeometry(obj.geometry);
    }
    if (Array.isArray(obj.material)) {
      obj.material = obj.material.map((mat) => cloneMaterial(mat));
    } else if (obj.material) {
      obj.material = cloneMaterial(obj.material);
    }
  });
}

function disposeMaterial(material) {
  if (!material) return;
  if (Array.isArray(material)) {
    material.forEach(disposeMaterial);
    return;
  }
  if (typeof material.dispose === "function") {
    material.dispose();
  }
}

function disposeObject3D(root) {
  if (!root || typeof root.traverse !== "function") return;
  root.traverse((obj) => {
    if (obj.geometry && typeof obj.geometry.dispose === "function") {
      obj.geometry.dispose();
    }
    disposeMaterial(obj.material);
  });
}

function alignModelForwardAxisY(root) {
  const box = new THREE.Box3().setFromObject(root);
  const size = new THREE.Vector3();
  box.getSize(size);
  if (size.x >= size.y && size.x >= size.z) {
    root.rotation.z = -Math.PI / 2;
  } else if (size.z >= size.x && size.z >= size.y) {
    root.rotation.x = Math.PI / 2;
  }
}

function normalizeModelSizeAndCenter(root, targetSize) {
  if (!root) return;
  root.updateMatrixWorld(true);
  const box = new THREE.Box3().setFromObject(root);
  const size = new THREE.Vector3();
  box.getSize(size);
  const maxDim = Math.max(size.x, size.y, size.z);
  if (!Number.isFinite(maxDim) || maxDim <= 0) return;
  const scale = targetSize / maxDim;
  root.scale.multiplyScalar(scale);
  root.updateMatrixWorld(true);
  const centeredBox = new THREE.Box3().setFromObject(root);
  const center = new THREE.Vector3();
  centeredBox.getCenter(center);
  root.position.sub(center);
}

function prepareFlightModel(root) {
  if (!root) return root;
  alignModelForwardAxisY(root);
  normalizeModelSizeAndCenter(root, FLIGHT_MODEL_TARGET_SIZE);
  root.traverse((obj) => {
    if (!obj || !obj.isMesh) return;
    obj.castShadow = true;
    obj.receiveShadow = true;
  });
  return root;
}

async function loadFlightModelTemplate() {
  if (flightModelTemplate) return flightModelTemplate;
  if (flightModelLoadPromise) return flightModelLoadPromise;

  flightModelLoadPromise = (async () => {
    if (!THREE || typeof THREE.GLTFLoader !== "function") {
      throw new Error("GLTFLoader is not available");
    }

    const loader = new THREE.GLTFLoader();
    const bases = getBaseCandidates();
    let lastError = null;

    for (const base of bases) {
      const url = joinBasePath(base, "models/airplane/scene.gltf");
      try {
        const gltf = await new Promise((resolve, reject) => {
          loader.load(url, resolve, undefined, reject);
        });
        const root = gltf && (gltf.scene || (gltf.scenes && gltf.scenes[0]));
        if (!root) {
          throw new Error("GLTF loaded without scene root");
        }
        flightModelTemplate = prepareFlightModel(root);
        return flightModelTemplate;
      } catch (err) {
        lastError = err;
      }
    }

    throw lastError || new Error("Unable to load airplane GLTF model");
  })();

  try {
    return await flightModelLoadPromise;
  } finally {
    if (!flightModelTemplate) {
      flightModelLoadPromise = null;
    }
  }
}

async function createFlightPlaneObject() {
  try {
    const template = await loadFlightModelTemplate();
    const instance = template.clone(true);
    makeObjectResourcesUnique(instance);
    return instance;
  } catch (err) {
    console.warn("GLTF airplane load failed, using primitive fallback.", err);
    return createFlightPlane(0xffffff);
  }
}

function createFlightPlane(color = 0xffffff) {
  const group = new THREE.Group();
  const bodyMat = new THREE.MeshPhongMaterial({
    color,
    emissive: 0x1a3e55,
    emissiveIntensity: 0.45,
    shininess: 90,
  });
  const wingMat = new THREE.MeshPhongMaterial({
    color: 0xdfe9f0,
    emissive: 0x10384a,
    emissiveIntensity: 0.35,
    shininess: 65,
  });
  const canopyMat = new THREE.MeshPhongMaterial({
    color: 0x7fd8ff,
    emissive: 0x145f86,
    emissiveIntensity: 0.4,
    transparent: true,
    opacity: 0.9,
    shininess: 120,
  });

  // Nose points to +Y so orientation can follow curve tangent.
  const noseGeo = new THREE.ConeGeometry(0.003, 0.012, 10);
  const nose = new THREE.Mesh(noseGeo, bodyMat);
  nose.position.set(0, 0.010, 0);
  group.add(nose);

  const bodyGeo = new THREE.CylinderGeometry(0.0024, 0.0028, 0.022, 12);
  const body = new THREE.Mesh(bodyGeo, bodyMat);
  body.position.set(0, 0, 0);
  group.add(body);

  const tailBodyGeo = new THREE.ConeGeometry(0.0022, 0.008, 10);
  const tailBody = new THREE.Mesh(tailBodyGeo, bodyMat);
  tailBody.position.set(0, -0.015, 0);
  tailBody.rotation.z = Math.PI;
  group.add(tailBody);

  const mainWingGeo = new THREE.BoxGeometry(0.020, 0.001, 0.006);
  const mainWing = new THREE.Mesh(mainWingGeo, wingMat);
  mainWing.position.set(0, 0.0015, 0);
  group.add(mainWing);

  const wingL = new THREE.Mesh(new THREE.BoxGeometry(0.010, 0.0008, 0.004), wingMat);
  wingL.position.set(-0.007, 0.0009, 0.0015);
  wingL.rotation.y = -0.32;
  group.add(wingL);

  const wingR = new THREE.Mesh(new THREE.BoxGeometry(0.010, 0.0008, 0.004), wingMat);
  wingR.position.set(0.007, 0.0009, 0.0015);
  wingR.rotation.y = 0.32;
  group.add(wingR);

  const tailWingGeo = new THREE.BoxGeometry(0.008, 0.0008, 0.003);
  const tailWing = new THREE.Mesh(tailWingGeo, wingMat);
  tailWing.position.set(0, -0.011, 0);
  group.add(tailWing);

  const tailFinGeo = new THREE.BoxGeometry(0.0012, 0.0052, 0.0022);
  const tailFin = new THREE.Mesh(tailFinGeo, wingMat);
  tailFin.position.set(0, -0.0105, 0);
  group.add(tailFin);

  const canopyGeo = new THREE.SphereGeometry(0.0024, 10, 10);
  const canopy = new THREE.Mesh(canopyGeo, canopyMat);
  canopy.position.set(0, 0.005, 0.0012);
  group.add(canopy);

  group.scale.setScalar(FLIGHT_PLANE_SCALE);
  return group;
}

async function startFlightAnimation(curve) {
  if (!curve || !earth) return;
  flightCurve = curve;
  flightAnimProgress = 0;
  flightAnimActive = true;
  flightAnimLastTs = 0;

  if (flightPlane) {
    if (flightPlane.parent) flightPlane.parent.remove(flightPlane);
    disposeObject3D(flightPlane);
    flightPlane = null;
  }

  flightPlane = await createFlightPlaneObject();
  const p0 = curve.getPoint(0);
  flightPlane.position.copy(p0);
  earth.add(flightPlane);
  currentRouteObjects.push(flightPlane);
}

function updateFlightAnimation(deltaSec) {
  if (!flightAnimActive || !flightCurve || !flightPlane) return;

  flightAnimProgress = (flightAnimProgress + deltaSec * flightAnimSpeed) % 1;
  const t = flightAnimProgress;

  const p = flightCurve.getPoint(t);
  const lift = p.clone().normalize().multiplyScalar(0.0012);
  flightPlane.position.copy(p).add(lift);

  const tangent = flightCurve.getTangent(t);
  if (tangent.lengthSq() > 1e-9) {
    const forward = tangent.normalize();
    const normal = flightPlane.position.clone().normalize();
    const right = new THREE.Vector3().crossVectors(normal, forward).normalize();
    if (right.lengthSq() > 1e-9) {
      const up = new THREE.Vector3().crossVectors(forward, right).normalize();
      // Model nose follows local +Z, so map basis Z to route tangent.
      const basis = new THREE.Matrix4().makeBasis(right, up, forward);
      const q = new THREE.Quaternion().setFromRotationMatrix(basis);
      if (!flightHeadingFixQuat) {
        flightHeadingFixQuat = new THREE.Quaternion().setFromAxisAngle(
          new THREE.Vector3(0, 1, 0),
          FLIGHT_MODEL_HEADING_OFFSET,
        );
      }
      q.multiply(flightHeadingFixQuat);
      flightPlane.quaternion.copy(q);
    }
  }
}

async function showFlightRoute(originCode, destCode) {
  console.log("🌍 showFlightRoute called:", originCode, "->", destCode);
  try {
    const originKey = String(originCode || "").toUpperCase();
    const destKey = String(destCode || "").toUpperCase();
    const airports = await getAirportCoords([originKey, destKey]);

    const origin = airports[originKey];
    const dest = airports[destKey];

    if (!origin || !dest) {
      console.error("Airport coordinates not found:", originCode, destCode);
      return;
    }

    clearRoute();

    placeMarker(origin.lat, origin.lon, 0x00ff00, 0.012); // Green for origin
    placeMarker(dest.lat, dest.lon, 0xff0000, 0.012); // Red for destination

    const route = drawRoute(origin.lat, origin.lon, dest.lat, dest.lon, 0x00ffff);
    if (route && route.curve) {
      await startFlightAnimation(route.curve);
    }
    highlightCountriesByPoints(origin, dest);

    let centerLat = (origin.lat + dest.lat) / 2;
    let centerLon = (origin.lon + dest.lon) / 2;
    let centerPos = latLongToVector3(centerLat, centerLon, 0.5, 1.5);

    earthCamera.position.copy(centerPos);
    earthCamera.lookAt(earthScene.position);
  } catch (error) {
    console.error("Error loading flight route:", error);
  }
}

async function getAirportCoords(codes) {
  const uniqueCodes = Array.from(
    new Set((codes || []).map((c) => String(c || "").toUpperCase())),
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

        let data = null;
        try {
          data = JSON.parse(raw);
        } catch (_) {
          continue;
        }
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
      } catch (_) {
        // keep trying fallback bases
      }
    }
    if (!loadedFromApi) {
      console.warn("Airport coords API failed, fallback to JSON");
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

      const raw = await res.text();
      if (!raw) continue;

      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== "object") continue;

      airportJsonCache = parsed;
      return;
    } catch (_) {
      // try next base path
    }
  }
  airportJsonCache = null;
}

function clearRoute() {
  flightAnimActive = false;
  flightAnimProgress = 0;
  flightAnimLastTs = 0;
  flightCurve = null;
  currentRouteObjects.forEach((obj) => {
    if (obj.parent) {
      obj.parent.remove(obj);
    }
    disposeObject3D(obj);
  });
  currentRouteObjects = [];
  flightPlane = null;
}

function animateEarth() {
  if (!window.earthInitialized) return;

  requestAnimationFrame(animateEarth);
  if (window.pauseEarth) return; // CPU/GPU tejash uchun

  const now = performance.now();
  if (!flightAnimLastTs) {
    flightAnimLastTs = now;
  }
  const dt = Math.min(0.05, (now - flightAnimLastTs) / 1000);
  flightAnimLastTs = now;
  updateFlightAnimation(dt);


  orbitControls.update();
  earthRenderer.render(earthScene, earthCamera);
}

function resizeEarth() {
  if (!window.earthInitialized) return;

  const container = earthRenderer.domElement.parentElement;
  earthCamera.aspect = container.clientWidth / container.clientHeight;
  earthCamera.updateProjectionMatrix();
  earthRenderer.setSize(container.clientWidth, container.clientHeight);
}
