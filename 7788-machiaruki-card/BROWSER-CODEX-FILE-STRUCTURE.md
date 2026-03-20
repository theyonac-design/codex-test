# BROWSER-CODEX FILE STRUCTURE

ブラウザ版 Codex へ 1 ファイルずつアップロードする際に、そのまま参照できる構成一覧です。

```text
7788-machiaruki-card/
├── 7788-machiaruki-card.php
├── SPEC.md
├── BROWSER-CODEX-FILE-STRUCTURE.md
├── BROWSER-CODEX-UPLOAD-ORDER.txt
├── assets/
│   ├── css/
│   │   └── machiaruki-card.css
│   ├── js/
│   │   └── machiaruki-card.js
│   └── img/
│       └── .gitkeep
├── includes/
│   ├── class-machiaruki-admin.php
│   ├── class-machiaruki-api.php
│   ├── class-machiaruki-map.php
│   ├── class-machiaruki-recommend.php
│   ├── class-machiaruki-render.php
│   ├── class-machiaruki-shortcodes.php
│   └── class-machiaruki-storage.php
└── templates/
    ├── action-buttons.php
    ├── card-empty.php
    ├── card-list.php
    ├── card-public-fallback.php
    ├── card-recommend.php
    ├── card-single.php
    └── card-slider.php
```

## 補足
- `assets/img/.gitkeep` は、Git 上で空ディレクトリを維持するための保持ファイルです。
- 実アップロード時に画像アセットが不要であれば、`img/` は空のままでも構いません。
- `SPEC.md` はブラウザ版 Codex 側で意図を共有するための補助資料です。
