class AirportNavigation {
  constructor(canvasId) {
    this.canvas = document.getElementById(canvasId);
    this.ctx = this.canvas.getContext("2d");
    this.worldWidth = 0;
    this.worldHeight = 0;
    this.pxScale = 1;
    this.nodes = [];
    this.path = [];
    this.barriers = [];
    this.collisionGrid = null;
    this.gridSize = 30; // Grid o'lchami oshirildi (tezlik uchun)
    this.backgroundImage = new Image();
    this.kioskPos = { x: 500, y: 800 };
    this.offset = 0;
    this.pathRevealProgress = 0; 
    this.isAnimatingPath = false;
    this.pathDrawDuration = 3.0; // Sekinlashtirildi (User talabi: 3.0s)
    this.cameraProgress = 0;
    this.totalPathLength = 0;
    this.pathSegments = [];
    this.lastFrameTime = performance.now();
    this.lastRenderTime = 0;
    this.activeFps = 60; // Yanada silliq animatsiya uchun
    this.idleFps = 10;
    this.needsRender = true;
    this.lowFxMode = false;
    this.mapReady = false;
    this.pendingTarget = null;
    this.resizeCanvasToContainer();
    window.addEventListener("resize", () => this.resizeCanvasToContainer());
    this.animate();
  }

  resizeCanvasToContainer() {
    const container = this.canvas ? this.canvas.parentElement : null;
    if (!container) return;
    
    const nextW = Math.max(800, container.clientWidth || 1280);
    const nextH = Math.max(600, container.clientHeight || 720);
    
    if (this.canvas.width !== nextW || this.canvas.height !== nextH) {
      console.log("[NAV] Resizing canvas to:", nextW, "x", nextH);
      this.canvas.width = nextW;
      this.canvas.height = nextH;
      this.needsRender = true;
    }
  }

  animate() {
    const now = performance.now();
    const dt = Math.min(0.05, (now - this.lastFrameTime) / 1000);
    this.lastFrameTime = now;

    if (this.isAnimatingPath) {
      this.offset += dt * 15;
      if (this.offset > 25) this.offset = 0;
      
      const step = dt / this.pathDrawDuration;
      this.pathRevealProgress = Math.min(1, this.pathRevealProgress + step);
      this.cameraProgress = Math.min(1, this.cameraProgress + step);

      if (this.pathRevealProgress >= 1 && this.cameraProgress >= 1) {
        this.isAnimatingPath = false;
      }
    }

    const mustRender = this.isAnimatingPath || this.needsRender;
    if (mustRender) {
      const targetFps = this.isAnimatingPath ? this.activeFps : this.idleFps;
      const frameInterval = 1000 / targetFps;
      if (now - this.lastRenderTime >= frameInterval) {
        this.render();
        this.lastRenderTime = now;
        if (!this.isAnimatingPath) this.needsRender = false;
      }
    }
    requestAnimationFrame(() => this.animate());
  }

  async loadMap(imagePath) {
    return new Promise((resolve, reject) => {
      this.backgroundImage.onload = () => {
        this.worldWidth = this.backgroundImage.naturalWidth || this.backgroundImage.width;
        this.worldHeight = this.backgroundImage.naturalHeight || this.backgroundImage.height;
        console.log('[NAV] Map loaded:', this.worldWidth, 'x', this.worldHeight);
        this.resizeCanvasToContainer();
        this.updateCollisionGrid();
        this.needsRender = true;
        this.mapReady = true;
        if (this.pendingTarget) {
          const targetName = this.pendingTarget;
          this.pendingTarget = null;
          this.findPath(targetName);
        }
        resolve();
      };
      this.backgroundImage.onerror = (err) => {
        console.error('[NAV] Failed to load map image:', imagePath, err);
        this.mapReady = false;
        reject(err);
      };
      this.backgroundImage.src = imagePath;
    });
    try {
      const apiBase = (window.location.pathname.includes("/admin/")) ? "../" : "";
      const res = await fetch(`${apiBase}api/barriers.php`);
      this.barriers = await res.json();
      this.updateCollisionGrid();
    } catch (e) {
      console.error("Barriers fetch error:", e);
    }
  }

