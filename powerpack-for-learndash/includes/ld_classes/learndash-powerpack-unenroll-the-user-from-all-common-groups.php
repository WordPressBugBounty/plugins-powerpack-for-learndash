<?php
/**
 * Unenroll The User From All Common Groups
 *
 * @version 1.0.0
 * @package LearnDash PowerPack
 */

defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'LearnDash_PowerPack_Unenroll_The_User_From_All_Common_Groups', false ) ) {
	/**
	 * LearnDash_PowerPack_Unenroll_The_User_From_All_Common_Groups Class.
	 */
	class LearnDash_PowerPack_Unenroll_The_User_From_All_Common_Groups {
		/**
		 * Current class name.
		 *
		 * @var string
		 */
		public $current_class = '';

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->current_class = get_class( $this );

			if ( 'active' === learndash_powerpack_is_current_class_active( $this->current_class ) ) {
				add_action(
					'learndash_user_course_access_expired',
					[ $this, 'learndash_user_course_access_expired_func' ],
					20,
					2
				);
			}
		}

		/**
		 * Unenroll the user from all common groups when course access is expired.
		 *
		 * Hook: learndash_user_course_access_expired
		 *
		 * @param int $user_id The ID of the user.
		 * @param int $course_id The ID of the course.
		 * @return void
		 */
		public function learndash_user_course_access_expired_func( $user_id = 0, $course_id = 0 ) {
			$user_id   = absint( $user_id );
			$course_id = absint( $course_id );
			
			if ( ( ! empty( $user_id ) ) && ( ! empty( $course_id ) ) ) {

				// Get all the Groups the User is enrolled in.
				$user_group_ids = $this->get_user_group_ids( $user_id );
			
				if ( ! empty( $user_group_ids ) ) {
				
					// Get all the Groups the Course is enrolled in.
					$course_group_ids = $this->get_course_group_ids( $course_id );

					if ( ! empty( $course_group_ids ) ) {

						// Get the common Groups that both the User and Course are enrolled in.
						$common_group_ids = array_values( array_intersect( $user_group_ids, $course_group_ids ) );
						
						if ( ! empty( $common_group_ids ) ) {
							foreach ( $common_group_ids as $group_id ) {
								$group_id = absint( $group_id );

								// Only proceed when we have a valid group ID.
								if ( ! empty( $group_id ) ) {
									// First check if the Group checkbox "Enable automatic group enrollment when a user enrolls into any associated group course" is set.
									$group_auto_enroll_all_courses = get_post_meta( $group_id, 'ld_auto_enroll_group_courses', true );
									if ( 'yes' === $group_auto_enroll_all_courses ) {

										// Unenroll the User from the Group.
										ld_update_group_access( $user_id, $group_id, true );
									} else {
										// Else check if the Course is one of the auto-enroll courses.
										$group_auto_enroll_courses_ids = get_post_meta( $group_id, 'ld_auto_enroll_group_course_ids', true );
										$group_auto_enroll_courses_ids = $this->normalize_id_list( $group_auto_enroll_courses_ids );
										
										if ( ! empty( $group_auto_enroll_courses_ids ) ) {
											if ( in_array( $course_id, $group_auto_enroll_courses_ids, true ) ) {

												// Unenroll the User from the Group.
												ld_update_group_access( $user_id, $group_id, true );
											}
										}
									}
								}	
							}
						}
					}
				}
			}
		}

		/**
		 * Normalize a value into an array of positive integer IDs.
		 *
		 * Supports arrays, CSV strings, numeric strings, serialized values,
		 * and arrays of WP_Post objects.
		 *
		 * @param mixed $value Raw value.
		 * @return int[] Normalized list of unique IDs.
		 */
		private function normalize_id_list( $value ) {
			$ids = array();

			$value = maybe_unserialize( $value );

			// Convert WP_Post[] into ID[] when needed.
			if ( is_array( $value ) && ( ! empty( $value ) ) ) {
				$first = reset( $value );

				if ( is_object( $first ) && isset( $first->ID ) ) {
					$value = wp_list_pluck( $value, 'ID' );
				}
			}

			// Only parse when the value is a supported type. Otherwise keep as empty array.
			if ( is_array( $value ) || is_string( $value ) || is_int( $value ) ) {
				$ids = wp_parse_id_list( $value );
				$ids = array_values( array_unique( $ids ) );
			}

			return $ids;
		}

		/**
		 * Get all the Groups the User is enrolled in.
		 *
		 * @param int $user_id The ID of the user.
		 * @return int[] The Group IDs.
		 */
		private function get_user_group_ids( $user_id ) {
			$user_id = absint( $user_id );
			
			$groups = array();
			$ids = array();

			if ( ! empty( $user_id ) ) {

				// LearnDash function names/return types can vary by version.
				if ( function_exists( 'learndash_get_users_group_ids' ) ) {
					$groups = learndash_get_users_group_ids( $user_id );
				} elseif ( function_exists( 'learndash_get_users_groups' ) ) {
					$groups = learndash_get_users_groups( $user_id );
				}

				/**
				 * Allow override for compatibility if LearnDash changes internals.
				 *
				 * @param mixed $groups  Raw groups value.
				 * @param int   $user_id User ID.
				 */
				$groups = apply_filters( 'learndash_powerpack_user_group_ids_raw', $groups, $user_id );

				$ids = $this->normalize_id_list( $groups );
			}

			return $ids;
		}

		/**
		 * Get all the Groups the Course is enrolled in.
		 *
		 * @param int $course_id The ID of the course.
		 * @return int[] The Group IDs.
		 */
		private function get_course_group_ids( $course_id ) {
			$course_id = absint( $course_id );

			$groups = array();
			$ids = array();

			if ( ! empty( $course_id ) ) {

				// LearnDash function names/return types can vary by version.
				if ( function_exists( 'learndash_get_course_group_ids' ) ) {
					$groups = learndash_get_course_group_ids( $course_id );
				} elseif ( function_exists( 'learndash_get_course_groups' ) ) {
					$groups = learndash_get_course_groups( $course_id );
				}

				$groups = apply_filters( 'learndash_powerpack_course_group_ids_raw', $groups, $course_id );

				$ids = $this->normalize_id_list( $groups );
			}

			return $ids;
		}

		/**
		 * Add the class details.
		 *
		 * @return array The class details.
		 */
		public function learndash_powerpack_class_details() {
			$ld_type           = esc_html__( 'group', 'learndash-powerpack' );
			$class_title       = esc_html__( 'Unenroll the User', 'learndash-powerpack' );
			$class_description = esc_html__( 'Unenroll the User from All common Groups when the Course access is expired.', 'learndash-powerpack' );

			return [
				'title'       => $class_title,
				'ld_type'     => $ld_type,
				'description' => $class_description,
				'settings'    => false,
			];
		}
	}

	new LearnDash_PowerPack_Unenroll_The_User_From_All_Common_Groups();
}
