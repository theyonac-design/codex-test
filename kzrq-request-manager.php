<?php
/**
 * Plugin Name: KZRQ Request Manager
 * Description: 業務依頼・修正依頼の一元管理と外部共有機能 - kazumasa-yonaiyama.site 用
 * Version: 1.0.6
 *
 * ==============================================================================
 * 【仕様・使い方ガイド】
 * * 1. 概要
 * 業務依頼の受付、進捗管理、および外部（クライアント・代理店等）へのセキュアな進捗共有を行うプラグインです。
 * WP標準の投稿テーブルを汚さず、独自のカスタムテーブルを使用して軽量・堅牢に動作します。
 * * 2. 初期設定（必須）
 * プラグイン有効化後、管理画面の「固定ページ」から以下の2つのページを作成してください。
 * （※スラッグ名は「業務依頼管理」>「設定」画面で変更可能です）
 * * A. 依頼フォームページ
 * - スラッグ: request-form
 * - 本文: [kzrq_request_form]
 * * B. 相手確認用ページ
 * - スラッグ: request-board
 * - 本文: [kzrq_request_board]
 * * 3. 運用フロー
 * ① 【公開先管理】にて、進捗を共有したい相手（グループ・会社）を作成します。
 * => 作成すると、相手専用のシークレットURL（Token付き）が発行されます。
 * ② クライアントが「依頼フォームページ」から修正依頼等を送信します。
 * ③ 管理者は【依頼一覧】で案件を確認し、「詳細・編集」から対応内容やステータスを更新します。
 * ④ 案件を特定の「公開先」に紐付け、「相手確認用ページに表示する」をONにして保存します。
 * ⑤ クライアントは①の専用URLにアクセスすることで、自分に紐付いた案件のみを閲覧できます。
 * * 4. セキュリティと注意事項
 * - 内部メモ（internal_note）は管理者専用であり、相手側の画面には絶対に表示されません。
 * - トークン再発行を行うと、旧URLおよび対象者のログインセッション(Cookie)は直ちに無効化されます。
 * - Cloudflare や WP Rocket 等の強力なキャッシュ環境下でも、対象ページは自動的にキャッシュを
 * バイパス（DONOTCACHEPAGE等）し、情報の漏洩や二重送信を防ぐ設計になっています。
 * - ファイルアップロードは、設定された拡張子とWP標準のMIMEタイプ検証により厳重に保護されます。
 * ==============================================================================
 *
 * Update Log:
 * - 2026-03-19 : [v1.0.0] 新規作成 (要件定義ベース)
 * - 2026-03-19 : [v1.0.1] セキュリティ強化（noindexフック位置変更、PRG対応、検索追加等）
 * - 2026-03-19 : [v1.0.2] パスワードハッシュ化、Cookie署名強化、MIME制限強化
 * - 2026-03-19 : [v1.0.3] 対象サイト環境の表記修正
 * - 2026-03-19 : [v1.0.4] フォルダローダー構成を廃止し、直下配置用スタンドアロン構成に変更
 * - 2026-03-19 : [v1.0.5] メール検証順序修正、設定空値の安全なフォールバック、DB保存エラー判定追加
 * - 2026-03-19 : [v1.0.6] フォーム側メール検証改善、max_upload_mb下限補正、DB更新失敗判定、MIME厳密検証・通知追加
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( class_exists( 'KZRQ_Request_Manager' ) ) {
	return;
}

class KZRQ_Request_Manager {

	private $db_version = '1.0.6';
	private $table_requests;
	private $table_groups;
	private $form_errors = array();
	private $allowed_statuses = array( '新規受付', '確認中', '対応中', '確認依頼中', '返答待ち', '完了', '保留', '取消' );
	private $allowed_billings = array( '未定', '無料対応', '有料対応', '有料予定', '見積提出', '請求書発行予定', '承認待ち', '請求済み', '入金済み', '保留' );

	public function __construct() {
		global $wpdb;
		$this->table_requests = $wpdb->prefix . 'kzrq_requests';
		$this->table_groups   = $wpdb->prefix . 'kzrq_groups';

		// 初期化・フック登録
		add_action( 'init', array( $this, 'init_plugin' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		
		// キャッシュ・SEO・フォーム処理を早い段階で処理
		add_action( 'template_redirect', array( $this, 'handle_frontend_requests' ) );

		// ショートコード登録
		add_shortcode( 'kzrq_request_form', array( $this, 'shortcode_request_form' ) );
		add_shortcode( 'kzrq_request_board', array( $this, 'shortcode_request_board' ) );
	}

	/**
	 * DB初期化とバージョンチェック
	 */
	public function init_plugin() {
		$current_version = get_option( 'kzrq_db_version', '0.0.0' );
		if ( version_compare( $current_version, $this->db_version, '<' ) ) {
			$this->setup_database();
			update_option( 'kzrq_db_version', $this->db_version );
		}
	}

	private function setup_database() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql1 = "CREATE TABLE {$this->table_requests} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			receipt_no varchar(50) NOT NULL,
			public_group_id bigint(20) unsigned DEFAULT 0,
			requester_name varchar(100) NOT NULL,
			requester_contact varchar(100) DEFAULT '',
			requester_email varchar(100) NOT NULL,
			requester_company varchar(100) DEFAULT '',
			request_body text NOT NULL,
			attachment_url varchar(255) DEFAULT '',
			attachment_path varchar(255) DEFAULT '',
			status varchar(50) DEFAULT '新規受付',
			billing_type varchar(50) DEFAULT '未定',
			response_body text,
			response_url varchar(255) DEFAULT '',
			response_page_password varchar(100) DEFAULT '',
			internal_note text,
			public_flag tinyint(1) DEFAULT 0,
			ip_address varchar(50) DEFAULT '',
			user_agent text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY receipt_no (receipt_no),
			KEY public_group_id (public_group_id)
		) $charset_collate;";

		$sql2 = "CREATE TABLE {$this->table_groups} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			group_name varchar(100) NOT NULL,
			company_name varchar(100) DEFAULT '',
			contact_name varchar(100) DEFAULT '',
			email varchar(100) DEFAULT '',
			view_token varchar(64) NOT NULL,
			access_password varchar(255) DEFAULT '',
			note text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY view_token (view_token)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql1 );
		dbDelta( $sql2 );
	}

	/**
	 * 管理画面メニュー登録
	 */
	public function admin_menu() {
		add_menu_page( '業務依頼管理', '業務依頼管理', 'manage_options', 'kzrq-requests', array( $this, 'admin_page_requests' ), 'dashicons-clipboard', 25 );
		add_submenu_page( 'kzrq-requests', '依頼一覧', '依頼一覧', 'manage_options', 'kzrq-requests', array( $this, 'admin_page_requests' ) );
		add_submenu_page( 'kzrq-requests', '公開先管理', '公開先管理', 'manage_options', 'kzrq-groups', array( $this, 'admin_page_groups' ) );
		add_submenu_page( 'kzrq-requests', '設定', '設定', 'manage_options', 'kzrq-settings', array( $this, 'admin_page_settings' ) );
		add_submenu_page( null, '依頼詳細編集', '依頼詳細編集', 'manage_options', 'kzrq-request-edit', array( $this, 'admin_page_request_edit' ) );
	}

	/**
	 * 設定とサニタイズ登録
	 */
	public function register_settings() {
		register_setting( 'kzrq_options_group', 'kzrq_settings', array( $this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		
		$input['board_noindex']    = ! empty( $input['board_noindex'] ) ? '1' : '0';
		$input['require_password'] = ! empty( $input['require_password'] ) ? '1' : '0';
		$input['notify_admin']     = ! empty( $input['notify_admin'] ) ? '1' : '0';
		
		$input['form_slug']  = sanitize_title( $input['form_slug'] ?? 'request-form' );
		$input['board_slug'] = sanitize_title( $input['board_slug'] ?? 'request-board' );

		if ( '' === $input['form_slug'] ) {
			$input['form_slug'] = 'request-form';
			add_settings_error( 'kzrq_options_group', 'invalid_form_slug', 'フォームURLスラッグが空または不正なため、デフォルト値に戻しました。' );
		}

		if ( '' === $input['board_slug'] ) {
			$input['board_slug'] = 'request-board';
			add_settings_error( 'kzrq_options_group', 'invalid_board_slug', '確認ページURLスラッグが空または不正なため、デフォルト値に戻しました。' );
		}

		if ( $input['form_slug'] === $input['board_slug'] ) {
			$input['board_slug'] = 'request-board' === $input['form_slug'] ? 'request-board-view' : 'request-board';
			add_settings_error( 'kzrq_options_group', 'duplicate_slug', 'フォームURLスラッグと確認ページURLスラッグが重複していたため、確認ページURLスラッグを安全な値に補正しました。' );
		}
		// 下限を1MBに丸める
		$input['max_upload_mb'] = max( 1, intval( $input['max_upload_mb'] ?? 5 ) );

		// 通知メールアドレスの妥当性確認とフォールバック
		$raw_notify = trim( (string) ( $input['notify_email'] ?? '' ) );
		if ( $raw_notify !== '' && ! is_email( $raw_notify ) ) {
			add_settings_error( 'kzrq_options_group', 'invalid_email', '通知先メールアドレスが不正な形式のため、管理者メールアドレスに戻しました。' );
			$input['notify_email'] = get_option( 'admin_email' );
		} else {
			$input['notify_email'] = $raw_notify === '' ? get_option( 'admin_email' ) : sanitize_email( $raw_notify );
		}

		// 許可拡張子の安全な処理（WPのMIMEマップに存在するものだけに絞り込む）
		$exts = strtolower( (string) ( $input['allowed_exts'] ?? '' ) );
		$ext_array = array_filter( array_map( 'trim', explode( ',', $exts ) ) );
		$ext_array = array_unique( $ext_array );
		
		$wp_mimes = get_allowed_mime_types();
		$valid_exts = array();
		foreach ( $wp_mimes as $ext_pattern => $mime ) {
			$valid_exts = array_merge( $valid_exts, explode( '|', strtolower( $ext_pattern ) ) );
		}
		
		$final_exts = array();
		foreach ( $ext_array as $e ) {
			if ( in_array( $e, $valid_exts, true ) ) {
				$final_exts[] = $e;
			}
		}

		if ( empty( $final_exts ) ) {
			add_settings_error( 'kzrq_options_group', 'invalid_ext', '指定された拡張子がシステムで許可されていない、または空のため、デフォルト設定に戻しました。' );
			$input['allowed_exts'] = 'jpg,jpeg,png,pdf,zip';
		} else {
			$input['allowed_exts'] = implode( ',', $final_exts );
		}

		return $input;
	}

	private function get_settings() {
		$defaults = array(
			'form_slug'        => 'request-form',
			'board_slug'       => 'request-board',
			'notify_admin'     => '1',
			'notify_email'     => get_option('admin_email'),
			'allowed_exts'     => 'jpg,jpeg,png,pdf,zip',
			'max_upload_mb'    => 5,
			'board_noindex'    => '1',
			'require_password' => '0',
		);
		return wp_parse_args( get_option( 'kzrq_settings', array() ), $defaults );
	}

	private function get_client_ip() {
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$ip = trim( $ips[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	private function generate_receipt_no() {
		global $wpdb;

		for ( $i = 0; $i < 5; $i++ ) {
			$candidate = gmdate( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 6, false, false ) );
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_requests} WHERE receipt_no = %s", $candidate ) );

			if ( 0 === $exists ) {
				return $candidate;
			}
		}

		return '';
	}

	private function sanitize_select_value( $value, $allowed_values, $default ) {
		$value = sanitize_text_field( wp_unslash( (string) $value ) );

		return in_array( $value, $allowed_values, true ) ? $value : $default;
	}

	private function is_valid_group_id( $group_id ) {
		global $wpdb;

		if ( $group_id <= 0 ) {
			return false;
		}

		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_groups} WHERE id = %d", $group_id ) );

		return $count > 0;
	}

	/**
	 * フロントエンド事前処理
	 */
	public function handle_frontend_requests() {
		$settings = $this->get_settings();

		if ( is_page( $settings['form_slug'] ) || is_page( $settings['board_slug'] ) ) {
			nocache_headers();
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
		}

		if ( is_page( $settings['board_slug'] ) && $settings['board_noindex'] === '1' ) {
			add_filter( 'wp_robots', function( $robots ) {
				$robots['noindex'] = true;
				$robots['nofollow'] = true;
				return $robots;
			} );
		}

		if ( is_page( $settings['form_slug'] ) && isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['kzrq_action'] ) && $_POST['kzrq_action'] === 'submit_request' ) {
			$this->process_form_submission( $settings );
		}

		if ( is_page( $settings['board_slug'] ) && isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['kzrq_action'] ) && $_POST['kzrq_action'] === 'board_auth' ) {
			$this->process_board_auth();
		}
	}

	/**
	 * フォーム送信処理
	 */
	private function process_form_submission( $settings ) {
		if ( ! isset( $_POST['kzrq_nonce'] ) || ! wp_verify_nonce( $_POST['kzrq_nonce'], 'kzrq_submit_request' ) ) {
			$this->form_errors[] = 'セッションが切れました。ページを更新して再送信してください。';
			return;
		}

		// 生データの取得と形式チェック
		$raw_req_email = trim( (string) wp_unslash( $_POST['req_email'] ?? '' ) );
		$req_name      = sanitize_text_field( wp_unslash( $_POST['req_name'] ?? '' ) );
		$req_company   = sanitize_text_field( wp_unslash( $_POST['req_company'] ?? '' ) );
		$req_contact   = sanitize_text_field( wp_unslash( $_POST['req_contact'] ?? '' ) );
		$req_body      = sanitize_textarea_field( wp_unslash( $_POST['req_body'] ?? '' ) );

		if ( empty( $req_name ) || $raw_req_email === '' || empty( $req_body ) ) {
			$this->form_errors[] = '必須項目が入力されていません。';
		} elseif ( ! is_email( $raw_req_email ) ) {
			$this->form_errors[] = 'メールアドレスの形式が正しくありません。';
		}

		if ( ! empty( $this->form_errors ) ) {
			return; // エラーがあればここで終了
		}

		// エラーがなければサニタイズを適用
		$req_email = sanitize_email( $raw_req_email );
		$attach_url = '';
		$attach_path = '';

		if ( ! empty( $_FILES['req_file']['name'] ) ) {
			$file = $_FILES['req_file'];
			$allowed_exts_array = array_map( 'trim', explode( ',', $settings['allowed_exts'] ) );
			$max_size = intval( $settings['max_upload_mb'] ) * 1024 * 1024;

			if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
				$this->form_errors[] = 'ファイルアップロードに失敗しました。時間をおいて再度お試しください。';
			} elseif ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
				$this->form_errors[] = 'アップロードファイルを確認できませんでした。';
			} else {
				$file_data = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
			
				if ( ! $file_data['ext'] || ! $file_data['type'] || ! in_array( strtolower( $file_data['ext'] ), $allowed_exts_array, true ) ) {
					$this->form_errors[] = '許可されていないファイル形式、またはファイルが偽装されています。';
				} elseif ( $file['size'] > $max_size ) {
					$this->form_errors[] = 'ファイルサイズが大きすぎます。';
				} else {
					$wp_mimes = get_allowed_mime_types();
					$mime_map = array();
					foreach ( $wp_mimes as $ext_pattern => $mime ) {
						$exts = explode( '|', $ext_pattern );
						foreach ( $exts as $e ) {
							if ( in_array( strtolower( $e ), $allowed_exts_array, true ) ) {
								$mime_map[ $ext_pattern ] = $mime;
								break;
							}
						}
					}

					require_once( ABSPATH . 'wp-admin/includes/file.php' );
				$upload_overrides = array( 
					'test_form' => false,
					'mimes'     => $mime_map
				);
				$movefile = wp_handle_upload( $file, $upload_overrides );
				
					if ( is_array( $movefile ) && ! isset( $movefile['error'] ) ) {
						$attach_url = $movefile['url'];
						$attach_path = $movefile['file'];
					} else {
						$error_message = is_array( $movefile ) && isset( $movefile['error'] ) ? $movefile['error'] : '不明なエラー';
						$this->form_errors[] = 'ファイルアップロードに失敗しました: ' . esc_html( $error_message );
					}
				}
			}
		}

		if ( empty( $this->form_errors ) ) {
			global $wpdb;
			$receipt_no = $this->generate_receipt_no();

			if ( '' === $receipt_no ) {
				$this->form_errors[] = '受付番号の採番に失敗しました。時間をおいて再度お試しください。';
				return;
			}
			
			$inserted = $wpdb->insert(
				$this->table_requests,
				array(
					'receipt_no'        => $receipt_no,
					'requester_name'    => $req_name,
					'requester_contact' => $req_contact,
					'requester_email'   => $req_email,
					'requester_company' => $req_company,
					'request_body'      => $req_body,
					'attachment_url'    => $attach_url,
					'attachment_path'   => $attach_path,
					'ip_address'        => $this->get_client_ip(),
					'user_agent'        => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' )
				),
				array( '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' )
			);

			if ( false === $inserted ) {
				$this->form_errors[] = '保存に失敗しました。時間をおいて再度お試しください。';
				return;
			}

			if ( $settings['notify_admin'] === '1' ) {
				$subj = "【システム通知】新規業務依頼を受付しました [{$receipt_no}]";
				$body = "新規依頼を受付ました。\n\n受付番号: {$receipt_no}\nお名前: {$req_name}\n会社名: {$req_company}\nメール: {$req_email}\n\n【依頼内容】\n{$req_body}\n\n管理画面より確認してください。";
				wp_mail( $settings['notify_email'], $subj, $body );
			}

			$redirect_url = add_query_arg( array( 'kzrq_submitted' => '1', 'receipt' => $receipt_no ), remove_query_arg( array('kzrq_action', 'kzrq_nonce') ) );
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * 確認ページ パスワード認証処理
	 */
	private function process_board_auth() {
		global $wpdb;
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$password = isset( $_POST['access_password'] ) ? sanitize_text_field( wp_unslash( $_POST['access_password'] ) ) : '';
		
		if ( ! isset( $_POST['kzrq_auth_nonce'] ) || ! wp_verify_nonce( $_POST['kzrq_auth_nonce'], 'kzrq_board_auth' ) ) {
			return;
		}

		$group = $wpdb->get_row( $wpdb->prepare( "SELECT id, view_token, access_password FROM {$this->table_groups} WHERE view_token = %s", $token ) );
		
		if ( $group && ! empty( $group->access_password ) && wp_check_password( $password, $group->access_password ) ) {
			$cookie_name = 'kzrq_auth_' . $group->id;
			$cookie_value = hash_hmac( 'sha256', $group->id . '|' . $group->view_token, wp_salt( 'auth' ) );
			
			if ( PHP_VERSION_ID >= 70300 ) {
				setcookie( $cookie_name, $cookie_value, array(
					'expires'  => 0,
					'path'     => COOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax'
				) );
			} else {
				setcookie( $cookie_name, $cookie_value, 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			}

			$redirect_url = add_query_arg( 'token', $token, remove_query_arg( array('kzrq_action', 'kzrq_auth_nonce', 'access_password') ) );
			wp_safe_redirect( $redirect_url );
			exit;
		} else {
			$this->form_errors[] = 'パスワードが間違っています。';
		}
	}


	/**
	 * 【フロント】依頼フォーム ショートコード
	 */
	public function shortcode_request_form() {
		$settings = $this->get_settings();

		if ( isset( $_GET['kzrq_submitted'] ) && '1' === wp_unslash( $_GET['kzrq_submitted'] ) ) {
			$receipt = sanitize_text_field( wp_unslash( $_GET['receipt'] ?? '' ) );
			$html = '<div style="padding:15px; background:#eef9f0; border:1px solid #c3e6cb; color:#155724; margin-bottom:20px;">';
			$html .= '<strong>送信が完了しました。</strong><br>受付番号: ' . esc_html( $receipt );
			$html .= '</div>';
			$html .= '<p><a href="' . esc_url( remove_query_arg( array('kzrq_submitted', 'receipt') ) ) . '">続けて入力する</a></p>';
			return $html;
		}

		ob_start();
		?>
		<style>
			.kzrq-form-group { margin-bottom: 15px; }
			.kzrq-form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
			.kzrq-form-group input[type="text"], .kzrq-form-group input[type="email"], .kzrq-form-group textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
			.kzrq-required { color: red; font-size: 0.8em; margin-left: 5px; }
			.kzrq-submit-btn { background: #0073aa; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
			.kzrq-submit-btn:hover { background: #005177; }
			.kzrq-error { color: red; margin-bottom: 15px; padding: 10px; border: 1px solid red; background: #fff3f3; }
		</style>
		<div class="kzrq-form-wrapper">
			<?php if ( ! empty( $this->form_errors ) ) : ?>
				<div class="kzrq-error">
					<?php foreach ( $this->form_errors as $err ) echo esc_html( $err ) . '<br>'; ?>
				</div>
			<?php endif; ?>

			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'kzrq_submit_request', 'kzrq_nonce' ); ?>
				<input type="hidden" name="kzrq_action" value="submit_request">
				
				<div class="kzrq-form-group">
					<label>お名前 <span class="kzrq-required">必須</span></label>
					<input type="text" name="req_name" required value="<?php echo esc_attr( wp_unslash( $_POST['req_name'] ?? '' ) ); ?>">
				</div>
				<div class="kzrq-form-group">
					<label>会社名</label>
					<input type="text" name="req_company" value="<?php echo esc_attr( wp_unslash( $_POST['req_company'] ?? '' ) ); ?>">
				</div>
				<div class="kzrq-form-group">
					<label>メールアドレス <span class="kzrq-required">必須</span></label>
					<input type="email" name="req_email" required value="<?php echo esc_attr( wp_unslash( $_POST['req_email'] ?? '' ) ); ?>">
				</div>
				<div class="kzrq-form-group">
					<label>連絡先（電話等）</label>
					<input type="text" name="req_contact" value="<?php echo esc_attr( wp_unslash( $_POST['req_contact'] ?? '' ) ); ?>">
				</div>
				<div class="kzrq-form-group">
					<label>依頼内容 <span class="kzrq-required">必須</span></label>
					<textarea name="req_body" rows="6" required><?php echo esc_textarea( wp_unslash( $_POST['req_body'] ?? '' ) ); ?></textarea>
				</div>
				<div class="kzrq-form-group">
					<label>添付ファイル (最大 <?php echo esc_html($settings['max_upload_mb']); ?>MB / <?php echo esc_html($settings['allowed_exts']); ?>)</label>
					<input type="file" name="req_file">
				</div>
				<div class="kzrq-form-group">
					<button type="submit" class="kzrq-submit-btn">依頼を送信する</button>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * 【フロント】相手確認用ページ ショートコード
	 */
	public function shortcode_request_board() {
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		if ( empty( $token ) ) {
			return '<p style="color:red;">無効なアクセスです。(Token missing)</p>';
		}

		global $wpdb;
		$group = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_groups} WHERE view_token = %s", $token ) );
		if ( ! $group ) {
			return '<p style="color:red;">無効または期限切れのURLです。</p>';
		}

		$settings = $this->get_settings();

		if ( $settings['require_password'] === '1' && ! empty( $group->access_password ) ) {
			$cookie_name = 'kzrq_auth_' . $group->id;
			$expected_hmac = hash_hmac( 'sha256', $group->id . '|' . $group->view_token, wp_salt( 'auth' ) );
			$cookie_value = isset( $_COOKIE[ $cookie_name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) ) : '';
			$is_authenticated = '' !== $cookie_value && hash_equals( $expected_hmac, $cookie_value );

			if ( ! $is_authenticated ) {
				ob_start();
				?>
				<div style="max-width:400px; margin:0 auto; padding:20px; border:1px solid #ccc; border-radius:8px; background:#fff;">
					<h3 style="margin-top:0;">パスワード認証</h3>
					<?php if ( ! empty( $this->form_errors ) ) : ?>
						<p style="color:red;"><?php echo esc_html( $this->form_errors[0] ); ?></p>
					<?php endif; ?>
					<form method="post" action="">
						<?php wp_nonce_field( 'kzrq_board_auth', 'kzrq_auth_nonce' ); ?>
						<input type="hidden" name="kzrq_action" value="board_auth">
						<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
						<p><input type="password" name="access_password" style="width:100%; padding:8px;" placeholder="パスワードを入力" required></p>
						<p><button type="submit" style="background:#0073aa; color:#fff; padding:10px; border:none; width:100%; border-radius:4px;">閲覧する</button></p>
					</form>
				</div>
				<?php
				return ob_get_clean();
			}
		}

		$requests = $wpdb->get_results( $wpdb->prepare( 
			"SELECT * FROM {$this->table_requests} WHERE public_group_id = %d AND public_flag = 1 ORDER BY created_at DESC", 
			$group->id 
		) );

		ob_start();
		?>
		<style>
			.kzrq-board-wrap { max-width: 800px; margin: 0 auto; }
			.kzrq-card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 20px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
			.kzrq-card-head { display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px; flex-wrap: wrap; gap:10px; }
			.kzrq-status { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; background: #f0f0f1; color: #3c434a; font-weight:bold; }
			.kzrq-status.status-完了 { background: #d1e7dd; color: #0f5132; }
			.kzrq-status.status-対応中 { background: #cff4fc; color: #055160; }
			.kzrq-item { margin-bottom: 10px; }
			.kzrq-label { font-size: 0.85em; color: #666; display: block; }
			.kzrq-response-area { background: #f8f9fa; padding: 10px; border-left: 4px solid #0073aa; margin-top: 15px; }
		</style>
		<div class="kzrq-board-wrap">
			<h2><?php echo esc_html( $group->company_name . ' ' . $group->contact_name ); ?> 様 案件進捗確認ページ</h2>
			<p>現在の進行状況をご確認いただけます。</p>
			
			<?php if ( empty( $requests ) ) : ?>
				<p>現在表示可能な案件履歴はありません。</p>
			<?php else : ?>
				<?php foreach ( $requests as $req ) : ?>
					<div class="kzrq-card">
						<div class="kzrq-card-head">
							<div>
								<span class="kzrq-label">受付日時: <?php echo esc_html( wp_date( 'Y/m/d H:i', strtotime( $req->created_at ) ) ); ?></span>
								<strong>NO: <?php echo esc_html( $req->receipt_no ); ?></strong>
							</div>
							<div>
								<span class="kzrq-status status-<?php echo esc_attr( $req->status ); ?>"><?php echo esc_html( $req->status ); ?></span>
								<span class="kzrq-status"><?php echo esc_html( $req->billing_type ); ?></span>
							</div>
						</div>
						
						<div class="kzrq-item">
							<span class="kzrq-label">依頼内容</span>
							<div><?php echo nl2br( esc_html( $req->request_body ) ); ?></div>
						</div>

						<?php if ( ! empty( $req->response_body ) || ! empty( $req->response_url ) ) : ?>
							<div class="kzrq-response-area">
								<span class="kzrq-label">当方からの対応・回答</span>
								<?php if ( ! empty( $req->response_body ) ) : ?>
									<div style="margin-bottom:10px;"><?php echo nl2br( esc_html( $req->response_body ) ); ?></div>
								<?php endif; ?>
								
								<?php if ( ! empty( $req->response_url ) ) : ?>
									<div class="kzrq-item">
										<span class="kzrq-label">確認用URL</span>
										<a href="<?php echo esc_url( $req->response_url ); ?>" target="_blank" rel="noopener"><?php echo esc_url( $req->response_url ); ?></a>
									</div>
									<?php if ( ! empty( $req->response_page_password ) ) : ?>
										<div class="kzrq-item">
											<span class="kzrq-label">確認ページ閲覧用パスワード</span>
											<code><?php echo esc_html( $req->response_page_password ); ?></code>
										</div>
									<?php endif; ?>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * 【管理画面】依頼一覧
	 */
	public function admin_page_requests() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '権限がありません' );
		global $wpdb;

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) && $_POST['action'] === 'delete_request' ) {
			if ( isset( $_POST['id'], $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'kzrq_delete_request_' . $_POST['id'] ) ) {
				$deleted = $wpdb->delete( $this->table_requests, array( 'id' => intval( wp_unslash( $_POST['id'] ) ) ), array( '%d' ) );
				if ( false === $deleted ) {
					echo '<div class="error"><p>削除に失敗しました。</p></div>';
				} else {
					echo '<div class="updated"><p>削除しました。</p></div>';
				}
			}
		}

		$where = "WHERE 1=1";
		$bindings = array();

		if ( ! empty( $_GET['s'] ) ) {
			$s = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) . '%';
			$where .= " AND (requester_name LIKE %s OR requester_company LIKE %s OR requester_email LIKE %s OR request_body LIKE %s)";
			array_push( $bindings, $s, $s, $s, $s );
		}
		if ( ! empty( $_GET['filter_status'] ) ) {
			$where .= " AND status = %s";
			$bindings[] = sanitize_text_field( wp_unslash( $_GET['filter_status'] ) );
		}
		if ( ! empty( $_GET['filter_billing'] ) ) {
			$where .= " AND billing_type = %s";
			$bindings[] = sanitize_text_field( wp_unslash( $_GET['filter_billing'] ) );
		}
		if ( isset( $_GET['filter_group'] ) && $_GET['filter_group'] !== '' ) {
			$where .= " AND public_group_id = %d";
			$bindings[] = intval( wp_unslash( $_GET['filter_group'] ) );
		}
		if ( ! empty( $_GET['filter_public_only'] ) ) {
			$where .= " AND public_flag = 1";
		}

		$sql = "SELECT * FROM {$this->table_requests} $where ORDER BY created_at DESC LIMIT 100";
		if ( ! empty( $bindings ) ) {
			$sql = $wpdb->prepare( $sql, $bindings );
		}
		$items = $wpdb->get_results( $sql );

		$groups = $wpdb->get_results( "SELECT id, group_name, company_name FROM {$this->table_groups} ORDER BY id ASC" );
		$statuses = $this->allowed_statuses;
		$billings = $this->allowed_billings;

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">業務依頼一覧</h1>
			<hr class="wp-header-end">

			<form method="get" style="background:#fff; padding:15px; margin-bottom:15px; border:1px solid #ccd0d4; display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
				<input type="hidden" name="page" value="kzrq-requests">
				<div><label>キーワード</label><br><input type="search" name="s" value="<?php echo esc_attr( wp_unslash( $_GET['s'] ?? '' ) ); ?>"></div>
				<div><label>ステータス</label><br>
					<select name="filter_status">
						<option value="">--すべて--</option>
						<?php foreach ( $statuses as $st ) echo '<option value="'.esc_attr($st).'" '.selected( wp_unslash( $_GET['filter_status'] ?? '' ), $st, false ).'>'.esc_html($st).'</option>'; ?>
					</select>
				</div>
				<div><label>料金区分</label><br>
					<select name="filter_billing">
						<option value="">--すべて--</option>
						<?php foreach ( $billings as $bt ) echo '<option value="'.esc_attr($bt).'" '.selected( wp_unslash( $_GET['filter_billing'] ?? '' ), $bt, false ).'>'.esc_html($bt).'</option>'; ?>
					</select>
				</div>
				<div><label>公開先</label><br>
					<select name="filter_group">
						<option value="">--すべて--</option>
						<option value="0" <?php selected( wp_unslash( $_GET['filter_group'] ?? '' ), '0' ); ?>>未設定</option>
						<?php foreach ( $groups as $g ) echo '<option value="'.esc_attr($g->id).'" '.selected( wp_unslash( $_GET['filter_group'] ?? '' ), $g->id, false ).'>'.esc_html($g->company_name).'</option>'; ?>
					</select>
				</div>
				<div><label><input type="checkbox" name="filter_public_only" value="1" <?php checked( wp_unslash( $_GET['filter_public_only'] ?? '' ), '1' ); ?>> 公開中のみ</label></div>
				<div><input type="submit" class="button" value="検索・絞り込み"></div>
				<?php if(!empty($_GET['s'])||!empty($_GET['filter_status'])||!empty($_GET['filter_billing'])||isset($_GET['filter_group'])): ?>
					<div><a href="?page=kzrq-requests" class="button">クリア</a></div>
				<?php endif; ?>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:50px;">ID</th>
						<th style="width:120px;">受付番号</th>
						<th style="width:120px;">日時</th>
						<th>依頼者/会社</th>
						<th>要約</th>
						<th>ステータス</th>
						<th>料金区分</th>
						<th>公開</th>
						<th style="width:150px;">操作</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $items ) ) : ?>
						<tr><td colspan="9">データがありません。</td></tr>
					<?php else : ?>
						<?php foreach ( $items as $item ) : ?>
							<tr>
								<td><?php echo intval( $item->id ); ?></td>
								<td><?php echo esc_html( $item->receipt_no ); ?></td>
								<td><?php echo esc_html( wp_date( 'Y/m/d H:i', strtotime( $item->created_at ) ) ); ?></td>
								<td>
									<?php echo esc_html( $item->requester_name ); ?><br>
									<small><?php echo esc_html( $item->requester_company ); ?></small>
								</td>
								<td><?php echo esc_html( wp_trim_words( $item->request_body, 15, '...' ) ); ?></td>
								<td><?php echo esc_html( $item->status ); ?></td>
								<td><?php echo esc_html( $item->billing_type ); ?></td>
								<td><?php echo $item->public_flag ? '<span style="color:green;font-weight:bold;">公開</span>' : '非公開'; ?></td>
								<td>
									<div style="display:flex; gap:5px;">
										<a href="?page=kzrq-request-edit&id=<?php echo $item->id; ?>" class="button button-small">編集</a>
										<form method="post" action="" onsubmit="return confirm('本当に削除しますか？');">
											<?php wp_nonce_field( 'kzrq_delete_request_' . $item->id ); ?>
											<input type="hidden" name="action" value="delete_request">
											<input type="hidden" name="id" value="<?php echo $item->id; ?>">
											<button type="submit" class="button button-small" style="color:red; border-color:red;">削除</button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * 【管理画面】依頼詳細・編集
	 */
	public function admin_page_request_edit() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '権限がありません' );
		global $wpdb;

		$id = isset( $_GET['id'] ) ? intval( wp_unslash( $_GET['id'] ) ) : 0;
		if ( ! $id ) wp_die( '不正なアクセスです' );

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['kzrq_edit_nonce'] ) && wp_verify_nonce( $_POST['kzrq_edit_nonce'], 'kzrq_edit_action' ) ) {
			$status          = $this->sanitize_select_value( $_POST['status'] ?? '', $this->allowed_statuses, '新規受付' );
			$billing_type    = $this->sanitize_select_value( $_POST['billing_type'] ?? '', $this->allowed_billings, '未定' );
			$public_group_id = isset( $_POST['public_group_id'] ) ? intval( wp_unslash( $_POST['public_group_id'] ) ) : 0;
			$public_flag     = isset( $_POST['public_flag'] ) ? 1 : 0;

			if ( $public_group_id > 0 && ! $this->is_valid_group_id( $public_group_id ) ) {
				echo '<div class="error"><p>指定された公開グループが無効なため、公開設定を解除しました。</p></div>';
				$public_flag = 0;
				$public_group_id = 0;
			}

			if ( $public_flag && 0 === $public_group_id ) {
				echo '<div class="error"><p>公開する場合は、有効な公開グループを選択してください。</p></div>';
				$public_flag = 0;
			}

			$updated = $wpdb->update(
				$this->table_requests,
				array(
					'status'                 => $status,
					'billing_type'           => $billing_type,
					'response_body'          => sanitize_textarea_field( wp_unslash( $_POST['response_body'] ?? '' ) ),
					'response_url'           => esc_url_raw( wp_unslash( $_POST['response_url'] ?? '' ) ),
					'response_page_password' => sanitize_text_field( wp_unslash( $_POST['response_page_password'] ?? '' ) ),
					'internal_note'          => sanitize_textarea_field( wp_unslash( $_POST['internal_note'] ?? '' ) ),
					'public_flag'            => $public_flag,
					'public_group_id'        => $public_group_id,
				),
				array( 'id' => $id ),
				array( '%s','%s','%s','%s','%s','%s','%d','%d' ),
				array( '%d' )
			);
			if ( false === $updated ) {
				echo '<div class="error"><p>更新に失敗しました。</p></div>';
			} else {
				echo '<div class="updated"><p>更新しました。</p></div>';
			}
		}

		$item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_requests} WHERE id = %d", $id ) );
		if ( ! $item ) wp_die( 'データが見つかりません' );

		$groups = $wpdb->get_results( "SELECT id, group_name, company_name FROM {$this->table_groups} ORDER BY id ASC" );
		
		$statuses = $this->allowed_statuses;
		$billings = $this->allowed_billings;

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">依頼詳細・編集 (ID: <?php echo $id; ?>)</h1>
			<a href="?page=kzrq-requests" class="page-title-action">一覧へ戻る</a>
			<hr class="wp-header-end">

			<div style="display:flex; gap: 20px;">
				<div style="flex:1; background:#fff; padding:20px; border:1px solid #ccd0d4;">
					<h3>A. フォーム送信内容 (受付: <?php echo esc_html($item->receipt_no); ?>)</h3>
					<table class="form-table">
						<tr><th>送信日時</th><td><?php echo esc_html( $item->created_at ); ?></td></tr>
						<tr><th>依頼者名</th><td><?php echo esc_html( $item->requester_name ); ?></td></tr>
						<tr><th>会社名</th><td><?php echo esc_html( $item->requester_company ); ?></td></tr>
						<tr><th>メールアドレス</th><td><a href="mailto:<?php echo esc_attr( $item->requester_email ); ?>"><?php echo esc_html( $item->requester_email ); ?></a></td></tr>
						<tr><th>連絡先</th><td><?php echo esc_html( $item->requester_contact ); ?></td></tr>
						<tr><th>依頼内容</th><td><div style="background:#f0f0f1; padding:10px;"><?php echo nl2br( esc_html( $item->request_body ) ); ?></div></td></tr>
						<tr><th>添付ファイル</th>
							<td>
								<?php if ( $item->attachment_url ) : ?>
									<a href="<?php echo esc_url( $item->attachment_url ); ?>" target="_blank" class="button">ダウンロード・確認</a>
									<br><small><?php echo esc_html( basename( $item->attachment_path ) ); ?></small>
								<?php else : ?>なし<?php endif; ?>
							</td>
						</tr>
						<tr><th>IP / UA</th><td><small><?php echo esc_html( $item->ip_address ); ?><br><?php echo esc_html( $item->user_agent ); ?></small></td></tr>
					</table>
				</div>

				<div style="flex:1; background:#fff; padding:20px; border:1px solid #ccd0d4;">
					<h3>B. 管理・対応入力</h3>
					<form method="post" action="">
						<?php wp_nonce_field( 'kzrq_edit_action', 'kzrq_edit_nonce' ); ?>
						<table class="form-table">
							<tr>
								<th>進捗ステータス</th>
								<td><select name="status"><?php foreach($statuses as $st) echo '<option value="'.esc_attr($st).'" '.selected($item->status, $st, false).'>'.esc_html($st).'</option>'; ?></select></td>
							</tr>
							<tr>
								<th>料金区分</th>
								<td><select name="billing_type"><?php foreach($billings as $bt) echo '<option value="'.esc_attr($bt).'" '.selected($item->billing_type, $bt, false).'>'.esc_html($bt).'</option>'; ?></select></td>
							</tr>
							<tr>
								<th>対応内容 (相手に表示)</th>
								<td><textarea name="response_body" rows="4" style="width:100%;"><?php echo esc_textarea( $item->response_body ); ?></textarea></td>
							</tr>
							<tr>
								<th>対応URL (相手に表示)</th>
								<td><input type="text" name="response_url" value="<?php echo esc_attr( $item->response_url ); ?>" style="width:100%;"></td>
							</tr>
							<tr>
								<th>URL閲覧パスワード (相手に表示)</th>
								<td><input type="text" name="response_page_password" value="<?php echo esc_attr( $item->response_page_password ); ?>" style="width:100%;"></td>
							</tr>
							<tr>
								<th><span style="color:red;">内部メモ (絶対非公開)</span></th>
								<td><textarea name="internal_note" rows="3" style="width:100%; background:#fff3f3;"><?php echo esc_textarea( $item->internal_note ); ?></textarea></td>
							</tr>
							<tr>
								<th>公開設定</th>
								<td><label><input type="checkbox" name="public_flag" value="1" <?php checked( $item->public_flag, 1 ); ?>> <strong>相手確認用ページに表示する</strong></label></td>
							</tr>
							<tr>
								<th>紐付け先公開グループ</th>
								<td>
									<select name="public_group_id">
										<option value="0">-- 未設定 --</option>
										<?php foreach ( $groups as $g ) echo '<option value="'.esc_attr($g->id).'" '.selected( $item->public_group_id, $g->id, false ).'>'.esc_html($g->company_name . ' (' . $g->group_name . ')').'</option>'; ?>
									</select>
								</td>
							</tr>
						</table>
						<p class="submit"><input type="submit" class="button button-primary" value="変更を保存"></p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * 【管理画面】公開先グループ管理
	 */
	public function admin_page_groups() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '権限がありません' );
		global $wpdb;

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['kzrq_group_nonce'] ) && wp_verify_nonce( $_POST['kzrq_group_nonce'], 'kzrq_group_action' ) ) {
			$action = sanitize_key( wp_unslash( $_POST['action_type'] ?? '' ) );
			
			if ( $action === 'add' || $action === 'edit' ) {
				$raw_email = trim( (string) wp_unslash( $_POST['email'] ?? '' ) );
				if ( $raw_email !== '' && ! is_email( $raw_email ) ) {
					echo '<div class="error"><p>不正なメールアドレス形式です。保存を中止しました。</p></div>';
				} else {
					$email_input = $raw_email === '' ? '' : sanitize_email( $raw_email );

					$data = array(
						'group_name'   => sanitize_text_field( wp_unslash( $_POST['group_name'] ?? '' ) ),
						'company_name' => sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) ),
						'contact_name' => sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) ),
						'email'        => $email_input,
						'note'         => sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ),
					);

					$raw_pass = sanitize_text_field( wp_unslash( $_POST['access_password'] ?? '' ) );
					if ( ! empty( $raw_pass ) ) {
						$data['access_password'] = wp_hash_password( $raw_pass );
					} elseif ( $action === 'add' ) {
						$data['access_password'] = '';
					}

					if ( '' === $data['group_name'] ) {
						echo '<div class="error"><p>管理用グループ名は必須です。</p></div>';
					} elseif ( $action === 'add' ) {
						$data['view_token'] = wp_generate_password( 24, false );
						$inserted = $wpdb->insert( $this->table_groups, $data );
						if ( false === $inserted ) {
							echo '<div class="error"><p>公開先の追加に失敗しました。</p></div>';
						} else {
							echo '<div class="updated"><p>公開先を追加しました。</p></div>';
						}
					} else {
						$id = isset( $_POST['group_id'] ) ? intval( wp_unslash( $_POST['group_id'] ) ) : 0;
						if ( $id <= 0 ) {
							echo '<div class="error"><p>更新対象の公開先IDが不正です。</p></div>';
						} else {
							if ( isset( $_POST['reissue_token'] ) ) {
								$data['view_token'] = wp_generate_password( 24, false );
							}
							
							$formats = array();
							foreach ( $data as $key => $val ) {
								$formats[] = '%s';
							}

							$updated = $wpdb->update( $this->table_groups, $data, array( 'id' => $id ), $formats, array('%d') );
							if ( false === $updated ) {
								echo '<div class="error"><p>公開先の更新に失敗しました。</p></div>';
							} else {
								echo '<div class="updated"><p>公開先を更新しました。</p></div>';
							}
						}
					}
				}
			}
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) && $_POST['action'] === 'delete_group' ) {
			$id = intval( wp_unslash( $_POST['id'] ) );
			if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'kzrq_delete_group_' . $id ) ) {
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_requests} WHERE public_group_id = %d", $id ) );
				if ( $count > 0 ) {
					echo '<div class="error"><p>この公開先には紐づく依頼が存在するため削除できません。先に依頼の紐付けを解除してください。</p></div>';
				} else {
					$deleted = $wpdb->delete( $this->table_groups, array( 'id' => $id ), array( '%d' ) );
					if ( false === $deleted ) {
						echo '<div class="error"><p>削除に失敗しました。</p></div>';
					} else {
						echo '<div class="updated"><p>削除しました。</p></div>';
					}
				}
			}
		}

		$groups = $wpdb->get_results( "SELECT * FROM {$this->table_groups} ORDER BY id DESC" );
		$settings = $this->get_settings();
		$base_url = home_url( '/' . $settings['board_slug'] . '/' );

		$edit_group = null;
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
			$edit_group = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_groups} WHERE id = %d", intval( wp_unslash( $_GET['id'] ) ) ) );
		}

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">公開先管理</h1>
			<hr class="wp-header-end">

			<div style="display:flex; gap:20px;">
				<div style="flex:2;">
					<h3>登録済み公開先一覧</h3>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr><th>ID</th><th>グループ名</th><th>会社名/担当</th><th>認証パスワード</th><th>公開URL(Token)</th><th>操作</th></tr>
						</thead>
						<tbody>
							<?php if(empty($groups)): ?><tr><td colspan="6">登録なし</td></tr><?php else: ?>
								<?php foreach($groups as $g): 
									$board_url = add_query_arg( 'token', $g->view_token, $base_url );
								?>
									<tr>
										<td><?php echo $g->id; ?></td>
										<td><?php echo esc_html($g->group_name); ?></td>
										<td><?php echo esc_html($g->company_name); ?><br><small><?php echo esc_html($g->contact_name); ?></small></td>
										<td><?php echo ! empty($g->access_password) ? '<strong>設定あり</strong>' : '<span style="color:#aaa;">未設定</span>'; ?></td>
										<td>
											<input type="text" readonly value="<?php echo esc_url($board_url); ?>" style="width:100%; font-size:11px;" onfocus="this.select();">
											<a href="<?php echo esc_url($board_url); ?>" target="_blank" class="button button-small">確認</a>
										</td>
										<td>
											<div style="display:flex; gap:5px;">
												<a href="?page=kzrq-groups&action=edit&id=<?php echo $g->id; ?>" class="button button-small">編集</a>
												<form method="post" action="" onsubmit="return confirm('削除しますか？');">
													<?php wp_nonce_field( 'kzrq_delete_group_' . $g->id ); ?>
													<input type="hidden" name="action" value="delete_group">
													<input type="hidden" name="id" value="<?php echo $g->id; ?>">
													<button type="submit" class="button button-small" style="color:red; border-color:red;">削除</button>
												</form>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<div style="flex:1; background:#fff; padding:15px; border:1px solid #ccd0d4;">
					<h3><?php echo $edit_group ? '公開先の編集' : '新規公開先の追加'; ?></h3>
					<form method="post" action="?page=kzrq-groups">
						<?php wp_nonce_field( 'kzrq_group_action', 'kzrq_group_nonce' ); ?>
						<input type="hidden" name="action_type" value="<?php echo $edit_group ? 'edit' : 'add'; ?>">
						<?php if($edit_group): ?><input type="hidden" name="group_id" value="<?php echo $edit_group->id; ?>"><?php endif; ?>
						
						<p><label>管理用グループ名 (必須)<br>
							<input type="text" name="group_name" value="<?php echo $edit_group ? esc_attr($edit_group->group_name) : ''; ?>" required style="width:100%;">
						</label></p>
						<p><label>会社名<br>
							<input type="text" name="company_name" value="<?php echo $edit_group ? esc_attr($edit_group->company_name) : ''; ?>" style="width:100%;">
						</label></p>
						<p><label>担当者名<br>
							<input type="text" name="contact_name" value="<?php echo $edit_group ? esc_attr($edit_group->contact_name) : ''; ?>" style="width:100%;">
						</label></p>
						<p><label>メールアドレス<br>
							<input type="email" name="email" value="<?php echo $edit_group ? esc_attr($edit_group->email) : ''; ?>" style="width:100%;">
						</label></p>
						<p><label>追加認証パスワード<br>
							<input type="text" name="access_password" value="" style="width:100%;" placeholder="<?php echo $edit_group && !empty($edit_group->access_password) ? '変更する場合のみ入力' : 'パスワードを入力'; ?>">
						</label></p>
						<p><label>内部メモ<br>
							<textarea name="note" rows="2" style="width:100%;"><?php echo $edit_group ? esc_textarea($edit_group->note) : ''; ?></textarea>
						</label></p>
						
						<?php if($edit_group): ?>
							<p style="background:#fff3f3; padding:10px; border-left:4px solid red;">
								<label><input type="checkbox" name="reissue_token" value="1"> <strong>Tokenを再発行する</strong></label><br>
								<small>※再発行すると発行済みの旧URL、および対象者の認証Cookieはすべて無効化されます。</small>
							</p>
						<?php endif; ?>

						<p><input type="submit" class="button button-primary" value="<?php echo $edit_group ? '更新する' : '追加する'; ?>"></p>
						<?php if($edit_group): ?><p><a href="?page=kzrq-groups">新規追加モードに戻る</a></p><?php endif; ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * 【管理画面】設定
	 */
	public function admin_page_settings() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '権限がありません' );
		$settings = $this->get_settings();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">各種設定</h1>
			<hr class="wp-header-end">
			<?php settings_errors( 'kzrq_options_group' ); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'kzrq_options_group' ); ?>
				<table class="form-table">
					<tr>
						<th>フォームURLスラッグ</th>
						<td>
							<input type="text" name="kzrq_settings[form_slug]" value="<?php echo esc_attr($settings['form_slug']); ?>" class="regular-text"><br>
							<p class="description"><strong style="color:red;">【重要】</strong>固定ページ（スラッグ: <code><?php echo esc_html($settings['form_slug']); ?></code>）を作成し、本文に <code>[kzrq_request_form]</code> と記載してください。未作成の場合はページが表示されません。</p>
						</td>
					</tr>
					<tr>
						<th>確認ページURLスラッグ</th>
						<td>
							<input type="text" name="kzrq_settings[board_slug]" value="<?php echo esc_attr($settings['board_slug']); ?>" class="regular-text"><br>
							<p class="description"><strong style="color:red;">【重要】</strong>固定ページ（スラッグ: <code><?php echo esc_html($settings['board_slug']); ?></code>）を作成し、本文に <code>[kzrq_request_board]</code> と記載してください。未作成の場合はページが表示されません。</p>
						</td>
					</tr>
					<tr>
						<th>SEO (確認ページ)</th>
						<td><label><input type="checkbox" name="kzrq_settings[board_noindex]" value="1" <?php checked($settings['board_noindex'], '1'); ?>> 確認ページに noindex, nofollow を付与する (推奨)</label></td>
					</tr>
					<tr>
						<th>追加パスワード認証</th>
						<td><label><input type="checkbox" name="kzrq_settings[require_password]" value="1" <?php checked($settings['require_password'], '1'); ?>> 公開先ページで追加パスワード認証を必須にする</label><br>
						<p class="description">※チェックを入れた場合、公開先に設定された「追加認証パスワード」の入力が閲覧時に要求されます。</p></td>
					</tr>
					<tr>
						<th>管理者通知メール</th>
						<td><label><input type="checkbox" name="kzrq_settings[notify_admin]" value="1" <?php checked($settings['notify_admin'], '1'); ?>> 依頼受付時にメール通知する</label><br>
						通知先: <input type="email" name="kzrq_settings[notify_email]" value="<?php echo esc_attr($settings['notify_email']); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th>添付ファイル許可拡張子</th>
						<td><input type="text" name="kzrq_settings[allowed_exts]" value="<?php echo esc_attr($settings['allowed_exts']); ?>" class="regular-text"> (カンマ区切り)</td>
					</tr>
					<tr>
						<th>アップロード最大サイズ</th>
						<td><input type="number" name="kzrq_settings[max_upload_mb]" value="<?php echo esc_attr($settings['max_upload_mb']); ?>" class="small-text" min="1"> MB</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

new KZRQ_Request_Manager();
