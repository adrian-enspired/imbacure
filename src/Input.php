<?php
/**
 * @package    at.ritz
 * @author     Adrian <adrian@enspi.red>
 * @copyright  2020
 * @license    GPL-3.0 (only)
 *
 *  This program is free software: you can redistribute it and/or modify it
 *  under the terms of the GNU General Public License, version 3.
 *  The right to apply the terms of later versions of the GPL is RESERVED.
 *
 *  This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 *  without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *  See the GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License along with this program.
 *  If not, see <http://www.gnu.org/licenses/gpl-3.0.txt>.
 */

namespace at\ritz;

use at\ritz\ {
  Error\InputException,
  Error\LogicException,
  Filter\Factory as FilterFactory,
  Filter\Filter,
  Validator\Factory as ValidatorFactory,
  Validator\Validator
};

/**
 * Manages input collection, normalization, and validation.
 */
class Input {

  /** @var bool Flag for a required input. */
  public const REQUIRED = true;

  /** @var bool Flag for an optional input. */
  public const OPTIONAL = false;

  /** @var array[] Input name:[isrequired, filter, validator] map. */
  protected $definition = [];

  /** @var scalar[]|array[] Input name:validated value map. */
  protected $input = [];

  /** @var scalar[]|array[] Input name:raw value map. */
  protected $rawInput = [];

  /**
   * @param array[] $definitions Input name:[required,filter,validator] map
   * @throws LogicException BAD_DEFINITION If any given definition is invalid
   */
  public function __construct(array $definitions) {
    foreach ($definitions as $name => $definition) {
      try {
        $this->addDefinition($name, ...$definition);
      } catch (Error | TypeError $e) {
        throw LogicException::create(LogicException::BAD_DEFINITION, ["name" => $name], $e);
      }
    }
  }

  /**
   * Adds an input to the input definition.
   *
   * This does not affect existing inputs or validation (@see addValue() and/or parse()).
   *
   * @param string $name Input name
   * @param bool|null $isRequired Is this input required?
   * @param array|null $filter Filter definition (@see Filter)
   * @param callable|null $validator Validation callback(mixed $value) : void
   *  must throw InputException if value is invalid
   * @return Input $this
   */
  public function addDefinition(
    string $name,
    bool $isRequired = false,
    ?Filter $filter = null,
    ?Validator $validator = null
  ) : Input {
    $this->_definition[$name] = [
      $isRequired,
      $filter ?? FilterFactory::default(),
      $validator ?? ValidatorFactory::default()
    ];

    return $this;
  }

  /**
   * Adds a value to the raw inputs to be parsed.
   *
   * Use this to add/override an input when parsing has already been completed.
   *
   * @param string $name Input name
   * @param mixed $raw Raw input value
   * @throws InputException If any input(s) fail validation
   * @return Input $this
   */
  public function addValue(string $name, $raw) : Input {
    return $this->parse([$name => $raw] + $this->rawInput);
  }

  /**
   * Gets a filtered+validated input value by name.
   *
   * @param string $name Input name
   * @throws LogicException NO_SUCH_INPUT If input name does not exist
   * @return mixed Validated input value
   */
  public function get(string $name) {
    if (! $this->has($name)) {
      throw LogicException::create(LogicException::NO_SUCH_INPUT, ["name" => $name]);
    }

    return $this->input[$name];
  }

  /**
   * Gets a raw (unfiltered+unvalidated) input value by name.
   *
   * @param string $name Input name
   * @throws LogicException NO_SUCH_RAW_INPUT If input name does not exist
   * @return mixed Raw input value
   */
  public function getRaw(string $name) {
    if (! $this->hasRaw($name)) {
      throw LogicException::create(LogicException::NO_SUCH_RAW_INPUT, ["name" => $name]);
    }

    return $this->rawInput[$name];
  }

  /**
   * Checks whether an input name is defined.
   *
   * @param string $name Input name
   * @return bool True if input name is defined; false otherwise
   */
  public function has(string $name) : bool {
    return array_key_exists($name, $this->input);
  }

  /**
   * Checks whether an input name was provided (regardless of whether is was defined or valid).
   *
   * @param string $name Input name
   * @return bool True if input was provided; false otherwise
   */
  public function hasRaw(string $name) : bool {
    return array_key_exists($name, $this->rawInput);
  }

  /**
   * Sets, filters and validates raw input.
   *
   * @param mixed[] $raw Map of raw input name:value pairs
   * @throws InputException If any input(s) fail validation
   * @return Input $this
   */
  public function parse(array $raw) : Input {
    $this->rawInput = $raw;

    $names = array_keys($this->definition);
    $this->input = array_fill_keys($names, null);

    $errors = new InputException();
    foreach ($names as $name) {
      try {
        $this->input[$name] = $this->validate($name, $raw[$name] ?? null);
      } catch (InputException $e) {
        $errors->addError($e);
      }
    }

    if ($errors->hasErrors()) {
      throw $errors;
    }

    return $this;
  }

  /**
   * Gets all defined inputs as an array.
   *
   * @param bool $omitEmpty Omit inputs that were not submitted?
   * @return array Validated input name:value pairs
   */
  public function toArray(bool $omitEmpty = false) : array {
    return $omit_empty ?
      array_filter($this->input) :
      $this->input;
  }

  /**
   * Validates filtered input.
   *
   * @param string $name Input name
   * @param mixed $raw Raw input value
   * @throws InputException If input is invalid
   * @return mixed Filtered, validated value on success
   */
  protected function validate(string $name, $raw) {
    [$required, $filter, $validator] = $this->_definition[$name];

    // "" is the http equivalent of null
    if ($raw === "" || $raw === null) {
      if ($required) {
        throw InputException::create(InputException::REQUIRED);
      }

      return null;
    }

    try {
      $value = $filter->apply($raw);
      $validator->apply($value);
    } catch (TypeError $e) {
      // captures the word before the first comma in the exception message,
      //  which should be the expected type.
      preg_match("((\w+),)u", $e->getMessage(), $m);
      throw InputException::create(
        InputException::TYPEERROR,
        [
          "expected" => $m[1],
          // @todo get_debug_type()
          "actual" => (is_object($value) ? get_class($value) : gettype($value))
        ]
      );
    }

    return $value;
  }
}
