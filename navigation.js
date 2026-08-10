/**
 * ACCSESS — Interactive Airport Navigation Engine
 * Ultra-Smooth Canvas 2D Navigation with Drag/Pan, Pinch-Zoom, Glowing Path & Pulse Markers
 */

class AirportNavigation {
  constructor(canvasId) {
    this.canvas = document.getElementById(canvasId);
    if (!this.canvas) return;
    this.ctx = this.canvas.getContext("2d");

    this.worldWidth = 0;
    this.worldHeight = 0;
    this.backgroundImage = new Image();

    // Transform State (Pan & Zoom)
    this.scale = 1;
    this.minScale = 0.1;
    this.maxScale = 6.0;
    this.panX = 0;
    this.panY = 0;
    this.fitScale = 1;

    // User Interaction (Mouse & Touch)
    this.isDragging = false;
    this.dragStartX = 0;
    this.dragStartY = 0;
    this.userHasPanned = false;
    this.touchStartDist = 0;

    // Navigation & Pathfinding
    this.nodes = [];
    this.path = [];
    this.smoothedPath = [];
    this.barriers = [];
    this.collisionGrid = null;
    this.gridSize = 30;
    this.kioskPos = { x: 500, y: 800 };
    this.targetNode = null;

    // Animation Properties
    this.pathRevealProgress = 0;
    this.isAnimatingPath = false;
    this.pathDrawDuration = 2.5; // seconds
    this.cameraProgress = 0;
    this.totalPathLength = 0;
    this.pathSegments = [];
    this.pulsePhase = 0;

    this.lastFrameTime = performance.now();
    this.needsRender = true;
    this.mapReady = false;
    this.pendingTarget = null;

    this.resizeCanvasToContainer();
    this.setupEventListeners();
    window.addEventListener("resize", () => {
      this.resizeCanvasToContainer();
      this.centerMap();
    });

    this.animate();
  }

  resizeCanvasToContainer() {
    const container = this.canvas ? this.canvas.parentElement : null;
    if (!container) return;

    const nextW = container.clientWidth || 1280;
    const nextH = container.clientHeight || 720;

    if (this.canvas.width !== nextW || this.canvas.height !== nextH) {
      this.canvas.width = nextW;
      this.canvas.height = nextH;
      this.calculateFitScale();
      this.needsRender = true;
    }
  }

  calculateFitScale() {
    if (!this.worldWidth || !this.worldHeight || !this.canvas.width || !this.canvas.height) return;
    const padding = 20;
    const scaleX = (this.canvas.width - padding * 2) / this.worldWidth;
    const scaleY = (this.canvas.height - padding * 2) / this.worldHeight;
    this.fitScale = Math.min(scaleX, scaleY);
    this.minScale = this.fitScale * 0.7;
    this.maxScale = this.fitScale * 7.0;
  }

  centerMap() {
    if (!this.worldWidth || !this.worldHeight) return;
    this.calculateFitScale();
    this.scale = this.fitScale;
    this.panX = (this.canvas.width - this.worldWidth * this.scale) / 2;
    this.panY = (this.canvas.height - this.worldHeight * this.scale) / 2;
    this.userHasPanned = false;
    this.needsRender = true;
  }

