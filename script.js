const cols = 8;
const rows = 14;
const cell = 40;

const canvas = document.getElementById("gameCanvas");
const ctx = canvas.getContext("2d");
const nextCanvas = document.getElementById("nextCanvas");
const nextCtx = nextCanvas.getContext("2d");

const scoreEl = document.getElementById("score");
const levelEl = document.getElementById("level");
const chainEl = document.getElementById("chain");
const overlay = document.getElementById("overlayMessage");
const spotMessage = document.getElementById("spotMessage");
const legend = document.getElementById("legend");

const startButton = document.getElementById("startButton");
const pauseButton = document.getElementById("pauseButton");
const restartButton = document.getElementById("restartButton");

const pieces = [
  { id: "lake", name: "十和田湖", icon: "湖", color: "#3d92df", category: "青系" },
  { id: "oirase", name: "奥入瀬渓流", icon: "渓", color: "#3dbfd9", category: "青系" },
  { id: "barayaki", name: "十和田バラ焼き", icon: "焼", color: "#b35432", category: "赤茶系" },
  { id: "wagyu", name: "十和田湖和牛", icon: "牛", color: "#995342", category: "赤茶系" },
  { id: "pork", name: "ガーリックポーク", icon: "豚", color: "#bc6f54", category: "赤茶系" },
  { id: "garlic", name: "にんにく", icon: "蒜", color: "#58b57f", category: "緑系" },
  { id: "negi", name: "ねぎ", icon: "葱", color: "#5ac17c", category: "緑系" },
  { id: "gobo", name: "ごぼう", icon: "牛蒡", color: "#6fb464", category: "緑系" },
  { id: "nagaimo", name: "長いも", icon: "芋", color: "#95d57e", category: "緑系" },
  { id: "museum", name: "現代美術館", icon: "館", color: "#e6e6ec", category: "白・アート系", text: "#4b4b60" },
  { id: "street", name: "官庁街通り", icon: "並", color: "#7ea06e", category: "自然・並木系" },
  { id: "komakko", name: "駒っこランド", icon: "馬", color: "#d7a56b", category: "馬モチーフ系" },
  { id: "himemasu", name: "十和田湖ひめます", icon: "魚", color: "#5b88d8", category: "魚系" },
];

const comboWords = ["奥入瀬コンボ！", "バラ焼きボーナス！", "ひめます連鎖！", "とわだグルメ連撃！"];
const levelTitles = ["十和田湖エリアへ", "奥入瀬渓流エリアへ", "現代美術館エリアへ", "官庁街通りエリアへ", "駒っこランドエリアへ"];
const spotTexts = [
  "十和田湖：青く澄んだ湖面と季節の景観が魅力。",
  "奥入瀬渓流：水音と緑に包まれる癒やしの散策路。",
  "十和田市現代美術館：街に開かれた体験型アート空間。",
  "官庁街通り：桜並木が美しい十和田のシンボルロード。",
  "駒っこランド：馬とのふれあいを楽しめる人気スポット。",
];

let board = [];
let currentPair = null;
let nextPair = null;
let score = 0;
let level = 1;
let chain = 0;
let lastDrop = 0;
let dropInterval = 900;
let running = false;
let paused = false;
let gameOver = false;
let spotIndex = 0;
let animationFrameId = null;

function initLegend() {
  const seen = new Set();
  pieces.forEach((p) => {
    if (seen.has(p.category)) return;
    seen.add(p.category);
    const li = document.createElement("li");
    li.textContent = `${p.category}：${pieces
      .filter((x) => x.category === p.category)
      .map((x) => x.name)
      .join("・")}`;
    legend.appendChild(li);
  });
}

function createEmptyBoard() {
  return Array.from({ length: rows }, () => Array(cols).fill(null));
}

function randomPiece() {
  return pieces[Math.floor(Math.random() * pieces.length)];
}

function makePair() {
  return {
    x: Math.floor(cols / 2),
    y: 0,
    rotation: 0,
    a: randomPiece(),
    b: randomPiece(),
  };
}

function pairCells(pair) {
  const offsets = [
    { x: 0, y: -1 },
    { x: 1, y: 0 },
    { x: 0, y: 1 },
    { x: -1, y: 0 },
  ];
  const second = offsets[pair.rotation];
  return [
    { x: pair.x, y: pair.y, piece: pair.a },
    { x: pair.x + second.x, y: pair.y + second.y, piece: pair.b },
  ];
}

