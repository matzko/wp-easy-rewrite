<?php 

/**
 * Copyright 2011 Austin Matzko
 *
 * Licensed under the GPL 2
 * Version 1.1
 */

if ( ! class_exists( 'Easy_WP_Rewrite_Handler' ) ) {
	class Easy_WP_Rewrite_Handler {

		private $_rules_callbacks_query_vars; 
		public $rules; // the array of rewrite rules associated with callbacks

		/**
		 * Map a group of rewrite rules to a callback
		 *
		 * @param array $rules An array with keys as rewrite regex rules and values as callbacks
		 */
		public function __construct($rules = array())
		{
			$this->rules = $rules;
			add_action('init', array(&$this, 'event_init'));
			add_action('template_redirect', array(&$this, 'event_template_redirect'));
		}

		public function event_init()
		{
			global $wp, $wp_rewrite;
			foreach( (array) $this->rules as $rule => $callback ) {
				$query_vars = $this->_build_query_variables($rule);
				$this->_rules_callbacks_query_vars[$rule] = array(
					$callback,
					$query_vars,
				);

				$matched_vars = array();
				foreach( $query_vars as $match => $var ) {
					$wp->add_query_var($var);
					if ( empty( $match ) ) {
						$matched_vars[] = $var . '=1'; 
					} else {
						$matched_vars[] = $var . '=$matches[' . $match . ']'; 
					}
				}
				
				$matched_string = $wp_rewrite->index . '?' . implode('&', $matched_vars);
				add_rewrite_rule($rule, $matched_string, 'top');
			}
		}

		/**
		 * Trigger callbacks when appropriate rewrite rules are matched, during the template_redirect event.
		 */
		public function event_template_redirect()
		{
			global $wp;
			if ( isset( $this->_rules_callbacks_query_vars[$wp->matched_rule] ) ) {
				$callback_args = array();
				$callback = $this->_rules_callbacks_query_vars[$wp->matched_rule][0];
				foreach( (array) $this->_rules_callbacks_query_vars[$wp->matched_rule][1] as $key => $value ) {
					if ( ! empty( $key ) ) {
						$callback_args[$key] = get_query_var($value);
					}
				}
				if ( is_callable($callback) ) {
					ksort($callback_args);
					call_user_func_array($callback, $callback_args);
				}
			}
		}

		/**
		 * Flush the saved WordPress rewrite rules and rebuild them
		 */
		function flush_rewrite_rules()
		{
			global $wp_rewrite;
			$this->event_init();
			$wp_rewrite->flush_rules();
		}
		/**
		 * Generate query variables from a regex string
		 *  
		 * @param string $regex The regex string
		 * @return array An array of matches to query variables (0 for the whole string, 1 for the first match, etc.)
		 */
		private function _build_query_variables($regex = '')
		{
			$vars = array();
			// assume there are as many matching patterns as opening parentheses
			$match_count = 0;
			$_ignore = str_replace('(', 'X', $regex, $match_count);
			while ( 0 <= $match_count ) {
				$vars[$match_count] = substr(md5($regex . 'easy-rewrite' . $match_count), 0, 10);
				$match_count--;
			}

			return $vars;
		}
	}
}

