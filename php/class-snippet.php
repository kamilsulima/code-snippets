<?php

/**
 * A snippet object
 *
 * @since 2.4.0
 * @package Code_Snippets
 *
 * @property int    $id             The database ID
 * @property string $name           The display name
 * @property string $desc           The formatted description
 * @property string $code           The executable code
 * @property array  $tags           An array of the tags
 * @property int    $scope          The scope number
 * @property bool   $active         The active status
 * @property bool   $network        true if is multisite-wide snippet, false if site-wide
 * @property bool   $shared_network Whether the snippet is a shared network snippet
 *
 * @property-read array  $tags_list The tags in string list format
 * @property-read string $scope_name The name of the scope
 */
class Snippet {

	/**
	 * The snippet metadata fields.
	 * Initialized with default values.
	 * @var array
	 */
	private $fields = array(
		'id' => 0,
		'name' => '',
		'desc' => '',
		'code' => '',
		'tags' => array(),
		'scope' => 0,
		'active' => false,
		'network' => null,
		'shared_network' => null,
	);

	private $field_aliases = array(
		'description' => 'desc',
	);

	/**
	 * Set all of the snippet fields from an array or object.
	 * Invalid fields will be ignored
	 *
	 * @param array|object $fields List of fields
	 */
	public function set_fields( $fields ) {

		/* Only accept arrays or objects */
		if ( ! $fields || is_string( $fields ) ) {
			return;
		}

		/* Convert objects into arrays */
		if ( is_object( $fields ) ) {
			$fields = get_object_vars( $fields );
		}

		/* Loop through the passed fields and set them */
		foreach ( $fields as $field => $value ) {
			$this->set_field( $field, $value );
		}
	}

	/**
	 * Constructor function
	 * @param array|object $fields Initial snippet fields
	 */
	public function __construct( $fields = null ) {
		$this->set_fields( $fields );
	}

	/**
	 * Internal function for validating the name of a field
	 *
	 * @param string $field A field name
	 *
	 * @return string The validated field name
	 */
	private function validate_field_name( $field ) {

		/* If a field alias is set, remap it to the valid field name */
		if ( isset( $this->field_aliases[ $field ] ) ) {
			return $this->field_aliases[ $field ];
		}

		return $field;
	}

	/**
	 * Check if a field is set
	 * @param  string  $field The field name
	 * @return bool           Whether the field is set
	 */
	public function __isset( $field ) {
		$field = $this->validate_field_name( $field );
		return isset( $this->fields[ $field ] ) || method_exists( $this, 'get_' . $field );
	}

	/**
	 * Retrieve a field's value
	 * @param  string $field The field name
	 * @return mixed         The field value
	 */
	public function __get( $field ) {
		$field = $this->validate_field_name( $field );

		if ( method_exists( $this, 'get_' . $field ) ) {
			return call_user_func( array( $this, 'get_' . $field ) );
		}

		return $this->fields[ $field ];
	}

	/**
	 * Set the value of a field
	 *
	 * @throws ErrorException When an invalid $field is undefined for the class
	 *
	 * @param string $field The field name
	 * @param mixed  $value The field value
	 */
	public function __set( $field, $value ) {
		$field = $this->validate_field_name( $field );

		if ( ! $this->is_allowed_field( $field ) ) {
			throw new ErrorException( 'Trying to set invalid property on Snippets class: ' . $field, 0, E_WARNING );
		}

		/* Check if the field value should be filtered */
		if ( method_exists( $this, 'prepare_' . $field ) ) {
			$value = call_user_func( array( $this, 'prepare_' . $field ), $value );
		}

		$this->fields[ $field ] = $value;
	}

	/**
	 * Retrieve the list of fields allowed to be written to
	 *
	 * @return array
	 */
	public function get_allowed_fields() {
		return array_keys( $this->fields ) + array_keys( $this->field_aliases );
	}

