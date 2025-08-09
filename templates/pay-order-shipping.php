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
		<div class="wc-deposits-rbs-shipping-rates-content">
			<?php if ( ! empty( $shipping_rates ) ) : ?>
				<h4><?php esc_html_e( 'Available Shipping Methods', 'deposits-remaining-balance-shipping' ); ?></h4>
				<form method="post" action="" id="wc-deposits-rbs-shipping-form">
					<ul class="wc-deposits-rbs-shipping-methods">
						<?php foreach ( $shipping_rates as $index => $rate ) : ?>
							<li class="wc-deposits-rbs-shipping-method">
								<label>
									<input 
										type="radio" 
										name="wc_deposits_rbs_shipping_method" 
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
					
					<div class="wc-deposits-rbs-shipping-actions">
						<button type="submit" name="wc_deposits_rbs_update_shipping" class="button">
							<?php esc_html_e( 'Update Shipping', 'deposits-remaining-balance-shipping' ); ?>
						</button>
					</div>
					
					<?php wp_nonce_field( 'wc_deposits_rbs_update_shipping', 'wc_deposits_rbs_shipping_nonce' ); ?>
				</form>
			<?php else : ?>
				<div class="wc-deposits-rbs-shipping-message">
					<p class="wc-deposits-rbs-no-shipping">
						<?php esc_html_e( 'No shipping options available for your location.', 'deposits-remaining-balance-shipping' ); ?>
					</p>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
