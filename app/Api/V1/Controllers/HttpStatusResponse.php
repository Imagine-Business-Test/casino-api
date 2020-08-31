<?php

namespace App\Api\V1\Controllers;

/**
 * #author Ezugudor
 * This trait houses request responses with the correct standard HTTP status c
 * codes with nice descriptions and infos.
 */
trait HttpStatusResponse
{

    /**
     * Map of standard HTTP status code/reason phrases
     *
     * @var array
     */
    private $statusArray = [
        // INFORMATIONAL CODES
        ['code' => 102, 'desc' => 'Processing', 'message' => ''],
        ['code' => 103, 'desc' => 'Early Hints', 'message' => ''],
        // SUCCESS CODES
        ['code' => 200, 'desc' => 'OK', 'message' => 'Request Success.'],
        ['code' => 201, 'desc' => 'Created', 'message' => ''],
        ['code' => 202, 'desc' => 'Accepted', 'message' => ''],
        ['code' => 203, 'desc' => 'Non-Authoritative Information', 'message' => ''],
        ['code' => 204, 'desc' => 'No Content', 'message' => ''],
        ['code' => 205, 'desc' => 'Reset Content', 'message' => ''],
        ['code' => 206, 'desc' => 'Partial Content', 'message' => ''],
        ['code' => 207, 'desc' => 'Multi-Status', 'message' => ''],
        ['code' => 208, 'desc' => 'Already Reported', 'message' => ''],
        ['code' => 226, 'desc' => 'IM Used', 'message' => ''],
        // REDIRECTION CODES

        ['code' => 302, 'desc' => 'Found', 'message' => ''],

        // CLIENT ERROR
        ['code' => 400, 'desc' => 'Bad Request', 'message' => ''],
        ['code' => 401, 'desc' => 'Unauthorized', 'message' => 'Validation Error'],
        ['code' => 403, 'desc' => 'Forbidden', 'message' => ''],
        ['code' => 404, 'desc' => 'Not Found', 'message' => ''],
        ['code' => 408, 'desc' => 'Request Timeout', 'message' => ''],
        ['code' => 409, 'desc' => 'Conflict', 'message' => ''],

        // SERVER ERROR
        ['code' => 499, 'desc' => 'Client Closed Request', 'message' => ''],
        ['code' => 500, 'desc' => 'Internal Server Error', 'message' => 'Internal Server Error'],

    ];

    public function customHttpResponse($statusCode, $message = null, $data = null)
    {
        $response = [];
        foreach ($this->statusArray as $status) {
            if ($status['code'] === $statusCode) {
                $response = array(
                    'status_code' => $status['code'],
                    'status_type' => $status['desc'],
                    'status_desc' => $status['message'],
                    'message' => $message,
                    'data' => $data,
                );
            }
        }
        return $response;
    }






    /**
     * Map of standard HTTP status code/reason phrases
     *
     * @var array
     */
    private $phrases = [
        // INFORMATIONAL CODES
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        // SUCCESS CODES
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        // REDIRECTION CODES
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy', // Deprecated to 306 => '(Unused)'
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        // CLIENT ERROR
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        444 => 'Connection Closed Without Response',
        451 => 'Unavailable For Legal Reasons',
        // SERVER ERROR
        499 => 'Client Closed Request',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
        599 => 'Network Connect Timeout Error',
    ];
}