function canPlace(cells) {
  return cells.every((c) => c.x >= 0 && c.x < cols && c.y >= 0 && c.y < rows && !board[c.y][c.x]);
}

function spawnPair() {
  currentPair = nextPair || makePair();
  currentPair.x = Math.floor(cols / 2);
  currentPair.y = 1;
  currentPair.rotation = 0;
  nextPair = makePair();
  if (!canPlace(pairCells(currentPair))) {
    endGame();
  }
}

function lockPair() {
  pairCells(currentPair).forEach((c) => {
    if (c.y >= 0 && c.y < rows) board[c.y][c.x] = c.piece;
  });
  resolveBoard();
}

function resolveBoard() {
  let localChain = 0;
  while (true) {
    const matches = findMatches();
    if (matches.length === 0) break;
    localChain += 1;
    chain = localChain;
    chainEl.textContent = chain;
    matches.forEach(({ x, y }) => (board[y][x] = null));
    applyGravity();

    const gain = matches.length * 15 * localChain;
    score += gain;
    showOverlay(`${comboWords[(localChain - 1) % comboWords.length]}\n+${gain}点`, 700);
  }

  if (localChain > 0) {
    updateLevel();
    updateSpotMessage();
  }

  chain = 0;
  chainEl.textContent = "0";
  spawnPair();
}

function findMatches() {
  const visited = Array.from({ length: rows }, () => Array(cols).fill(false));
  const matches = [];

  for (let y = 0; y < rows; y += 1) {
    for (let x = 0; x < cols; x += 1) {
      if (!board[y][x] || visited[y][x]) continue;
      const group = floodFill(x, y, visited);
      if (group.length >= 3) matches.push(...group);
    }
  }
  return matches;
}

function floodFill(startX, startY, visited) {
  const start = board[startY][startX];
  const stack = [{ x: startX, y: startY }];
  const result = [];

  while (stack.length) {
    const { x, y } = stack.pop();
    if (x < 0 || x >= cols || y < 0 || y >= rows || visited[y][x]) continue;
    const cur = board[y][x];
    if (!cur) continue;
    const sameType = cur.id === start.id;
    const sameCategory = cur.category === start.category;
    if (!sameType && !sameCategory) continue;

    visited[y][x] = true;
    result.push({ x, y });

    stack.push({ x: x + 1, y });
    stack.push({ x: x - 1, y });
    stack.push({ x, y: y + 1 });
    stack.push({ x, y: y - 1 });
  }

  return result;
}

function applyGravity() {
  for (let x = 0; x < cols; x += 1) {
    let pointer = rows - 1;
    for (let y = rows - 1; y >= 0; y -= 1) {
      if (board[y][x]) {
        board[pointer][x] = board[y][x];
        if (pointer !== y) board[y][x] = null;
        pointer -= 1;
      }
    }
    for (let y = pointer; y >= 0; y -= 1) board[y][x] = null;
  }
}

function move(dx, dy) {
  if (!running || paused || gameOver) return;
  const draft = { ...currentPair, x: currentPair.x + dx, y: currentPair.y + dy };
  if (canPlace(pairCells(draft))) {
    currentPair = draft;
  } else if (dy === 1) {
    lockPair();
  }
}

function rotate() {
  if (!running || paused || gameOver) return;
  const draft = { ...currentPair, rotation: (currentPair.rotation + 1) % 4 };
  if (canPlace(pairCells(draft))) {
    currentPair = draft;
    return;
  }
  for (const kick of [-1, 1, -2, 2]) {
    const kicked = { ...draft, x: draft.x + kick };
    if (canPlace(pairCells(kicked))) {
      currentPair = kicked;
      return;
    }
  }
}

function updateLevel() {
  const newLevel = Math.min(10, Math.floor(score / 500) + 1);
  if (newLevel > level) {
    level = newLevel;
    dropInterval = Math.max(220, 900 - (level - 1) * 70);
    showOverlay(levelTitles[(level - 1) % levelTitles.length], 1000);
  }
  scoreEl.textContent = score;
  levelEl.textContent = level;
}

