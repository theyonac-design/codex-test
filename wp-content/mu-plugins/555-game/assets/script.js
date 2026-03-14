/*
 * 555-game MUプラグイン用スクリプト
 * shortcode [towakomyu_game] 内のDOMを初期化して実行。
 */

(function () {
  "use strict";

  const DEBUG = false;

  function logDebug(...args) {
    if (DEBUG) {
      console.log("[towakomyu-game]", ...args);
    }
  }

  function reportError(message, error, messageEl) {
    const text = `エラー: ${message}`;
    if (messageEl) {
      messageEl.textContent = `${text}。ページを再読み込みしてください。`;
    }
    console.error("[towakomyu-game]", message, error || "");
  }

  document.addEventListener("DOMContentLoaded", function () {
    const root = document.getElementById("towakomyu-game");
    if (!root) {
      return;
    }

    const canvas = document.getElementById("towakomyu-game-canvas");
    const startBtn = document.getElementById("towakomyu-game-start");
    const restartBtn = document.getElementById("towakomyu-game-restart");
    const leftBtn = document.getElementById("towakomyu-game-left");
    const rightBtn = document.getElementById("towakomyu-game-right");
    const rotateBtn = document.getElementById("towakomyu-game-rotate");
    const dropBtn = document.getElementById("towakomyu-game-drop");
    const scoreEl = document.getElementById("towakomyu-game-score");
    const highScoreEl = document.getElementById("towakomyu-game-high-score");
    const messageEl = document.getElementById("towakomyu-game-message");

    try {
      if (!canvas || !startBtn || !restartBtn || !scoreEl || !messageEl) {
        throw new Error("必要なDOM要素が不足しています");
      }

      const ctx = canvas.getContext("2d");
      if (!ctx) {
        throw new Error("Canvas描画コンテキストを取得できません");
      }

      const COLS = 6;
      const ROWS = 10;
      const CELL = 50;
      const WIDTH = COLS * CELL;
      const HEIGHT = ROWS * CELL;

      canvas.width = WIDTH;
      canvas.height = HEIGHT;

      const items = [
        { name: "十和田湖", icon: "湖", category: "青", color: "#3f93dc" },
        { name: "奥入瀬", icon: "渓", category: "青", color: "#38b3c8" },
        { name: "バラ焼き", icon: "焼", category: "赤", color: "#b65b3c" },
        { name: "和牛", icon: "牛", category: "赤", color: "#965543" },
        { name: "にんにく", icon: "蒜", category: "緑", color: "#62b26f" },
        { name: "ねぎ", icon: "葱", category: "緑", color: "#4cbc73" },
        { name: "現代美術館", icon: "館", category: "白", color: "#ececf4", text: "#525267" },
        { name: "駒っこランド", icon: "馬", category: "馬", color: "#dba66a" },
      ];

      const comboTexts = ["奥入瀬コンボ！", "バラ焼きボーナス！", "ひめます連鎖！"];

      let board = createBoard();
      let current = null;
      let score = 0;
      let highScore = 0;
      let running = false;
      let gameOver = false;
      let dropTimer = 0;
      let dropMs = 900;
      let rafId = null;

      function createBoard() {
        return Array.from({ length: ROWS }, function () {
          return Array(COLS).fill(null);
        });
      }

      function randomItem() {
        return items[Math.floor(Math.random() * items.length)];
      }

      function makePair() {
        return {
          x: Math.floor(COLS / 2),
          y: 1,
          rotation: 0,
          a: randomItem(),
          b: randomItem(),
        };
      }

      function pairCells(pair) {
        const o = [
          { x: 0, y: -1 },
          { x: 1, y: 0 },
          { x: 0, y: 1 },
          { x: -1, y: 0 },
        ][pair.rotation];

        return [
          { x: pair.x, y: pair.y, item: pair.a },
          { x: pair.x + o.x, y: pair.y + o.y, item: pair.b },
        ];
      }

      function canPlace(cells) {
        return cells.every(function (c) {
          return c.x >= 0 && c.x < COLS && c.y >= 0 && c.y < ROWS && !board[c.y][c.x];
        });
      }

      function spawnPair() {
        current = makePair();
        if (!canPlace(pairCells(current))) {
          finishGame();
        }
      }

      function drawCell(x, y, item) {
        const px = x * CELL;
        const py = y * CELL;
        const r = 10;

        ctx.fillStyle = item.color;
        ctx.beginPath();
        roundRect(ctx, px + 2, py + 2, CELL - 4, CELL - 4, r);
        ctx.fill();

        ctx.fillStyle = item.text || "#fff";
        ctx.font = "bold 20px sans-serif";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(item.icon, px + CELL / 2, py + CELL / 2 + 1);
      }

      function roundRect(context, x, y, w, h, r) {
        context.moveTo(x + r, y);
        context.lineTo(x + w - r, y);
        context.quadraticCurveTo(x + w, y, x + w, y + r);
        context.lineTo(x + w, y + h - r);
        context.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
        context.lineTo(x + r, y + h);
        context.quadraticCurveTo(x, y + h, x, y + h - r);
        context.lineTo(x, y + r);
        context.quadraticCurveTo(x, y, x + r, y);
        context.closePath();
      }

      function drawBoard() {
        ctx.clearRect(0, 0, WIDTH, HEIGHT);
        for (let y = 0; y < ROWS; y += 1) {
          for (let x = 0; x < COLS; x += 1) {
            ctx.strokeStyle = "rgba(255,255,255,0.5)";
            ctx.strokeRect(x * CELL, y * CELL, CELL, CELL);
            if (board[y][x]) {
              drawCell(x, y, board[y][x]);
            }
          }
        }
        if (current) {
          pairCells(current).forEach(function (c) {
            if (c.y >= 0) drawCell(c.x, c.y, c.item);
          });
        }
      }

      function setMessage(text) {
        messageEl.textContent = text;
      }

      function updateScore(add) {
        score += add;
        if (score > highScore) {
          highScore = score;
        }
        scoreEl.textContent = String(score);
        highScoreEl.textContent = String(highScore);
      }

      function move(dx, dy) {
        if (!running || gameOver || !current) return;
        const draft = {
          x: current.x + dx,
          y: current.y + dy,
          rotation: current.rotation,
          a: current.a,
          b: current.b,
        };
        if (canPlace(pairCells(draft))) {
          current = draft;
          return;
        }
        if (dy === 1) {
          lockPair();
        }
      }

      function rotate() {
        if (!running || gameOver || !current) return;
        const draft = {
          x: current.x,
          y: current.y,
          rotation: (current.rotation + 1) % 4,
          a: current.a,
          b: current.b,
        };
        if (canPlace(pairCells(draft))) {
          current = draft;
          return;
        }
        const kicks = [-1, 1];
        for (let i = 0; i < kicks.length; i += 1) {
          const kicked = { ...draft, x: draft.x + kicks[i] };
          if (canPlace(pairCells(kicked))) {
            current = kicked;
            return;
          }
        }
      }

      function lockPair() {
        pairCells(current).forEach(function (c) {
          if (c.y >= 0 && c.y < ROWS) {
            board[c.y][c.x] = c.item;
          }
        });
        current = null;
        resolveBoard();
        spawnPair();
      }

      function resolveBoard() {
        let chain = 0;
        while (true) {
          const matched = findMatches();
          if (matched.length === 0) break;

          chain += 1;
          matched.forEach(function (pos) {
            board[pos.y][pos.x] = null;
          });

          applyGravity();
          const gain = matched.length * 10 * chain;
          updateScore(gain);
          setMessage(`${comboTexts[(chain - 1) % comboTexts.length]} +${gain}`);
        }

        if (chain === 0) {
          setMessage("同じカテゴリを3つ以上つなげよう！");
        }
      }

      function findMatches() {
        const visited = Array.from({ length: ROWS }, function () {
          return Array(COLS).fill(false);
        });
        const out = [];

        for (let y = 0; y < ROWS; y += 1) {
          for (let x = 0; x < COLS; x += 1) {
            if (!board[y][x] || visited[y][x]) continue;
            const group = floodFill(x, y, visited);
            if (group.length >= 3) {
              out.push(...group);
            }
          }
        }
        return out;
      }

      function floodFill(startX, startY, visited) {
        const start = board[startY][startX];
        const stack = [{ x: startX, y: startY }];
        const result = [];

        while (stack.length) {
          const pos = stack.pop();
          const x = pos.x;
          const y = pos.y;

          if (x < 0 || x >= COLS || y < 0 || y >= ROWS || visited[y][x]) continue;
          const cur = board[y][x];
          if (!cur) continue;

          const sameType = cur.name === start.name;
          const sameCategory = cur.category === start.category;
          if (!sameType && !sameCategory) continue;

          visited[y][x] = true;
          result.push({ x: x, y: y });

          stack.push({ x: x + 1, y: y });
          stack.push({ x: x - 1, y: y });
          stack.push({ x: x, y: y + 1 });
          stack.push({ x: x, y: y - 1 });
        }

        return result;
      }

      function applyGravity() {
        for (let x = 0; x < COLS; x += 1) {
          let w = ROWS - 1;
          for (let y = ROWS - 1; y >= 0; y -= 1) {
            if (board[y][x]) {
              board[w][x] = board[y][x];
              if (w !== y) board[y][x] = null;
              w -= 1;
            }
          }
          for (let y = w; y >= 0; y -= 1) {
            board[y][x] = null;
          }
        }
      }

      function finishGame() {
        gameOver = true;
        running = false;
        if (rafId) {
          cancelAnimationFrame(rafId);
          rafId = null;
        }
        setMessage(`ゲームオーバー！ スコア: ${score} / リスタートして再挑戦しよう`);
      }

      function loop(now) {
        if (!running || gameOver) {
          drawBoard();
          return;
        }

        if (now - dropTimer >= dropMs) {
          move(0, 1);
          dropTimer = now;
        }

        drawBoard();
        rafId = requestAnimationFrame(loop);
      }

      function startGame() {
        if (rafId) {
          cancelAnimationFrame(rafId);
          rafId = null;
        }

        board = createBoard();
        current = null;
        score = 0;
        gameOver = false;
        running = true;
        dropTimer = 0;
        dropMs = 900;
        scoreEl.textContent = "0";
        setMessage("スタート！ 同じカテゴリを3つつなげよう。");
        spawnPair();
        drawBoard();
        rafId = requestAnimationFrame(loop);
      }

      function restartGame() {
        startGame();
      }

      startBtn.addEventListener("click", startGame);
      restartBtn.addEventListener("click", restartGame);
      leftBtn.addEventListener("click", function () {
        move(-1, 0);
        drawBoard();
      });
      rightBtn.addEventListener("click", function () {
        move(1, 0);
        drawBoard();
      });
      rotateBtn.addEventListener("click", function () {
        rotate();
        drawBoard();
      });
      dropBtn.addEventListener("click", function () {
        move(0, 1);
        drawBoard();
      });

      document.addEventListener("keydown", function (e) {
        if (!root.contains(document.activeElement) && document.activeElement !== document.body) {
          return;
        }
        if (e.key === "ArrowLeft") {
          e.preventDefault();
          move(-1, 0);
        } else if (e.key === "ArrowRight") {
          e.preventDefault();
          move(1, 0);
        } else if (e.key === "ArrowDown") {
          e.preventDefault();
          move(0, 1);
        } else if (e.key === "ArrowUp") {
          e.preventDefault();
          rotate();
        }
        drawBoard();
      });

      // スワイプ: 左右移動 / 上スワイプで回転 / 下スワイプで落下
      let touchStartX = 0;
      let touchStartY = 0;
      canvas.addEventListener(
        "touchstart",
        function (e) {
          if (!e.touches || !e.touches[0]) return;
          touchStartX = e.touches[0].clientX;
          touchStartY = e.touches[0].clientY;
        },
        { passive: true }
      );

      canvas.addEventListener(
        "touchend",
        function (e) {
          if (!e.changedTouches || !e.changedTouches[0]) return;
          const dx = e.changedTouches[0].clientX - touchStartX;
          const dy = e.changedTouches[0].clientY - touchStartY;
          const absX = Math.abs(dx);
          const absY = Math.abs(dy);
          const threshold = 20;

          if (absX < threshold && absY < threshold) {
            rotate();
          } else if (absX > absY) {
            if (dx > 0) move(1, 0);
            else move(-1, 0);
          } else {
            if (dy > 0) move(0, 1);
            else rotate();
          }
          drawBoard();
        },
        { passive: true }
      );

      logDebug("initialized", root.dataset.version || "no-version");
      drawBoard();
    } catch (error) {
      reportError("ゲーム初期化に失敗しました", error, messageEl);
    }
  });
})();
