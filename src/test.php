<?php

declare(strict_types=1);

// class Test
// {
//     private $test = ['test' => 'test'];
// }
//
// class Test2
// {
//     public function __construct(
//         private Test $test
//     ) {
//     }
// }
//
// $test = new Test();
// $test2 = new Test2($test);
// $test3 = new Test2($test);
//
// var_dump([$test, $test2, $test3]);
// $serialized = serialize([$test, $test2, $test3]);
// var_dump($serialized);
//
// var_dump(unserialize($serialized));

function getRandomString(int $length = 10, string $prefix = ''): string
{
    if (function_exists('sodium_bin2base64')) { // sodium is a core extension since ?
        $string = sodium_bin2base64(random_bytes($length + 10), SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING);

        return substr($prefix . str_replace(['/', '+', '='], '', $string), 0, $length);
    }

    return substr($prefix . str_replace(['/', '+', '='], '', base64_encode(random_bytes($length + 10))), 0, $length);
}
