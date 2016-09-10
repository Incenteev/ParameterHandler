<?php

namespace Incenteev\ParameterHandler\Parser;

interface ParserInterface
{
    /**
     * Parses a string to a PHP value.
     *
     * @param string $value A JSON string.
     * @param int    $flags Bitmask of decode options.
     *
     * @return mixed A PHP value
     *
     * @throws ParseException If the string format is not valid
     */
    public function parse($value, $flags = 0);
}
