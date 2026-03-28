<?php
/**
 * Activity stream view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

?>
<div class="wu-styling">

	<div id="activity-stream-content">

	<div v-if="loading"
		class="wu-text-center wu-bg-gray-100 wu-rounded wu-uppercase wu-font-semibold wu-text-xs wu-text-gray-700 wu-p-4">
		<span class="wu-blinking-animation"><?php esc_html_e('Loading...', 'ultimate-multisite'); ?></span>
	</div>

	<div v-if='!queried.count && !loading' v-cloak class='wu-feed-loading wu-mb-6'>
		<?php esc_html_e('No more items to display', 'ultimate-multisite'); ?>
	</div>

	<div v-if="!loading" class="wu-widget-inset">

		<ul class="wu-m-0 wu-p-0 wu-divide-gray-200" v-cloak>

		<li 
			class="wu-m-0"
			:class="index > 0 ? 'wu-border-solid wu-border-0 wu-border-t wu-border-gray-300' : ''" 
			v-for="(event, index) in queried.events"
		>
			<a :href="'<?php echo esc_attr(wu_network_admin_url('wp-ultimo-view-event', ['id' => ''])); ?>=' + event.id" class="wu-block hover:wu-bg-gray-50">
			<div class="wu-px-4 wu-py-4 wu-flex wu-items-center">
				<div class="wu-min-w-0 wu-flex-1 sm:wu-flex sm:wu-items-center">
				<div class="wu-mt-4 wu-flex-shrink-0 sm:wu-mt-0 sm:wu-mr-4">
					<div class="wu-flex wu-relative">

					<img v-if="event.author.avatar"
						class="wu-inline-block wu-h-7 wu-w-7 wu-rounded-full wu-ring-2 wu-ring-white" 
						:src="event.author.avatar"
						:alt="event.author.display_name"
					>

					<div v-if="!event.author.avatar" class="wu-flex wu-h-7 wu-w-7 wu-rounded-full wu-ring-2 wu-ring-white wu-bg-gray-300 wu-items-center wu-justify-center">
						<span class="dashicons-wu-tools wu-text-gray-700 wu-text-xl"></span>
					</div>

					<span
						role="tooltip"
						:aria-label="event.initiator.charAt(0).toUpperCase() + event.initiator.slice(1) + ' - ' + event.severity_label"
						class="wu-absolute wu-rounded-full wu--mb-2 wu--mr-2 wu-flex wu-items-center wu-justify-center wu-font-mono wu-bottom-0 wu-right-0 wu-font-bold wu-h-3 wu-w-3 wu-uppercase wu-text-2xs wu-p-1 wu-border-solid wu-border-2 wu-border-white"
						:class="event.severity_classes"
					>
						{{ event.severity_label[0] }}
					</span>

					</div>
				</div>
				<div>
					<div class="wu-flex wu-font-medium wu-text-gray-700 wu-truncate">
					<p class="wu-m-0 wu-p-0 wu-capitalize">{{ event.object_type }}</p>
					<p class="wu-p-0 wu-m-0 wu-ml-1 wu-font-normal wu-text-gray-600">
						<?php // translators: %s: the event name ?>
						<?php printf(esc_html__('with %s', 'ultimate-multisite'), '{{ event.slug }}'); ?>
					</p>
					</div>
					<div class="wu-mt-1">
					<div class="wu-text-sm wu-text-gray-600">
						<!-- Heroicon name: calendar -->
						<p class="wu-p-0 wu-m-0">
						<span v-html="event.message"></span>
						<span class="wu-text-gray-700 wu-ml-2"><span class="dashicons-wu-clock wu-mr-1 wu-align-middle"></span>{{ $moment(event.date_created, "YYYYMMDD").fromNow() }}</span>
						<?php // translators: %s name of the author ?>
						<span v-if="event.author.display_name" class="wu-text-gray-700"><?php printf(esc_html__('by %s', 'ultimate-multisite'), '{{ event.author.display_name }}'); ?></span>
						</p>
					</div>
					</div>
				</div>
				</div>
				<div class="wu-ml-auto wu-flex-shrink-0">
				<svg class="wu-h-5 wu-w-5 wu-text-gray-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
					<path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
				</svg>
				</div>
			</div>
			</a>
		</li>

		</ul>

		<div v-cloak class="wu-p-4 wu-bg-gray-100 wu-border-solid wu-border-0 wu-border-t wu-border-gray-300">

		<ul
			v-if='!loading'
			class='wu-feed-pagination wu-m-0 wu-flex wu-justify-between'>
			<li class="wu-w-1/3 wu-m-0">
			<a href="#" class="wu-block" v-on:click.prevent="refresh">
				<?php esc_html_e('Refresh', 'ultimate-multisite'); ?>
			</a>
			</li>
			<li v-if="page > 1" class="wu-w-1/3 wu-text-center wu-m-0">
			<a href="#" v-on:click.prevent="navigatePrev" class="wu-block">
				&larr; <?php esc_html_e('Previous Page', 'ultimate-multisite'); ?>
			</a>
			</li>
			<li v-if="hasMore() && !loading" class="wu-w-1/3 wu-text-right wu-m-0">
			<a href="#" v-on:click.prevent="navigateNext" class="wu-block">
				<?php esc_html_e('Next Page', 'ultimate-multisite'); ?>
				&rarr;
			</a>
			</li>
		</ul>

		</div>

	</div>

	</div>

</div>

<?php
// wu-functions must be a dependency so window.wu_moment is defined before activity-stream.js runs.
wp_enqueue_script('wu-activity-stream', wu_get_asset('activity-stream.js', 'js'), ['wu-vue', 'wu-functions'], wu_get_version(), true);
wp_add_inline_script('wu-activity-stream', 'var wu_activity_stream_nonce = "' . esc_js(wp_create_nonce('wu_activity_stream')) . '";', 'before');
?>
