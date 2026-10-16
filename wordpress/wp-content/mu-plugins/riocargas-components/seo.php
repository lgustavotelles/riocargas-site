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

/**
 * Schema.org LocalBusiness — matriz + filiais (dados da pagina /unidades/).
 * Emitido uma vez por pagina, em todas (canonical para a organizacao).
 */
add_action(
	'wp_head',
	function () {
		if ( is_admin() || is_feed() || ! ( is_singular() || is_front_page() ) ) {
			return;
		}
		$addr = function ( $street, $city, $state ) {
			return array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $street,
				'addressLocality' => $city,
				'addressRegion'   => $state,
				'addressCountry'  => 'BR',
			);
		};
		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'LocalBusiness',
			'@id'             => home_url( '/#empresa' ),
			'name'            => 'Rio Cargas Transporte e Logística',
			'url'             => home_url( '/' ),
			'logo'            => rc_seo_image( 0 ),
			'email'           => 'sac@riocargas.com.br',
			'telephone'       => '+55-21-3995-9846',
			'address'         => $addr( 'Rua Benjamin Silva, 180, Pavuna', 'Rio de Janeiro', 'RJ' ),
			'openingHours'    => 'Mo-Fr 08:30-17:30',
			'subOrganization' => array(
				array( '@type' => 'LocalBusiness', 'name' => 'Rio Cargas — Filial Guarulhos', 'telephone' => '+55-11-2422-7616', 'address' => $addr( 'Rua Guararapes, 415, Vila São Rafael', 'Guarulhos', 'SP' ) ),
				array( '@type' => 'LocalBusiness', 'name' => 'Rio Cargas — Filial Cariacica', 'address' => $addr( 'Rua São Jorge, 48, Alto Lage', 'Cariacica', 'ES' ) ),
				array( '@type' => 'LocalBusiness', 'name' => 'Rio Cargas — Filial Nova Serrana', 'address' => $addr( 'Rua João de Freitas, 390, Park Dona Gumercinda Martins', 'Nova Serrana', 'MG' ) ),
				array( '@type' => 'LocalBusiness', 'name' => 'Rio Cargas — Unidade Campos dos Goytacazes', 'address' => $addr( 'Estrada do Capão, 378, Parque Aurora', 'Campos dos Goytacazes', 'RJ' ) ),
				array( '@type' => 'LocalBusiness', 'name' => 'Rio Cargas — Unidade São Pedro da Aldeia', 'address' => $addr( 'Rodovia Amaral Peixoto, Lt 0293 Qd14 KM 106, Balneário das Conchas', 'São Pedro da Aldeia', 'RJ' ) ),
				array( '@type' => 'LocalBusiness', 'name' => 'Rio Cargas — Unidade Barra Mansa', 'address' => $addr( 'Rodovia Presidente Dutra, 175, KM 268-50, Boa Sorte', 'Barra Mansa', 'RJ' ) ),
			),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	},
	2
);
