<?php
/**
 * WP-CLI 内容修复脚本 —— 对应审计报告 2.2 节："Blackberry lce" 类拼写错误批量核查与修复。
 *
 * 用法（在网站根目录，通过 SSH 执行）：
 *
 *   # 第一步：只扫描、不修改，打印所有命中的记录供人工复核
 *   wp eval-file wordpress-fixes/wp-cli/fix-content-typos.php
 *
 *   # 第二步：确认无误后，加 --apply 参数真正执行替换
 *   wp eval-file wordpress-fixes/wp-cli/fix-content-typos.php --apply
 *
 * 安全设计：
 *   - 默认 dry-run，绝不会在未加 --apply 参数时修改任何数据；
 *   - 只匹配独立单词 "lce"（大小写不敏感，带单词边界 \b，不会误伤 "excellence" 等词）；
 *   - 替换时保留大小写风格：lce -> ice, Lce -> Ice, LCE -> ICE；
 *   - 覆盖范围：文章标题(post_title)、slug(post_name)、摘要(post_excerpt)、正文(post_content)、
 *     Rank Math SEO 标题/描述(meta: rank_math_title / rank_math_description)、
 *     图片附件的 alt 文本(meta: _wp_attachment_image_alt)；
 *   - 执行后自动 flush 一次 Rank Math/WordPress 相关缓存（如检测到对应函数存在）。
 *
 * 建议：先在预发布/测试环境跑一遍，或至少先跑一次 --apply 之前做好数据库备份。
 */

if ( ! defined( 'WP_CLI' ) ) {
	echo "请通过 wp eval-file 命令运行本脚本（需要 WP-CLI 环境）。\n";
	exit( 1 );
}

$apply = in_array( '--apply', $args ?? array(), true )
	|| ( isset( $assoc_args ) && array_key_exists( 'apply', $assoc_args ) );

// 兼容 wp eval-file 的参数解析方式（不同 WP-CLI 版本变量名可能不同，做个兜底判断）。
if ( ! $apply && isset( $GLOBALS['argv'] ) ) {
	$apply = in_array( '--apply', $GLOBALS['argv'], true );
}

WP_CLI::log( $apply ? '=== 执行模式：将实际修改数据库 ===' : '=== 预览模式（dry-run），不会修改任何数据。加 --apply 才会真正执行。 ===' );

$pattern = '/\b([Ll][Cc][Ee])\b/';

/**
 * 按命中片段的大小写风格，生成对应大小写的替换文本。
 */
function auiget_fix_case( $matched ) {
	if ( $matched === strtoupper( $matched ) ) {
		return 'ICE';
	}
	if ( ctype_upper( $matched[0] ) ) {
		return 'Ice';
	}
	return 'ice';
}

function auiget_apply_fix( $text, $pattern ) {
	return preg_replace_callback( $pattern, function ( $m ) {
		return auiget_fix_case( $m[1] );
	}, $text );
}

global $wpdb;

$total_hits = 0;
$total_changed = 0;

/* -------------------- 1. post_title / post_name / post_excerpt / post_content -------------------- */
$posts = $wpdb->get_results(
	"SELECT ID, post_type, post_title, post_name, post_excerpt, post_content
	 FROM {$wpdb->posts}
	 WHERE post_status IN ('publish','draft','pending','future')
	   AND (
	        post_title   REGEXP '[Ll][Cc][Ee]'
	     OR post_name    REGEXP '[Ll][Cc][Ee]'
	     OR post_excerpt REGEXP '[Ll][Cc][Ee]'
	     OR post_content REGEXP '[Ll][Cc][Ee]'
	   )"
);

WP_CLI::log( sprintf( '文章/商品命中 %d 条（post_title/post_name/post_excerpt/post_content 任一字段包含 "lce"）：', count( $posts ) ) );

