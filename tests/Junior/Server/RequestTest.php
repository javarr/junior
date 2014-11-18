<?php
use Spray\Spray;


class ServerRequestTest extends PHPUnit_Framework_TestCase {

    /**
     * @return Junior\Serverside\Request
     */
    public function getEmptyRequest()
    {
        return $this->getMock('Junior\Serverside\Request',
                               null,
                               array(),
                               '',
                               false);
    }

    public function testNewRequest()
    {
        $json_rpc = '2.0';
        $method = 'testmethod';
        $params = array('foo', 'bar');
        $id = 10;
        $json = "{\"jsonrpc\":\"{$json_rpc}\", \"method\":\"{$method}\", \"params\":[\"{$params[0]}\", \"{$params[1]}\"], \"id\": {$id}}";
        $request = new Junior\Serverside\Request($json);
        $this->assertEquals($json_rpc, $request->jsonRpc);
        $this->assertEquals($method, $request->method);
        $this->assertEquals($params, $request->params);
        $this->assertEquals($id, $request->id);
    }

    public function testNewRequestInvalidRequest()
    {
        $request = new Junior\Serverside\Request('');
        $this->assertEquals(Junior\Serverside\Request::JSON_RPC_VERSION, $request->jsonRpc);
        $this->assertEquals(Junior\Serverside\Request::ERROR_INVALID_REQUEST, $request->errorCode);
        $this->assertEquals("Invalid Request.", $request->errorMessage);

        $request = new Junior\Serverside\Request('[]');
        $this->assertEquals(Junior\Serverside\Request::JSON_RPC_VERSION, $request->jsonRpc);
        $this->assertEquals(Junior\Serverside\Request::ERROR_INVALID_REQUEST, $request->errorCode);
        $this->assertEquals("Invalid Request.", $request->errorMessage);
    }

    public function testNewRequestParseError()
    {
        $request = new Junior\Serverside\Request('[bad:json::]');
        $this->assertEquals(Junior\Serverside\Request::JSON_RPC_VERSION, $request->jsonRpc);
        $this->assertEquals(Junior\Serverside\Request::ERROR_PARSE_ERROR, $request->errorCode);
        $this->assertEquals("Parse error.", $request->errorMessage);
    }

    public function testNewRequestBatch()
    {
        $json_rpc = '2.0';
        $method = 'testmethod';
        $params = array('foo', 'bar');
        $id = 10;
        $json = "{\"jsonrpc\":\"{$json_rpc}\", \"method\":\"{$method}\", \"params\":[\"{$params[0]}\", \"{$params[1]}\"], \"id\": {$id}}";
        $batch_json = "[$json,$json,$json]";
        $requests = new Junior\Serverside\Request($batch_json);
        foreach ($requests->requests as $request) {
            $this->assertEquals($json_rpc, $request->jsonRpc);
            $this->assertEquals($method, $request->method);
            $this->assertEquals($params, $request->params);
            $this->assertEquals($id, $request->id);
        }
    }

    public function testCheckValidGood()
    {
        $request = $this->getEmptyRequest();
        $request->jsonRpc = '2.0';
        $request->method = 'testMethod';
        $request->id = 10;
        $this->assertTrue($request->checkValid());
    }

    public function testCheckValidErrorAlreadySet()
    {
        $request = $this->getEmptyRequest();
        $request->jsonRpc = '2.0';
        $request->method = 'testMethod';
        $request->errorCode = 10;
        $request->errorMessage = 'Error!';
        $request->id = 10;
        $this->assertFalse($request->checkValid());
    }

    public function testCheckValidInvalidRequest()
    {
        $error_code = Junior\Serverside\Request::ERROR_INVALID_REQUEST;
        $error_message = 'Invalid Request.';

        $request = $this->getEmptyRequest();
        $request->jsonRpc = null;
        $request->method = 'testMethod';
        $request->id = 10;
        $this->assertFalse($request->checkValid());
        $this->assertEquals($error_code, $request->errorCode);
        $this->assertEquals($error_message, $request->errorMessage);

        $request = $this->getEmptyRequest();
        $request->jsonRpc = '2.0';
        $request->method = null;
        $request->id = 10;
        $this->assertFalse($request->checkValid());
        $this->assertEquals($error_code, $request->errorCode);
        $this->assertEquals($error_message, $request->errorMessage);

        $request = $this->getEmptyRequest();
        $request->jsonRpc = '2.0';
        $request->method = '!!!function';
        $request->id = 10;
        $this->assertFalse($request->checkValid());
        $this->assertEquals($error_code, $request->errorCode);
        $this->assertEquals($error_message, $request->errorMessage);
    }

    public function testCheckValidReservedPrefix()
    {
        $error_code = Junior\Serverside\Request::ERROR_RESERVED_PREFIX;
        $error_message = 'Illegal method name; Method cannot start with \'rpc.\'';

        $request = $this->getEmptyRequest();
        $request->jsonRpc = '2.0';
        $request->method = 'rpc.notvalid';
        $request->id = 10;
        $this->assertFalse($request->checkValid());
        $this->assertEquals($error_code, $request->errorCode);
        $this->assertEquals($error_message, $request->errorMessage);
    }

    public function testCheckValidMismatchedVersion()
    {
        $error_code = Junior\Serverside\Request::ERROR_MISMATCHED_VERSION;
        $error_message = 'Client/Server JSON-RPC version mismatch; Expected \'2.0\'';

        $request = $this->getEmptyRequest();
        $request->jsonRpc = '1.0';
        $request->method = 'method';
        $request->id = 10;
        $this->assertFalse($request->checkValid());
        $this->assertEquals($error_code, $request->errorCode);
        $this->assertEquals($error_message, $request->errorMessage);
    }

    public function testIsBatch()
    {
        $request = $this->getEmptyRequest();
        $request->batch = true;

        $this->assertTrue($request->isBatch());
    }

    public function testIsNotify()
    {
        $request = $this->getEmptyRequest();
        $request->id = null;

        $this->assertTrue($request->isNotification());

        $request->id = 10;

        $this->assertFalse($request->isNotification());
    }

    public function testIsNotifyWithZero()
    {
        $request = $this->getEmptyRequest();
        $request->id = 0;

        $this->assertFalse($request->isNotification());
    }

    public function testResponseJSON()
    {
        $request = $this->getEmptyRequest();
        $json_version = Junior\Serverside\Request::JSON_RPC_VERSION;
        $request->errorCode = 10;
        $request->errorMessage = 'Error!';
        $request->id = 1;

        $json = "{\"jsonrpc\":\"{$json_version}\",\"error\":{\"code\":{$request->errorCode},\"message\":\"{$request->errorMessage}\"},\"id\":{$request->id}}";
        $this->assertEquals($json, $request->toResponseJSON());

        $request->result = 'foo';

        $json = "{\"jsonrpc\":\"{$json_version}\",\"result\":\"{$request->result}\",\"id\":{$request->id}}";
        $this->assertEquals($json, $request->toResponseJSON());
    }

}