<?php
/**
 * NIBWOZ UI 1.2.0 — marcação padrão da interface dos plugins NIBWOZ.
 *
 * O namespace leva a versão completa do kit: dois plugins com versões diferentes do kit
 * convivem no mesmo site sem conflito, e plugins com a mesma versão reaproveitam a mesma classe.
 * O kit não tem textos próprios: todo texto chega já traduzido pelo plugin (text domain dele).
 *
 * @package NIBWOZ\UI
 */

namespace NIBWOZ\UI\V1_2_0;

defined( 'ABSPATH' ) || exit;

if ( class_exists( __NAMESPACE__ . '\Ui', false ) ) {
	return;
}

/**
 * Renderizadores estáticos. Toda saída é escapada aqui.
 */
final class Ui {

	public const VERSION = '1.2.0';

	/**
	 * Registra CSS e JS do kit com handles do plugin. Chame no admin_enqueue_scripts da tela do plugin.
	 *
	 * @param string $handle   Prefixo do plugin, por exemplo "nwzc".
	 * @param string $base_url URL da pasta nibwoz-ui dentro do plugin, terminada em barra.
	 * @return string Handle do CSS do kit, para usar como dependência do CSS do plugin.
	 */
	public static function enqueue( string $handle, string $base_url ): string {
		$css = $handle . '-nibwoz-ui';
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( $css, $base_url . 'nibwoz-ui.css', array( 'dashicons' ), self::VERSION );
		wp_enqueue_script( $css, $base_url . 'nibwoz-ui.js', array(), self::VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		return $css;
	}

	/**
	 * Abre a estrutura: .wrap, barra lateral (marca, navegação, salvar) e área principal.
	 *
	 * @param array{
	 *   title: string, product: string, version: string, wordmark_url: string, nav_label: string,
	 *   items: array<int, array{0: string, 1: string, 2: string, 3: bool}>,
	 *   save?: array{form: string, label: string, dirty: string}|null,
	 *   leave?: string, class?: string
	 * } $args items: rótulo, URL, classe dashicons, atual.
	 */
	public static function shell_open( array $args ): void {
		$class = trim( 'wrap nwz-ui ' . ( $args['class'] ?? '' ) );
		echo '<div class="' . esc_attr( $class ) . '"' . ( ! empty( $args['leave'] ) ? ' data-nwz-leave="' . esc_attr( $args['leave'] ) . '"' : '' ) . '>';
		echo '<h1 class="screen-reader-text">' . esc_html( $args['title'] ) . '</h1><div class="nwz-layout">';
		echo '<aside class="nwz-sidebar"><div class="nwz-brand">';
		echo '<img class="nwz-wordmark" src="' . esc_url( $args['wordmark_url'] ) . '" alt="NIBWOZ" width="124" height="33">';
		echo '<span class="nwz-product">' . esc_html( $args['product'] ) . '</span><span class="nwz-version">' . esc_html( $args['version'] ) . '</span></div>';
		echo '<nav aria-label="' . esc_attr( $args['nav_label'] ) . '">';
		foreach ( $args['items'] as $item ) {
			$current = ! empty( $item[3] );
			echo '<a class="nwz-nav-link' . ( $current ? ' is-current' : '' ) . '" href="' . esc_url( $item[1] ) . '"' . ( $current ? ' aria-current="page"' : '' ) . '>';
			echo '<span class="dashicons ' . esc_attr( $item[2] ) . '" aria-hidden="true"></span><span>' . esc_html( $item[0] ) . '</span></a>';
		}
		echo '</nav>';
		if ( ! empty( $args['save'] ) ) {
			echo '<div class="nwz-sidebar-save"><span class="nwz-dirty" data-nwz-dirty role="status" hidden><span class="nwz-dirty-dot" aria-hidden="true"></span>' . esc_html( $args['save']['dirty'] ) . '</span>';
			echo '<button type="submit" form="' . esc_attr( $args['save']['form'] ) . '" class="button button-primary" data-nwz-busy-on-submit><span class="dashicons dashicons-saved" aria-hidden="true"></span>' . esc_html( $args['save']['label'] ) . '</button></div>';
		}
		echo '</aside><main class="nwz-main">';
	}

	public static function shell_close(): void {
		echo '</main></div></div>';
	}

	/**
	 * Cabeçalho azul da tela.
	 */
	public static function page_head( string $kicker, string $title, string $text, ?callable $actions = null ): void {
		echo '<header class="nwz-page-head"><div><p class="nwz-kicker">' . esc_html( $kicker ) . '</p><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $text ) . '</p></div>';
		if ( null !== $actions ) {
			echo '<div class="nwz-head-actions">';
			$actions();
			echo '</div>';
		}
		echo '</header>';
	}

	/**
	 * Aviso. Tipos: success, error, warning, info.
	 *
	 * @param string[] $messages Textos simples.
	 */
	public static function notice( string $type, array $messages ): void {
		$type  = in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info';
		$icons = array(
			'success' => 'dashicons-yes-alt',
			'error'   => 'dashicons-warning',
			'warning' => 'dashicons-flag',
			'info'    => 'dashicons-info',
		);
		echo '<div class="nwz-notice is-' . esc_attr( $type ) . '" role="' . ( 'error' === $type ? 'alert' : 'status' ) . '"><span class="dashicons ' . esc_attr( $icons[ $type ] ) . '" aria-hidden="true"></span><div>';
		foreach ( $messages as $message ) {
			echo '<p>' . esc_html( (string) $message ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * Aviso temporário (toast): pílula grande no centro da tela que some com fade.
	 * Para confirmações (configurações salvas, cache limpo). Erros continuam em notice(), fixos.
	 * Tons: success ou info. $ms: tempo visível em milissegundos (mínimo 2000).
	 */
	public static function toast( string $message, string $tone = 'success', int $ms = 4000 ): void {
		$tone = 'info' === $tone ? 'info' : 'success';
		$icon = 'info' === $tone ? 'dashicons-info' : 'dashicons-yes-alt';
		echo '<div class="nwz-toast is-' . esc_attr( $tone ) . '" role="status" data-nwz-toast data-nwz-toast-ms="' . esc_attr( (string) max( 2000, $ms ) ) . '" hidden><span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span><span>' . esc_html( $message ) . '</span></div>';
	}

	/**
	 * Interruptor Fluent com campo oculto "0", para que desligado também seja enviado.
	 *
	 * @param array{id: string, name: string, checked: bool, label: string, help?: string} $args
	 */
	public static function toggle( array $args ): void {
		$help = $args['help'] ?? '';
		echo '<label class="nwz-toggle" for="' . esc_attr( $args['id'] ) . '">';
		echo '<input type="hidden" name="' . esc_attr( $args['name'] ) . '" value="0">';
		echo '<input type="checkbox" role="switch" id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['name'] ) . '" value="1"' . ( $args['checked'] ? ' checked' : '' ) . ( '' !== $help ? ' aria-describedby="' . esc_attr( $args['id'] ) . '-help"' : '' ) . '>';
		echo '<span class="nwz-toggle-control" aria-hidden="true"></span><span class="nwz-toggle-text"><strong>' . esc_html( $args['label'] ) . '</strong>';
		if ( '' !== $help ) {
			echo '<small id="' . esc_attr( $args['id'] ) . '-help">' . esc_html( $help ) . '</small>';
		}
		echo '</span></label>';
	}

	/**
	 * Campo numérico com unidade.
	 *
	 * @param array{id: string, name: string, value: int|float|string, min: int|float, max: int|float, step?: int|float, label: string, help: string, unit: string} $args
	 */
	public static function number( array $args ): void {
		echo '<div class="nwz-field"><label for="' . esc_attr( $args['id'] ) . '">' . esc_html( $args['label'] ) . '</label>';
		echo '<div class="nwz-input-unit"><input type="number" id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['name'] ) . '" value="' . esc_attr( (string) $args['value'] ) . '" min="' . esc_attr( (string) $args['min'] ) . '" max="' . esc_attr( (string) $args['max'] ) . '" step="' . esc_attr( (string) ( $args['step'] ?? 1 ) ) . '" aria-describedby="' . esc_attr( $args['id'] ) . '-help"><span aria-hidden="true">' . esc_html( $args['unit'] ) . '</span></div>';
		echo '<small id="' . esc_attr( $args['id'] ) . '-help">' . esc_html( $args['help'] ) . '</small></div>';
	}

	/**
	 * Indicador. Tons: blue, green, orange, dark ou vazio. Aceita amber (= orange) e violet (= dark) da 1.0.0.
	 */
	public static function stat( string $label, string $value, string $note = '', string $tone = '' ): void {
		$tone = strtr( $tone, array( 'amber' => 'orange', 'violet' => 'dark' ) );
		$tone = in_array( $tone, array( 'blue', 'green', 'orange', 'dark' ), true ) ? ' is-' . $tone : '';
		echo '<div class="nwz-stat' . esc_attr( $tone ) . '"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong>';
		if ( '' !== $note ) {
			echo '<small>' . esc_html( $note ) . '</small>';
		}
		echo '</div>';
	}

	/**
	 * Selo sim/não. Retorna HTML seguro.
	 */
	public static function badge( bool $ok, string $yes, string $no ): string {
		return '<span class="nwz-badge ' . ( $ok ? 'is-ok' : 'is-no' ) . '"><span class="dashicons ' . ( $ok ? 'dashicons-yes-alt' : 'dashicons-dismiss' ) . '" aria-hidden="true"></span>' . esc_html( $ok ? $yes : $no ) . '</span>';
	}

	/**
	 * Lista de definições. O valor aceita HTML filtrado por wp_kses_post.
	 *
	 * @param array<int, array{0: string, 1: string}> $rows
	 */
	public static function dl( array $rows ): void {
		echo '<dl class="nwz-dl">';
		foreach ( $rows as $row ) {
			echo '<div><dt>' . esc_html( $row[0] ) . '</dt><dd>' . wp_kses_post( $row[1] ) . '</dd></div>';
		}
		echo '</dl>';
	}
}