	/**
	 * Determine whether a field is allowed to be written to
	 *
	 * @param string $field The field name
	 *
	 * @return bool true if the is allowed, false if invalid
	 */
	public function is_allowed_field( $field ) {
		return array_key_exists( $field, $this->fields ) || array_key_exists( $field, $this->field_aliases );
	}

	/**
	 * Safely set the value for a field.
	 * If the field name is invalid, false will be returned instead of an error thrown
	 *
	 * @param string $field The field name
	 * @param mixed  $value The field value
	 *
	 * @return bool true if the field was set successfully, false if the field name is invalid
	 */
	public function set_field( $field, $value ) {
		if ( ! $this->is_allowed_field( $field ) ) {
			return false;
		}

		$this->__set( $field, $value );
		return true;
	}

	/**
	 * Prepare the ID by ensuring it is an absolute integer
	 * @param  int $id
	 * @return int
	 */
	private function prepare_id( $id ) {
		return absint( $id );
	}

	/**
	 * Prepare the code by removing php tags from beginning and end
	 * @param  string $code
	 * @return string
	 */
	private function prepare_code( $code ) {

		/* Remove <?php and <? from beginning of snippet */
		$code = preg_replace( '|^[\s]*<\?(php)?|', '', $code );

		/* Remove ?> from end of snippet */
		$code = preg_replace( '|\?>[\s]*$|', '', $code );

		return $code;
	}

	/**
	 * Prepare the scope by ensuring that it is a valid number
	 * @param  int $scope The field as provided
	 * @return int        The field in the correct format
	 */
	private function prepare_scope( $scope ) {
		$scope = (int) $scope;

		if ( in_array( $scope, array( 0, 1, 2 ) ) ) {
			return $scope;
		}

		return $this->fields['scope'];
	}

	/**
	 * Prepare the snippet tags by ensuring they are in the correct format
	 * @param  string|array $tags The tags as provided
	 * @return array              The tags as an array
	 */
	private function prepare_tags( $tags ) {
		return code_snippets_build_tags_array( $tags );
	}

	/**
	 * Prepare the active field by ensuring it is the correct type
	 * @param  bool|int $active The field as provided
	 * @return bool             The field in the correct format
	 */
	private function prepare_active( $active ) {
		if ( is_bool( $active ) ) {
			return $active;
		}

		return $active ? true : false;
	}

	/**
	 * If $network is anything other than true, set it to false
	 * @param  bool $network The provided field
	 * @return bool          The filtered field
	 */
	private function prepare_network( $network ) {

		if ( null === $network && function_exists( 'get_current_screen' ) && $screen = get_current_screen() ) {
			return $screen->in_admin( 'network' );
		}

		return true === $network;
	}

	/**
	 * Retrieve the tags in list format
	 * @return string The tags seperated by a comma and a space
	 */
	private function get_tags_list() {
		return implode( ', ', $this->fields['tags'] );
	}

	/**
	 * Retrieve the string representation of the scope
	 * @param  string $default The name to use for the default scope
	 * @return string          The name of the scope
	 */
	private function get_scope_name( $default = 'global' ) {

		switch ( intval( $this->fields['scope'] ) ) {
			case 1:
				return 'admin';
			case 2:
				return 'front-end';
			default:
			case 0:
				return $default;
		}
	}

	/**
	 * Determine if the snippet is a shared network snippet
	 * @return bool
	 */
	private function get_shared_network() {

		if ( isset( $this->fields['shared_network'] ) ) {
			return $this->fields['shared_network'];
		}

		if ( ! is_multisite() || ! $this->fields['network'] ) {
			$this->fields['shared_network'] = false;
		} else {
			$shared_network_snippets = get_site_option( 'shared_network_snippets', array() );
			$this->fields['shared_network'] = in_array( $this->fields['id'], $shared_network_snippets );
		}

		return $this->fields['shared_network'];
	}
}
