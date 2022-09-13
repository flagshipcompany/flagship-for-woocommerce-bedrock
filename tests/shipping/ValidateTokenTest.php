<?php
use FlagshipWoocommerceBedrock\Requests\Validate_Token_Request;

class ValidateTokenTest extends FlagshipShippingUnitTestCase
{
    public function setUp() : void 
    {
        parent::setUp();
    }

    public function testValidateToken()
    {
        $request = new Validate_Token_Request('1234567890987654321', 'https://test-api.smartship.io');
        $return = $request->validateToken();
        $this->assertEquals("Invalid Token. Returned with code: 403", $return);
    }
}
