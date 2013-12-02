<?php

namespace Clockwork;
use \PHPUnit_Framework_TestCase;

class ClockworkTest extends PHPUnit_Framework_TestCase {

    public function msisdProvider()
    {
        return array(
          array('01234567', 0), //starts with 0
          array('11234567', 1),
          array('1123456', 0),  //not enough numbers
          array('1123456789000', 1),
          array('11234567890000', 0) //too many numbers
        );
    }

    /**
     * @dataProvider msisdProvider
     */
    public function testIsValidMsisd($number, $expectedResult)
    {
        $this->assertEquals($expectedResult,Clockwork::is_valid_msisdn($number));
    }
} 