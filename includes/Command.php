<?php

namespace WP_CLI\Hammer;

use WP_CLI;
use WP_CLI\CommandWithDBObject;
use WP_CLI\Hammer\Prune;
use WP_CLI\Hammer\ContentFormatter;

/**
 * wp hammer is a command to clean your environment of personally identifiable information, remove extra content and prepare it for a staging / development environment.
 *
 */
class Command extends CommandWithDBObject {

	protected $settings;

	/**
	 * Clean your site to change passwords, email addresses and remove unneeded posts.
	 *
	 * ## OPTIONS
	 *
	 * [-f <format1>,<format2>,...<formatN>]
	 * : Which tables and/or columns to process and how to generate the new content.
	 *
	 * [-l <limit1>,<limit2>,...<limitN>]
	 * : Which tables to limit, the maximum number of rows to keep and the method of determining which rows to keep.
	 *
	 * ## EXAMPLES
	 *
	 *     wp hammer -l users=5
	 *     wp hammer -f posts.post_author=random,users.user_pass=auto,users.user_email='test+user__ID__@example.com'
	 *     wp hammer -f posts.post_title=ipsum,posts.post_content=markov -l users=10,posts=100.post_date
	 *
	 * @synopsis [<-f>] [<formats>] [<-l>] [<limits>]
	 */
	function __invoke( $args = array(), $assoc_args = array() ) {
		if ( ! count( $args ) && ! count( $assoc_args ) ) {
			$this->show_usage();
			return;
		}
		ob_end_flush();
		// All content manipulators are stored in pruners, formatters, generators folders. They are namespaced, but not in classes, so we can't use
		// the autoloader for them.
		// Also, because they need add_action/add_filter to load, we can only include them after WP has loaded, so it's not part of the autoloader.
		$content_manipulators = glob( __DIR__ . '/{pruners,formatters,generators}/*.php', GLOB_BRACE);

		foreach ( $content_manipulators as $content_manipulator ) {
			require_once $content_manipulator;
		}

		$this->settings = new Settings();
		$this->settings->parse_arguments( $args, $assoc_args );
		$this->run();
		ob_start();

	}

	/**
	 * Execute the WP Hammer command.
	 */
	function run() {
		global $wpdb;
		if ( $this->settings->dry_run ) {
			WP_CLI::line( 'Dry run enabled, not modifying the database' );
		}
		if ( false !== $this->settings->limits && ! is_null( $this->settings->limits ) ) {
			$prune = new Prune( $this->settings->limits, $this->settings->dry_run );
			$prune->run();
		}
		if ( false !== $this->settings->formats && ! is_null( $this->settings->formats ) ) {
			$formats = new ContentFormatter( $this->settings->formats, $this->settings->dry_run );
			$formats->run();
		}
	}

	function show_usage() {
		\WP_CLI::line( "usage: wp hammer -f <format1>,<format2>,...<formatN>" );
		\WP_CLI::line( "   or: wp hammer -l <limit1>,<limit2>,...<limitN>" );
		\WP_CLI::line( "   or: wp hammer -l <limit1>,...<limitN> -f <format1>,...<formatN>" );
		\WP_CLI::line( "" );
		\WP_CLI::line( "See 'wp help hammer' for more information on usage." );

	}
}

