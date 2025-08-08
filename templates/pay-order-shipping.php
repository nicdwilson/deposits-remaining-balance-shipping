<?php
/**
 * Shipping section template for pay order page
 *
 * @package deposits-remaining-balance-shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="wc-deposits-rbs-shipping-section" class="wc-deposits-rbs-shipping-section">
	<h3><?php esc_html_e( 'Shipping Options', 'deposits-remaining-balance-shipping' ); ?></h3>
	
	<div class="wc-deposits-rbs-shipping-rates">
		<?php if ( ! empty( $shipping_rates ) ) : ?>
			<ul class="wc-deposits-rbs-shipping-methods">
				<?php foreach ( $shipping_rates as $index => $rate ) : ?>
					<li class="wc-deposits-rbs-shipping-method">
						<label>
							<input 
								type="radio" 
								name="wc_deposits_rbs_shipping_method_radio" 
								value="<?php echo esc_attr( $rate->id ); ?>" 
								data-cost="<?php echo esc_attr( $rate->cost ); ?>"
								class="wc-deposits-rbs-shipping-method-radio"
								<?php echo ( $index === 0 ) ? 'checked' : ''; ?>
							/>
							<span class="wc-deposits-rbs-shipping-method-label">
								<?php echo esc_html( $rate->label ); ?>
							</span>
							<span class="wc-deposits-rbs-shipping-method-cost">
								<?php echo wc_price( $rate->cost ); ?>
							</span>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="wc-deposits-rbs-no-shipping">
				<?php esc_html_e( 'No shipping options available for your location.', 'deposits-remaining-balance-shipping' ); ?>
			</p>
		<?php endif; ?>
	</div>


</div>
