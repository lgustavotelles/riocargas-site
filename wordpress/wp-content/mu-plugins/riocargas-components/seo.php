<?php
/**
 * SEO enxuto: meta description + Open Graph.
 *
 * Fonte da descricao, por ordem:
 *   1. postmeta `rc_meta_desc` da pagina (editavel no admin / via script)
 *   2. excerpt da pagina
 *   3. primeiro trecho do conteudo (strip tags/shortcodes)
 *   4. texto padrao do site
 *
 * O sitemap nativo do WP ja existe em /wp-sitemap.xml — nao reimplementamos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Descricao efetiva de um post/pagina.
 *
 * @param int $post_id ID do post.
 * @return string
 */
function rc_seo_description( $post_id ) {
	$desc = get_post_meta( $post_id, 'rc_meta_desc', true );
	if ( ! $desc ) {
		$post = get_post( $post_id );
		if ( $post ) {
			$desc = $post->post_excerpt;
			if ( ! $desc ) {
				$desc = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
			}
		}
	}
	if ( ! $desc ) {
		$desc = 'Rio Cargas Transporte e Logistica — transporte de cargas com eficiencia e seguranca, matriz no Rio de Janeiro e filiais em SP, MG e ES.';
	}
	return wp_trim_words( trim( preg_replace( '/\s+/', ' ', $desc ) ), 30, '…' );
}

/**
 * URL da imagem social (og:image): logo do site, senao thumbnail da pagina.
 *
 * @param int $post_id ID do post.
 * @return string
 */
function rc_seo_image( $post_id ) {
	$thumb = get_the_post_thumbnail_url( $post_id, 'large' );
	if ( $thumb ) {
		return $thumb;
	}
	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$logo = wp_get_attachment_image_url( $logo_id, 'full' );
		if ( $logo ) {
			return $logo;
		}
	}
	return '';
}

add_action(
	'wp_head',
	function () {
		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
			return;
		}
		$post_id = is_singular() ? get_queried_object_id() : (int) get_option( 'page_on_front' );
		if ( ! $post_id || 'page' !== get_post_type( $post_id ) && ! is_front_page() ) {
			return;
		}

		$desc  = rc_seo_description( $post_id );
		$title = wp_get_document_title();
		$url   = is_front_page() ? home_url( '/' ) : get_permalink( $post_id );
		$image = rc_seo_image( $post_id );

		echo "\n<!-- RioCargas SEO -->\n";
		printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $desc ) );
		printf( '<meta property="og:locale" content="pt_BR" />' . "\n" );
		printf( '<meta property="og:type" content="%s" />' . "\n", is_front_page() ? 'website' : 'article' );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $desc ) );
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_attr( $url ) );
		printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
		if ( $image ) {
			printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image ) );
		}
		printf( '<meta name="twitter:card" content="summary" />' . "\n" );
		printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $title ) );
		printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $desc ) );
	},
	1
);
