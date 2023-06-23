<?php
namespace Atatus\Middleware;

use Closure;

use Exception;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AtatusLaravel
{

    /**
     * Get value if set, else default
     */
    function getOrElse($var, $default=null) {
        return isset($var) ? $var : $default;
    }

    /**
     * Set user.
     */
    public function setUser($userId, $userProperties){
        atatus_set_user($userId, $userProperties);
    }

    /**
     * Set company.
     */
    public function setCompany($companyId){
        atatus_set_company($companyId);
    }

    /**
     * Function for basic field validation (present and neither empty nor only white space.
     */
    function IsNullOrEmptyString($str){
        $isNullOrEmpty = false;
        if (!isset($str) || trim($str) === '') {
            $isNullOrEmpty = true;
        }
        return $isNullOrEmpty;
    }

    /**
     * Function for json validation.
     */
    function IsInValidJsonBody($requestBody) {
        $encoded_data = json_encode($requestBody);
        return (preg_match("/\\\\{3,}/", $encoded_data));
    }

    /**
     * Function for truncating string to max size.
     */
    function TruncateString($str, $maxBodySize) {
        if (!is_null($str) && is_string($str)) {
            $str = (strlen($str) > $maxBodySize) ? substr($str, 0, $maxBodySize) . '...(TRUNCATED)' : $str;
        }
        return $str;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // after response.

        $configClass = config('atatus.configClass');

        $maskRequestHeaders = null;
        $maskRequestBody = null;
        $maskResponseHeaders = null;
        $maskResponseBody = null;
        $identifyUserId = null;
        $identifyCompanyId = null;
        // $getCustomData = null;
        $skip = null;

        if ($configClass) {
            if (!class_exists($configClass)) {
                throw new Exception('The config class '.$configClass.' not found. Please be sure to specify full name space path.');
            }
            $configInstance = new $configClass();
            // $maskRequestHeaders = array($configInstance, 'maskRequestHeaders');
            $maskRequestBody = array($configInstance, 'maskRequestBody');
            // $maskResponseHeaders = array($configInstance, 'maskResponseHeaders');
            $maskResponseBody = array($configInstance, 'maskResponseBody');
            $identifyUserId = array($configInstance, 'identifyUserId');
            $identifyCompanyId = array($configInstance, 'identifyCompanyId');
            // $getCustomData = array($configInstance, 'getCustomData');
            $skip = array($configInstance, 'skip');
        }

        $logBody = config('atatus.logBody');
        $debug = config('atatus.debug');


        if (is_null($logBody)) {
            $logBody = true;
        }

        if (is_null($debug)) {
            $debug = false;
        }

        // if skip is defined, invoke skip function.
        if (is_callable($skip)) {
          if($skip($request, $response)) {
            if ($debug) {
              Log::info('[Atatus] : skip function returned true, so skipping this event.');
            }
            // if (extension_loaded('atatus')) {
            //     atatus_ignore_analytics_event();
            // }
            return $response;
          }
        }

        $requestData = [];
        $responseData = [];

        // Request Headers
        // $requestHeaders = [];
        // foreach($request->headers->keys() as $key) {
        //     $requestHeaders[$key] = (string) $request->headers->get($key);
        // }

        // can't use headers->all() because it is an array of arrays.
        // $request->headers->all();
        // if(is_callable($maskRequestHeaders)) {
        //     $requestData['headers'] = $maskRequestHeaders($requestHeaders);
        // } else {
        //     $requestData['headers'] = $requestHeaders;
        // }

        // Response Headers
        $responseHeaders = [];
        foreach($response->headers->keys() as $key) {
            $responseHeaders[$key] = (string) $response->headers->get($key);
        }

        $isAllowedContentType = array_key_exists('content-type', $responseHeaders) &&
                                $responseHeaders['content-type'] &&
                                (str_contains($responseHeaders['content-type'], 'text/plain') ||
                                 str_contains($responseHeaders['content-type'], 'application/json'));

        // if(is_callable($maskResponseHeaders)) {
        //     $responseData['headers'] = $maskResponseHeaders($responseHeaders);
        // } else {
        //     $responseData['headers'] = $responseHeaders;
        // }

        if($logBody) {

            // Request Body
            $requestContent = $request->getContent();
            if(!is_null($requestContent)) {
                $requestBody = json_decode($requestContent, true);

                // Log::info('request body is json - ' . $requestBody);
                if (is_null($requestBody) || $this->IsInValidJsonBody($requestBody) === 1) {
                    $requestData['body'] = $requestContent;
                } else {
                    if (is_callable($maskRequestBody)) {
                        $requestBody = $maskRequestBody($requestBody);
                        $requestData['body'] = json_encode($requestBody);
                    } else {
                        $requestData['body'] = $requestContent;
                    }
                }

                if (array_key_exists('body', $requestData)) {
                    $requestData['body'] = $this->TruncateString($requestData['body'], $maxBodySize);
                }

            }

            // Response Body
            $responseContent = $response->getContent();
            if ($isAllowedContentType && !is_null($responseContent)) {
                $jsonBody = json_decode($responseContent, true);

                if (is_null($jsonBody) || $this->IsInValidJsonBody($jsonBody) === 1) {
                    $responseData['body'] = $responseContent;
                } else {
                    if (is_callable($maskResponseBody)) {
                        $jsonBody = $maskResponseBody($jsonBody);
                        $responseData['body'] = json_encode($jsonBody);
                    } else {
                        $responseData['body'] = $responseContent;
                    }
                }

                if (array_key_exists('body', $responseData)) {
                    $responseData['body'] = $this->TruncateString($responseData['body'], $maxBodySize);
                }

            }
        }

        // Get user id
        $userId = '';
        $user = $request->user();
        if (is_callable($identifyUserId)) {
            $userId = $this->ensureString($identifyUserId($request, $response));
        } else if (!is_null($user)) {
            $userId = $this->ensureString($user['id']);
        }

        // Get Company id
        $companyId = '';
        if(is_callable($identifyCompanyId)) {
            $companyId = $this->ensureString($identifyCompanyId($request, $response));
        }

        // if (is_callable($getCustomData)) {
        //     $customData = $getCustomData($request, $response);
        //     if (empty($customData)) {
        //         $data['customData'] = null;
        //     } else {
        //         $data['customData'] = $customData;
        //     }
        // }

        if (extension_loaded('atatus')) {
            if (array_key_exists('body', $requestData)) {
                atatus_set_request_body($requestData['body']);
            }
            if (array_key_exists('body', $responseData)) {
                atatus_set_response_body($responseData['body']);
            }
            atatus_set_user($userId);
            atatus_set_company($companyId);
        }

        return $response;
    }

    protected function ensureString($item) {
      if (is_null($item)) {
        return $item;
      }
      if (is_string($item)) {
        return $item;
      }
      return strval($item);
    }
}
