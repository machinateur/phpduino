<?php

declare(strict_types=1);

// Example program for "echo.ino" communication to/from Arduino via USB serial.

use Machinateur\Arduino\streamWrapper;

require_once '../vendor/autoload.php';

// Register the protocol and stream wrapper.
streamWrapper::register();

// The device's name in `/dev/`.
//  Prefer `cu` if on Mac, use `ls /dev/tty.*` to find the available devices.
$deviceName     = 'tty.usbmodem2101';
$deviceName     =  'cu.usbmodem2101';

// The device's baud rate.
//  See Arduino docs at https://docs.arduino.cc/learn/built-in-libraries/software-serial#begin for conventinal rates.
// -> Supported baud rates are 300, 600, 1200, 2400, 4800, 9600, 14400, 19200, 28800, 31250, 38400, 57600, and 115200 bauds.
$deviceBaudRate = 9600;

// The device's parity bit configuration.
//  See Arduino docs at https://www.arduino.cc/reference/en/language/functions/communication/serial/begin/.
// -> none = -1
// ->  odd =  1
// -> even =  0
$deviceParity   = -1;

// The device's data size configuration.
//  See Arduino docs at https://www.arduino.cc/reference/en/language/functions/communication/serial/begin/.
// -> Default is `SERIAL_8N1`, so 8 is the data size.
$deviceDataSize = 8;

// The device's stop bit size configuration.
//  See Adruino docs at https://www.arduino.cc/reference/en/language/functions/communication/serial/begin/.
// -> Default is `SERIAL_8N1`, so 1 is the stop bit size.
$deviceStopSize = 1;

// The stream context configuration.
$context = \stream_context_create([
    streamWrapper::PROTOCOL_NAME => [
        'baud_rate'      => $deviceBaudRate,
        'parity'         => $deviceParity,
        'data_size'      => $deviceDataSize,
        'stop_size'      => $deviceStopSize,
        'custom_command' => 'Darwin' === \PHP_OS_FAMILY
            ? 'ignbrk -brkint -icrnl -imaxbel -opost -onlcr -isig -icanon -iexten -echo -echoe -echok -echoctl -echoke noflsh'
            : null,
    ],
]);

// Open the connection. Make sure the device is connected under the configured device name
//  and the `echo.ino` program is running.
$fd = \fopen("arduino://{$deviceName}", 'r+b', false, $context);

\stream_set_blocking(\STDIN, true);
echo 'echo: type and get a response...',
    \PHP_EOL,
    \PHP_EOL;

$waiting = false;
$input   = false;
$output  = '';

\fread($fd, 1);

while (!\feof($fd))
{
    if (!$waiting) {
        $input = \fgets(\STDIN);

        if ($input) {
            $waiting = false !== \fwrite($fd, $input);
        }
    }

    \usleep(5_000);

    $output .= \fread($fd, 1);

    if (!$output) {
        echo '.';

        continue;
    }

    if ($output === $input) {
        echo $output;

        $waiting = false;
        $input   = false;
        $output  = '';
    }
}

echo \PHP_EOL,
    \PHP_EOL;
