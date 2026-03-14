<?php
/**
 * Plugin Name: 555 Towakomyu Game (MU)
 * Description: 十和田市テーマのミニ落ち物ゲームを [towakomyu_game] shortcode で表示する MU プラグイン。
 * Version: 1.0.0
 * Author: Towakomyu
 *
 * 設置方法:
 * 1) このファイルを /wp-content/mu-plugins/555-game/555-game.php に配置
 * 2) assets/style.css, assets/script.js も同階層に配置
 * 3) 固定ページや投稿本文に [towakomyu_game] を記載
 * 4) 管理画面で有効化は不要（MUプラグインのため自動読み込み）
 *
 * スマホ確認 (iPhone Safari):
 * - 固定ページを開き、開始ボタン → タップ操作で左右/回転/落下を確認
 * - スコア更新、ゲームオーバー表示、リスタート動作を確認
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('TOWAKOMYU_GAME_VERSION')) {
    define('TOWAKOMYU_GAME_VERSION', '1.0.0');
}

if (!function_exists('towakomyu_game_enqueue_assets')) {
    /**
     * ゲーム用のCSS/JSを読み込む。
     */
    function towakomyu_game_enqueue_assets()
    {
        $base_url = content_url('mu-plugins/555-game/assets');

        wp_enqueue_style(
            'towakomyu-game-style',
            $base_url . '/style.css',
            array(),
            TOWAKOMYU_GAME_VERSION
        );

        wp_enqueue_script(
            'towakomyu-game-script',
            $base_url . '/script.js',
            array(),
            TOWAKOMYU_GAME_VERSION,
            true
        );
    }
}

if (!function_exists('towakomyu_game_shortcode')) {
    /**
     * Shortcode: [towakomyu_game]
     * 主要DOM ID:
     * - #towakomyu-game
     * - #towakomyu-game-start
     * - #towakomyu-game-score
     * - #towakomyu-game-restart
     * - #towakomyu-game-message
     */
    function towakomyu_game_shortcode($atts = array(), $content = null)
    {
        towakomyu_game_enqueue_assets();

        $version = esc_attr(TOWAKOMYU_GAME_VERSION);

        ob_start();
        ?>
        <section id="towakomyu-game" class="towakomyu-game" data-version="<?php echo $version; ?>">
            <header class="towakomyu-game__header">
                <h2 class="towakomyu-game__title">とわこみゅ さくっとパズル</h2>
                <p class="towakomyu-game__lead">十和田の食・自然・アートのピースを3つ以上つなげて消そう！</p>
            </header>

            <div class="towakomyu-game__status" role="status" aria-live="polite">
                <div class="towakomyu-chip">スコア: <strong id="towakomyu-game-score">0</strong></div>
                <div class="towakomyu-chip">ハイスコア: <strong id="towakomyu-game-high-score">0</strong></div>
            </div>

            <div class="towakomyu-game__board-wrap">
                <canvas id="towakomyu-game-canvas" width="300" height="420" aria-label="とわこみゅゲーム盤面"></canvas>
            </div>

            <p id="towakomyu-game-message" class="towakomyu-game__message" aria-live="polite">
                「ゲーム開始」を押すとスタート。下のボタンで操作できます。
            </p>

            <div class="towakomyu-game__controls" aria-label="ゲーム操作ボタン">
                <button type="button" id="towakomyu-game-start" class="towakomyu-btn towakomyu-btn--primary">ゲーム開始</button>
                <button type="button" id="towakomyu-game-restart" class="towakomyu-btn">リスタート</button>
                <button type="button" id="towakomyu-game-left" class="towakomyu-btn">← 左</button>
                <button type="button" id="towakomyu-game-rotate" class="towakomyu-btn">↻ 回転</button>
                <button type="button" id="towakomyu-game-right" class="towakomyu-btn">右 →</button>
                <button type="button" id="towakomyu-game-drop" class="towakomyu-btn towakomyu-btn--accent">↓ すばやく落とす</button>
            </div>

            <details class="towakomyu-game__help">
                <summary>あそびかた</summary>
                <ul>
                    <li>上から落ちるピースを左右・回転して並べます。</li>
                    <li>同じカテゴリのピースが3つ以上つながると消えます。</li>
                    <li>上まで積み上がるとゲームオーバーです。</li>
                </ul>
            </details>
        </section>
        <?php
        return ob_get_clean();
    }
}

add_shortcode('towakomyu_game', 'towakomyu_game_shortcode');
