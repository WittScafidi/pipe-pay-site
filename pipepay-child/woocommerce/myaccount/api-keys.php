<?php
/**
 * Kestrel API Manager for WooCommerce
 *
 * This source file is subject to the GNU General Public License v3.0 that is bundled with this plugin in the file license.txt.
 *
 * Please do not modify this file if you want to upgrade this plugin to newer versions in the future.
 * If you want to customize this file for your needs, please review our developer documentation.
 * Join our developer program at https://kestrelwp.com/developers
 *
 * @author    Kestrel
 * @copyright Copyright (c) 2013-2025 Kestrel Commerce LLC [support@kestrelwp.com]
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * License keys template - Shows license keys on the My Account page.
 *
 * @see https://docs.woocommerce.com/document/template-structure/ to override this template in your theme.
 *
 * @since 2.0.0
 *
 * @version 3.7.2
 *
 * @var int|null $user_id user ID
 */

wc_print_notices();

$user_id = empty( $user_id ) ? get_current_user_id() : $user_id;

if ( ! empty( $user_id ) ) :

	// every customer must have a master license key, and it is missing, so create it now
	if ( empty( WC_AM_USER()->get_master_api_key( $user_id ) ) ) : // @phpstan-ignore-line

		WC_AM_USER()->set_registration_master_key_and_status( $user_id ); // @phpstan-ignore-line

	endif;

	$master_api_key_status = WC_AM_USER()->has_api_access( $user_id ); // @phpstan-ignore-line

	if ( $master_api_key_status ) :

		$resources = WC_AM_API_RESOURCE_DATA_STORE()->get_api_resources_for_user_id_sort_by_product_title( $user_id ); // @phpstan-ignore-line

		/*
		 * Pipe Pay theme override (only change vs the stock WCAM 3.7.2 template):
		 * hide dead license rows. Cancelled/refunded/tier-superseded Stripe
		 * subscriptions and revoked licenses are deactivated (active = 0) and
		 * should not linger in the customer's list. Expired-but-still-active
		 * rows (payment-app annual licenses in/after their grace window) stay
		 * visible so the customer keeps their Renew path.
		 */
		$resources = array_values( array_filter( (array) $resources, static function ( $resource ) {
			return ! empty( $resource->active );
		} ) );

		if ( $resources ) :

			$master_api_key              = WC_AM_USER()->get_master_api_key( $user_id ); // @phpstan-ignore-line
			$hide_product_order_api_keys = WC_AM_USER()->hide_product_order_api_keys(); // @phpstan-ignore-line
			$hide_master_api_key         = WC_AM_USER()->hide_master_api_key(); // @phpstan-ignore-line
			$display_instance_id         = get_option( 'woocommerce_api_manager_display_activation_instance_id', 'no' );

			if ( ! $hide_master_api_key ) :

				/**
				 * Filters the user API key heading label.
				 *
				 * @since 3.4.2
				 *
				 * @param string $api_keys_heading
				 */
				$user_license_key_label = (string) apply_filters( 'wc_api_manager_my_account_master_api_key_heading', __( 'User license key', 'woocommerce-api-manager' ) );

				/* translators: Context: Refers to the user license key that can be used to activate any product */
				$tooltip = __( 'Can be used to activate any product', 'woocommerce-api-manager' );

				?>
				<table class="woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_api_manager my_account_orders">
					<thead>
						<tr id="master-api-key-header">
							<th class="master-api-key"><span class="nobr"><?php echo esc_html( $user_license_key_label ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<tr class="order">
							<td class="api-manager-master-api-key">
								<abbr title="<?php echo esc_html( $tooltip ); ?>" style="cursor: help;"><?php echo esc_html( $master_api_key ); ?></abbr>
							</td>
						</tr>
					</tbody>
				</table>
				<?php

			endif;

			?>
			<table class="woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_api_manager my_account_orders">
				<tbody>
					<tr id="product-order-api-key-header">
						<hr>
						<?php

						if ( ! $hide_product_order_api_keys ) :

							/**
							 * Filters the product order license key description.
							 *
							 * @since 3.1.0
							 *
							 * @param string $order_api_key_message
							 */
							$order_api_key_message = (string) apply_filters( 'wc_api_manager_my_account_product_order_api_key_message', __( 'A product license key is used to activate a single product from a single order.', 'woocommerce-api-manager' ) );

							?>
							<td>
								<?php echo esc_html( $order_api_key_message ); ?>
							</td>
							<?php

						endif;

						?>
					</tr>
				</tbody>
			</table>
			<table class="woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_api_manager my_account_orders">
				<thead>
					<?php

					/**
					 * Filters the product title heading.
					 *
					 * @since 3.4.2
					 *
					 * @param string $product_title_heading
					 */
					$product_title_heading = (string) apply_filters( 'wc_api_manager_my_account_product_title_heading', __( 'Product title', 'woocommerce-api-manager' ) );

					/**
					 * Filters the product order license key heading.
					 *
					 * @since 3.4.2
					 *
					 * @param string $product_order_license_key_heading
					 */
					$product_order_license_key_heading = (string) apply_filters( 'wc_api_manager_my_account_product_order_api_key_heading', __( 'Product license key', 'woocommerce-api-manager' ) );

					?>
					<tr>
						<th class="<?php echo esc_attr( 'api-manager-software-product' ); ?>"><span class="nobr"><?php echo esc_html( $product_title_heading ); ?></span></th>
						<th class="<?php echo esc_attr( 'api-manager-software-product' ); ?>"><span class="nobr" style="white-space: nowrap;"><?php esc_html_e( 'Product ID', 'woocommerce-api-manager' ); ?></span></th>
						<?php if ( ! $hide_product_order_api_keys ) : ?>
							<th class="<?php echo esc_attr( 'api-manager-key' ); ?>"><span class="nobr" style="white-space: nowrap;"><?php echo esc_html( $product_order_license_key_heading ); ?></span></th>
						<?php endif; ?>
						<th class="<?php echo esc_attr( 'api-manager-activation' ); ?>"><span class="nobr"><?php esc_html_e( 'Activations', 'woocommerce-api-manager' ); ?></span></th>
						<th class="<?php echo esc_attr( 'api-manager-expire' ); ?>"><span class="nobr"><?php esc_html_e( 'Next payment', 'woocommerce-api-manager' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
				<?php

				foreach ( $resources as $resource ) :

					// refreshing cache here will also delete API cache for activations about to be deleted.
					WC_AM_SMART_CACHE()->delete_activation_api_cache_by_order_id( $resource->order_id ); // @phpstan-ignore-line

					$product_object          = WC_AM_PRODUCT_DATA_STORE()->get_product_object( $resource->product_id ); // @phpstan-ignore-line
					$parent_product_id       = $resource->parent_id;
					$is_api                  = WC_AM_PRODUCT_DATA_STORE()->is_api_product( $parent_product_id ); // @phpstan-ignore-line
					$order_id                = $resource->order_id;
					$order_completed_status  = WC_AM_ORDER_DATA_STORE()->has_status_completed( $order_id ); // @phpstan-ignore-line
					$order_processing_status = WC_AM_ORDER_DATA_STORE()->has_status_processing( $order_id ); // @phpstan-ignore-line

					if ( $is_api && ( $order_completed_status || ( WCAM()->get_grant_access_after_payment() && $order_processing_status ) ) ) :

						$product_title = $resource->product_title;

						$product_id = $resource->product_id;

						// phpcs:ignore
						$order = WC_AM_ORDER_DATA_STORE()->get_order_object( $order_id ); // @phpstan-ignore-line

						if ( WCAM()->get_wc_subs_exist() ) :
							$is_wc_sub = WC_AM_SUBSCRIPTION()->is_wc_subscription( $product_id ); // @phpstan-ignore-line
						else :
							$is_wc_sub = false;

						endif;

						$product_ids[]       = $product_id;
						$total_product_ids   = array_count_values( $product_ids );
						$skip_key_duplicates = $hide_product_order_api_keys && is_array( $product_ids ) && in_array( $product_id, $product_ids, true ) && $total_product_ids[ $product_id ] > 1; // @phpstan-ignore-line

						/**
						 * Toggles whether to show duplicate products under the same API key.
						 *
						 * Originally, this behavior was tied to whether $hide_product_order_api_keys was true, but it would produce confusing duplication, so it was omitted starting from v3.3.3.
						 *
						 * In v3.4.2, this filter was introduced, in case merchants using product variations need to restore the previous behavior.
						 * In v3.6.0, we tweaked the value of $skip_key_duplicates to skip duplicates when using user license keys, but keep showing them when product keys are used instead.
						 *
						 * @since 3.4.2
						 *
						 * @param bool $skip_key_duplicates default true if there are multiple keys sharing the same product
						 * @param bool $hide_master_api_key whether to hide the master API key
						 */
						if ( true === apply_filters( 'wc_api_manager_my_account_hide_duplicate_products', $skip_key_duplicates, $hide_master_api_key ) ) {
							continue; // skip duplicates
						}

						// calculate activations per Product ID # for the master license key
						$master_api_key_resources    = WC_AM_API_RESOURCE_DATA_STORE()->get_active_api_resources( $master_api_key, $product_id ); // @phpstan-ignore-line
						$total_activations_purchased = WC_AM_API_RESOURCE_DATA_STORE()->get_total_activations_purchased( $master_api_key_resources ); // @phpstan-ignore-line
						$total_activations           = WC_AM_API_RESOURCE_DATA_STORE()->get_total_activations( $master_api_key_resources ); // @phpstan-ignore-line
						$product_order_api_key       = WC_AM_API_RESOURCE_DATA_STORE()->get_api_resource_product_order_api_key( $order_id, $product_id ); // @phpstan-ignore-line
						$is_expired                  = WC_AM_ORDER_DATA_STORE()->is_time_expired( $resource->access_expires ); // @phpstan-ignore-line
						$grace_period_expired        = WC_AM_GRACE_PERIOD()->is_expired( $resource->api_resource_id ); // @phpstan-ignore-line

						if ( intval( $resource->sub_id ) !== 0 ) :
							$end_date     = WC_AM_SUBSCRIPTION()->has_end_date_by_sub( $resource->sub_id ) ? WC_AM_SUBSCRIPTION()->get_subscription_end_date_to_display( $order_id ) : ''; // @phpstan-ignore-line
							$next_payment = WC_AM_SUBSCRIPTION()->has_next_payment_by_sub( $resource->sub_id ) // @phpstan-ignore-line
								? esc_html( date_i18n( wc_date_format(), WC_AM_SUBSCRIPTION()->get_subscription_time_by_sub_id( $resource->sub_id, 'next_payment', 'site' ) ) ) // @phpstan-ignore-line
								/* translators: Placeholder: %s - Expiration date */
								: sprintf( esc_html__( 'Pending cancellation on %s', 'woocommerce-api-manager' ), '<br>' . esc_html( $end_date ) );
						elseif ( $resource->access_expires > 0 ) :
							$next_payment = $is_expired && ! $grace_period_expired
								/* translators: Placeholder: %s - Expiration date */
								? sprintf( esc_html__( 'Renewable until: %s', 'woocommerce-api-manager' ), esc_html( WC_AM_FORMAT()->unix_timestamp_to_date( WC_AM_GRACE_PERIOD()->get_expiration( $resource->api_resource_id ), true ) ) ) // @phpstan-ignore-line
								: esc_html( WC_AM_FORMAT()->unix_timestamp_to_date( $resource->access_expires, true ) ); // @phpstan-ignore-line
						else :
							$next_payment = esc_html__( 'Lifetime subscription', 'woocommerce-api-manager' );
						endif;

						if ( is_object( $order ) ) : // WooCommerce Subscriptions keys (display only active subscriptions)

							if ( WCAM()->get_wc_subs_exist() && ! empty( $resource->sub_id ) ) :

								$sub_id = $resource->sub_id;

								$sub_order_key = $resource->sub_order_key;

								?>
								<tr class="order">
									<td class="api-manager-product">
										<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>"><?php echo esc_html( $product_title ); ?></a>
									</td>
									<td class="api-manager-product-id">
										<?php
										echo absint( $product_id );
										?>
									</td>
									<?php if ( ! $hide_product_order_api_keys ) : ?>
										<td class="api-manager-product-order-api-key">
											<?php
											echo esc_attr( $product_order_api_key );

											?>
										</td>
									<?php endif; ?>
									<td class="api-manager-activations">
										<?php

										if ( ! $hide_product_order_api_keys ) :
											/* translators: Placeholder: %1$s - Total activations, %2$s - Total activations purchased */
											printf( esc_html__( '%1$s out of %2$s', 'woocommerce-api-manager' ), absint( $resource->activations_total ), absint( $resource->activations_purchased_total ) );
										else :
											/* translators: Placeholder: %1$s - Total activations, %2$s - Total activations purchased */
											printf( esc_html__( '%1$s out of %2$s', 'woocommerce-api-manager' ), absint( $total_activations ), absint( $total_activations_purchased ) );

										endif;

										?>
									</td>
									<td class="api-manager-expire" style="white-space: nowrap;">
										<?php echo esc_html( $next_payment ); ?>
										<hr>
										<a href="<?php echo esc_url( wc_get_endpoint_url( 'view-subscription', $resource->sub_id, wc_get_page_permalink( 'myaccount' ) ) ); ?>" class="woocommerce-button button view"><?php echo esc_html__( 'View', 'woocommerce-api-manager' ); ?></a>
									</td>
								</tr>
								<?php

							else : // Non-WooCommerce Subscriptions keys

								?>
								<tr class="order">
									<td class="api-manager-product">
										<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>"><?php echo esc_html( $product_title ); ?></a>
									</td>
									<td class="api-manager-product-id">
										<?php echo absint( $product_id ); ?>
									</td>
									<?php if ( ! $hide_product_order_api_keys ) : ?>

										<td class="api-manager-license-key">
											<?php

											if ( $is_expired && ! $grace_period_expired ) :

												echo esc_html( $next_payment );

												$discount = get_option( 'woocommerce_api_manager_manual_renewal_discount' );

												if ( ! empty( $discount ) ) :
													/* translators: Placeholder: %s - Percentage discount amount, e.g. "At a 10% discount" */
													echo '<hr>' . esc_html( sprintf( __( 'At a %s discount.', 'woocommerce-api-manager' ), $discount . '%' ) );

												endif;

											else :

												echo esc_html( $product_order_api_key );

											endif;

											?>
										</td>

									<?php endif; ?>
									<td class="api-manager-activations">
										<?php

										if ( ! $hide_product_order_api_keys ) :
											/* translators: Placeholder: %1$s - Total activations, %2$s - Total activations purchased */
											printf( esc_html__( '%1$s out of %2$s', 'woocommerce-api-manager' ), absint( $resource->activations_total ), absint( $resource->activations_purchased_total ) );
										else :
											/* translators: Placeholder: %1$s - Total activations, %2$s - Total activations purchased */
											printf( esc_html__( '%1$s out of %2$s', 'woocommerce-api-manager' ), absint( $total_activations ), absint( $total_activations_purchased ) );

										endif;

										?>
									</td>
									<td class="api-manager-expire" style="white-space: nowrap;">
										<?php

										$item_quantity = 1;

										if ( $resource->refund_qty < $resource->item_qty ) :
											$item_quantity = $resource->item_qty - $resource->refund_qty;

										endif;

										if ( $is_expired && ! $grace_period_expired ) :

											esc_html_e( 'Expired', 'woocommerce-api-manager' );

											?>
											<hr>
											<p class="order-again">
												<a href="<?php echo esc_url( WC_AM_URL()->api_resource_renewal_url_my_account( $resource->api_resource_id, $product_id, $item_quantity ) ); // @phpstan-ignore-line ?>" class="button"><?php esc_html_e( 'Renew', 'woocommerce-api-manager' ); ?></a>
											</p>
											<?php

										elseif ( WC_AM_RENEW_SUBSCRIPTION()->is_manual_renweal_period( $resource->access_expires, $resource->api_resource_id ) ) : // @phpstan-ignore-line

											echo esc_html( $next_payment );

											?>
											<hr>
											<p class="order-again">
												<a href="<?php echo esc_url( WC_AM_URL()->api_resource_renewal_url_my_account( $resource->api_resource_id, $product_id, $item_quantity ) ); // @phpstan-ignore-line ?>" class="button"><?php esc_html_e( 'Renew', 'woocommerce-api-manager' ); ?></a>
											</p>
											<?php

										else :

											echo esc_html( $next_payment );

										endif;

										?>
									</td>
								</tr>
								<?php

							endif;

							// end if user subscription is active, or if non-subscription API Key has API access
							$activation_data = WC_AM_API_ACTIVATION_DATA_STORE()->get_total_activations_resources_for_api_key_by_product_id( $master_api_key, $product_id ); // @phpstan-ignore-line

							if ( ! empty( $activation_data ) ) :

								foreach ( $activation_data as $key => $activation_info ) :

									// show product order API key
									if ( ! $hide_product_order_api_keys && $activation_info->api_resource_id === $resource->api_resource_id ) :

										?>
										<tr class="api-manager-domains">
											<td colspan="3" style="border-right: 0; padding-left: 5em;">
												<?php

												/**
												 * Filters the API keys delete button label.
												 *
												 * @since 1.0.0
												 *
												 * @param string $my_account_delete_button_label
												 */
												$my_account_delete_button_label = apply_filters( 'wc_api_manager_my_account_delete', __( 'Delete', 'woocommerce-api-manager' ) );

												?>
												<a href="<?php echo esc_url( WC_AM_URL()->delete_api_key_activation_my_account( $activation_info->instance, $activation_info->order_id, $activation_info->sub_parent_id, $activation_info->api_key, $activation_info->product_id, $user_id ) ); // @phpstan-ignore-line ?>" style="float: left;" class="button delete"><?php echo esc_html( $my_account_delete_button_label ); ?></a>
												<?php if ( $display_instance_id === 'both' ) : ?>
													<span style="vertical-align: middle; padding-left: 1.5em;">
														<?php
														echo esc_html__( 'Instance', 'woocommerce-api-manager' ) . ': ' . esc_html( $activation_info->instance );

														?>
													</span>
												<?php endif; ?>
												<?php

												if ( filter_var( $activation_info->object, FILTER_VALIDATE_URL ) ) :

													// if $object is a URL, then remove the trailing slash
													$obj_length = strlen( $activation_info->object );

													$object = ! empty( $activation_info->object ) && substr( $activation_info->object, $obj_length - 1, $obj_length ) === '/' ? substr( $activation_info->object, 0, $obj_length - 1 ) : $activation_info->object;

													?>
													<a style="text-align:left; vertical-align: middle; border-left: 0; padding-left: 1.5em;" href="<?php echo esc_url( $activation_info->object ); ?>" target="_blank"><?php echo esc_html( WC_AM_URL()->remove_url_prefix( $object ) ); // @phpstan-ignore-line ?></a><span style="vertical-align: middle; padding-left: 1.5em;"><?php echo esc_html__( ' Activated on ', 'woocommerce-api-manager' ) . esc_html( WC_AM_FORMAT()->unix_timestamp_to_date( $activation_info->activation_time ) ); // @phpstan-ignore-line ?></span>
													<?php

												else :

													?>
													<span  style="vertical-align: middle;"><?php echo esc_html( $activation_info->object ) . esc_html__( ' Activated on ', 'woocommerce-api-manager' ) . esc_html( WC_AM_FORMAT()->unix_timestamp_to_date( $activation_info->activation_time ) ); // @phpstan-ignore-line ?></span>
													<?php

												endif;

												?>
											</td>
										</tr>
										<?php

									elseif ( $hide_product_order_api_keys ) :

										?>
										<tr class="api-manager-domains">
											<td colspan="4" style="border-right: 0; padding-left: 5em;">
												<?php

												/**
												 * Filters the API keys delete button label.
												 *
												 * @since 1.0.0
												 *
												 * @param string $my_account_delete_button_label
												 */
												$my_account_delete_button_label = (string) apply_filters( 'wc_api_manager_my_account_delete', __( 'Delete', 'woocommerce-api-manager' ) );

												?>
												<a href="<?php echo esc_url( WC_AM_URL()->delete_api_key_activation_my_account( $activation_info->instance, $activation_info->order_id, $activation_info->sub_parent_id, $activation_info->api_key, $activation_info->product_id, $user_id ) ); // @phpstan-ignore-line ?>" style="float: left;" class="button delete"><?php echo esc_html( $my_account_delete_button_label ); ?></a>
												<?php if ( $display_instance_id === 'both' ) : ?>
													<span style="vertical-align: middle; padding-left: 1.5em;">
														<?php
														echo esc_html__( 'Instance', 'woocommerce-api-manager' ) . ': ' . esc_html( $activation_info->instance );

														?>
													</span>
												<?php endif; ?>
												<?php

												if ( filter_var( $activation_info->object, FILTER_VALIDATE_URL ) ) :

													// if $object is a URL, then remove the trailing slash
													$obj_length = strlen( $activation_info->object );

													$object = ! empty( $activation_info->object ) && substr( $activation_info->object, $obj_length - 1, $obj_length ) === '/' ? substr( $activation_info->object, 0, $obj_length - 1 ) : $activation_info->object;

													?>
													<a style="text-align:left; vertical-align: middle; border-left: 0; padding-left: 1.5em;" href="<?php echo esc_url( $activation_info->object ); ?>" target="_blank"><?php echo esc_html( WC_AM_URL()->remove_url_prefix( $object ) ); // @phpstan-ignore-line ?></a><span style="vertical-align: middle; padding-left: 1.5em;"><?php echo esc_html__( ' Activated on ', 'woocommerce-api-manager' ) . esc_html( WC_AM_FORMAT()->unix_timestamp_to_date( $activation_info->activation_time ) ); // @phpstan-ignore-line ?></span>
													<?php

												else :

													?>
													<span style="vertical-align: middle;"><?php echo esc_html( $activation_info->object ) . esc_html__( ' Activated on ', 'woocommerce-api-manager' ) . esc_html( WC_AM_FORMAT()->unix_timestamp_to_date( $activation_info->activation_time ) ); // @phpstan-ignore-line ?></span>
													<?php

												endif;

												?>
											</td>
										</tr>
										<?php

									endif;

								endforeach;

							endif;

						endif;

					endif;

				endforeach;

				?>
				</tbody>
			</table>
			<?php

		else :

			?>
			<div class="woocommerce-Message woocommerce-Message--info woocommerce-info">
				<?php

				/**
				 * Filters the URL to redirect to when no API products are available.
				 *
				 * @since 1.0.0
				 *
				 * @param string $shop_redirect_url URL to redirect to
				 */
				$shop_redirect_url = apply_filters( 'wc_api_manager_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) );

				?>
				<a class="woocommerce-Button button" href="<?php echo esc_url( $shop_redirect_url ); ?>"><?php esc_html_e( 'Go to shop', 'woocommerce-api-manager' ); ?></a>

				<?php esc_html_e( 'No licensing products available yet.', 'woocommerce-api-manager' ); ?>
			</div>
			<?php
		endif;

	else :

		?>
		<div class="woocommerce-Message woocommerce-Message--info woocommerce-info">
			<?php esc_html_e( 'This account has been disabled.', 'woocommerce-api-manager' ); ?>
		</div>
		<?php

	endif;

endif;
