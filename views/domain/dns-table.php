<?php
/**
 * DNS table view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;
?>
<div id="wu-dns-table" class="wu-widget-list-table wu-advanced-filters wu--m-3 wu-mt-2 wu--mb-3 wu-border-0 wu-border-t wu-border-solid wu-border-gray-400">

	<table class="wp-list-table widefat fixed striped wu-border-t-0" v-cloak>

	<thead>
		<tr>
		<th class="wu-w-4/12"><?php esc_html_e('Host', 'ultimate-multisite'); ?></th>
		<th class="wu-w-2/12"><?php esc_html_e('Type', 'ultimate-multisite'); ?></th>
		<th class="wu-w-4/12"><?php esc_html_e('IP / Target', 'ultimate-multisite'); ?></th>
		<th class="wu-w-2/12"><?php esc_html_e('TTL', 'ultimate-multisite'); ?></th>
		</tr>
	</thead>

	<tbody v-if="loading">

		<tr>

		<td colspan="4">

			<?php esc_html_e('Loading DNS entries...', 'ultimate-multisite'); ?>

		</td>

		</tr>

	</tbody>

	<tbody v-if="!loading && error">

		<tr>

		<td colspan="4">

			<div class="wu-mt-0 wu-p-4 wu-bg-red-100 wu-border wu-border-solid wu-border-red-200 wu-rounded-sm wu-text-red-500" v-html="error[0].message"></div>

		</td>

		</tr>

	</tbody>

	<tbody v-if="!loading && !error">

		<tr v-for="dns in results.entries">
		<td>{{ dns.host }}<span v-html="dns.tag" v-if="dns.tag"></span></td>
		<td>{{ dns.type }}</td>
		<td>{{ dns.data }}</td>
		<td>{{ dns.ttl }}</td>
		</tr>

		<tr v-for="dns in results.auth">
		<td>{{ dns.host }}<span v-html="dns.tag" v-if="dns.tag"></span></td>
		<td>{{ dns.type }}</td>
		<td>{{ dns.data }}</td>
		<td>{{ dns.ttl }}</td>
		</tr>

		<tr v-for="dns in results.additional">
		<td>{{ dns.host }}<span v-html="dns.tag" v-if="dns.tag"></span></td>
		<td>{{ dns.type }}</td>
		<td>{{ dns.data }}</td>
		<td>{{ dns.ttl }}</td>
		</tr>

		<tr>
		<td colspan="2"><?php esc_html_e('Your Network IP', 'ultimate-multisite'); ?></td>
		<td colspan="2" class="wu-text-left">{{ results.network_ip }}</td>
		</tr>

	</tbody>

	<tbody v-if="!loading && !error && results.www_entries && results.www_entries.length">

		<tr>
		<td colspan="4" class="wu-font-bold wu-bg-gray-100">
			<?php esc_html_e('www Subdomain Records', 'ultimate-multisite'); ?>
		</td>
		</tr>

		<tr v-for="dns in results.www_entries">
		<td>{{ dns.host }}<span v-html="dns.tag" v-if="dns.tag"></span></td>
		<td>{{ dns.type }}</td>
		<td>{{ dns.data }}</td>
		<td>{{ dns.ttl }}</td>
		</tr>

	</tbody>

	</table>

	<div v-if="!loading && !error && results.warnings && results.warnings.length" v-cloak>

		<div
			v-for="warning in results.warnings"
			class="wu-p-4 wu-m-0 wu-mt-0 wu-bg-yellow-100 wu-text-yellow-700 wu-border-0 wu-border-t wu-border-solid wu-border-yellow-300"
		>
			<span class="dashicons dashicons-warning wu-mr-1"></span>
			{{ warning }}
		</div>

	</div>

</div>