function updateSpotMessage() {
  const threshold = Math.floor(score / 700);
  if (threshold > spotIndex) {
    spotIndex = threshold;
    spotMessage.textContent = spotTexts[spotIndex % spotTexts.length];
  }
}

function showOverlay(text, ms = 900) {
  overlay.textContent = text;
  overlay.classList.remove("hidden");
  setTimeout(() => {
    if (!paused && !gameOver) overlay.classList.add("hidden");
  }, ms);
}

function drawCell(drawCtx, x, y, piece, size = cell) {
  drawCtx.fillStyle = piece.color;
  drawCtx.beginPath();
  drawCtx.roundRect(x + 2, y + 2, size - 4, size - 4, 10);
  drawCtx.fill();

  drawCtx.fillStyle = piece.text || "#ffffff";
  drawCtx.font = `${Math.floor(size * 0.36)}px sans-serif`;
  drawCtx.textAlign = "center";
  drawCtx.textBaseline = "middle";
  drawCtx.fillText(piece.icon, x + size / 2, y + size / 2 + 1);
}

function drawBoard() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  for (let y = 0; y < rows; y += 1) {
    for (let x = 0; x < cols; x += 1) {
      const px = x * cell;
      const py = y * cell;
      ctx.strokeStyle = "#ffffff44";
      ctx.strokeRect(px, py, cell, cell);
      if (board[y][x]) drawCell(ctx, px, py, board[y][x]);
    }
  }

  if (currentPair && !gameOver) {
    pairCells(currentPair).forEach((c) => {
      if (c.y >= 0) drawCell(ctx, c.x * cell, c.y * cell, c.piece);
    });
  }
}

function drawNext() {
  nextCtx.clearRect(0, 0, nextCanvas.width, nextCanvas.height);
  if (!nextPair) return;
  const mini = 52;
  drawCell(nextCtx, 48, 24, nextPair.a, mini);
  drawCell(nextCtx, 48, 82, nextPair.b, mini);
}

function loop(timestamp) {
  if (!running) return;
  if (!paused && !gameOver && timestamp - lastDrop > dropInterval) {
    move(0, 1);
    lastDrop = timestamp;
  }
  drawBoard();
  drawNext();
  animationFrameId = requestAnimationFrame(loop);
}

function startGame() {
  if (animationFrameId) {
    cancelAnimationFrame(animationFrameId);
    animationFrameId = null;
  }

  board = createEmptyBoard();
  score = 0;
  level = 1;
  chain = 0;
  spotIndex = 0;
  dropInterval = 900;
  paused = false;
  gameOver = false;
  running = true;
  lastDrop = 0;
  overlay.classList.add("hidden");
  pauseButton.textContent = "一時停止";
  scoreEl.textContent = "0";
  levelEl.textContent = "1";
  chainEl.textContent = "0";
  spotMessage.textContent = "まずはピースを消して観光情報を集めよう！";
  nextPair = makePair();
  spawnPair();
  animationFrameId = requestAnimationFrame(loop);
}

function togglePause() {
  if (!running || gameOver) return;
  paused = !paused;
  if (paused) {
    overlay.textContent = "一時停止中";
    overlay.classList.remove("hidden");
    pauseButton.textContent = "再開";
  } else {
    overlay.classList.add("hidden");
    pauseButton.textContent = "一時停止";
  }
}

function endGame() {
  gameOver = true;
  running = false;
  if (animationFrameId) {
    cancelAnimationFrame(animationFrameId);
    animationFrameId = null;
  }
  overlay.textContent = `ゲームオーバー\nスコア ${score}`;
  overlay.classList.remove("hidden");
}

document.addEventListener("keydown", (e) => {
  if (["ArrowLeft", "ArrowRight", "ArrowDown", "ArrowUp", " "].includes(e.key)) e.preventDefault();
  if (e.key === "ArrowLeft") move(-1, 0);
  if (e.key === "ArrowRight") move(1, 0);
  if (e.key === "ArrowDown") move(0, 1);
  if (e.key === "ArrowUp") rotate();
  if (e.key === " ") togglePause();
});

startButton.addEventListener("click", startGame);
pauseButton.addEventListener("click", togglePause);
restartButton.addEventListener("click", startGame);

board = createEmptyBoard();
initLegend();
drawBoard();
drawNext();
