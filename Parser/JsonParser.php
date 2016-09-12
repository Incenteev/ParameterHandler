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
        if ('' === $value) {
            $result = [];
        } else {
            $result = json_decode($value, $assoc, $depth, $flags);
            if (null === $result) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Dumps a given array of data to JSON format.
     *
     * @param array $data Data to dump.
     *
     * @return string Dumped JSON data
     */
    public function dump(array $data)
    {
        return $this->prettyPrint(json_encode($data));
    }

    /**
     * Returns pretty printed JSON string.
     * Custom implementation for compatibility with PHP < 5.4 and custom spacing.
     *
     * @param string $json         JSON data to be pretty printed
     * @param string $spacer       Spacer used e.g. ' ' or "\t"
     * @param int    $spacing      Multiplicand for spacer (count)
     * @param bool   $newLineAtEof Whether to write a nl at end of file or not
     *
     * @return string Pretty printed JSON data
     */
    protected function prettyPrint($json, $spacer = ' ', $spacing = 2, $newLineAtEof = true)
    {
        $result          = '';
        $level           = 0;
        $in_quotes       = false;
        $in_escape       = false;
        $ends_line_level = null;
        $json_length     = strlen($json);

        for ($i = 0; $i < $json_length; ++$i) {
            $char           = $json[$i];
            $new_line_level = null;
            $post           = '';
            if ($ends_line_level !== null) {
                $new_line_level  = $ends_line_level;
                $ends_line_level = null;
            }
            if ($in_escape) {
                $in_escape = false;
            } elseif ($char === '"') {
                $in_quotes = !$in_quotes;
            } elseif (!$in_quotes) {
                switch ($char) {
                    case '}':
                    case ']':
                        $level--;
                        $ends_line_level = null;
                        $new_line_level  = $level;
                        break;

                    case '{':
                    case '[':
                        $level++;
                    case ',':
                        $ends_line_level = $level;
                        break;

                    case ':':
                        $post = ' ';
                        break;

                    case ' ':
                    case "\t":
                    case "\n":
                    case "\r":
                        $char            = '';
                        $ends_line_level = $new_line_level;
                        $new_line_level  = null;
                        break;
                }
            } elseif ($char === '\\') {
                $in_escape = true;
            }
            if ($new_line_level !== null) {
                $result .= "\n".str_repeat(str_repeat($spacer, $spacing), $new_line_level);
            }
            $result .= $char.$post;
        }

        $result = trim($result);

        if (true === $newLineAtEof) {
            $result .= "\n";
        }

        return $result;
    }
}
