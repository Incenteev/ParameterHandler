<?php

namespace Incenteev\ParameterHandler\Parser;

class JsonParser implements ParserInterface
{
    /**
     * Parses a JSON string to a PHP value.
     *
     * @param string $value A JSON string
     * @param int    $flags Bitmask of JSON decode options. Currently only JSON_BIGINT_AS_STRING is supported (default is to cast large integers as floats)
     * @param bool   $assoc When TRUE, returned objects will be converted into associative arrays.
     * @param int    $depth User specified recursion depth.
     *
     * @return mixed A PHP value
     *
     * @throws ParseException On any error parsing the JSON data.
     */
    public function parse($value, $flags = 0, $assoc = true, $depth = 512)
    {
        $result = json_decode($value, $assoc, $depth, $flags);

        if (null === $result) {
            $errorCode    = json_last_error();
            $errorMessage = json_last_error_msg();

            //  public function __construct($message, $parsedLine = -1, $snippet = null, $parsedFile = null, \Exception $previous = null)
            throw new ParseException(
                sprintf('Error "%s: %s" while parsing JSON string.', $errorCode, $errorMessage)
            );
        }

        return $result;
    }
}
