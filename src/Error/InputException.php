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

namespace at\ritz\Error;

use at\exceptable\Exception;

class InputException extends Exception {

  /** @var int Nonspecific error case. */
  public const INVALID = 1;

  /** @var int A required input was missing. */
  public const REQUIRED = 1 << 1;

  /** @var int An input was provided of the wrong type. */
  public const TYPEERROR = 1 << 2;

  /** {@inheritDoc} */
  public const INFO = [
    self::INVALID => [
      "message" => "This input is invalid",
      "contextMessage" => "Input '{name}' is invalid"
    ],
    self::REQUIRED => [
      "message" => "This input is required",
      "contextMessage" => "Input '{name}' is required"
    ],
    self::TYPEERROR => [
      "message" => "This input is invalid",
      "contextMessage" => "Input '{name}' must be {expected}; {actual} provided"
    ]
  ];

  /**
   * Adds a (user-facing) error message to this Exception.
   *
   * If given another InputException, will append its error list.
   *
   * @param string|string[]|InputException $error Error to add
   * @param string|null $reference Error reference
   * @return InputException $this
   */
  public function addError($error, string $reference = null) : InputException {
    if ($error instanceof self) {
      $this->exceptions[] = $error;
      foreach ($error->getErrorList() as $e) {
        $this->addError($e[self::MSG], $reference ?? $e[self::REF]);
      }

      return $this;
    }

    if (! is_array($error)) {
      $error = [$error];
    }

    foreach ($error as $s) {
      if (! is_string($s)) {
        throw InvalidArgumentException::create(
          InvalidArgumentException::INPUTEXCEPTION_ADDERROR_ERROR,
          []
        );
      }
    }

    $this->_errors[] = [
      self::MSG => self::_translate(...$error),
      self::REF => $ref
    ];

    return $this;
  }
}