  setupEventListeners() {
    if (!this.canvas) return;

    // Mouse Down
    this.canvas.addEventListener("mousedown", (e) => {
      this.isDragging = true;
      this.dragStartX = e.clientX - this.panX;
      this.dragStartY = e.clientY - this.panY;
      this.userHasPanned = true;
      this.canvas.style.cursor = "grabbing";
    });

    // Mouse Move
    window.addEventListener("mousemove", (e) => {
      if (!this.isDragging) return;
      this.panX = e.clientX - this.dragStartX;
      this.panY = e.clientY - this.dragStartY;
      this.needsRender = true;
    });

    // Mouse Up
    window.addEventListener("mouseup", () => {
      if (this.isDragging) {
        this.isDragging = false;
        if (this.canvas) this.canvas.style.cursor = "grab";
      }
    });

    // Wheel Zoom
    this.canvas.addEventListener("wheel", (e) => {
      e.preventDefault();
      this.userHasPanned = true;

      const rect = this.canvas.getBoundingClientRect();
      const mouseX = e.clientX - rect.left;
      const mouseY = e.clientY - rect.top;

      const zoomFactor = e.deltaY < 0 ? 1.15 : 0.87;
      const newScale = Math.min(this.maxScale, Math.max(this.minScale, this.scale * zoomFactor));

      // Zoom towards mouse position
      this.panX = mouseX - (mouseX - this.panX) * (newScale / this.scale);
      this.panY = mouseY - (mouseY - this.panY) * (newScale / this.scale);
      this.scale = newScale;
      this.needsRender = true;
    }, { passive: false });

    // Touch Events (Mobile/Kiosk Touch)
    this.canvas.addEventListener("touchstart", (e) => {
      this.userHasPanned = true;
      if (e.touches.length === 1) {
        this.isDragging = true;
        this.dragStartX = e.touches[0].clientX - this.panX;
        this.dragStartY = e.touches[0].clientY - this.panY;
      } else if (e.touches.length === 2) {
        this.isDragging = false;
        this.touchStartDist = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY
        );
      }
    }, { passive: true });

