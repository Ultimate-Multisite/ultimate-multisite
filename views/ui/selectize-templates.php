<?php
/**
 * Selectize templates view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;
?>
<!-- WP User Template -->
<script type="text/html" id="wu-template-user">

	<div class="wu-p-4 wu-block wu-flex wu-items-center">

	<div>

		{{ typeof avatar !== 'undefined' ? avatar : '' }}

	</div>

	<div>

		<span class="wu-block">{{ display_name }} (#{{ ID }})</span>

		<small>{{ typeof user_email !== 'undefined' ? user_email : '<?php esc_html_e('Undefined', 'ultimate-multisite'); ?>' }}</small>

	</div>

	</div>

</script>
<!-- /WP User Template -->

<!-- Customer Template -->
<script type="text/html" id="wu-template-customer">

	<div class="wu-p-4 wu-block wu-flex wu-items-center">

	<div>

		{{ typeof avatar !== 'undefined' ? avatar : '' }}

	</div>

	<div>

		<span class="wu-block">{{ display_name }} (#{{ id }})</span>

		<small>{{ typeof user_email !== 'undefined' ? user_email : '<?php esc_html_e('Undefined', 'ultimate-multisite'); ?>' }}</small>

	</div>

	</div>

</script>
<!-- /Customer Template -->

<!-- Membership Template -->
<script type="text/html" id="wu-template-membership">

	<div class="wu-p-4 wu-block wu-flex wu-items-center">

	<div>

		{{ typeof customer.avatar !== 'undefined' ? customer.avatar : '' }}

	</div>

	<div>

		<span class="wu-block">{{ reference_code }} (#{{ id }})</span>

		<small>Customer: {{ customer.display_name }}</small><br>

		<small>{{ formatted_price }}</small>

	</div>

	</div>

</script>
<!-- /Membership Template -->

<!-- Site Template -->
<script type="text/html" id="wu-template-site">

	<div class="wu-p-4 wu-block wu-flex wu-items-center">

	<div>

		{{ typeof image !== 'undefined' ? image : '' }}

	</div>

	<div>

		<span class="wu-block">{{ title }}</span>

		<small>{{ siteurl }}</small><br>

	</div>

	</div>

</script>
<!-- /Site Template -->

<!-- Setting Template -->
<script type="text/html" id="wu-template-setting">

	<div class="wu-p-4 wu-block wu-flex wu-items-center">

	<div>

		<span class="wu-block">{{ title }}</span>

		<small>{{ section_title }}</small><br>

		<small>{{ desc }}</small>

	</div>

	</div>

</script>
<!-- /Setting Template -->

<!-- Product Template -->
<script type="text/html" id="wu-template-product">

	<div class="wu-p-4 wu-block wu-flex wu-items-center">

	<div>

		{{ typeof image !== 'undefined' ? image : '' }}

	</div>

	<div>

		<span class="wu-block">{{ name }} ({{ type }})</span>

		<small>{{ formatted_price }}</small>

	</div>

	</div>

</script>
<!-- /Product Template -->

<!-- Plan Template -->
<script type="text/html" id="wu-template-plan">

	<div class="wu-p-4 wu-block wu-flex wu-items-center">

	<div>

		{{ typeof image !== 'undefined' ? image : '' }}

	</div>

	<div>

		<span class="wu-block">{{ name }} ({{ type }})</span>

		<small>{{ formatted_price }}</small>

	</div>

	</div>

</script>
<!-- /Plan Template -->

<!-- Jumper Link Template -->
<script type="text/html" id="wu-template-jumper-link">

	<div class="wu-p-4 wu-block wu-flex wu-items-center">

	<div>

		<span class="wu-block">{{ text }}</span>

		<small><?php esc_html_e('Network Admin', 'ultimate-multisite'); ?> &rarr; {{ group }}</small>

	</div>

	</div>

</script>
<!-- /Jumper Link Template -->

<!-- Discount Code Template -->
<script type="text/html" id="wu-template-discount_code">

	<div class="wu-p-4 wu-block wu-flex wu-items-center">

	<div>

		<span class="wu-block">{{ code }} (#{{ id }})</span>

		<small>{{ discount_description }}</small>

	</div>

	</div>

</script>
<!-- /Discount Code Template -->

<!-- Domain Template -->
<script type="text/html" id="wu-template-domain">

	<div class="wu-p-4 wu-block wu-flex wu-items-center">

	<div>

		<span class="wu-block">{{ domain }}</span>

		<small><?php esc_html_e('Mapped Domain', 'ultimate-multisite'); ?></small>

	</div>

	</div>

</script>
<!-- /Domain Template -->

<!-- Webhook Template -->
<script type="text/html" id="wu-template-webhook">

	<div class="wu-p-4 wu-block wu-flex wu-items-center">

	<div>

		<span class="wu-block">{{ name }}</span>

		<small>{{ webhook_url }}</small>

	</div>

	</div>

</script>
<!-- /Webhook Template -->

<!-- Broadcast Template -->
<script type="text/html" id="wu-template-broadcast">

	<div class="wu-p-4 wu-block wu-flex wu-items-center">

	<div>

		<span class="wu-block">{{ title }}</span>

	</div>

	</div>

</script>
<!-- /Broadcast Template -->

<!-- Checkout Form Template -->
<script type="text/html" id="wu-template-checkout_form">

	<div class="wu-p-4 wu-block wu-flex wu-items-center">

	<div>

		<span class="wu-block">{{ name }}</span>

		<small>{{ slug }}</small>

	</div>

	</div>

</script>
<!-- /Checkout Form Template -->

<!-- Page Template -->
<script type="text/html" id="wu-template-page">

	<div class="wu-p-4 wu-block wu-flex wu-items-center">

	<div>

		<span class="wu-block">{{ post_title }} (#{{ ID }})</span>

		<small>/{{ post_name }} - {{ post_status.charAt(0).toUpperCase() + post_status.slice(1) }}</small>

	</div>

	</div>

</script>
<!-- /Page Template -->

<!-- Default Template -->
<script type="text/html" id="wu-template-default">

	<div class="wu-p-4 wu-block wu-flex wu-items-center">

	<div v-if="typeof avatar !== 'undefined' && avatar">

		{{ avatar }}

	</div>

	<div>

		<span class="wu-block">{{ label ?? id }} (#{{ id }})</span>

		<small>{{ description ?? id }}</small>

	</div>

	</div>

</script>
<!-- /Default Template -->

<!-- Nothing Found Template -->
<script type="text/html" id="wu-template-none">

	<div class="wu-p-4 wu-block wu-flex wu-items-center">

	<?php esc_html_e('Nothing Found...', 'ultimate-multisite'); ?>

	</div>

</script>
<!-- /Nothing Found Template -->

<?php

	/**
	 * Allow plugin developers to add more selectize templates.
	 *
	 * @since 2.0.0
	 */
	do_action('wu_selectize_templates');

?>
