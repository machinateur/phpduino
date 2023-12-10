<?php

/**
 * MIT License
 *
 * Copyright (c) 2021-2023 machinateur
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

// Example program for "echo-nonblocking.ino" communication to/from Arduino via USB serial, asynchronously.
// This one contains concise time monitoring too, so it provides a blueprint for how to monitor and optimise the script
//  communication performance. Besides that, it counts the number of loop iterations, which is going to be higher,
//  as the Arduino (see source) sleeps for one second before returning each bit.

use Machinateur\Arduino\streamWrapper;

// Setup time monitoring.
$time   = [
    [(float)\microtime(true), 'i'],                 // init
];

require_once __DIR__ . '/../../vendor/autoload.php';

streamWrapper::register();

$time[] = [(float)\microtime(true), 'g'];           // register

$deviceName = 'cu.usbmodem2101';

if (empty($deviceName)) {
    \trigger_error(
        \sprintf('No device name specified in "%s" on line "%d"!', __FILE__, __LINE__ - 4), \E_USER_ERROR);
}

$deviceBaudRate = 19200; // note the use of a different baud rate in this example, for demonstration
$deviceParity   =    -1; // still use no parity
$deviceDataSize =     8; // still use 8 data bits
$deviceStopSize =     1; // and still use 1 stop bit

$deviceCustomCommand = null; // with no custom command

$context = \stream_context_create([
    streamWrapper::PROTOCOL_NAME => [
        'baud_rate'      => $deviceBaudRate,
        'parity'         => $deviceParity,
        'data_size'      => $deviceDataSize,
        'stop_size'      => $deviceStopSize,
        'custom_command' => null,
        // keep the threshold, as the default
        'usleep_s'       => 2,
    ],
]);

$time[] = [(float)\microtime(true), 'x'];           // context

// Somewhere along the way, 4 bytes are sent to the device. Seems to only be on non-default baud rates.
$fd = \fopen("arduino://{$deviceName}", 'r+b', false, $context);

$time[] = [(float)\microtime(true), 'o'];           // open

$input  = 'hello world';
$output = '';

\stream_set_chunk_size($fd, 1);

$count  = 0;
$cursor = 0;
$time[] = [(float)\microtime(true), 'b'];           // begin

while ($input !== $output) {
    ++$count;

    $read   = [$fd];
    $write  = [$fd];
    $except = [];

    $result = \stream_select($read, $write, $except, 1, 200_000);

    if (false === $result) {
        echo 'Error', \PHP_EOL;

        break;
    }

    echo $output, \PHP_EOL;

    if (0 === $result) {
        continue;
    }

    if ([] !== $read) {
        $char = \fgetc($read[0]);

        $time[]  = [(float)\microtime(true), 'r'];  // read

        // See https://www.man7.org/linux/man-pages/man7/ascii.7.html.
        if (false === $char || 128 <= \ord($char)) {
            echo \bin2hex($char), \PHP_EOL;

            continue;
        }

        $output .= $char;
    }

    if ([] !== $write) {
        if (\strlen($input) <= $cursor) {
            continue;
        }

        if (0 === $cursor) {
            $output = '';
        }

        \fwrite($write[0], $input[$cursor++], 1);

        $time[]  = [(float)\microtime(true), 'w'];  // write
    }
}

$time[]  = [(float)\microtime(true), 'e'];          // end

\fclose($fd);

$time[] = [(float)\microtime(true), 'c'];           // close

echo '', \PHP_EOL;
echo 'Input:  ', $input,  \PHP_EOL;
echo 'Output: ', $output, \PHP_EOL;
echo 'Count:  ', $count,  \PHP_EOL;
echo '', \PHP_EOL;
echo 'Time:   ', \PHP_EOL;
foreach ($time as [$ts, $log]) {
    echo '* ', \sprintf('%.06f %s', $ts - $time[0][0], $log), \PHP_EOL;
}
echo '', \PHP_EOL;
