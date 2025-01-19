<?php

namespace ByJG\WebRequest\Helper;

use ByJG\WebRequest\ContentDisposition;
use ByJG\WebRequest\Exception\MessageException;
use ByJG\WebRequest\Exception\RequestException;
use ByJG\WebRequest\HttpMethod;
use ByJG\WebRequest\MultiPartItem;
use ByJG\WebRequest\Psr7\MemoryStream;
use ByJG\WebRequest\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class RequestMultiPart extends Request
{
    /**
     * @param UriInterface $uri
     * @param HttpMethod|string $method
     * @param MultiPartItem[] $multiPartItem
     * @param string|null $boundary
     * @return Request
     * @throws MessageException
     * @throws RequestException
     */
    public static function build(UriInterface $uri, HttpMethod|string $method, array $multiPartItem, ?string $boundary = null): Request
    {
        $request = Request::getInstance($uri)
            ->withMethod($method);

        return self::buildMultiPart($multiPartItem, $request, $boundary);
    }

    /**
     * @param MultiPartItem[] $multiPartItems
     * @param RequestInterface $request
     * @param string|null $boundary
     * @return Request|RequestInterface
     */
    public static function buildMultiPart(array $multiPartItems, RequestInterface $request, ?string $boundary = null): Request|RequestInterface
    {
        $stream = new MemoryStream();

        $boundary = (is_null($boundary) ? md5((string)time()) : $boundary);

        $contentType = "multipart/form-data";

        foreach ($multiPartItems as $item) {
            $item->build($stream, $boundary);
            if ($item->getContentDisposition() != ContentDisposition::formData) {
                $contentType = "multipart/related";
            }
        }

        $stream->write("--$boundary--");

        return $request
                ->withBody($stream)
                ->withHeader("Content-Type", "$contentType; boundary=$boundary");
    }
}
