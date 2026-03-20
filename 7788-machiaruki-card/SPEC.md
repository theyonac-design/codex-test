# 7788-machiaruki-card 仕様書

## 1. 概要
`7788-machiaruki-card` は、とわこみゅ向けの MU プラグインとして、観光・イベント・グルメ・店舗・ホテルなどの詳細ページからスポットを収集し、ユーザー自身の「まち歩きカード」を作成・保存・公開表示できる機能を提供します。

- プラグイン種別: MU プラグイン
- 主ファイル: `7788-machiaruki-card.php`
- 現在バージョン表記: `1.2.8`
- 想定環境: WordPress / Cloudflare Pro / WP Rocket / Elementor / JetEngine / PWA / Rank Math

## 2. 主な責務
本機能の責務は次のとおりです。

1. 各詳細ページに「まち歩きカードに追加」導線を提供する
2. ユーザーがスポットを並び替え、ルート名や説明を編集できる UI を提供する
3. 保存済みルートを公開カードとして一覧・個別・スライダー表示する
4. 位置情報から Google マップ導線を組み立てる
5. WordPress 管理画面上で公開カードを投稿タイプとして管理できるようにする

## 3. 対応投稿タイプ
初期状態では、以下の投稿タイプを対象とします。

- `tourist-spot`
- `events`
- `gourmet`
- `shops`
- `hotels`

補足:
- 対象投稿タイプはフィルターで変更可能です。
- `twkm_machiaruki_card` 自体は「追加対象」には含めません。

## 4. 保存・データの考え方
### 4-1. ルートデータ
ルート情報は、`twkm_machiaruki_card` 投稿タイプのメタ情報として保持されます。

主なメタキー:
- `_twkm_machiaruki_route_data`
- `_twkm_machiaruki_route_area`
- `_twkm_machiaruki_route_labels`
- `_twkm_machiaruki_route_duration`
- `_twkm_machiaruki_route_spot_count`

### 4-2. フロント側下書き
フロント側編集中のドラフトは、ローカル保存キー `twkmMachiarukiRouteDraft` を利用する前提です。

### 4-3. 位置情報
地図導線用の位置情報は投稿タイプごとのメタマップから取得し、以下を優先して扱います。

- Google Maps URL
- 住所
- 緯度経度
- 生の lat/lng 情報

位置情報が不足するスポットはカードには保持できますが、Google マップのルートには含まれない場合があります。

## 5. 公開 UI / ショートコード
本プラグインでは、以下のショートコードを提供します。

### 5-1. 追加ボタン
- `[twkm_machiaruki_add_button]`
  - 各詳細ページで「まち歩きカードに追加」ボタンのみを表示します。

### 5-2. アクションボタン
- `[twkm_action_buttons]`
  - 「この記事を保存」と「まち歩きカードに追加」を横並び表示します。
  - 例: `[twkm_action_buttons favorite_shortcode='[existing_favorite_button]']`

### 5-3. 編集 UI
- `[twkm_machiaruki_manager]`
  - まち歩きカードの編集・並び替え・削除・保存 UI を表示します。
- `[twkm_machiaruki_modal_trigger]`
  - 編集モーダルを開くボタンを表示します。
- `[twkm_machiaruki_modal]`
  - hidden 状態の編集モーダル本体を表示します。

### 5-4. 公開表示
- `[twkm_machiaruki_card route_id="123"]`
  - 公開済みの個別カードを表示します。
- `[twkm_machiaruki_cards]`
- `[twkm_machiaruki_cards limit="6" area="" category="" orderby="date" order="DESC" show_private="0"]`
  - 公開済みカードの一覧を表示します。
- `[twkm_machiaruki_slider]`
- `[twkm_machiaruki_slider limit="6" area="" category="" autoplay="true" loop="false"]`
  - 公開済みカードをスライダー表示します。

## 6. 配置ガイド
主ファイル記載の配置ガイドに基づく推奨配置は次のとおりです。

- 観光 / イベント / ホテル / グルメ / 店舗などの詳細テンプレート
  - `[twkm_action_buttons]`
- まち歩き作成ページ
  - `[twkm_machiaruki_manager]`
- 固定ページ「まち歩き」（slug: `machi-aruki`）
  - `[twkm_machiaruki_cards limit="12"]`
  - `[twkm_machiaruki_slider limit="6" autoplay="false" loop="false"]`
- トップ / 特集ページ
  - `[twkm_machiaruki_slider]`
- まち歩き一覧ページ
  - `[twkm_machiaruki_cards]`

補足:
- 編集 UI は PWA やアプリ内導線から開く前提であり、公開ページ本文へ常設しない方針です。

## 7. 管理画面仕様
管理画面では、以下を登録します。

### 7-1. カスタム投稿タイプ
- 投稿タイプ: `twkm_machiaruki_card`
- 公開: `true`
- REST 対応: `true`
- アーカイブ: `true`
- リライト slug: `machiaruki-card`

### 7-2. タクソノミー
- エリア: `twkm_machiaruki_area`
- ラベル: `twkm_machiaruki_category`

### 7-3. 管理一覧列
- エリア
- スポット数
- 所要時間

### 7-4. サイドメタボックス
- スポット数
- 所要時間
- エリア
- 利用ショートコード例

## 8. Ajax / フロント動作
### 8-1. Ajax アクション
- `twkm_machiaruki_picker`
  - スポット追加用ピッカー一覧を返します。
  - ログイン / 非ログイン双方に対応します。

### 8-2. フロント設定
スクリプトローカライズでは、次のような情報を JS に渡します。

- バージョン
- `admin-ajax.php` の URL
- Ajax アクション名
- 1ページあたり件数
- ローカル保存キー
- ログイン状態
- 管理者閲覧状態
- UI 用文言群

## 9. 地図導線仕様
地図導線は Google マップ URL を組み立てます。

- スポット 0 件: ルートなし
- スポット 1 件: 検索 URL またはスポット固有マップ URL
- スポット 2 件以上: origin / destination / waypoints を用いた徒歩ルート URL

補足:
- waypoint 数はフィルターで上限変更可能です。
- travelmode の初期値は `walking` です。

## 10. テンプレート責務
- `templates/action-buttons.php`
  - 保存導線と追加導線の横並び表示
- `templates/card-empty.php`
  - 空状態表示
- `templates/card-list.php`
  - 公開カード一覧表示
- `templates/card-public-fallback.php`
  - 公開カード未取得時のフォールバック表示
- `templates/card-recommend.php`
  - 関連カード表示
- `templates/card-single.php`
  - 個別カード表示
- `templates/card-slider.php`
  - スライダー表示

## 11. ディレクトリ構成
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

## 12. 注意事項
- Cloudflare / WP Rocket / PWA を前提とし、現在時刻依存や不要な動的描画を増やさない方針です。
- Elementor / Jet 系の再描画を壊さないよう、フロント JS は再初期化や遅延読込影響を考慮する必要があります。
- canonical / meta / JSON-LD の責務は本プラグインに寄せず、既存 SEO 構成との競合を避ける必要があります。
- 本資料は、現時点の実装ファイルから読み取れる範囲の要約です。実運用フローや外部導線の一部は不明です。
