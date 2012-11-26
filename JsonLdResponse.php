<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle;

use Symfony\Component\HttpFoundation\Response;

/**
 * JsonLdResponse represents an HTTP response in JSON-LD format.
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class JsonLdResponse extends Response
{
    /**
     * @var boolean Include whitespaces to improve readability?
     */
    private static $pretty = false;

    /**
     * Pretty-print responses?
     *
     * If pretty-print is activated, whitespaces are included when
     * serializing the response to improve readability?
     *
     * @param boolean $pretty Include whitespaces to improve readability?
     */
    public static function setPretty($pretty)
    {
        self::$pretty = $pretty;
    }

    /**
     * Constructor
     *
     * @param mixed   $data    The response data
     * @param integer $status  The response status code
     * @param array   $headers An array of response headers
     */
    public function __construct($data = array(), $status = 200, $headers = array())
    {
        parent::__construct('', $status, $headers);

        // Only set the header when there is none
        // in order to not overwrite a custom definition.
        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', 'application/ld+json');
        }

        $this->processData($data);
    }

    /**
     * {@inheritDoc}
     */
    public static function create($data = array(), $status = 200, $headers = array())
    {
        return new static($data, $status, $headers);
    }

    /**
     * Processes the data
     *
     * If necessary, the data is serialized and then set as the content of
     * the response.
     *
     * @param mixed $data
     *
     * @return JsonLdResponse
     */
    public function processData($data = array())
    {
        // return an empty object instead of an empty array
        if (is_array($data) && 0 === count($data)) {
            $data = new \stdClass();
        }

        if (!is_string($data)) {
            $options = 0;

            if (PHP_VERSION_ID >= 50400)
            {
                $options |= JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

                if (self::$pretty)
                {
                    $options |= JSON_PRETTY_PRINT;
                }

                $data = json_encode($data, $options);
            }
            else
            {
                $data = json_encode($data);
                $data = str_replace('\\/', '/', $data);  // unescape slahes

                // unescape unicode
                $data = preg_replace_callback(
                    '/\\\\u([a-f0-9]{4})/',
                    function ($match) {
                        return iconv('UCS-4LE', 'UTF-8', pack('V', hexdec($match[1])));
                    },
                    $data);
            }
        }

        return $this->setContent($data);
    }
}
