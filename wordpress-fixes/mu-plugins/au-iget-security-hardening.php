<?php
/**
 * Plugin Name: AU IGET Vape Shop - Security Hardening
 * Description: 针对 auigetvapeshop.com 诊断报告（第3节）落地的轻量安全加固，
 *              不依赖第三方安全插件，纯 WordPress 核心 API，可安全叠加在现有插件之上。
 *              部署方式见 wordpress-fixes/DEPLOY.md。
 * Version:     1.0.0
 * Author:      Cursor Cloud Agent Audit
 *
 * 覆盖项（对应审计报告 3.2 节）：
 *   1. 补齐缺失的安全响应头 (HSTS / X-Frame-Options / Referrer-Policy / Permissions-Policy)
 *   2. 完全禁用 XML-RPC，移除 X-Pingback 头，避免暴力破解/DDoS放大攻击面
 *   3. 阻止未登录状态下通过 REST API 枚举用户名 (/wp-json/wp/v2/users)
 *   4. 阻止 ?author=1 方式的用户名枚举
 *   5. 禁用后台主题/插件在线文件编辑器 (DISALLOW_FILE_EDIT)
 *   6. 移除页面中泄露的 WordPress 版本号 (generator meta / RSS / 脚本版本号)
 *
 * 注意：本文件为 mu-plugin（must-use plugin），放入 wp-content/mu-plugins/ 目录后
 * 会自动启用，无需在后台"插件"页面激活，也无法被误停用。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------------
 * 1. 安全响应头
 * 现状（实测）：仅有 x-content-type-options: nosniff，缺 HSTS/CSP/XFO/Referrer-Policy/Permissions-Policy
 * 说明：为避免 Content-Security-Policy 因未逐一核对第三方脚本域名（Google Fonts、
 * Slider Revolution、Elementor 内联脚本等）而"一刀切"导致前端功能损坏，此处默认
 * 不强制启用 CSP，仅加入风险最低、收益明确的几项；CSP 建议在人工核对好白名单后
 * 再单独启用（见文件末尾注释里的示例）。
 * ------------------------------------------------------------------- */
add_action( 'send_headers', function () {
	if ( is_admin() ) {
		return;
	}

	if ( is_ssl() ) {
		header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains; preload' );
	}

	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()' );
}, 20 );

/* ---------------------------------------------------------------------
 * 2. 完全禁用 XML-RPC
 * 现状（实测）：xmlrpc.php 返回 Cloudflare 520（源站异常响应），既不安全也不干净。
 * ------------------------------------------------------------------- */
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'pings_open', '__return_false', 20 );

// 移除 pingback 方法，防止被用于 XML-RPC pingback 反射/放大攻击。
add_filter( 'xmlrpc_methods', function ( $methods ) {
	unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
	return $methods;
} );

// 移除 <head> 中的 pingback 链接与响应头中的 X-Pingback。
remove_action( 'wp_head', 'rsd_link' );
add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
} );

/* ---------------------------------------------------------------------
 * 3. 阻止 REST API 未登录用户枚举 (/wp-json/wp/v2/users, /wp-json/wp/v2/users/<id>)
 * 现状（实测）：该端点会随机被主机的验证码拦截，行为不稳定，需要在应用层明确拒绝。
 * ------------------------------------------------------------------- */
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( is_user_logged_in() ) {
		return $endpoints;
	}
	foreach ( array_keys( $endpoints ) as $route ) {
		if ( preg_match( '#^/wp/v2/users#', $route ) ) {
			unset( $endpoints[ $route ] );
		}
	}
	return $endpoints;
} );

/* ---------------------------------------------------------------------
 * 4. 阻止 ?author=1 方式的用户名枚举（自动跳转到首页）
 * ------------------------------------------------------------------- */
add_action( 'template_redirect', function () {
	if ( is_admin() ) {
		return;
	}
	if ( isset( $_GET['author'] ) && ! is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
} );

/* ---------------------------------------------------------------------
 * 5. 禁用后台主题/插件在线文件编辑器
 * 说明：一旦后台账号被入侵，攻击者常见的第一步就是通过"外观 > 主题文件编辑器"
 * 或"插件 > 插件文件编辑器"直接写入恶意代码，禁用该功能可显著缩小横向破坏面。
 * ------------------------------------------------------------------- */
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

/* ---------------------------------------------------------------------
 * 6. 移除 WordPress 版本号泄露
 * ------------------------------------------------------------------- */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

add_filter( 'style_loader_src', 'auiget_remove_wp_ver_css_js', 9999 );
add_filter( 'script_loader_src', 'auiget_remove_wp_ver_css_js', 9999 );
function auiget_remove_wp_ver_css_js( $src ) {
	if ( strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) !== false ) {
		$src = remove_query_arg( 'ver', $src );
	}
	return $src;
}

/**
 * 可选：CSP 示例（默认关闭）。核对完所有第三方脚本域名后，取消下方注释即可启用。
 * 目前已知需要放行的域名（基于实测）：
 *   - fonts.googleapis.com / fonts.gstatic.com （Google Fonts）
 *   - 站内自身域名（Slider Revolution / Elementor / Woodmart 资源均为同源）
 * 若未来接入支付网关（如 Stripe/其他）、地图、聊天插件等，需要把对应域名加入白名单，
 * 否则会导致对应功能被浏览器拦截。
 */
// add_action( 'send_headers', function () {
// 	if ( is_admin() ) {
// 		return;
// 	}
// 	header(
// 		"Content-Security-Policy: default-src 'self'; " .
// 		"img-src 'self' data: https:; " .
// 		"style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
// 		"font-src 'self' https://fonts.gstatic.com; " .
// 		"script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
// 		"frame-ancestors 'self';"
// 	);
// }, 21 );
