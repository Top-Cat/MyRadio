<?php
/**
 * MyRadio_JsonFormLoader class.
 * @version 20131002
 * @author Matt Windsor <matt.windsor@ury.org.uk>
 * @package MyRadio_Core
 */

/**
 * A loader for forms written declaratively in JSON format.
 *
 * The format is thus:
 * {
 *   'name': 'form_name',
 *   'module': 'module_name',
 *   'action': 'form_completion_action',
 *   'options': { ... },
 *   'fields': {
 *     'field_name': {
 *       'type': 'constant name without TYPE_, case insensitive',
 *       'label': 'etc etc',
 *       'options': { ... }
 *     }
 *   }
 * }
 *
 * @version 20130428
 * @author Matt Windsor <matt.windsor@ury.org.uk>
 * @package MyRadio_Core
 */
class MyRadio_JsonFormLoader {
  /**
   * The prefix for strings that signify special processing directives.
   * @const string
   */
  const SPECIAL_PREFIX = '!';

  /**
   * The name of the current MyRadio module.
   * @var string
   */
  private $module;

  /**
   * The internal form representation, ready to render to a form.
   * @var array
   */
  private $form_array;

  /**
   * A ReflectionClass used to introspect the form field class.
   * @var ReflectionClass
   */
  private $rc;


  /**
   * Constructs a new MyRadio_JsonFormLoader.
   *
   * @param string $module  The name of the calling MyRadio module.
   *
   * @return MyRadio_JsonFormLoader
   */
  public function __construct($module) {
    $this->module = $module;
    $this->form_array = null;
    $this->rc = new ReflectionClass('MyRadioFormField');
  }

  /**
   * Infers a type constant (TYPE_XYZ) from a case insensitive name.
   *
   * @param string $name  The name of the type constant.
   * @return int  The type constant.
   */
  private function getTypeConstant($name) {
    return $this->rc->getconstant('TYPE_' . strtoupper($name));
  }

  /**
   * Adds a section to a form.
   *
   * @param array     $field    The field description array to compile.
   *                            This should be an array of form items in this
   *                            section.
   * @param MyRadioForm $form     The form to add the compiled field to.
   * @param array     $options  The array of options to the !section directive.
   *                            This should contain two numeric indices:
   *                            the repeat directive and the section name.
   * @param array     $binds    The current variable bindings, for use with the
   *                            !bind directive.
   * @return Nothing.
   */
  private function addSectionToForm($field, $form, $options, $binds) {
    // Section header
    $this->addFieldToForm(
      base64_encode($options[1]), // Lazy way of generating valid name
      [
        'type' => 'section',
        'label' => $options[1],
        'options' => []
      ], 
      $form,
      $binds
    );

    // There isn't any section nesting, so just add the section contents in
    // below the header.
    foreach($field as $name => $infield) {
      $this->addFieldToForm($name, $infield, $form, $binds);
    }
  }

  /**
   * Adds a repeated field block to a form.
   *
   * @param array     $field    The field description array to compile.
   *                            This should be an array of form items to
   *                            repeat.
   * @param MyRadioForm $form     The form to add the compiled field to.
   * @param array     $options  The array of options to the !repeat directive.
   *                            This should contain three numeric indices:
   *                            the repeat directive, the repetition ID start,
   *                            and end.
   * @param array     $binds    The current variable bindings, for use with the
   *                            !bind directive.
   * @return Nothing.
   */
  private function addRepeatedFieldsToForm($field, $form, $options, $binds) {
    $start = intval($options[1]);
    $end = intval($options[2]);

    if ($start >= $end) {
      throw new MyRadioException(
        'Start and end wrong way around on !repeat: start=' .
        $start .
        ', end=' .
        $end .
        '.'
      );
    }
    for ($i = $start; $i <= $end; $i++) {
      foreach ($field as $name => $infield) {
        $inbinds = array_merge(
          ['repeater' => strval($i)],
          $binds
        );
        $this->addFieldToForm($name . $i, $infield, $form, $inbinds);
      }
    }
  }

  /**
   * Performs binding of !bind(foo) strings in a field description to their
   * entries in the binding arry.
   *
   * @param array $field  The field description array to bind on.
   * @param array $binds  The current variable bindings, for use with the
   */
  private function doBinding(&$field, $binds) {
    foreach($field as $key => &$value) {
      if (strpos($value, self::SPECIAL_PREFIX) === 0) {
        $matches = [];
        if (preg_match('/^!bind\( *(\w+) *\)$/', $value, $matches)) {
          if (array_key_exists($matches[1], $binds)) {
            $value = $binds[$matches[1]];
          } else {
            throw new MyRadioException(
              'Tried to !bind to unbound form variable: ' . $matches[1] . '.'
            );
          }
        }
        // Recursively bind
        if (is_array($value)) {
          $this->doBinding($value, $binds);
        }
      }
    }
  }
  
