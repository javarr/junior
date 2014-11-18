<?php
namespace Junior;

use Junior\Serverside\Request;


class Server {
    const ERROR_INVALID_PARAMS = -32602;
    const ERROR_METHOD_NOT_FOUND = -32601;
    const ERROR_EXCEPTION = -32099;

    public $exposedInstance, $input;

    public function __construct($exposedInstance)
    {
        if (!is_object($exposedInstance)) {
            throw new Serverside\Exception("Server requires an object");
        }

        $this->exposedInstance = $exposedInstance;
        $this->input = 'php://input';
    }

    /**
     * @param $methodName
     *
     * @return bool
     */
    public function methodExists($methodName)
    {
        return method_exists($this->exposedInstance, $methodName);
    }

    /**
     * @param $method
     * @param $params
     *
     * @return mixed
     * @throws Serverside\Exception
     */
    public function invokeMethod($method, $params)
    {
        // for named parameters, convert from object to assoc array
        if (is_object($params)) {
            $array = array();
            foreach ($params as $key => $val) {
                $array[$key] = $val;
            }
            $params = array($array);
        }

        if ($params === null) {
            $params = array();
        }
        $reflection = new \ReflectionMethod($this->exposedInstance, $method);
        
        if (!$reflection->isPublic()) {
            throw new Serverside\Exception("Called method is not publicly accessible.");
        }

        // enforce correct number of arguments
        $numRequiredParams = $reflection->getNumberOfRequiredParameters();
        if ($numRequiredParams > count($params)) {
            throw new Serverside\Exception("Too few parameters passed.");
        }
        
        return $reflection->invokeArgs($this->exposedInstance, $params);
    }

    /**
     * @throws Serverside\Exception
     */
    public function process()
    {
        // try to read input
        try {
            $json = file_get_contents($this->input);
        } catch (\Exception $e) {
            $message = "Server unable to read request body.";
            $message .= PHP_EOL . $e->getMessage();
            throw new Serverside\Exception($message);
        }

        // handle communication errors
        if ($json === false) {
            throw new Serverside\Exception("Server unable to read request body.");
        }

        $request = $this->makeRequest($json);

        // set content type to json if not testing
        if (!(defined('ENV') && ENV == 'TEST')) {
            header('Content-type: application/json');
        }

        // handle json parse error and empty batch
        if ($request->errorCode && $request->errorMessage) {
            echo $request->toResponseJSON();
            return;
        }

        // respond with json
        echo $this->handleRequest($request);
    }

    // create new request (used for test mocking purposes)
    public function makeRequest($json)
    {
        return new Request($json);
    }

    /**
     * handle request object and return response json
     *
     * @param Request $request
     *
     * @return null|string
     */
    public function handleRequest($request)
    {
        // recursion for batch
        if ($request->isBatch()) {
            $batch = array();
            foreach ($request->requests as $req) {
                $batch[] = $this->handleRequest($req);
            }
            $responses = implode(',',array_filter($batch, function($a){return $a !== null;}));
            if ($responses != null) {
                return "[{$responses}]";
            } else {
                return null;
            }
        }

        if ($request->checkValid()) {

            if (!$this->methodExists($request->method)) {
                $request->errorCode = self::ERROR_METHOD_NOT_FOUND;
                $request->errorMessage = "Method not found.";
                return $request->toResponseJSON();
            }

            // try to call method with params
            try {
                $response = $this->invokeMethod($request->method, $request->params);
                if (!$request->isNotification()) {
                    $request->result = $response;
                } else {
                    return null;
                }
            } catch (\Exception $e) {
                $request->errorCode = self::ERROR_EXCEPTION;
                $request->errorMessage = $e->getMessage();
            }
        }

        // return whatever we got
        return $request->toResponseJSON();
    }

}
