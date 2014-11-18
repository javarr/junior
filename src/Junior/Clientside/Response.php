<?php
namespace Junior\Clientside;

class Response
{

    public $result;
    public $id;
    public $errorCode;
    public $errorMessage;

    // create a new json rpc response object
    public function __construct($result, $id = null, $errorCode = null, $errorMessage = null)
    {
        $this->result = $this->recursiveUTF8Decode($result);
        $this->id = $id;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
    }

    // return result or error if applicable
    public function __toString()
    {
        if ($this->errorMessage) {
            return "{$this->errorCode}: {$this->errorMessage}";
        }
    
        return $this->result;
    }

    // recursively decode utf8
    private function recursiveUTF8Decode($result)
    {
        if (is_array($result)) {
            foreach ($result as &$value) {
                $value = $this->recursiveUTF8Decode($value);
            }
            return $result;
        } else {
            return utf8_decode($result);
        }
    }
}