<?php
/**
 * Created by PhpStorm.
 * User: jg
 * Date: 13/07/16
 * Time: 10:04
 */

namespace ByJG\Util;

class WebRequestMulti
{
    const GET = "get";
    const POST = "post";
    const PUT = "put";
    const DELETE = "delete";
    const UPLOAD = "upload";

    /**
     * @var array
     */
    protected $webRequest = [];

    /**
     * @var \Closure
     */
    protected $defaultOnSuccess = null;

    /**
     * @var \Closure
     */
    protected $defaultOnError = null;

    public function __construct(\Closure $defaultOnSuccess = null, \Closure $defaultOnError = null)
    {
        $this->defaultOnSuccess = $defaultOnSuccess;
        if (is_null($defaultOnSuccess)) {
            $this->defaultOnSuccess = function () {
                return;
            };
        }

        $this->defaultOnError = $defaultOnError;
        if (is_null($defaultOnError)) {
            $this->defaultOnError = function () {
                return;
            };
        }
    }

    public function addRequest(
        WebRequest $webRequest,
        $method,
        $params = [],
        \Closure $onSuccess = null,
        \Closure $onError = null
    ) {
        if (is_null($onSuccess)) {
            $onSuccess = $this->defaultOnSuccess;
        }

        if (is_null($onError)) {
            $onError = $this->defaultOnError;
        }

        $data = new \stdClass();
        $data->webRequest = $webRequest;
        $data->method = $method;
        $data->params = $params;
        $data->onSuccess = $onSuccess;
        $data->onError = $onError;
        $data->handle = null;

        $this->webRequest[] = $data;

        return $this;
    }

    public function execute()
    {
        // multi handle
        $mh = curl_multi_init();

        // loop through $data and create curl handles
        // then add them to the multi-handle
        foreach ($this->webRequest as $id => $object) {
            switch ($object->method) {
                case self::GET:
                    $object->handle = $object->webRequest->prepareGet($object->params);
                    break;
                case self::PUT:
                    $object->handle = $object->webRequest->preparePut($object->params);
                    break;
                case self::POST:
                    $object->handle = $object->webRequest->preparePost($object->params);
                    break;
                case self::DELETE:
                    $object->handle = $object->webRequest->prepareDelete($object->params);
                    break;
                case self::UPLOAD:
                    $object->handle = $object->webRequest->preparePostUploadFile($object->params);
                    break;
                default:
                    throw new CurlException("Invalid Method '{$object->method}'");
            }
            $this->webRequest[$id] = $object;
            curl_multi_add_handle($mh, $object->handle);
        }

        // execute the handles
        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);

            // Check status
            switch ($status) {
                case CURLM_BAD_HANDLE:
                    throw new CurlException('Bad Handle');
                    break;
                case CURLM_BAD_EASY_HANDLE:
                    throw new CurlException('Bad Easy Handle');
                    break;
                case CURLM_OUT_OF_MEMORY:
                    throw new CurlException('Out of memory');
                    break;
                case CURLM_INTERNAL_ERROR:
                    throw new CurlException('Internal Error');
                    break;
            }
        } while ($running > 0);

        // get content and remove handles
        $errorList = [];
        foreach ($this->webRequest as $id => $object) {
            $body = curl_multi_getcontent($object->handle);
            $error = curl_error($object->handle);
            if (!empty($error)) {
                curl_multi_remove_handle($mh, $object->handle);
                $closure = $object->onError;
                try {
                    $closure($error, $id);
                } catch (\Exception $ex) {
                    $errorList[] = $ex;
                }
                continue;
            }
            
            $header_size = curl_getinfo($object->handle, CURLINFO_HEADER_SIZE);
            $closure = $object->onSuccess;
            
            try {
                $closure(substr($body, $header_size), $id);
            } catch (\Exception $ex) {
                $errorList[] = $ex;
            }
            
            curl_multi_remove_handle($mh, $object->handle);
        }

        // all done
        curl_multi_close($mh);
        
        if (count($errorList) > 0) {
            throw $errorList[0];
        }
    }
}
