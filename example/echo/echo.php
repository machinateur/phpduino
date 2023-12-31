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

// Example program for "echo.ino" communication to/from Arduino via USB serial.

use Machinateur\Arduino\streamWrapper;

require_once __DIR__ . '/../../vendor/autoload.php';

// Register the protocol and stream wrapper.
streamWrapper::register();

/**
 * The device's name in `/dev/`.
 *  Prefer `cu` if on Mac, use `ls /dev/tty.*` to find the available devices.
 *
 * On windows, use the device manager to identify the correct serial (com) port, under the `COM` device group.
 */
if ('Darwin' === \PHP_OS_FAMILY) {
    $deviceName = 'tty.usbmodem2101';
    $deviceName = 'cu.usbmodem2101';
} elseif ('Windows' === \PHP_OS_FAMILY) {
    $deviceName = 'COM7';
} else {
    $deviceName = '';

    \trigger_error(
        \sprintf('No device name specified in "%s" on line "%d"!', __FILE__, __LINE__ - 3), \E_USER_ERROR);
}

/**
 * The device's baud rate.
 *
 * See Arduino docs at https://docs.arduino.cc/learn/built-in-libraries/software-serial#begin for conventional rates.
 *
 * Supported baud rates are:
 *  - ` 300`
 *  - ` 600`
 *  - ` 1200`
 *  - ` 2400`
 *  - ` 4800`
 *  - ` 9600`
 *  - ` 14400`
 *  - ` 19200`
 *  - ` 28800`
 *  - ` 31250`
 *  - ` 38400`
 *  - ` 57600`
 *  - `115200`
 */
$deviceBaudRate = 9600;

/**
 * The device's parity bit configuration.
 *
 * See Arduino docs at https://www.arduino.cc/reference/en/language/functions/communication/serial/begin/.
 * - `none` = `-1`
 * - ` odd` = ` 1`
 * - `even` = ` 0`
 *
 * Default is `SERIAL_8N1`, so `N` is the data size.
 */
$deviceParity   = -1;

/**
 * The device's data size configuration.
 *
 * See Arduino docs at https://www.arduino.cc/reference/en/language/functions/communication/serial/begin/.
 *
 * Default is `SERIAL_8N1`, so `8` is the data size.
 */
$deviceDataSize = 8;

/**
 * The device's stop bit size configuration.
 *
 * See Arduino docs at https://www.arduino.cc/reference/en/language/functions/communication/serial/begin/.
 *
 * Default is `SERIAL_8N1`, so `1` is the stop bit size.
 */
$deviceStopSize = 1;

/**
 * The device custom command. If set, will be yielded in favor of default commands.
 *
 * Try to stop the reset on `fclose()`, see https://stackoverflow.com/a/59549518.
 * - `[-]hup` = send a hangup signal when the last process closes the tty
 *
 * Ideally only run with the `-hup` option if not yet set, so there will be no more restarts due to RTS HANGUP.
 *  Only use if desired.
 */
$deviceCustomCommand = null;

// The stream context configuration.
$context = \stream_context_create([
    streamWrapper::PROTOCOL_NAME => [
        'baud_rate'      => $deviceBaudRate,
        'parity'         => $deviceParity,
        'data_size'      => $deviceDataSize,
        'stop_size'      => $deviceStopSize,
        'custom_command' => null,
        // A safe threshold for the arduino to boot on `fopen()`.
        'usleep_s'       => 2,
    ],
]);

// Open the connection. Make sure the device is connected under the configured device name
//  and the `echo.ino` program is running.
$fd = \fopen("arduino://{$deviceName}", 'r+b', false, $context);

$input  = 'hello world';
$output = '';

// As it turns out, this is the key, as for serial (com) ports on windows represented as file stream:
//  If the stream is read buffered, and it does not represent a plain file, at most one read of up to a number of bytes
//  equal to the chunk size (usually 8192) is made; depending on the previously buffered data, the size of the returned
//  data may be larger than the chunk size.
// This undocumented behaviour of the streamWrapper API may be observed when removing this call and monitoring the value
//  of `$count` in `streamWrapperAbstract::stream_read()` when called during `fread($fd, 1)`. The `$count` will be 8192!
\stream_set_chunk_size($fd, 1);

\fwrite($fd, $input);

do {
    $char = \fgetc($fd);

    // See https://www.man7.org/linux/man-pages/man7/ascii.7.html.
    if (false === $char || 128 <= \ord($char)) {
        continue;
    }

    $output .= $char;
    //echo $output.\PHP_EOL;
} while ($output !== $input);

echo \PHP_EOL,
    $output,
    \PHP_EOL,
    \PHP_EOL;