foreach ( $posts as $p ) {
	$total_hits++;
	$fields_changed = array();
	$update = array();

	foreach ( array( 'post_title', 'post_name', 'post_excerpt', 'post_content' ) as $field ) {
		$original = $p->$field;
		if ( $original === null || $original === '' ) {
			continue;
		}
		if ( ! preg_match( $pattern, $original ) ) {
			continue;
		}
		$fixed = auiget_apply_fix( $original, $pattern );
		if ( $fixed !== $original ) {
			$fields_changed[] = $field;
			$update[ $field ] = $fixed;
		}
	}

	if ( empty( $fields_changed ) ) {
		continue;
	}

	WP_CLI::log( sprintf(
		'  [ID %d | %s] 字段 %s 命中："%s" -> 建议改为："%s"',
		$p->ID,
		$p->post_type,
		implode( ', ', $fields_changed ),
		$p->post_title,
		isset( $update['post_title'] ) ? $update['post_title'] : $p->post_title
	) );

	if ( $apply ) {
		$wpdb->update( $wpdb->posts, $update, array( 'ID' => $p->ID ) );
		clean_post_cache( $p->ID );
		$total_changed++;
	}
}

/* -------------------- 2. Rank Math SEO meta（标题/描述） -------------------- */
$meta_keys = array( 'rank_math_title', 'rank_math_description' );
foreach ( $meta_keys as $meta_key ) {
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT post_id, meta_value FROM {$wpdb->postmeta}
		 WHERE meta_key = %s AND meta_value REGEXP '[Ll][Cc][Ee]'",
		$meta_key
	) );

	WP_CLI::log( sprintf( 'Meta「%s」命中 %d 条：', $meta_key, count( $rows ) ) );

	foreach ( $rows as $row ) {
		$total_hits++;
		$fixed = auiget_apply_fix( $row->meta_value, $pattern );
		if ( $fixed === $row->meta_value ) {
			continue;
		}
		WP_CLI::log( sprintf(
			'  [post_id %d] "%s" -> "%s"',
			$row->post_id,
			$row->meta_value,
			$fixed
		) );
		if ( $apply ) {
			update_post_meta( $row->post_id, $meta_key, $fixed );
			$total_changed++;
		}
	}
}

/* -------------------- 3. 图片附件 alt 文本 -------------------- */
$alt_rows = $wpdb->get_results(
	"SELECT post_id, meta_value FROM {$wpdb->postmeta}
	 WHERE meta_key = '_wp_attachment_image_alt' AND meta_value REGEXP '[Ll][Cc][Ee]'"
);

WP_CLI::log( sprintf( '图片 alt 文本命中 %d 条：', count( $alt_rows ) ) );

foreach ( $alt_rows as $row ) {
	$total_hits++;
	$fixed = auiget_apply_fix( $row->meta_value, $pattern );
	if ( $fixed === $row->meta_value ) {
		continue;
	}
	WP_CLI::log( sprintf(
		'  [attachment %d] "%s" -> "%s"',
		$row->post_id,
		$row->meta_value,
		$fixed
	) );
	if ( $apply ) {
		update_post_meta( $row->post_id, '_wp_attachment_image_alt', $fixed );
		$total_changed++;
	}
}

/* -------------------- 收尾 -------------------- */
if ( $apply ) {
	if ( function_exists( 'rank_math' ) ) {
		// Rank Math 没有公开的"清缓存"CLI 命令，这里只是尝试清一下 WP 对象缓存，
		// 确保后台/前台立即看到新值，Sitemap 会在下次生成时自动更新。
		wp_cache_flush();
	}
	WP_CLI::success( sprintf( '完成。共命中 %d 处，已修改 %d 处。建议重新生成一次 Sitemap 并在 GSC 里重新提交受影响 URL。', $total_hits, $total_changed ) );
} else {
	WP_CLI::log( '' );
	WP_CLI::warning( sprintf(
		'预览完成，共命中 %d 处，未做任何修改。核对无误后执行： wp eval-file wordpress-fixes/wp-cli/fix-content-typos.php --apply',
		$total_hits
	) );
}