    this.canvas.addEventListener("touchmove", (e) => {
      if (e.touches.length === 1 && this.isDragging) {
        this.panX = e.touches[0].clientX - this.dragStartX;
        this.panY = e.touches[0].clientY - this.dragStartY;
        this.needsRender = true;
      } else if (e.touches.length === 2 && this.touchStartDist > 0) {
        const currentDist = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY
        );
        const factor = currentDist / this.touchStartDist;
        const newScale = Math.min(this.maxScale, Math.max(this.minScale, this.scale * factor));

        const midX = (e.touches[0].clientX + e.touches[1].clientX) / 2;
        const midY = (e.touches[0].clientY + e.touches[1].clientY) / 2;

        this.panX = midX - (midX - this.panX) * (newScale / this.scale);
        this.panY = midY - (midY - this.panY) * (newScale / this.scale);
        this.scale = newScale;
        this.touchStartDist = currentDist;
        this.needsRender = true;
      }
    }, { passive: true });

    this.canvas.addEventListener("touchend", () => {
      this.isDragging = false;
      this.touchStartDist = 0;
    }, { passive: true });
  }

  async loadMap(imagePath) {
    console.log("[NAV] Loading map from:", imagePath);
    await new Promise((resolve, reject) => {
      this.backgroundImage.onload = () => {
        this.worldWidth = this.backgroundImage.naturalWidth || this.backgroundImage.width;
        this.worldHeight = this.backgroundImage.naturalHeight || this.backgroundImage.height;
        console.log("[NAV] Map loaded size:", this.worldWidth, "x", this.worldHeight);
        this.resizeCanvasToContainer();
        this.centerMap();
        resolve();
      };
      this.backgroundImage.onerror = (err) => {
        console.warn("[NAV] Map load failed for:", imagePath, "Trying fallback...");
        if (!imagePath.includes("airport_map.jpg")) {
          this.backgroundImage.src = "img/airport_map.jpg";
        } else {
          reject(err);
        }
      };
      this.backgroundImage.src = imagePath;
    });

    // Load Barriers
    try {
      const apiBase = window.location.pathname.includes("/admin/") ? "../" : "";
      const res = await fetch(`${apiBase}api/barriers.php`);
      this.barriers = await res.json();
      console.log("[NAV] Barriers loaded:", this.barriers.length);
    } catch (e) {
      this.barriers = [];
    }

    this.updateCollisionGrid();
    this.mapReady = true;

    if (this.pendingTarget) {
      const target = this.pendingTarget;
      this.pendingTarget = null;
      this.findPath(target);
    }
  }

  setNodes(nodes) {
    this.nodes = Array.isArray(nodes) ? nodes : [];
    const kiosk = this.nodes.find((n) => n.type === "kiosk_start" || n.name === "Kiosk");
    if (kiosk) {
      this.kioskPos = { x: Number(kiosk.pos_x), y: Number(kiosk.pos_y) };
    }
    this.needsRender = true;
  }

  updateCollisionGrid() {
    if (!this.worldWidth || !this.worldHeight) return;
    const cols = Math.ceil(this.worldWidth / this.gridSize) + 1;
    const rows = Math.ceil(this.worldHeight / this.gridSize) + 1;
    this.collisionGrid = new Uint8Array(cols * rows);

    const MARGIN = 1;
    this.barriers.forEach((b) => {
      const d = b.barrier_data;
      if (!d) return;
      const bx = Number(d.x) || 0;
      const by = Number(d.y) || 0;
      const bw = Number(d.w || d.width) || 0;
      const bh = Number(d.h || d.height) || 0;
      if (bw <= 0 || bh <= 0) return;

      const x1 = Math.max(0, Math.floor(bx / this.gridSize) - MARGIN);
      const x2 = Math.min(cols - 1, Math.ceil((bx + bw) / this.gridSize) + MARGIN);
      const y1 = Math.max(0, Math.floor(by / this.gridSize) - MARGIN);
      const y2 = Math.min(rows - 1, Math.ceil((by + bh) / this.gridSize) + MARGIN);

      for (let x = x1; x <= x2; x++) {
        for (let y = y1; y <= y2; y++) {
          this.collisionGrid[y * cols + x] = 1;
        }
      }
    });
  }

  isGridBlocked(gx, gy) {
    if (!this.collisionGrid) return false;
    const cols = Math.ceil(this.worldWidth / this.gridSize) + 1;
    const rows = Math.ceil(this.worldHeight / this.gridSize) + 1;
    if (gx < 0 || gy < 0 || gx >= cols || gy >= rows) return true;
    return this.collisionGrid[gy * cols + gx] === 1;
  }

  findNearestWalkable(x, y) {
    let gx = Math.round(x / this.gridSize);
    let gy = Math.round(y / this.gridSize);
    if (!this.isGridBlocked(gx, gy)) return { x: gx, y: gy };

    for (let r = 1; r < 25; r++) {
      for (let dx = -r; dx <= r; dx++) {
        for (let dy = -r; dy <= r; dy++) {
          if (Math.abs(dx) === r || Math.abs(dy) === r) {
            if (!this.isGridBlocked(gx + dx, gy + dy)) {
              return { x: gx + dx, y: gy + dy };
            }
          }
        }
      }
    }
    return { x: gx, y: gy };
  }

  aStar(startPt, endPt) {
    const start = this.findNearestWalkable(startPt.x, startPt.y);
    const end = this.findNearestWalkable(endPt.x, endPt.y);

    const key = (p) => `${p.x},${p.y}`;
    let openSet = [start];
    let cameFrom = new Map();
    let gScore = new Map();
    let fScore = new Map();

    gScore.set(key(start), 0);
    fScore.set(key(start), Math.hypot(start.x - end.x, start.y - end.y));

    while (openSet.length > 0) {
      let current = openSet.reduce((a, b) => (fScore.get(key(a)) < fScore.get(key(b)) ? a : b));
      if (current.x === end.x && current.y === end.y) {
        let path = [current];
        while (cameFrom.has(key(current))) {
          current = cameFrom.get(key(current));
          path.unshift(current);
        }
        return path;
      }

      openSet = openSet.filter((n) => n !== current);
      const dirs = [
        { x: 0, y: 1 }, { x: 0, y: -1 }, { x: 1, y: 0 }, { x: -1, y: 0 },
        { x: 1, y: 1 }, { x: -1, y: -1 }, { x: 1, y: -1 }, { x: -1, y: 1 }
      ];

      for (let d of dirs) {
        let neighbor = { x: current.x + d.x, y: current.y + d.y };
        if (this.isGridBlocked(neighbor.x, neighbor.y)) continue;

        if (d.x !== 0 && d.y !== 0) {
          if (this.isGridBlocked(current.x + d.x, current.y) && this.isGridBlocked(current.x, current.y + d.y)) {
            continue;
          }
        }

        const moveCost = d.x !== 0 && d.y !== 0 ? 1.414 : 1.0;
        let tentativeG = gScore.get(key(current)) + moveCost;

        if (!gScore.has(key(neighbor)) || tentativeG < gScore.get(key(neighbor))) {
          cameFrom.set(key(neighbor), current);
          gScore.set(key(neighbor), tentativeG);
          fScore.set(key(neighbor), tentativeG + Math.hypot(neighbor.x - end.x, neighbor.y - end.y));
          if (!openSet.some((n) => n.x === neighbor.x && n.y === neighbor.y)) {
            openSet.push(neighbor);
          }
        }
      }
    }
    return null;
  }

  isLineBlocked(p1, p2) {
    if (!this.collisionGrid) return false;
    const dx = p2.x - p1.x;
    const dy = p2.y - p1.y;
    const dist = Math.hypot(dx, dy);
    if (dist === 0) return false;

    const steps = Math.ceil(dist * 2);
    for (let i = 0; i <= steps; i++) {
      const t = i / steps;
      const gx = Math.round(p1.x + dx * t);
      const gy = Math.round(p1.y + dy * t);
      if (this.isGridBlocked(gx, gy)) {
        return true;
      }
    }
    return false;
  }

  simplifyPath(rawGridPath) {
    if (!rawGridPath || rawGridPath.length < 3) return rawGridPath;

    // String Pulling / Line-of-sight path shortening (Portals)
    const simplified = [rawGridPath[0]];
    let currentIdx = 0;

    while (currentIdx < rawGridPath.length - 1) {
      let furthestVisibleIdx = currentIdx + 1;

      for (let testIdx = rawGridPath.length - 1; testIdx > currentIdx; testIdx--) {
        if (!this.isLineBlocked(rawGridPath[currentIdx], rawGridPath[testIdx])) {
          furthestVisibleIdx = testIdx;
          break;
        }
      }

      simplified.push(rawGridPath[furthestVisibleIdx]);
      currentIdx = furthestVisibleIdx;
    }

    return simplified;
  }

  smoothPath(rawGridPath, startPos, endPos) {
    if (!rawGridPath || rawGridPath.length < 2) {
      if (startPos && endPos) return [startPos, endPos];
      return [];
    }

    // 1. Agar start va end o'rtasida to'g'ri chiziqda to'siq bo'lmasa -> To'g'ridan-to'g'ri 100% tekis chiziq!
    if (startPos && endPos) {
      const gStart = { x: Math.round(startPos.x / this.gridSize), y: Math.round(startPos.y / this.gridSize) };
      const gEnd = { x: Math.round(endPos.x / this.gridSize), y: Math.round(endPos.y / this.gridSize) };
      if (!this.isLineBlocked(gStart, gEnd)) {
        return [startPos, endPos];
      }
    }

    // 2. Line of sight (String pulling) orqali zigzag kataklarni olib tashlash
    const simpleGrid = this.simplifyPath(rawGridPath);
    let worldPts = simpleGrid.map((p) => ({ x: p.x * this.gridSize, y: p.y * this.gridSize }));

    if (startPos) worldPts[0] = { x: startPos.x, y: startPos.y };
    if (endPos) worldPts[worldPts.length - 1] = { x: endPos.x, y: endPos.y };

    if (worldPts.length <= 2) {
      return worldPts;
    }

    // 3. To'siqlar atrofidagi burilish burchaklarini qisqa silliqlash (Chaikin)
    let pts = worldPts;
    for (let iteration = 0; iteration < 2; iteration++) {
      if (pts.length < 3) break;
      const smoothed = [pts[0]];
      for (let i = 0; i < pts.length - 1; i++) {
        const p0 = pts[i];
        const p1 = pts[i + 1];
        smoothed.push({
          x: 0.85 * p0.x + 0.15 * p1.x,
          y: 0.85 * p0.y + 0.15 * p1.y
        });
        smoothed.push({
          x: 0.15 * p0.x + 0.85 * p1.x,
          y: 0.15 * p0.y + 0.85 * p1.y
        });
      }
      smoothed.push(pts[pts.length - 1]);
      pts = smoothed;
    }
    return pts;
  }

  computePathMetrics() {
    this.pathSegments = [];
    this.totalPathLength = 0;
    if (!this.smoothedPath || this.smoothedPath.length < 2) return;

    for (let i = 1; i < this.smoothedPath.length; i++) {
      const len = Math.hypot(
        this.smoothedPath[i].x - this.smoothedPath[i - 1].x,
        this.smoothedPath[i].y - this.smoothedPath[i - 1].y
      );
      this.pathSegments.push(len);
      this.totalPathLength += len;
    }
  }

  getPointAtProgress(progress) {
    if (!this.smoothedPath || this.smoothedPath.length < 2) return null;
    const target = this.totalPathLength * progress;
    let current = 0;

    for (let i = 1; i < this.smoothedPath.length; i++) {
      const seg = this.pathSegments[i - 1];
      if (current + seg >= target) {
        const t = (target - current) / (seg || 1);
        return {
          x: this.smoothedPath[i - 1].x + (this.smoothedPath[i].x - this.smoothedPath[i - 1].x) * t,
          y: this.smoothedPath[i - 1].y + (this.smoothedPath[i].y - this.smoothedPath[i - 1].y) * t,
        };
      }
      current += seg;
    }
    return this.smoothedPath[this.smoothedPath.length - 1];
  }

  findPath(targetName) {
    if (!this.mapReady) {
      this.pendingTarget = targetName;
      return null;
    }

    const tLower = String(targetName).trim().toLowerCase();
    const target = this.nodes.find((n) => (n.name || "").trim().toLowerCase() === tLower) ||
      this.nodes.find((n) => (n.name || "").trim().toLowerCase().includes(tLower));

    if (!target) {
      console.warn("[NAV] Target not found:", targetName);
      return null;
    }

    this.targetNode = target;
    const startPos = this.kioskPos;
    const endPos = { x: Number(target.pos_x), y: Number(target.pos_y) };

    console.log("[NAV] Routing to:", target.name, "| Start:", startPos, "| End:", endPos);

    const rawPath = this.aStar(startPos, endPos);
    this.smoothedPath = this.smoothPath(rawPath, startPos, endPos);

    this.computePathMetrics();
    this.pathRevealProgress = 0;
    this.cameraProgress = 0;
    this.isAnimatingPath = true;
    this.userHasPanned = false;
    this.needsRender = true;

    // Show Map Modal
    const modal = document.getElementById("map-modal");
    if (modal) modal.classList.remove("hide");

    return target;
  }

  navigateTo(posX, posY, name) {
    this.targetNode = { name: name || "Manzil", pos_x: posX, pos_y: posY };
    const startPos = this.kioskPos;
    const endPos = { x: Number(posX), y: Number(posY) };

    const rawPath = this.aStar(startPos, endPos);
    this.smoothedPath = this.smoothPath(rawPath, startPos, endPos);

    this.computePathMetrics();
    this.pathRevealProgress = 0;
    this.cameraProgress = 0;
    this.isAnimatingPath = true;
    this.userHasPanned = false;
    this.needsRender = true;
  }

  animate() {
    const now = performance.now();
    const dt = Math.min(0.05, (now - this.lastFrameTime) / 1000);
    this.lastFrameTime = now;
    this.pulsePhase = (this.pulsePhase + dt * 2.5) % (Math.PI * 2);

    if (this.isAnimatingPath) {
      const step = dt / this.pathDrawDuration;
      this.pathRevealProgress = Math.min(1, this.pathRevealProgress + step);
      this.cameraProgress = Math.min(1, this.cameraProgress + step * 0.9);

      if (this.pathRevealProgress >= 1 && this.cameraProgress >= 1) {
        this.isAnimatingPath = false;
      }
      this.needsRender = true;
    }

    if (this.needsRender || this.isAnimatingPath || this.smoothedPath.length > 0) {
      this.render();
    }

    requestAnimationFrame(() => this.animate());
  }

  render() {
    if (!this.canvas || !this.ctx) return;
    if (!this.backgroundImage.complete || this.backgroundImage.naturalWidth === 0) {
      return;
    }

    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    this.ctx.imageSmoothingEnabled = true;
    this.ctx.imageSmoothingQuality = "high";

    // Auto camera follow along path if not manually panned
    if (!this.userHasPanned && this.smoothedPath && this.smoothedPath.length > 1) {
      const camPt = this.getPointAtProgress(Math.min(this.cameraProgress, this.pathRevealProgress));
      if (camPt) {
        const zoom = Math.min(this.maxScale, this.fitScale * 2.2);
        this.scale = zoom;
        this.panX = this.canvas.width / 2 - camPt.x * this.scale;
        this.panY = this.canvas.height / 2 - camPt.y * this.scale;
      }
    }

    this.ctx.save();
    this.ctx.setTransform(this.scale, 0, 0, this.scale, this.panX, this.panY);

    // 1. Draw Map Image
    try {
      this.ctx.drawImage(this.backgroundImage, 0, 0, this.worldWidth, this.worldHeight);
    } catch (e) {}

    // 2. Draw Navigation Path
    if (this.smoothedPath && this.smoothedPath.length >= 2) {
      this.drawGlowingPath();
      this.drawKioskMarker();
      if (this.pathRevealProgress > 0.3) {
        this.drawDestinationMarker();
      }
    }

    this.ctx.restore();
  }

  drawGlowingPath() {
    const totalPoints = this.smoothedPath.length;
    const progressWeight = this.pathRevealProgress * (totalPoints - 1);
    const fullPoints = Math.floor(progressWeight);
    const partialSegment = progressWeight - fullPoints;

    const currentPoints = [];
    for (let i = 0; i <= fullPoints; i++) {
      if (this.smoothedPath[i]) currentPoints.push(this.smoothedPath[i]);
    }
    if (partialSegment > 0 && fullPoints < totalPoints - 1) {
      const p1 = this.smoothedPath[fullPoints];
      const p2 = this.smoothedPath[fullPoints + 1];
      if (p1 && p2) {
        currentPoints.push({
          x: p1.x + (p2.x - p1.x) * partialSegment,
          y: p1.y + (p2.y - p1.y) * partialSegment
        });
      }
    }

    if (currentPoints.length < 2) return;

    // Layer 1: Outer Neon Glow
    this.ctx.beginPath();
    this.ctx.moveTo(currentPoints[0].x, currentPoints[0].y);
    for (let i = 1; i < currentPoints.length; i++) {
      this.ctx.lineTo(currentPoints[i].x, currentPoints[i].y);
    }
    this.ctx.strokeStyle = "rgba(0, 210, 255, 0.45)";
    this.ctx.lineWidth = 18 / this.scale;
    this.ctx.lineCap = "round";
    this.ctx.lineJoin = "round";
    this.ctx.stroke();

    // Layer 2: Vibrant Core Neon Line
    this.ctx.beginPath();
    this.ctx.moveTo(currentPoints[0].x, currentPoints[0].y);
    for (let i = 1; i < currentPoints.length; i++) {
      this.ctx.lineTo(currentPoints[i].x, currentPoints[i].y);
    }
    this.ctx.strokeStyle = "#00f2fe";
    this.ctx.lineWidth = 8 / this.scale;
    this.ctx.stroke();

    // Layer 3: Inner White Highlight
    this.ctx.beginPath();
    this.ctx.moveTo(currentPoints[0].x, currentPoints[0].y);
    for (let i = 1; i < currentPoints.length; i++) {
      this.ctx.lineTo(currentPoints[i].x, currentPoints[i].y);
    }
    this.ctx.strokeStyle = "#ffffff";
    this.ctx.lineWidth = 3 / this.scale;
    this.ctx.stroke();
  }

  drawKioskMarker() {
    const p = this.kioskPos;
    const r = 16 / this.scale;
    const pulseR = r * (1 + 0.6 * Math.sin(this.pulsePhase));

    // Radar pulse wave
    this.ctx.beginPath();
    this.ctx.arc(p.x, p.y, pulseR, 0, Math.PI * 2);
    this.ctx.strokeStyle = `rgba(0, 255, 136, ${Math.max(0.1, 0.7 - 0.5 * Math.sin(this.pulsePhase))})`;
    this.ctx.lineWidth = 3 / this.scale;
    this.ctx.stroke();

    // Solid Beacon Core
    this.ctx.beginPath();
    this.ctx.arc(p.x, p.y, r * 0.7, 0, Math.PI * 2);
    this.ctx.fillStyle = "#00ff88";
    this.ctx.fill();
    this.ctx.strokeStyle = "#ffffff";
    this.ctx.lineWidth = 2.5 / this.scale;
    this.ctx.stroke();

    // Label
    this.drawMarkerLabel(p.x, p.y - r * 1.5, "SIZ SHU YERDASIZ", "#00ff88");
  }

  drawDestinationMarker() {
    if (!this.targetNode) return;
    const p = { x: Number(this.targetNode.pos_x), y: Number(this.targetNode.pos_y) };
    const r = 18 / this.scale;
    const pulseR = r * (1 + 0.5 * Math.sin(this.pulsePhase + Math.PI));

    // Outer Target Pulse
    this.ctx.beginPath();
    this.ctx.arc(p.x, p.y, pulseR, 0, Math.PI * 2);
    this.ctx.strokeStyle = `rgba(255, 59, 48, ${Math.max(0.1, 0.8 - 0.5 * Math.sin(this.pulsePhase))})`;
    this.ctx.lineWidth = 3.5 / this.scale;
    this.ctx.stroke();

    // Target Pin Core
    this.ctx.beginPath();
    this.ctx.arc(p.x, p.y, r * 0.8, 0, Math.PI * 2);
    this.ctx.fillStyle = "#ff3b30";
    this.ctx.fill();
    this.ctx.strokeStyle = "#ffffff";
    this.ctx.lineWidth = 3 / this.scale;
    this.ctx.stroke();

    // Inner White Dot
    this.ctx.beginPath();
    this.ctx.arc(p.x, p.y, r * 0.3, 0, Math.PI * 2);
    this.ctx.fillStyle = "#ffffff";
    this.ctx.fill();

    // Destination Name Badge
    const label = this.targetNode.name || "Manzil";
    this.drawMarkerLabel(p.x, p.y - r * 1.6, label.toUpperCase(), "#ff3b30");
  }

  drawMarkerLabel(x, y, text, colorHex) {
    this.ctx.save();
    this.ctx.font = `bold ${Math.max(12, 15 / this.scale)}px 'Orbitron', 'Outfit', sans-serif`;
    const textWidth = this.ctx.measureText(text).width;
    const padX = 8 / this.scale;
    const padY = 4 / this.scale;
    const boxH = 22 / this.scale;

    // Background pill
    this.ctx.fillStyle = "rgba(5, 12, 24, 0.9)";
    this.ctx.strokeStyle = colorHex;
    this.ctx.lineWidth = 1.5 / this.scale;
    this.ctx.beginPath();
    this.ctx.roundRect(x - textWidth / 2 - padX, y - boxH, textWidth + padX * 2, boxH, 4 / this.scale);
    this.ctx.fill();
    this.ctx.stroke();

    // Text
    this.ctx.fillStyle = "#ffffff";
    this.ctx.textAlign = "center";
    this.ctx.textBaseline = "middle";
    this.ctx.fillText(text, x, y - boxH / 2);
    this.ctx.restore();
  }
}

// Global hook
window.AirportNavigation = AirportNavigation;
window.navigateToLocation = function (locationName) {
  if (window.airportNav && typeof window.airportNav.findPath === "function") {
    console.log("[GLOBAL] Navigating to:", locationName);
    window.airportNav.findPath(locationName);
  }
};
