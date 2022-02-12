<?php

class Test {
    private $test = ['test' => 'test'];
}

class Test2 {
    public function __construct(
        private Test $test
    )
    {
    }
}

$test = new Test();
$test2 = new Test2($test);
$test3 = new Test2($test);

var_dump([$test, $test2, $test3]);
$serialized = serialize([$test, $test2, $test3]);
var_dump($serialized);


var_dump(unserialize($serialized));
