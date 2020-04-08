<?php

namespace SimonMarx\Utils;

/**
 * Class StringUtils
 * @package SimonMarx\Utils
 *
 * provides some helper function for strings
 */
class StringUtils
{
    /**
     * check if a string contains the given search string
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function stringContains(string $haystack, string $needle): bool
    {
        return \strpos($haystack, $needle) !== false;
    }

    /**
     * check if string contains one of the given search strings
     *
     * @param string $haystack
     * @param array $needle
     * @return bool
     */
    public static function stringContainsOneOf(string $haystack, array $needle): bool
    {
        foreach ($needle as $searchForSingle) {
            if (self::stringContains($haystack, $searchForSingle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * check if the last characters of a string are the same, as the given $match parameter
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function stringEndsWith(string $haystack, string $needle): bool
    {
        return \substr(
                $haystack,
                \strlen($haystack) - \strlen($needle),
                \strlen($needle)
            ) === $needle;
    }

    /**
     * converts a base64 "web safe" string into normal base64
     *
     * @param string $base64WebSafe
     * @return string
     */
    public static function makeBase64WebUnsafe(string $base64WebSafe): string
    {
        $search = ['_', '-', '*'];
        $replaces = ['/', '+', '='];

        return \str_replace($search, $replaces, $base64WebSafe);
    }
}