  /**
   * Compiles a special field description into a field and adds it to the given
   * form.
   *
   * @param string    $name   The special field directive stored as field name.
   * @param array     $field  The field description array to compile.
   * @param MyRadioForm $form   The form to add the compiled field to.
   * @param array     $binds  The current variable bindings, for use with the
   *                          !bind directive.
   * @return Nothing.
   */
  private function addSpecialFieldToForm($name, $field, $form, $binds) {
    $matches = [];
    // TODO: Replace regexes with something a bit less awful.
    $operators = [
      '/^!repeat\( *([0-9]+) *, *([0-9]+) *\)$/' => 'addRepeatedFieldsToForm',
      '/^!section\((.*)\)$/' => 'addSectionToForm'
    ];
    $done = false;

    foreach ($operators as $regex => $callback) {
      if (preg_match($regex, $name, $matches)) {
        call_user_func([$this, $callback], $field, $form, $matches, $binds);
        $done = true;
      }
    }

    if (!$done) {
      throw new MyRadioException('Illegal special field name: ' . $name . '.');
    }
  }

  /**
   * Compiles a regular field description into a field and adds it to the given
   * form.
   *
   * @param string    $name   The name of the field.
   * @param array     $field  The field description array to compile.
   * @param MyRadioForm $form   The form to add the compiled field to.
   * @param array     $binds  The current variable bindings, for use with the
   *                          !bind directive.
   * @return Nothing.
   */
  private function addNormalFieldToForm($name, $field, $form, $binds) {
    $type = $this->getTypeConstant($field['type']);

    // The constructor will complain if these are passed into the parameters
    // array.
    unset($field['name']);
    unset($field['type']);

    $this->doBinding($field, $binds);

    $form->addField(new MyRadioFormField($name, $type, $field));
  }

  /**
   * Compiles a field description into a field and adds it to the given form.
   *
   * @param string    $name   The name of the field.
   * @param array     $field  The field description array to compile.
   * @param MyRadioForm $form   The form to add the compiled field to.
   * @param array     $binds  The current variable bindings, for use with the
   *                          !bind directive.
   * @return Nothing.
   */
  private function addFieldToForm($name, $field, $form, $binds) {
    if (strpos($name, self::SPECIAL_PREFIX) === 0) {
      $this->addSpecialFieldToForm($name, $field, $form, $binds);
    } else {
      $this->addNormalFieldToForm($name, $field, $form, $binds);
    }
  }

  /**
   * Loads a form from its filename, from the module's forms directory.
   *
   * @param string $name  The (file)name of the form, without the '.json'.
   * @return MyRadio_JsonFormLoader  this.
   */
  public function fromName($name) {
    return $this->fromPath(
      'Models/' . $this->module . '/' . $name . '.json'
    );
  }

  /**
   * Loads a form from its file path.
   *
   * @param string $path  The path to load from.
   * @return MyRadio_JsonFormLoader  this.
   */
  public function fromPath($path) {
    return $this->fromString(
      file_get_contents($path, true)
    );
  }
  
  /**
   * Loads a form from a JSON string.
   *
   * @param string $str  The string to load from.
   * @return MyRadio_JsonFormLoader  this.
   */
  public function fromString($str) {
    $this->form_array = json_decode($str, true);
    if ($this->form_array === null) {
      throw new MyRadioException(
        'Failed to load form from JSON: Code ' .
        json_last_error() 
      );
    }
    return $this;
  }

  /**
   * Compiles a previously loaded form to a form object.
   *
   * @param string    $action  The name of the action to trigger on submission.
   * @param array     $binds   The mapping of names used in !bind directives to
   *                           variables.
   *
   * @return MyRadioForm  The processed form.
   */
  public function toForm($action, $binds=[]) {
    // TODO: Complain if no form is loaded.
    $f = $this->form_array;

    $form = new MyRadioForm(
      $f['name'],
      $this->module,
      $action,
      $f['options']
    );

    foreach($f['fields'] as $name => $field) {
      $this->addFieldToForm($name, $field, $form, $binds);
    }

    return $form;
  }

  /**
   * Loads and renders a form from its MyRadio module and name.
   *
   * This is a convenience wrapper for 'fromPath'.
   *
   * @param string $module  The name of the calling MyRadio module.
   * @param string $name    The (file)name of the form, without the '.json'.
   * @param string $action  The name of the action to trigger on submission.
   * @return MyRadioForm  The processed form.
   */
  public static function loadFromModule($module, $name, $action, $binds=[]) {
    return (
      new MyRadio_JsonFormLoader($module)
    )->fromName($name)->toForm($action, $binds);
  }
}

?>