  updateCollisionGrid() {
    if (!this.worldWidth || !this.worldHeight) return;
    const cols = Math.ceil(this.worldWidth / this.gridSize) + 1;
    const rows = Math.ceil(this.worldHeight / this.gridSize) + 1;
    this.collisionGrid = new Uint8Array(cols * rows);
    this.barriers.forEach((b) => {
      const d = b.barrier_data;
      if (!d) return;
      const bx = Number(d.x) || 0;
      const by = Number(d.y) || 0;
      const bw = Number(d.w || d.width) || 0;
      const bh = Number(d.h || d.height) || 0;
      if (bw <= 0 || bh <= 0) return;
      
      const x1 = Math.max(0, Math.floor(bx / this.gridSize));
      const x2 = Math.min(cols - 1, Math.floor((bx + bw) / this.gridSize));
      const y1 = Math.max(0, Math.floor(by / this.gridSize));
      const y2 = Math.min(rows - 1, Math.floor((by + bh) / this.gridSize));
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

  findNearestWalkable(gx, gy) {
      if (!this.isGridBlocked(gx, gy)) return { x: gx, y: gy };
      const maxRadius = 10;
      for (let r = 1; r <= maxRadius; r++) {
          for (let dx = -r; dx <= r; dx++) {
              for (let dy = -r; dy <= r; dy++) {
                  if (Math.abs(dx) !== r && Math.abs(dy) !== r) continue;
                  const nx = gx + dx;
                  const ny = gy + dy;
                  if (!this.isGridBlocked(nx, ny)) return { x: nx, y: ny };
              }
          }
      }
      return { x: gx, y: gy };
  }

  setNodes(nodes) {
    this.nodes = (nodes || []).map(n => ({...n, pos_x: Number(n.pos_x), pos_y: Number(n.pos_y)}));
    const kiosk = this.nodes.find(n => n.type === 'kiosk_start' || n.name === 'kiosk_start');
    if (kiosk) this.kioskPos = { x: kiosk.pos_x, y: kiosk.pos_y };
    this.needsRender = true;
  }

  resetZoom() {
    this.isAnimatingPath = false;
    this.path = [];
    this.pathRevealProgress = 0; 
    this.cameraProgress = 0;
    this.needsRender = true;
  }

  navigateTo(targetX, targetY, targetName) {
    const tx = Number(targetX);
    const ty = Number(targetY);
    if (isNaN(tx) || isNaN(ty) || (tx === 0 && ty === 0)) return this.findPath(targetName);

    if (!this.mapReady || !this.worldWidth) {
      this.pendingTarget = targetName;
      return;
    }

    this.resetZoom();
    const start = { x: this.kioskPos.x, y: this.kioskPos.y };
    const end = { x: tx, y: ty };

    let gridPath = this.aStar(this.toGrid(start), this.toGrid(end));
    if (gridPath && gridPath.length > 1) {
      this.path = gridPath.map(p => this.fromGrid(p));
    } else {
      this.path = [start, end];
    }

    this.pathRevealProgress = 0.01;
    this.cameraProgress = 0.01;
    this.isAnimatingPath = true;
    this.needsRender = true;
    this.computePathMetrics();
    
    const modal = document.getElementById("map-modal");
    if (modal) modal.classList.remove("hide");
  }

  findPath(targetName) {
    if (!this.mapReady) {
      this.pendingTarget = targetName;
      return null;
    }
    if (!this.nodes || this.nodes.length === 0) return null;

    const normalize = (text) =>
      String(text || "").toLowerCase().replace(/[_-]+/g, " ").replace(/[^\p{L}\p{N}\s]/gu, " ").replace(/\s+/g, " ").trim();

    const normalizedSearch = normalize(targetName);
    const directMatches = this.nodes.filter((n) => {
      const nodeName = normalize(n.name);
      return nodeName.includes(normalizedSearch) || normalizedSearch.includes(nodeName);
    });

    let target = directMatches[0] || null;
    if (!target) return null;

    const start = { x: this.kioskPos.x, y: this.kioskPos.y };
    const end = { x: target.pos_x, y: target.pos_y };
    
    const gridPath = this.aStar(this.toGrid(start), this.toGrid(end));
    if (gridPath) {
      this.path = gridPath.map(p => this.fromGrid(p));
    } else {
      this.path = [start, end];
    }

    this.pathRevealProgress = 0;
    this.cameraProgress = 0;
    this.isAnimatingPath = true;
    this.needsRender = true;
    this.computePathMetrics();
    
    const modal = document.getElementById("map-modal");
    if (modal) modal.classList.remove("hide");
    return target;
  }

  aStar(inputStart, inputEnd) {
    const start = this.findNearestWalkable(inputStart.x, inputStart.y);
    const end = this.findNearestWalkable(inputEnd.x, inputEnd.y);
    let openSet = [start];
    let cameFrom = new Map();
    let gScore = new Map();
    let fScore = new Map();
    const key = (p) => `${p.x},${p.y}`;
    gScore.set(key(start), 0);
    fScore.set(key(start), Math.abs(start.x - end.x) + Math.abs(start.y - end.y));

    while (openSet.length > 0) {
      let current = openSet.reduce((a, b) => fScore.get(key(a)) < fScore.get(key(b)) ? a : b);
      if (current.x === end.x && current.y === end.y) {
        let path = [current];
        while (cameFrom.has(key(current))) {
          current = cameFrom.get(key(current));
          path.unshift(current);
        }
        return path;
      }
      openSet = openSet.filter(n => n !== current);
      const dirs = [{x:0,y:1},{x:0,y:-1},{x:1,y:0},{x:-1,y:0},{x:1,y:1},{x:-1,y:-1},{x:1,y:-1},{x:-1,y:1}];
      for (let d of dirs) {
        let neighbor = { x: current.x + d.x, y: current.y + d.y };
        if (this.isGridBlocked(neighbor.x, neighbor.y)) continue;
        if (d.x !== 0 && d.y !== 0) {
          if (this.isGridBlocked(current.x + d.x, current.y) && this.isGridBlocked(current.x, current.y + d.y)) continue;
        }
        let tentativeG = gScore.get(key(current)) + (d.x !== 0 && d.y !== 0 ? 1.4 : 1);
        if (!gScore.has(key(neighbor)) || tentativeG < gScore.get(key(neighbor))) {
          cameFrom.set(key(neighbor), current);
          gScore.set(key(neighbor), tentativeG);
          fScore.set(key(neighbor), tentativeG + Math.abs(neighbor.x - end.x) + Math.abs(neighbor.y - end.y));
          if (!openSet.some(n => n.x === neighbor.x && n.y === neighbor.y)) openSet.push(neighbor);
        }
      }
    }
    return null;
  }

  toGrid(p) { return { x: Math.round(p.x / this.gridSize), y: Math.round(p.y / this.gridSize) }; }
  fromGrid(p) { return { x: p.x * this.gridSize, y: p.y * this.gridSize }; }

  computePathMetrics() {
    this.pathSegments = []; this.totalPathLength = 0;
    if (!this.path) return;
    for (let i = 1; i < this.path.length; i++) {
        const len = Math.hypot(this.path[i].x - this.path[i-1].x, this.path[i].y - this.path[i-1].y);
        this.pathSegments.push(len);
        this.totalPathLength += len;
    }
  }

  getPointAtProgress(progress) {
    if (!this.path || this.path.length < 2) return null;
    const target = this.totalPathLength * progress;
    let current = 0;
    for (let i = 1; i < this.path.length; i++) {
        const seg = this.pathSegments[i-1];
        if (current + seg >= target) {
            const t = (target - current) / seg;
            return {
                x: this.path[i-1].x + (this.path[i].x - this.path[i-1].x) * t,
                y: this.path[i-1].y + (this.path[i].y - this.path[i-1].y) * t,
                dirX: (this.path[i].x - this.path[i-1].x) / seg,
                dirY: (this.path[i].y - this.path[i-1].y) / seg
            };
        }
        current += seg;
    }
    const last = this.path[this.path.length-1];
    return { x: last.x, y: last.y, dirX: 1, dirY: 0 };
  }

  render() {
    if (!this.canvas) return;
    if (!this.backgroundImage.complete || this.backgroundImage.naturalWidth === 0) {
      console.warn('[NAV] Background image not ready yet');
      return;
    }
    if (this.canvas.width < 10 || this.canvas.height < 10) this.resizeCanvasToContainer();

    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    this.ctx.imageSmoothingEnabled = true;
    this.ctx.imageSmoothingQuality = "high";

    const follow = window.NAV_CAMERA_FOLLOW !== false;
    const zoom = window.NAV_CAMERA_ZOOM || 1.7;
    let cameraPos = null;
    
    if (follow && this.path && this.path.length > 1) {
       cameraPos = this.getPointAtProgress(Math.min(this.cameraProgress, this.pathRevealProgress));
    }

    const wW = this.worldWidth || 1000;
    const wH = this.worldHeight || 1000;
    const fitScale = Math.min(this.canvas.width / wW, this.canvas.height / wH);
    const scale = (cameraPos && follow) ? fitScale * zoom : fitScale;
    this.pxScale = 1 / scale;

    let offsetX = (this.canvas.width - wW * scale) / 2;
    let offsetY = (this.canvas.height - wH * scale) / 2;

    if (cameraPos && follow) {
       offsetX = this.canvas.width/2 - cameraPos.x * scale;
       offsetY = this.canvas.height/2 - cameraPos.y * scale;
       offsetX = Math.min(0, Math.max(this.canvas.width - wW * scale, offsetX));
       offsetY = Math.min(0, Math.max(this.canvas.height - wH * scale, offsetY));
    }

    this.ctx.save();
    this.ctx.setTransform(scale, 0, 0, scale, offsetX, offsetY);
    try {
      this.ctx.drawImage(this.backgroundImage, 0, 0);
    } catch (err) {
      console.error('[NAV] drawImage error:', err);
      this.ctx.restore();
      return;
    }

    if (this.path && this.path.length >= 2) {
      this.ctx.strokeStyle = window.NAV_LINE_COLOR || "#ff3b30";
      this.ctx.lineWidth = ((window.NAV_LINE_WIDTH || 12) / 2) * this.pxScale; // 2 barobar kichikroq
      this.ctx.lineCap = "round"; this.ctx.lineJoin = "round";
      
      this.ctx.beginPath();
      this.ctx.moveTo(this.path[0].x, this.path[0].y);
      
      const totalPoints = this.path.length;
      const progressWeight = this.pathRevealProgress * (totalPoints - 1);
      const fullPoints = Math.floor(progressWeight);
      const partialSegment = progressWeight - fullPoints;

      for (let i = 1; i <= fullPoints; i++) {
        if (this.path[i]) this.ctx.lineTo(this.path[i].x, this.path[i].y);
      }

      if (partialSegment > 0 && fullPoints < totalPoints - 1) {
        const p1 = this.path[fullPoints];
        const p2 = this.path[fullPoints + 1];
        if (p1 && p2) {
            const px = p1.x + (p2.x - p1.x) * partialSegment;
            const py = p1.y + (p2.y - p1.y) * partialSegment;
            this.ctx.lineTo(px, py);
        }
      }
      this.ctx.stroke();

      this.ctx.fillStyle = "#007bff";
      this.ctx.beginPath(); 
      this.ctx.arc(this.path[0].x, this.path[0].y, 8 * this.pxScale, 0, Math.PI * 2); // 16 -> 8 (2 barobar kichik)
      this.ctx.fill();
      this.ctx.strokeStyle = "white";
      this.ctx.lineWidth = 1.5 * this.pxScale; // 3 -> 1.5
      this.ctx.stroke();

      if (this.pathRevealProgress > 0.9) {
          const last = this.path[this.path.length - 1];
          if (last) {
            this.ctx.fillStyle = "#ff3b30";
            this.ctx.beginPath(); 
            this.ctx.arc(last.x, last.y, 11 * this.pxScale, 0, Math.PI * 2); // 22 -> 11
            this.ctx.fill();
            this.ctx.strokeStyle = "white";
            this.ctx.lineWidth = 2 * this.pxScale; // 4 -> 2
            this.ctx.stroke();
            this.ctx.fillStyle = "white";
            this.ctx.beginPath();
            this.ctx.arc(last.x, last.y, 4 * this.pxScale, 0, Math.PI * 2); // 8 -> 4
            this.ctx.fill();
          }
      }
    }
    this.ctx.restore();
  }
}


// Global funksiya - 3D globusdan chaqirish uchun
window.navigateToLocation = function(locationName) {
  if (window.airportNav && typeof window.airportNav.findPath === 'function') {
    console.log('[GLOBAL] Navigating to:', locationName);
    window.airportNav.findPath(locationName);
  } else {
    console.error('[GLOBAL] airportNav not found');
  }
};
